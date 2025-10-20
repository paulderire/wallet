<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /MY CASH/pages/login.php'); exit; }
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = !empty($user['is_admin']);

if (!$is_admin) {
    header('Location: /MY CASH/pages/dashboard.php');
    exit;
}

$success_msg = $error_msg = '';

// Handle adding new trade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trade'])) {
  try {
    $conn->beginTransaction();
    
    $trade_date = $_POST['trade_date'] ?? date('Y-m-d');
    $trade_time = $_POST['trade_time'] ?? date('H:i');
    $pair = trim($_POST['pair']);
    $trade_type = $_POST['type']; // buy or sell
    $entry_price = floatval($_POST['entry_price']);
    $exit_price = isset($_POST['exit_price']) && $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
    $lot_size = floatval($_POST['lot_size']);
    $profit_loss = isset($_POST['profit_loss']) && $_POST['profit_loss'] !== '' ? floatval($_POST['profit_loss']) : null;
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'closed_win';
    $close_date = $_POST['close_date'] ?? null;
    $close_time = $_POST['close_time'] ?? null;
    $startup_amount = isset($_POST['startup_amount']) && $_POST['startup_amount'] !== '' ? floatval($_POST['startup_amount']) : 1000.00;
    
    // Calculate pips if exit price exists
    $pips = null;
    if ($exit_price !== null) {
      if ($trade_type === 'buy') {
        $pips = ($exit_price - $entry_price) * 10000; // For most pairs
      } else { // sell
        $pips = ($entry_price - $exit_price) * 10000;
      }
      $pips = round($pips, 2);
    }
    
    // Auto-determine status if closed
    if (in_array($status, ['closed_win', 'closed_loss']) && $profit_loss !== null) {
      $status = $profit_loss >= 0 ? 'closed_win' : 'closed_loss';
      if (!$close_date) $close_date = $trade_date;
      if (!$close_time) $close_time = $trade_time;
    }
    
    // Calculate USD and RWF amounts
    $usd_amount = $profit_loss !== null ? abs($profit_loss) : null;
    $rwf_amount = $profit_loss !== null ? $profit_loss * 1500 : null; // 1 USD = 1500 RWF
    
    // Calculate risk/reward ratio (simplified)
    $risk_reward_ratio = null;
    if ($profit_loss !== null && $profit_loss != 0) {
      $risk = $startup_amount * 0.02; // Assume 2% risk
      $reward = abs($profit_loss);
      if ($risk > 0) {
        $ratio = $reward / $risk;
        $risk_reward_ratio = '1:' . number_format($ratio, 1);
      }
    }
    
    // Combine date and time into DATETIME format
    $entry_datetime = $trade_date . ' ' . $trade_time;
    $exit_datetime = ($close_date && $close_time) ? $close_date . ' ' . $close_time : null;
    
    // Insert trade
    $stmt = $conn->prepare("
      INSERT INTO forex_trades (
        user_id, entry_date, exit_date, 
        currency_pair, trade_type, entry_price, exit_price, pips, risk_reward_ratio,
        lot_size, startup_amount, usd_amount, rwf_amount,
        profit_loss, status, notes
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $user_id, $entry_datetime, $exit_datetime,
      $pair, $trade_type, $entry_price, $exit_price, $pips, $risk_reward_ratio,
      $lot_size, $startup_amount, $usd_amount, $rwf_amount,
      $profit_loss, $status, $notes
    ]);
    
    // Update or create Forex Account balance
    $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Forex Account' LIMIT 1");
    $accStmt->execute([$user_id]);
    $forexAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$forexAccount) {
      // Create Forex Account if it doesn't exist (currency in RWF)
      $createAcc = $conn->prepare("INSERT INTO accounts (user_id, name, type, balance, currency) VALUES (?, 'Forex Account', 'Forex', 0, 'RWF')");
      $createAcc->execute([$user_id]);
      $forex_account_id = $conn->lastInsertId();
    } else {
      $forex_account_id = $forexAccount['id'];
    }
    
    // Recalculate total balance from all trades (using RWF amounts)
    $totalStmt = $conn->prepare("SELECT COALESCE(SUM(rwf_amount), 0) as total FROM forex_trades WHERE user_id = ?");
    $totalStmt->execute([$user_id]);
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Update account balance to match total RWF amount
    $balStmt = $conn->prepare("UPDATE accounts SET balance = ?, currency = 'RWF' WHERE id = ?");
    $balStmt->execute([$total, $forex_account_id]);
    
    $conn->commit();
    $success_msg = "Trade recorded successfully! P/L: $" . number_format($profit_loss, 2) . " USD (" . number_format($rwf_amount, 0) . " RWF)";
    $_POST = array();
    
  } catch (Exception $e) {
    $conn->rollBack();
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Handle delete trade
if (isset($_GET['delete'])) {
  try {
    $conn->beginTransaction();
    
    $trade_id = intval($_GET['delete']);
    $deleteStmt = $conn->prepare("DELETE FROM forex_trades WHERE id = ? AND user_id = ?");
    $deleteStmt->execute([$trade_id, $user_id]);
    
    // Recalculate and update Forex Account balance
    $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Forex Account' LIMIT 1");
    $accStmt->execute([$user_id]);
    $forexAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($forexAccount) {
      $totalStmt = $conn->prepare("SELECT COALESCE(SUM(rwf_amount), 0) as total FROM forex_trades WHERE user_id = ?");
      $totalStmt->execute([$user_id]);
      $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
      
      $balStmt = $conn->prepare("UPDATE accounts SET balance = ?, currency = 'RWF' WHERE id = ?");
      $balStmt->execute([$total, $forexAccount['id']]);
    }
    
    $conn->commit();
    $success_msg = "Trade deleted successfully!";
    header('Location: /MY CASH/pages/forex_journal.php');
    exit;
    
  } catch (Exception $e) {
    $conn->rollBack();
    $error_msg = "Error deleting trade: " . $e->getMessage();
  }
}

// Get all trades
$stmt = $conn->prepare("SELECT * FROM forex_trades WHERE user_id = ? ORDER BY entry_date DESC");
$stmt->execute([$user_id]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map database columns to expected display names
foreach ($trades as &$trade) {
  $trade['pair'] = $trade['currency_pair'] ?? '';
  $trade['type'] = $trade['trade_type'] ?? 'buy';
  
  // Extract date and time from entry_date (DATETIME)
  if (!empty($trade['entry_date'])) {
    $trade['trade_date'] = date('Y-m-d', strtotime($trade['entry_date']));
    $trade['trade_time'] = date('H:i:s', strtotime($trade['entry_date']));
  }
  
  // Extract date and time from exit_date (DATETIME)
  if (!empty($trade['exit_date'])) {
    $trade['close_date'] = date('Y-m-d', strtotime($trade['exit_date']));
    $trade['close_time'] = date('H:i:s', strtotime($trade['exit_date']));
  }
}
unset($trade); // Break reference

// Calculate statistics
$total_profit = 0;
$total_loss = 0;
$winning_trades = 0;
$losing_trades = 0;
$total_pips = 0;
$total_rwf = 0;

foreach ($trades as $trade) {
  if ($trade['profit_loss'] > 0) {
    $total_profit += $trade['profit_loss'];
    $winning_trades++;
  } elseif ($trade['profit_loss'] < 0) {
    $total_loss += abs($trade['profit_loss']);
    $losing_trades++;
  }
  
  // Sum pips and RWF
  if ($trade['pips'] !== null) {
    $total_pips += $trade['pips'];
  }
  if ($trade['rwf_amount'] !== null) {
    $total_rwf += $trade['rwf_amount'];
  }
}

$total_trades = count($trades);
$net_profit = $total_profit - $total_loss;
$win_rate = $total_trades > 0 ? ($winning_trades / $total_trades) * 100 : 0;

include __DIR__ . '/../includes/header.php';
?>

<style>
.forex-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:24px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow)}
.stat-card.profit{border-top:4px solid #10b981}
.stat-card.loss{border-top:4px solid #ef4444}
.stat-card.net{border-top:4px solid #8b5cf6}
.stat-card.rate{border-top:4px solid #3b82f6}
.stat-card.pips{border-top:4px solid #f59e0b}
.stat-card.rwf{border-top:4px solid #06b6d4}
.stat-label{font-size:.85rem;color:var(--muted);margin-bottom:8px}
.stat-value{font-size:2rem;font-weight:700;color:var(--card-text)}
.stat-value.positive{color:#10b981}
.stat-value.negative{color:#ef4444}
.trades-table{width:100%;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden}
.trades-table th{background:rgba(139,92,246,.1);padding:16px;text-align:left;font-weight:600;color:var(--card-text);border-bottom:1px solid var(--border-weak)}
.trades-table td{padding:16px;border-bottom:1px solid var(--border-weak)}
.trades-table tr:last-child td{border-bottom:none}
.trades-table tr:hover{background:rgba(139,92,246,.05)}
.type-badge{padding:4px 12px;border-radius:16px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.type-badge.buy{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3)}
.type-badge.sell{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3)}
.status-badge{padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;display:inline-flex;align-items:center;gap:4px}
.status-badge::before{content:'';width:6px;height:6px;border-radius:50%;display:inline-block}
.status-badge.status-open{background:linear-gradient(135deg,rgba(59,130,246,.2),rgba(59,130,246,.1));color:#3b82f6;border:1px solid rgba(59,130,246,.4);box-shadow:0 2px 8px rgba(59,130,246,.2)}
.status-badge.status-open::before{background:#3b82f6;animation:pulse 2s infinite}
.status-badge.status-pending{background:linear-gradient(135deg,rgba(251,191,36,.2),rgba(251,191,36,.1));color:#f59e0b;border:1px solid rgba(251,191,36,.4);box-shadow:0 2px 8px rgba(251,191,36,.2)}
.status-badge.status-pending::before{background:#f59e0b}
.status-badge.status-win{background:linear-gradient(135deg,rgba(16,185,129,.2),rgba(16,185,129,.1));color:#10b981;border:1px solid rgba(16,185,129,.4);box-shadow:0 2px 8px rgba(16,185,129,.2)}
.status-badge.status-win::before{background:#10b981}
.status-badge.status-loss{background:linear-gradient(135deg,rgba(239,68,68,.2),rgba(239,68,68,.1));color:#ef4444;border:1px solid rgba(239,68,68,.4);box-shadow:0 2px 8px rgba(239,68,68,.2)}
.status-badge.status-loss::before{background:#ef4444}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
.profit-cell{font-weight:700;font-size:1.1rem}
.profit-cell.positive{color:#10b981}
.profit-cell.negative{color:#ef4444}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px">
  <div>
    <h2>📊 Forex Trading Journal</h2>
    <p class="muted">Track your forex trades and performance</p>
  </div>
  <button id="show-add-trade" class="button primary">+ Add Trade</button>
</div>

<?php if ($success_msg): ?>
<div class="alert success" style="margin-bottom:16px"><?=htmlspecialchars($success_msg)?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert danger" style="margin-bottom:16px"><?=htmlspecialchars($error_msg)?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="forex-stats">
  <div class="stat-card profit">
    <div class="stat-label">Total Profit</div>
    <div class="stat-value positive">$<?=number_format($total_profit, 2)?></div>
    <div class="stat-label"><?=$winning_trades?> winning trades</div>
  </div>
  <div class="stat-card loss">
    <div class="stat-label">Total Loss</div>
    <div class="stat-value negative">$<?=number_format($total_loss, 2)?></div>
    <div class="stat-label"><?=$losing_trades?> losing trades</div>
  </div>
  <div class="stat-card net">
    <div class="stat-label">Net Profit/Loss (USD)</div>
    <div class="stat-value <?=$net_profit >= 0 ? 'positive' : 'negative'?>">$<?=number_format($net_profit, 2)?></div>
    <div class="stat-label">Total: <?=$total_trades?> trades</div>
  </div>
  <div class="stat-card rwf">
    <div class="stat-label">Net P/L (RWF)</div>
    <div class="stat-value <?=$total_rwf >= 0 ? 'positive' : 'negative'?>"><?=number_format($total_rwf, 0)?> FRW</div>
    <div class="stat-label">1 USD = 1,500 RWF</div>
  </div>
  <div class="stat-card pips">
    <div class="stat-label">Total Pips</div>
    <div class="stat-value <?=$total_pips >= 0 ? 'positive' : 'negative'?>"><?=number_format($total_pips, 1)?></div>
    <div class="stat-label">Across all trades</div>
  </div>
  <div class="stat-card rate">
    <div class="stat-label">Win Rate</div>
    <div class="stat-value"><?=number_format($win_rate, 1)?>%</div>
    <div class="stat-label"><?=$winning_trades?>W / <?=$losing_trades?>L</div>
  </div>
</div>

<!-- Trades Table -->
<?php if (empty($trades)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
  <div style="font-size:3rem;margin-bottom:16px;opacity:.3">📈</div>
  <h3>No trades recorded yet</h3>
  <p class="muted">Start tracking your forex trades to see your performance</p>
</div>
<?php else: ?>
<table class="trades-table">
  <thead>
    <tr>
      <th>Date</th>
      <th>Pair</th>
      <th>Type</th>
      <th>Status</th>
      <th>Entry</th>
      <th>Exit</th>
      <th>Pips</th>
      <th>P/L (USD)</th>
      <th>P/L (RWF)</th>
      <th>R:R</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($trades as $trade): 
      $status = $trade['status'] ?? 'closed_win';
      $statusLabel = '';
      $statusClass = '';
      switch($status) {
        case 'open':
          $statusLabel = 'OPEN';
          $statusClass = 'status-open';
          break;
        case 'pending':
          $statusLabel = 'PENDING';
          $statusClass = 'status-pending';
          break;
        case 'closed_win':
          $statusLabel = 'WIN';
          $statusClass = 'status-win';
          break;
        case 'closed_loss':
          $statusLabel = 'LOSS';
          $statusClass = 'status-loss';
          break;
      }
    ?>
    <tr>
      <td><?=date('M d, Y', strtotime($trade['trade_date']))?></td>
      <td><strong><?=htmlspecialchars($trade['pair'])?></strong></td>
      <td><span class="type-badge <?=htmlspecialchars($trade['type'])?>"><?=strtoupper($trade['type'])?></span></td>
      <td><span class="status-badge <?=$statusClass?>"><?=$statusLabel?></span></td>
      <td><?=number_format($trade['entry_price'], 5)?></td>
      <td>
        <?php if ($trade['exit_price']): ?>
          <?=number_format($trade['exit_price'], 5)?>
        <?php else: ?>
          <span style="color:var(--muted);font-style:italic">—</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($trade['pips'] !== null): ?>
          <span class="<?=$trade['pips'] >= 0 ? 'positive' : 'negative'?>" style="font-weight:600">
            <?=number_format($trade['pips'], 1)?>
          </span>
        <?php else: ?>
          <span style="color:var(--muted);font-style:italic">—</span>
        <?php endif; ?>
      </td>
      <td class="profit-cell <?=$trade['profit_loss'] >= 0 ? 'positive' : 'negative'?>">
        <?php if ($status === 'open' || $status === 'pending'): ?>
          <span style="color:var(--muted);font-style:italic">—</span>
        <?php else: ?>
          $<?=number_format($trade['profit_loss'], 2)?>
        <?php endif; ?>
      </td>
      <td class="<?=$trade['rwf_amount'] >= 0 ? 'positive' : 'negative'?>">
        <?php if ($status === 'open' || $status === 'pending'): ?>
          <span style="color:var(--muted);font-style:italic">—</span>
        <?php else: ?>
          <?=number_format($trade['rwf_amount'], 0)?> FRW
        <?php endif; ?>
      </td>
      <td>
        <?php if ($trade['risk_reward_ratio']): ?>
          <span style="font-weight:500;color:var(--accent-primary)"><?=htmlspecialchars($trade['risk_reward_ratio'])?></span>
        <?php else: ?>
          <span style="color:var(--muted);font-style:italic">—</span>
        <?php endif; ?>
      </td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="/MY CASH/pages/forex_trade_detail.php?id=<?=$trade['id']?>" class="button secondary small" title="View Details">👁️</a>
          <a href="?delete=<?=$trade['id']?>" onclick="return confirm('Delete this trade?')" class="button danger small" title="Delete">🗑️</a>
        </div>
      </td>
    </tr>
    <?php if (!empty($trade['notes'])): ?>
    <tr>
      <td colspan="11" style="background:rgba(139,92,246,.05);font-size:.85rem;color:var(--muted);font-style:italic">
        📝 <?=htmlspecialchars($trade['notes'])?>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<script>
document.getElementById('show-add-trade').addEventListener('click', function() {
  const today = new Date().toISOString().split('T')[0];
  const now = new Date().toTimeString().split(' ')[0].slice(0,5);
  
  var html = '<div style="padding:24px"><h3 style="margin-bottom:24px">📈 Add New Trade</h3>' +
    '<form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
    '<div><label>Trade Date</label><input type="date" name="trade_date" value="' + today + '" required style="width:100%"></div>' +
    '<div><label>Trade Time</label><input type="time" name="trade_time" value="' + now + '" required style="width:100%"></div>' +
    '<div><label>Currency Pair</label><input type="text" name="pair" placeholder="EUR/USD" required style="width:100%"></div>' +
    '<div><label>Type</label><select name="type" required style="width:100%"><option value="buy">Buy</option><option value="sell">Sell</option></select></div>' +
    '<div><label>Status</label><select name="status" id="trade-status" onchange="toggleCloseDateFields(this)" required style="width:100%">' +
    '<option value="open">Open (Active Trade)</option>' +
    '<option value="pending">Pending (Awaiting Entry)</option>' +
    '<option value="closed_win" selected>Closed - Win</option>' +
    '<option value="closed_loss">Closed - Loss</option>' +
    '</select></div>' +
    '<div id="close-date-field"><label>Close Date</label><input type="date" name="close_date" value="' + today + '" style="width:100%"></div>' +
    '<div><label>Entry Price</label><input type="number" step="0.00001" name="entry_price" placeholder="1.08500" required style="width:100%"></div>' +
    '<div><label>Exit Price</label><input type="number" step="0.00001" name="exit_price" placeholder="1.08750" style="width:100%"></div>' +
    '<div><label>Lot Size</label><input type="number" step="0.01" name="lot_size" placeholder="0.10" required style="width:100%"></div>' +
    '<div><label>Startup Amount ($)</label><input type="number" step="0.01" name="startup_amount" value="1000.00" placeholder="1000.00" style="width:100%"><small style="display:block;margin-top:4px;color:var(--muted)">Initial capital for this trade</small></div>' +
    '<div><label>Profit/Loss ($)</label><input type="number" step="0.01" name="profit_loss" placeholder="25.50" style="width:100%"><small style="display:block;margin-top:4px;color:var(--muted)">Leave empty for open trades</small></div>' +
    '<div style="grid-column:1/-1"><label>Notes (Optional)</label><textarea name="notes" rows="3" placeholder="Trade notes, strategy, etc..." style="width:100%"></textarea></div>' +
    '<div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end">' +
    '<button type="button" class="button ghost" onclick="WCModal.close()">Cancel</button>' +
    '<button type="submit" name="add_trade" class="button primary">💾 Add Trade</button>' +
    '</div>' +
    '</form></div>';
  window.WCModal.open(html);
  
  // Add toggle function after modal opens
  setTimeout(function() {
    window.toggleCloseDateFields = function(select) {
      const closeField = document.getElementById('close-date-field');
      if (select.value === 'open' || select.value === 'pending') {
        closeField.style.display = 'none';
      } else {
        closeField.style.display = 'block';
      }
    };
  }, 100);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
