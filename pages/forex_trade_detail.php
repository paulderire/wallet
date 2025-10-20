
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

$trade_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$trade_id) {
    header('Location: /MY CASH/pages/forex_journal.php');
    exit;
}

$success_msg = $error_msg = '';

// Handle trade deletion
if (isset($_GET['delete'])) {
    try {
        $conn->beginTransaction();
        
        // Delete trade
        $stmt = $conn->prepare("DELETE FROM forex_trades WHERE id = ? AND user_id = ?");
        $stmt->execute([$trade_id, $user_id]);
        
        // Update Forex Account balance
        $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Forex Account' LIMIT 1");
        $accStmt->execute([$user_id]);
        $forexAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($forexAccount) {
            $totalStmt = $conn->prepare("SELECT COALESCE(SUM(profit_loss), 0) as total FROM forex_trades WHERE user_id = ?");
            $totalStmt->execute([$user_id]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $balStmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $balStmt->execute([$total, $forexAccount['id']]);
        }
        
        $conn->commit();
        header('Location: /MY CASH/pages/forex_journal.php?deleted=1');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_msg = "Error deleting trade: " . $e->getMessage();
    }
}

// Handle trade update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_trade'])) {
    try {
        $conn->beginTransaction();
        
        $trade_date = $_POST['trade_date'];
        $trade_time = $_POST['trade_time'];
        $pair = trim($_POST['pair']);
        $trade_type = $_POST['type'];
        $entry_price = floatval($_POST['entry_price']);
        $exit_price = isset($_POST['exit_price']) && $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
        $lot_size = floatval($_POST['lot_size']);
        $profit_loss = isset($_POST['profit_loss']) && $_POST['profit_loss'] !== '' ? floatval($_POST['profit_loss']) : null;
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'];
        $close_date = $_POST['close_date'] ?? null;
        $close_time = $_POST['close_time'] ?? null;
        $startup_amount = isset($_POST['startup_amount']) && $_POST['startup_amount'] !== '' ? floatval($_POST['startup_amount']) : 1000.00;
        
        // Calculate pips
        $pips = null;
        if ($exit_price !== null) {
            if ($trade_type === 'buy') {
                $pips = ($exit_price - $entry_price) * 10000;
            } else {
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
        
        // Calculate amounts
        $usd_amount = $profit_loss !== null ? abs($profit_loss) : null;
        $rwf_amount = $profit_loss !== null ? $profit_loss * 1500 : null;
        
        // Calculate R:R
        $risk_reward_ratio = null;
        if ($profit_loss !== null && $profit_loss != 0) {
            $risk = $startup_amount * 0.02;
            $reward = abs($profit_loss);
            if ($risk > 0) {
                $ratio = $reward / $risk;
                $risk_reward_ratio = '1:' . number_format($ratio, 1);
            }
        }
        
        // Combine date and time for entry_date and exit_date
        $entry_datetime = $trade_date . ' ' . $trade_time;
        $exit_datetime = ($close_date && $close_time) ? $close_date . ' ' . $close_time : null;
        
        // Update trade
        $stmt = $conn->prepare("
            UPDATE forex_trades SET
                entry_date = ?, exit_date = ?,
                currency_pair = ?, trade_type = ?, entry_price = ?, exit_price = ?, pips = ?,
                risk_reward_ratio = ?, lot_size = ?, startup_amount = ?,
                usd_amount = ?, rwf_amount = ?, profit_loss = ?, status = ?, notes = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $entry_datetime, $exit_datetime,
            $pair, $trade_type, $entry_price, $exit_price, $pips,
            $risk_reward_ratio, $lot_size, $startup_amount,
            $usd_amount, $rwf_amount, $profit_loss, $status, $notes,
            $trade_id, $user_id
        ]);
        
        // Update Forex Account balance
        $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Forex Account' LIMIT 1");
        $accStmt->execute([$user_id]);
        $forexAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($forexAccount) {
            $totalStmt = $conn->prepare("SELECT COALESCE(SUM(profit_loss), 0) as total FROM forex_trades WHERE user_id = ?");
            $totalStmt->execute([$user_id]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $balStmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $balStmt->execute([$total, $forexAccount['id']]);
        }
        
        $conn->commit();
        $success_msg = "Trade updated successfully!";
        
        // Re-fetch updated trade data
        $stmt = $conn->prepare("SELECT * FROM forex_trades WHERE id = ? AND user_id = ?");
        $stmt->execute([$trade_id, $user_id]);
        $trade = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Fetch trade details (if not already fetched from update)
if (!isset($trade) || !$trade) {
    $stmt = $conn->prepare("SELECT * FROM forex_trades WHERE id = ? AND user_id = ?");
    $stmt->execute([$trade_id, $user_id]);
    $trade = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$trade || empty($trade)) {
    // Trade not found - redirect back
    header('Location: /MY CASH/pages/forex_journal.php?error=trade_not_found');
    exit;
}

// Map database columns to expected names and set defaults
$trade['pair'] = $trade['currency_pair'] ?? '';
$trade['type'] = $trade['trade_type'] ?? 'buy';

// Extract date and time from entry_date (DATETIME)
$entry_datetime = $trade['entry_date'] ?? date('Y-m-d H:i:s');
$trade['trade_date'] = date('Y-m-d', strtotime($entry_datetime));
$trade['trade_time'] = date('H:i:s', strtotime($entry_datetime));

// Extract date and time from exit_date (DATETIME)
if (!empty($trade['exit_date'])) {
    $exit_datetime = $trade['exit_date'];
    $trade['close_date'] = date('Y-m-d', strtotime($exit_datetime));
    $trade['close_time'] = date('H:i:s', strtotime($exit_datetime));
} else {
    $trade['close_date'] = null;
    $trade['close_time'] = null;
}

// Set other defaults
$trade['entry_price'] = $trade['entry_price'] ?? 0;
$trade['exit_price'] = $trade['exit_price'] ?? null;
$trade['lot_size'] = $trade['lot_size'] ?? 0;
$trade['profit_loss'] = $trade['profit_loss'] ?? 0;
$trade['pips'] = $trade['pips'] ?? null;
$trade['usd_amount'] = $trade['usd_amount'] ?? null;
$trade['rwf_amount'] = $trade['rwf_amount'] ?? null;
$trade['risk_reward_ratio'] = $trade['risk_reward_ratio'] ?? null;
$trade['startup_amount'] = $trade['startup_amount'] ?? 1000;
$trade['notes'] = $trade['notes'] ?? '';
$trade['status'] = $trade['status'] ?? 'open';

// Determine status details
$status = $trade['status'];
$statusLabel = '';
$statusClass = '';
$statusIcon = '';
switch($status) {
    case 'open':
        $statusLabel = 'OPEN';
        $statusClass = 'status-open';
        $statusIcon = '🔵';
        break;
    case 'pending':
        $statusLabel = 'PENDING';
        $statusClass = 'status-pending';
        $statusIcon = '🟡';
        break;
    case 'closed_win':
        $statusLabel = 'WIN';
        $statusClass = 'status-win';
        $statusIcon = '🟢';
        break;
    case 'closed_loss':
        $statusLabel = 'LOSS';
        $statusClass = 'status-loss';
        $statusIcon = '🔴';
        break;
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.trade-detail-container{max-width:1200px;margin:0 auto}
.detail-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:32px;border-radius:16px;margin-bottom:24px;box-shadow:0 8px 32px rgba(102,126,234,0.3)}
.detail-header h1{margin:0 0 8px 0;font-size:2rem;display:flex;align-items:center;gap:12px}
.detail-header .subtitle{opacity:0.9;font-size:1rem}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;margin-bottom:24px}
.detail-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;box-shadow:var(--overlay-shadow)}
.detail-card h3{margin:0 0 20px 0;font-size:1.1rem;color:var(--card-text);display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:2px solid var(--border-weak)}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-weak)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-weight:600;color:var(--muted);font-size:0.9rem}
.detail-value{font-weight:600;color:var(--card-text);font-size:1rem}
.detail-value.large{font-size:1.5rem;font-weight:700}
.detail-value.positive{color:#10b981}
.detail-value.negative{color:#ef4444}
.status-badge{padding:8px 16px;border-radius:20px;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;display:inline-flex;align-items:center;gap:6px}
.status-badge::before{content:'';width:8px;height:8px;border-radius:50%;display:inline-block}
.status-badge.status-open{background:linear-gradient(135deg,rgba(59,130,246,.2),rgba(59,130,246,.1));color:#3b82f6;border:1px solid rgba(59,130,246,.4)}
.status-badge.status-open::before{background:#3b82f6;animation:pulse 2s infinite}
.status-badge.status-pending{background:linear-gradient(135deg,rgba(251,191,36,.2),rgba(251,191,36,.1));color:#f59e0b;border:1px solid rgba(251,191,36,.4)}
.status-badge.status-pending::before{background:#f59e0b}
.status-badge.status-win{background:linear-gradient(135deg,rgba(16,185,129,.2),rgba(16,185,129,.1));color:#10b981;border:1px solid rgba(16,185,129,.4)}
.status-badge.status-win::before{background:#10b981}
.status-badge.status-loss{background:linear-gradient(135deg,rgba(239,68,68,.2),rgba(239,68,68,.1));color:#ef4444;border:1px solid rgba(239,68,68,.4)}
.status-badge.status-loss::before{background:#ef4444}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
.edit-form{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;margin-bottom:6px;font-weight:600;color:var(--card-text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text)}
.action-buttons{display:flex;gap:12px;margin-top:24px}
.notes-section{background:rgba(139,92,246,.05);padding:16px;border-radius:8px;border-left:4px solid #8b5cf6;margin-bottom:24px}
</style>

<div class="trade-detail-container">
    <div class="detail-header">
        <h1>
            <?= $statusIcon ?> Trade #<?= $trade['id'] ?> - <?= htmlspecialchars($trade['pair']) ?>
        </h1>
        <div class="subtitle">
            <?= strtoupper($trade['type']) ?> • 
            <?= date('F d, Y \a\t H:i', strtotime($trade['trade_date'] . ' ' . $trade['trade_time'])) ?>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <!-- Trade Information -->
        <div class="detail-card">
            <h3>📊 Trade Information</h3>
            <div class="detail-row">
                <span class="detail-label">Currency Pair</span>
                <span class="detail-value"><?= htmlspecialchars($trade['pair']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Trade Type</span>
                <span class="detail-value"><?= strtoupper($trade['type']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Lot Size</span>
                <span class="detail-value"><?= number_format($trade['lot_size'], 2) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Startup Amount</span>
                <span class="detail-value">$<?= number_format($trade['startup_amount'] ?? 1000, 2) ?></span>
            </div>
        </div>

        <!-- Price Information -->
        <div class="detail-card">
            <h3>💹 Price Information</h3>
            <div class="detail-row">
                <span class="detail-label">Entry Price</span>
                <span class="detail-value"><?= number_format($trade['entry_price'], 5) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Exit Price</span>
                <span class="detail-value">
                    <?= $trade['exit_price'] ? number_format($trade['exit_price'], 5) : '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Pips</span>
                <span class="detail-value <?= $trade['pips'] >= 0 ? 'positive' : 'negative' ?>">
                    <?= $trade['pips'] !== null ? number_format($trade['pips'], 1) : '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Risk:Reward</span>
                <span class="detail-value">
                    <?= $trade['risk_reward_ratio'] ?? '—' ?>
                </span>
            </div>
        </div>

        <!-- Profit/Loss -->
        <div class="detail-card">
            <h3>💰 Profit/Loss</h3>
            <div class="detail-row">
                <span class="detail-label">P/L (USD)</span>
                <span class="detail-value large <?= $trade['profit_loss'] >= 0 ? 'positive' : 'negative' ?>">
                    <?php if ($status === 'open' || $status === 'pending'): ?>
                        —
                    <?php else: ?>
                        $<?= number_format($trade['profit_loss'], 2) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">P/L (RWF)</span>
                <span class="detail-value large <?= $trade['rwf_amount'] >= 0 ? 'positive' : 'negative' ?>">
                    <?php if ($status === 'open' || $status === 'pending'): ?>
                        —
                    <?php else: ?>
                        <?= number_format($trade['rwf_amount'], 0) ?> FRW
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">USD Amount</span>
                <span class="detail-value">
                    <?= $trade['usd_amount'] ? '$' . number_format($trade['usd_amount'], 2) : '—' ?>
                </span>
            </div>
        </div>

        <!-- Dates & Times -->
        <div class="detail-card">
            <h3>📅 Dates & Times</h3>
            <div class="detail-row">
                <span class="detail-label">Entry Date</span>
                <span class="detail-value"><?= date('M d, Y', strtotime($trade['trade_date'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Entry Time</span>
                <span class="detail-value"><?= date('H:i', strtotime($trade['trade_time'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Close Date</span>
                <span class="detail-value">
                    <?= $trade['close_date'] ? date('M d, Y', strtotime($trade['close_date'])) : '—' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Close Time</span>
                <span class="detail-value">
                    <?= $trade['close_time'] ? date('H:i', strtotime($trade['close_time'])) : '—' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($trade['notes'])): ?>
    <div class="notes-section">
        <strong>📝 Notes:</strong><br>
        <?= nl2br(htmlspecialchars($trade['notes'])) ?>
    </div>
    <?php endif; ?>

    <div class="action-buttons" style="margin-top:24px">
        <button class="button primary" id="edit-trade-btn">✏️ Edit Trade</button>
        <a href="/MY CASH/pages/forex_journal.php" class="button secondary">← Back to Journal</a>
        <a href="?delete=<?= $trade['id'] ?>" onclick="return confirm('Are you sure you want to delete this trade?')" class="button danger">🗑️ Delete</a>
    </div>

    <!-- Edit Form (Hidden by default) -->
    <div id="edit-form" style="display:none;margin-top:24px">
        <div class="edit-form">
            <h3 style="margin-bottom:20px">✏️ Edit Trade</h3>
            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label>Trade Date</label>
                    <input type="date" name="trade_date" value="<?= $trade['trade_date'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Trade Time</label>
                    <input type="time" name="trade_time" value="<?= $trade['trade_time'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Currency Pair</label>
                    <input type="text" name="pair" value="<?= htmlspecialchars($trade['pair']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="buy" <?= $trade['type'] === 'buy' ? 'selected' : '' ?>>Buy</option>
                        <option value="sell" <?= $trade['type'] === 'sell' ? 'selected' : '' ?>>Sell</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="closed_win" <?= $status === 'closed_win' ? 'selected' : '' ?>>Closed - Win</option>
                        <option value="closed_loss" <?= $status === 'closed_loss' ? 'selected' : '' ?>>Closed - Loss</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Close Date</label>
                    <input type="date" name="close_date" value="<?= $trade['close_date'] ?>">
                </div>
                <div class="form-group">
                    <label>Entry Price</label>
                    <input type="number" step="0.00001" name="entry_price" value="<?= $trade['entry_price'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Exit Price</label>
                    <input type="number" step="0.00001" name="exit_price" value="<?= $trade['exit_price'] ?>">
                </div>
                <div class="form-group">
                    <label>Lot Size</label>
                    <input type="number" step="0.01" name="lot_size" value="<?= $trade['lot_size'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Startup Amount ($)</label>
                    <input type="number" step="0.01" name="startup_amount" value="<?= $trade['startup_amount'] ?? 1000 ?>">
                </div>
                <div class="form-group">
                    <label>Profit/Loss ($)</label>
                    <input type="number" step="0.01" name="profit_loss" value="<?= $trade['profit_loss'] ?>">
                </div>
                <div class="form-group">
                    <label>Close Time</label>
                    <input type="time" name="close_time" value="<?= $trade['close_time'] ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Notes</label>
                    <textarea name="notes" rows="4"><?= htmlspecialchars($trade['notes']) ?></textarea>
                </div>
                <div class="action-buttons" style="grid-column:1/-1">
                    <button type="submit" name="update_trade" class="button primary">💾 Save Changes</button>
                    <button type="button" onclick="document.getElementById('edit-form').style.display='none'" class="button secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('edit-trade-btn').addEventListener('click', function() {
    document.getElementById('edit-form').style.display = 'block';
    document.getElementById('edit-form').scrollIntoView({ behavior: 'smooth' });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
