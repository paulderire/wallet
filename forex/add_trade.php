<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . '/../includes/db.php';

// Check if user is admin
$is_admin = false;
try {
  $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_admin = !empty($user['is_admin']);
} catch (Exception $e) {}

if (!$is_admin) {
  header("Location: /MY CASH/pages/dashboard.php");
  exit;
}

$user_id = $_SESSION['user_id'];

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $currency_pair = $_POST['currency_pair'] ?? '';
    $trade_type = $_POST['trade_type'] ?? 'buy';
    $entry_price = floatval($_POST['entry_price'] ?? 0);
    $exit_price = !empty($_POST['exit_price']) ? floatval($_POST['exit_price']) : null;
    $stop_loss = !empty($_POST['stop_loss']) ? floatval($_POST['stop_loss']) : null;
    $take_profit = !empty($_POST['take_profit']) ? floatval($_POST['take_profit']) : null;
    $lot_size = floatval($_POST['lot_size'] ?? 0);
    $risk_percentage = !empty($_POST['risk_percentage']) ? floatval($_POST['risk_percentage']) : null;
    $strategy_used = $_POST['strategy_used'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d H:i:s');
    $exit_date = !empty($_POST['exit_date']) ? $_POST['exit_date'] : null;
    $status = $_POST['status'] ?? 'open';
    
    // Calculate P/L if trade is closed
    $profit_loss = null;
    if ($status === 'closed' && $exit_price) {
      $pip_diff = $trade_type === 'buy' ? ($exit_price - $entry_price) : ($entry_price - $exit_price);
      $profit_loss = $pip_diff * $lot_size * 100000; // Simplified calculation
    }
    
    $stmt = $conn->prepare("INSERT INTO forex_trades (user_id, currency_pair, trade_type, entry_price, exit_price, stop_loss, take_profit, lot_size, risk_percentage, profit_loss, status, strategy_used, notes, entry_date, exit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $currency_pair, $trade_type, $entry_price, $exit_price, $stop_loss, $take_profit, $lot_size, $risk_percentage, $profit_loss, $status, $strategy_used, $notes, $entry_date, $exit_date]);
    
    $success = 'Trade added successfully!';
    header("Location: /MY CASH/forex/dashboard.php");
    exit;
  } catch (Exception $e) {
    $error = 'Error adding trade: ' . $e->getMessage();
  }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.form-container {
  max-width: 900px;
  margin: 0 auto;
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 8px 32px rgba(var(--card-text-rgb), 0.1);
}
.form-header {
  text-align: center;
  margin-bottom: 40px;
}
.form-header h1 {
  font-size: 2rem;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin: 0 0 12px 0;
}
.form-header p {
  color: var(--muted);
  font-size: 1rem;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 24px;
}
.form-group-full {
  grid-column: 1 / -1;
}
.form-group label {
  display: block;
  font-weight: 600;
  color: var(--card-text);
  margin-bottom: 8px;
  font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 14px 16px;
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
  border-radius: 10px;
  color: var(--card-text);
  font-size: 0.95rem;
  transition: all 0.3s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #a78bfa;
  background: rgba(var(--card-text-rgb), 0.04);
  box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.12);
}
.form-group textarea {
  resize: vertical;
  min-height: 100px;
}
.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 32px;
}
.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-weight: 500;
}
.alert.success {
  background: rgba(46, 213, 115, 0.15);
  color: #2ed573;
  border: 1px solid rgba(46, 213, 115, 0.3);
}
.alert.error {
  background: rgba(245, 87, 108, 0.15);
  color: #f5576c;
  border: 1px solid rgba(245, 87, 108, 0.3);
}
.radio-group {
  display: flex;
  gap: 16px;
}
.radio-option {
  flex: 1;
  position: relative;
}
.radio-option input[type="radio"] {
  position: absolute;
  opacity: 0;
}
.radio-option label {
  display: block;
  padding: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  border: 2px solid rgba(var(--card-text-rgb), 0.1);
  border-radius: 10px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
}
.radio-option input:checked + label {
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-color: #667eea;
  color: white;
}
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .form-container {
    padding: 24px;
  }
}
</style>

<div class="form-container">
  <div class="form-header">
    <h1>📝 Add New Trade</h1>
    <p>Log your forex trade with all the details</p>
  </div>

  <?php if($success): ?>
    <div class="alert success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>
  <?php if($error): ?>
    <div class="alert error"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-grid">
      <div class="form-group">
        <label>Currency Pair *</label>
        <input type="text" name="currency_pair" placeholder="EUR/USD" required>
      </div>

      <div class="form-group">
        <label>Trade Type *</label>
        <div class="radio-group">
          <div class="radio-option">
            <input type="radio" name="trade_type" value="buy" id="type-buy" checked>
            <label for="type-buy">📈 Buy</label>
          </div>
          <div class="radio-option">
            <input type="radio" name="trade_type" value="sell" id="type-sell">
            <label for="type-sell">📉 Sell</label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Entry Price *</label>
        <input type="number" step="0.00001" name="entry_price" placeholder="1.08550" required>
      </div>

      <div class="form-group">
        <label>Exit Price</label>
        <input type="number" step="0.00001" name="exit_price" placeholder="1.08750">
      </div>

      <div class="form-group">
        <label>Stop Loss</label>
        <input type="number" step="0.00001" name="stop_loss" placeholder="1.08350">
      </div>

      <div class="form-group">
        <label>Take Profit</label>
        <input type="number" step="0.00001" name="take_profit" placeholder="1.08950">
      </div>

      <div class="form-group">
        <label>Lot Size *</label>
        <input type="number" step="0.01" name="lot_size" placeholder="0.10" required>
      </div>

      <div class="form-group">
        <label>Risk Percentage</label>
        <input type="number" step="0.1" name="risk_percentage" placeholder="2.0">
      </div>

      <div class="form-group">
        <label>Entry Date & Time *</label>
        <input type="datetime-local" name="entry_date" value="<?=date('Y-m-d\TH:i')?>" required>
      </div>

      <div class="form-group">
        <label>Exit Date & Time</label>
        <input type="datetime-local" name="exit_date">
      </div>

      <div class="form-group">
        <label>Strategy Used</label>
        <input type="text" name="strategy_used" placeholder="Breakout, Trend Following, etc.">
      </div>

      <div class="form-group">
        <label>Status *</label>
        <select name="status" required>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
          <option value="pending">Pending</option>
        </select>
      </div>

      <div class="form-group form-group-full">
        <label>Notes / Analysis</label>
        <textarea name="notes" placeholder="Trade rationale, market conditions, lessons learned..."></textarea>
      </div>
    </div>

    <div class="form-actions">
      <a href="/MY CASH/forex/dashboard.php" class="button ghost">Cancel</a>
      <button type="submit" class="button primary">💾 Save Trade</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
