<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

$cols = []; try { $colStmt = $conn->query("SHOW COLUMNS FROM accounts"); $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0); } catch (Exception $e) { $cols = []; }
$hasType = in_array('type', $cols, true); $hasBalance = in_array('balance', $cols, true); $hasCurrency = in_array('currency', $cols, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
  $name = trim($_POST['name']); $fields = ['name','user_id']; $placeholders = ['?','?']; $values = [$name, $user_id];
  if ($hasType) { $type = trim($_POST['type'] ?? 'Checking'); $fields[] = 'type'; $placeholders[] = '?'; $values[] = $type; }
  if ($hasBalance) { $balance = floatval($_POST['balance'] ?? 0); $fields[] = 'balance'; $placeholders[] = '?'; $values[] = $balance; }
  if ($hasCurrency) { $currency = trim($_POST['currency'] ?? 'USD'); $fields[] = 'currency'; $placeholders[] = '?'; $values[] = $currency; }
  $sql = "INSERT INTO accounts (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
  $stmt = $conn->prepare($sql); $stmt->execute($values);
  header('Location: /MY CASH/pages/accounts.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_account'])) {
  $edit_id = intval($_POST['edit_id']); $edit_name = trim($_POST['edit_name']); $params = [$edit_name]; $sql = "UPDATE accounts SET name = ?";
  if ($hasType) { $sql .= ", type = ?"; $params[] = trim($_POST['edit_type'] ?? 'Checking'); }
  if ($hasBalance) { $sql .= ", balance = ?"; $params[] = floatval($_POST['edit_balance'] ?? 0); }
  if ($hasCurrency) { $sql .= ", currency = ?"; $params[] = trim($_POST['edit_currency'] ?? 'USD'); }
  $sql .= " WHERE id = ? AND user_id = ?"; $params[] = $edit_id; $params[] = $user_id;
  $stmt = $conn->prepare($sql); $stmt->execute($params);
  header('Location: /MY CASH/pages/accounts.php'); exit;
}

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']); $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
  $stmt->execute([$id, $user_id]); header('Location: /MY CASH/pages/accounts.php'); exit;
}

$transferError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer'])) {
  $fromId = intval($_POST['from_account']); $toId = intval($_POST['to_account']); $amount = floatval($_POST['amount'] ?? 0); $notes = trim($_POST['notes'] ?? '');
  if ($fromId === $toId) { $transferError = 'Cannot transfer to the same account.'; }
  elseif ($amount <= 0) { $transferError = 'Amount must be greater than zero.'; }
  else {
    try {
      $s = $conn->prepare('SELECT id, user_id FROM accounts WHERE id IN (?,?) AND user_id = ?');
      $s->execute([$fromId,$toId,$user_id]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
      if (count($rows) < 2) { $transferError = 'Invalid accounts.'; }
      else {
        $conn->beginTransaction();
        if ($hasBalance) {
          $bq = $conn->prepare('SELECT balance FROM accounts WHERE id = ?'); $bq->execute([$fromId]); $row = $bq->fetch(PDO::FETCH_ASSOC);
          if (!$row || floatval($row['balance']) < $amount) { $conn->rollBack(); $transferError = 'Insufficient funds.'; }
          else {
            $ins = $conn->prepare('INSERT INTO transactions (account_id,type,amount,notes) VALUES (?,?,?,?)');
            $ins->execute([$fromId,'withdraw',$amount,$notes]);
            $ins->execute([$toId,'deposit',$amount,'Transfer from account '.$fromId.($notes?(': '.$notes):'')]);
            $upd = $conn->prepare('UPDATE accounts SET balance = balance - ? WHERE id = ?'); $upd->execute([$amount,$fromId]);
            $upd2 = $conn->prepare('UPDATE accounts SET balance = balance + ? WHERE id = ?'); $upd2->execute([$amount,$toId]);
            $conn->commit(); header('Location: /MY CASH/pages/accounts.php'); exit;
          }
        } else {
          $balStmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount WHEN type='withdraw' THEN -amount ELSE 0 END),0) as computed FROM transactions WHERE account_id = ?");
          $balStmt->execute([$fromId]); $row = $balStmt->fetch(PDO::FETCH_ASSOC);
          if (!$row || floatval($row['computed']) < $amount) { $transferError = 'Insufficient funds.'; }
          else {
            $ins = $conn->prepare('INSERT INTO transactions (account_id,type,amount,notes) VALUES (?,?,?,?)');
            $ins->execute([$fromId,'withdraw',$amount,$notes]);
            $ins->execute([$toId,'deposit',$amount,'Transfer from account '.$fromId.($notes?(': '.$notes):'')]);
            $conn->commit(); header('Location: /MY CASH/pages/accounts.php'); exit;
          }
        }
      }
    } catch (Exception $e) { if ($conn->inTransaction()) $conn->rollBack(); $transferError = 'Transfer failed: ' . $e->getMessage(); }
  }
}

try {
  if ($hasBalance) {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY CASE WHEN name = 'Business Account' THEN 0 ELSE 1 END, id DESC");
    $stmt->execute([$user_id]); $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt = $conn->prepare("SELECT a.*, COALESCE((SELECT SUM(CASE WHEN t.type='deposit' THEN t.amount WHEN t='withdraw' THEN -t.amount ELSE 0 END) FROM transactions t WHERE t.account_id = a.id),0) AS balance FROM accounts a WHERE a.user_id = ? ORDER BY CASE WHEN a.name = 'Business Account' THEN 0 ELSE 1 END, a.id DESC");
    $stmt->execute([$user_id]); $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) { $accounts = []; }

// Auto-sync Forex Account balance with trades
try {
  // Check if Forex Account exists
  $forexAccStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Forex Account'");
  $forexAccStmt->execute([$user_id]);
  $forexAcc = $forexAccStmt->fetch(PDO::FETCH_ASSOC);
  
  if ($forexAcc) {
    // Get total P/L from all trades
    $tradeStmt = $conn->prepare("SELECT COALESCE(SUM(profit_loss), 0) as total FROM forex_trades WHERE user_id = ?");
    $tradeStmt->execute([$user_id]);
    $tradeTotal = $tradeStmt->fetch(PDO::FETCH_ASSOC);
    
    // Update Forex Account balance to match trades
    $updateStmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
    $updateStmt->execute([$tradeTotal['total'], $forexAcc['id']]);
  }
} catch (Exception $e) {}

// Get today's sales stats for Business Account
$today = date('Y-m-d');
$today_sales_stats = ['count' => 0, 'total' => 0];
try {
  $salesStmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM employee_tasks WHERE user_id = ? AND task_date = ? AND transaction_type = 'sale'");
  $salesStmt->execute([$user_id, $today]);
  $today_sales_stats = $salesStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get comprehensive Forex statistics
$forex_summary = [
  'total_trades' => 0,
  'total_profit' => 0,
  'total_loss' => 0,
  'net_profit' => 0,
  'winning_trades' => 0,
  'losing_trades' => 0,
  'win_rate' => 0,
  'account_balance' => 0
];

try {
  // Get trade statistics
  $forexSummaryStmt = $conn->prepare("
    SELECT 
      COUNT(*) as total_trades,
      COALESCE(SUM(CASE WHEN profit_loss > 0 THEN profit_loss ELSE 0 END), 0) as total_profit,
      COALESCE(SUM(CASE WHEN profit_loss < 0 THEN ABS(profit_loss) ELSE 0 END), 0) as total_loss,
      COALESCE(SUM(profit_loss), 0) as net_profit,
      SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
      SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades
    FROM forex_trades 
    WHERE user_id = ?
  ");
  $forexSummaryStmt->execute([$user_id]);
  $forexData = $forexSummaryStmt->fetch(PDO::FETCH_ASSOC);
  
  if ($forexData && $forexData['total_trades'] > 0) {
    $forex_summary = array_merge($forex_summary, $forexData);
    $forex_summary['win_rate'] = $forexData['total_trades'] > 0 
      ? round(($forexData['winning_trades'] / $forexData['total_trades']) * 100, 1) 
      : 0;
  }
  
  // Get Forex Account balance
  $forexAccBalStmt = $conn->prepare("SELECT balance FROM accounts WHERE user_id = ? AND name = 'Forex Account'");
  $forexAccBalStmt->execute([$user_id]);
  $forexAccBal = $forexAccBalStmt->fetch(PDO::FETCH_ASSOC);
  if ($forexAccBal) {
    $forex_summary['account_balance'] = floatval($forexAccBal['balance']);
  }
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
.accounts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-top:24px}
.account-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;box-shadow:var(--overlay-shadow);transition:transform .2s,box-shadow .2s}
.account-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(var(--card-text-rgb),.2)}
.account-card.checking{border-top:4px solid #667eea;background:linear-gradient(135deg,rgba(102,126,234,.08),rgba(102,126,234,.02))}
.account-card.savings{border-top:4px solid #2ed573;background:linear-gradient(135deg,rgba(46,213,115,.08),rgba(46,213,115,.02))}
.account-card.cash{border-top:4px solid #ffa500;background:linear-gradient(135deg,rgba(255,165,0,.08),rgba(255,165,0,.02))}
.account-card.business{border-top:4px solid #10b981;background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(16,185,129,.04));border:2px solid rgba(16,185,129,.3)}
.account-card.forex{border-top:4px solid #8b5cf6;background:linear-gradient(135deg,rgba(139,92,246,.12),rgba(139,92,246,.04));border:2px solid rgba(139,92,246,.3)}
.business-badge{background:linear-gradient(135deg,#10b981,#059669);color:white;padding:4px 12px;border-radius:16px;font-size:.7rem;font-weight:700;text-transform:uppercase;box-shadow:0 2px 8px rgba(16,185,129,.3);display:inline-block;margin-left:8px}
.forex-badge{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;padding:4px 12px;border-radius:16px;font-size:.7rem;font-weight:700;text-transform:uppercase;box-shadow:0 2px 8px rgba(139,92,246,.3);display:inline-block;margin-left:8px}
.account-name{font-size:1.4rem;font-weight:700;margin-bottom:8px;color:var(--card-text)}
.account-type-badge{display:inline-block;padding:4px 12px;border-radius:16px;font-size:.75rem;font-weight:600;text-transform:uppercase;margin-bottom:16px}
.account-type-badge.checking{background:rgba(102,126,234,.15);color:#667eea;border:1px solid rgba(102,126,234,.3)}
.account-type-badge.savings{background:rgba(46,213,115,.15);color:#2ed573;border:1px solid rgba(46,213,115,.3)}
.account-type-badge.cash{background:rgba(255,165,0,.15);color:#ffa500;border:1px solid rgba(255,165,0,.3)}
.account-type-badge.business{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3)}
.account-type-badge.forex{background:rgba(139,92,246,.15);color:#8b5cf6;border:1px solid rgba(139,92,246,.3)}
.business-info{background:rgba(16,185,129,.08);border-radius:8px;padding:12px;margin-top:12px;font-size:.85rem}
.business-info-item{display:flex;justify-content:space-between;margin:4px 0;color:var(--card-text)}
.forex-info{background:rgba(139,92,246,.08);border-radius:8px;padding:12px;margin-top:12px;font-size:.85rem}
.forex-info-item{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(124,58,237,.1)}
.forex-info-item:last-child{border-bottom:none}
.forex-summary-card{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:20px;padding:32px;margin-bottom:32px;color:white;box-shadow:0 12px 40px rgba(102,126,234,.4);position:relative;overflow:hidden}
.forex-summary-card::before{content:'';position:absolute;top:-50%;right:-50%;width:200%;height:200%;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,transparent 70%);pointer-events:none}
.forex-summary-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;position:relative;z-index:1}
.forex-summary-title{font-size:1.8rem;font-weight:800;display:flex;align-items:center;gap:12px}
.forex-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px;position:relative;z-index:1}
.forex-stat-box{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:20px;text-align:center;transition:transform .2s,background .2s}
.forex-stat-box:hover{transform:translateY(-4px);background:rgba(255,255,255,.25)}
.forex-stat-label{font-size:.85rem;text-transform:uppercase;font-weight:600;opacity:.9;margin-bottom:8px;letter-spacing:.5px}
.forex-stat-value{font-size:2rem;font-weight:900;margin-bottom:4px}
.forex-stat-sub{font-size:.8rem;opacity:.8}
@media (max-width:768px){.accounts-grid{grid-template-columns:1fr}.forex-stats-grid{grid-template-columns:repeat(2,1fr)}}
.account-balance{font-size:2rem;font-weight:700;margin:16px 0;color:var(--card-text)}
.account-currency{font-size:.9rem;color:var(--muted);margin-top:4px}
.account-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border-weak)}
.account-actions .button{flex:1;min-width:fit-content;font-size:.85rem}
@media (max-width:768px){.accounts-grid{grid-template-columns:1fr}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px">
<div><h2> Accounts</h2><p class="muted">Manage your financial accounts</p></div>
<button id="show-add-account" class="button primary">+ Add Account</button>
</div>

<?php if (!empty($transferError)): ?>
<div class="alert danger" style="margin-bottom:16px"><?=htmlspecialchars($transferError)?></div>
<?php endif; ?>

<?php if ($forex_summary['total_trades'] > 0): ?>
<div class="forex-summary-card">
<div class="forex-summary-header">
<div class="forex-summary-title">
<span>📈</span>
<span>Forex Trading Summary</span>
</div>
<a href="/MY CASH/pages/forex_journal.php" class="button" style="background:rgba(255,255,255,.2);color:white;border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(10px)">
View Journal →
</a>
</div>

<div class="forex-stats-grid">
<div class="forex-stat-box">
<div class="forex-stat-label">Net P/L</div>
<div class="forex-stat-value" style="color:<?=$forex_summary['net_profit'] >= 0 ? '#10b981' : '#fca5a5'?>">
$<?=number_format(abs($forex_summary['net_profit']), 2)?>
</div>
<div class="forex-stat-sub"><?=$forex_summary['net_profit'] >= 0 ? 'Profit' : 'Loss'?></div>
</div>

<div class="forex-stat-box">
<div class="forex-stat-label">Total Profit</div>
<div class="forex-stat-value" style="color:#10b981">
$<?=number_format($forex_summary['total_profit'], 2)?>
</div>
<div class="forex-stat-sub"><?=$forex_summary['winning_trades']?> winning trades</div>
</div>

<div class="forex-stat-box">
<div class="forex-stat-label">Total Loss</div>
<div class="forex-stat-value" style="color:#fca5a5">
$<?=number_format($forex_summary['total_loss'], 2)?>
</div>
<div class="forex-stat-sub"><?=$forex_summary['losing_trades']?> losing trades</div>
</div>

<div class="forex-stat-box">
<div class="forex-stat-label">Win Rate</div>
<div class="forex-stat-value"><?=$forex_summary['win_rate']?>%</div>
<div class="forex-stat-sub"><?=$forex_summary['total_trades']?> total trades</div>
</div>

<div class="forex-stat-box">
<div class="forex-stat-label">Account Balance</div>
<div class="forex-stat-value">
$<?=number_format($forex_summary['account_balance'], 2)?>
</div>
<div class="forex-stat-sub">Current balance</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if (empty($accounts)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
<div style="font-size:3rem;margin-bottom:16px;opacity:.3"></div>
<h3>No accounts yet</h3>
<p class="muted">Create your first account to start managing your finances</p>
</div>
<?php else: ?>
<div class="accounts-grid">
<?php foreach($accounts as $a): 
$balance = floatval($a['balance'] ?? 0); $type = strtolower($a['type'] ?? 'checking'); $currency = $a['currency'] ?? 'USD';
$is_business = ($a['name'] === 'Business Account');
$is_forex = ($a['name'] === 'Forex Account');
?>
<div class="account-card <?=htmlspecialchars($type)?>">
<div class="account-name">
  <?=htmlspecialchars($a['name'])?>
  <?php if ($is_business): ?>
    <span class="business-badge">AUTO-SYNC</span>
  <?php elseif ($is_forex): ?>
    <span class="forex-badge">LIVE SYNC</span>
  <?php endif; ?>
</div>
<?php if ($hasType): ?>
<span class="account-type-badge <?=htmlspecialchars($type)?>"><?=htmlspecialchars(ucfirst($type))?></span>
<?php endif; ?>
<div class="account-balance">
<span class="amount" data-currency="<?=htmlspecialchars($currency)?>" data-amount="<?=$balance?>"><?=number_format($balance,2)?></span>
</div>
<?php if ($hasCurrency): ?>
<div class="account-currency"><?=htmlspecialchars($currency)?> 
<?php if ($currency === 'RWF'): ?>
  • $<?=number_format($balance / 1500, 2)?> USD
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($is_business): ?>
<div class="business-info">
  <div style="font-weight:700;margin-bottom:8px;color:#059669">📊 Today's Business Activity</div>
  <div class="business-info-item">
    <span>Sales Today:</span>
    <span style="font-weight:700;color:#10b981"><?=$today_sales_stats['count']?> transactions</span>
  </div>
  <div class="business-info-item">
    <span>Revenue Today:</span>
    <span style="font-weight:700;color:#10b981">RWF <?=number_format($today_sales_stats['total'], 0)?></span>
  </div>
  <div style="font-size:.75rem;color:#718096;margin-top:8px;font-style:italic">
    💡 All employee sales are automatically deposited here
  </div>
</div>
<?php endif; ?>

<?php if ($is_forex): ?>
<?php
// Get forex trades stats
$forex_stats = ['total_trades' => 0, 'net_profit' => 0];
try {
  $forexStmt = $conn->prepare("SELECT COUNT(*) as total_trades, COALESCE(SUM(profit_loss), 0) as net_profit FROM forex_trades WHERE user_id = ?");
  $forexStmt->execute([$user_id]);
  $forex_stats = $forexStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<div class="forex-info">
  <div style="font-weight:700;margin-bottom:8px;color:#7c3aed">📈 Forex Trading Summary</div>
  <div class="forex-info-item">
    <span>Total Trades:</span>
    <span style="font-weight:700;color:#8b5cf6"><?=$forex_stats['total_trades']?> trades</span>
  </div>
  <div class="forex-info-item">
    <span>Net P/L:</span>
    <span style="font-weight:700;color:<?=$forex_stats['net_profit'] >= 0 ? '#10b981' : '#ef4444'?>">$<?=number_format($forex_stats['net_profit'], 2)?></span>
  </div>
  <div style="font-size:.75rem;color:#718096;margin-top:8px;font-style:italic">
    💡 Synced from your forex trading journal
  </div>
</div>
<?php endif; ?>

<div class="account-actions">
<?php if (!$is_business && !$is_forex): ?>
  <a class="button ghost small" href="/MY CASH/pages/account.php?id=<?=$a['id']?>"> View</a>
  <button class="button ghost small js-edit-account" data-account='<?=json_encode($a)?>'> Edit</button>
  <button class="button ghost small js-transfer-account" data-account='<?=json_encode($a)?>'> Transfer</button>
  <a href="/MY CASH/pages/accounts.php?delete=<?=$a['id']?>" onclick="return confirm('Delete this account?')" class="button danger small"> Delete</a>
<?php elseif ($is_business): ?>
  <button class="button ghost small js-transfer-account" data-account='<?=json_encode($a)?>'> Transfer</button>
  <span style="font-size:.75rem;color:#718096;font-style:italic">Protected account</span>
<?php elseif ($is_forex): ?>
  <button class="button ghost small js-transfer-account" data-account='<?=json_encode($a)?>'> Transfer</button>
  <a class="button primary small" href="/MY CASH/pages/forex_journal.php" style="margin-left:8px">📊 View Forex Journal</a>
  <span style="font-size:.75rem;color:#718096;font-style:italic;margin-top:8px;display:block">Protected account</span>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('show-add-account').addEventListener('click', function() {
  var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Add New Account</h3>' +
    '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
    '<div><label>Account Name</label><input type="text" name="name" placeholder="Checking, Savings, Cash..." required style="width:100%"></div>';
  <?php if ($hasType): ?>
  html += '<div><label>Account Type</label><select name="type" style="width:100%"><option value="Checking">Checking</option><option value="Savings">Savings</option><option value="Cash">Cash</option><option value="Investment">Investment</option></select></div>';
  <?php endif; ?>
  <?php if ($hasBalance): ?>
  html += '<div><label>Starting Balance</label><input type="number" step="0.01" name="balance" value="0.00" style="width:100%"></div>';
  <?php endif; ?>
  <?php if ($hasCurrency): ?>
  html += '<div><label>Currency</label><select name="currency" style="width:100%"><option value="USD">USD</option><option value="RWF">RWF</option><option value="EUR">EUR</option></select></div>';
  <?php endif; ?>
  html += '<div style="display:flex;gap:12px;margin-top:16px">' +
    '<button type="submit" name="add_account" class="button primary" style="flex:1">Add Account</button>' +
    '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
    '</div></form></div>';
  window.WCModal.open(html);
});

document.querySelectorAll('.js-edit-account').forEach(btn => {
  btn.addEventListener('click', function() {
    var account = JSON.parse(this.dataset.account);
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Edit Account</h3>' +
      '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
      '<input type="hidden" name="edit_id" value="' + account.id + '">' +
      '<div><label>Account Name</label><input type="text" name="edit_name" value="' + (account.name||'') + '" required style="width:100%"></div>';
    <?php if ($hasType): ?>
    html += '<div><label>Account Type</label><select name="edit_type" style="width:100%">' +
      '<option value="Checking"'+(account.type==='Checking'?' selected':'')+'>Checking</option>' +
      '<option value="Savings"'+(account.type==='Savings'?' selected':'')+'>Savings</option>' +
      '<option value="Cash"'+(account.type==='Cash'?' selected':'')+'>Cash</option>' +
      '<option value="Investment"'+(account.type==='Investment'?' selected':'')+'>Investment</option></select></div>';
    <?php endif; ?>
    <?php if ($hasBalance): ?>
    html += '<div><label>Balance</label><input type="number" step="0.01" name="edit_balance" value="' + (account.balance||0) + '" style="width:100%"></div>';
    <?php endif; ?>
    <?php if ($hasCurrency): ?>
    html += '<div><label>Currency</label><select name="edit_currency" style="width:100%">' +
      '<option value="USD"'+(account.currency==='USD'?' selected':'')+'>USD</option>' +
      '<option value="RWF"'+(account.currency==='RWF'?' selected':'')+'>RWF</option>' +
      '<option value="EUR"'+(account.currency==='EUR'?' selected':'')+'>EUR</option></select></div>';
    <?php endif; ?>
    html += '<div style="display:flex;gap:12px;margin-top:16px">' +
      '<button type="submit" name="edit_account" class="button primary" style="flex:1">Save Changes</button>' +
      '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
      '</div></form></div>';
    window.WCModal.open(html);
  });
});

document.querySelectorAll('.js-transfer-account').forEach(btn => {
  btn.addEventListener('click', function() {
    var src = JSON.parse(this.dataset.account);
    var options = '';
    <?php foreach($accounts as $ac): ?>
    if (<?=$ac['id']?> !== src.id) options += '<option value="<?=$ac['id']?>"><?=htmlspecialchars(addslashes($ac['name']))?></option>';
    <?php endforeach; ?>
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Transfer from ' + (src.name||'') + '</h3>' +
      '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
      '<input type="hidden" name="from_account" value="' + src.id + '">' +
      '<div><label>To Account</label><select name="to_account" style="width:100%">' + options + '</select></div>' +
      '<div><label>Amount</label><input type="number" step="0.01" name="amount" required style="width:100%"></div>' +
      '<div><label>Notes (optional)</label><input type="text" name="notes" style="width:100%"></div>' +
      '<div style="display:flex;gap:12px;margin-top:16px">' +
      '<button type="submit" name="transfer" class="button primary" style="flex:1">Transfer</button>' +
      '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
      '</div></form></div>';
    window.WCModal.open(html);
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
