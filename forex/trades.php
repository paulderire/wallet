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

include __DIR__ . '/../includes/header.php';
$user_id = $_SESSION['user_id'];

// Filters
$filter_pair = $_GET['pair'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

// Build query
$where = ["user_id = ?"];
$params = [$user_id];

if ($filter_pair) {
  $where[] = "currency_pair = ?";
  $params[] = $filter_pair;
}
if ($filter_type) {
  $where[] = "trade_type = ?";
  $params[] = $filter_type;
}
if ($filter_status) {
  $where[] = "status = ?";
  $params[] = $filter_status;
}
if ($filter_from) {
  $where[] = "entry_date >= ?";
  $params[] = $filter_from . ' 00:00:00';
}
if ($filter_to) {
  $where[] = "entry_date <= ?";
  $params[] = $filter_to . ' 23:59:59';
}

$where_clause = implode(' AND ', $where);

// Fetch trades
$trades = [];
try {
  $stmt = $conn->prepare("SELECT * FROM forex_trades WHERE $where_clause ORDER BY entry_date DESC");
  $stmt->execute($params);
  $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $trades = [];
}

// Get unique pairs for filter
$pairs = [];
try {
  $stmt = $conn->prepare("SELECT DISTINCT currency_pair FROM forex_trades WHERE user_id = ? ORDER BY currency_pair");
  $stmt->execute([$user_id]);
  $pairs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<style>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  flex-wrap: wrap;
  gap: 16px;
}
.page-title {
  font-size: 2rem;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin: 0;
}
.filters-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
}
.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.filter-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
  margin-bottom: 6px;
  text-transform: uppercase;
}
.filter-group select,
.filter-group input {
  width: 100%;
  padding: 10px 14px;
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
  border-radius: 8px;
  color: var(--card-text);
  font-size: 0.9rem;
}
.filter-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}
.trades-container {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
}
.trade-row {
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
  transition: all 0.3s;
  cursor: pointer;
}
.trade-row:hover {
  border-color: #a78bfa;
  background: rgba(167, 139, 250, 0.05);
  transform: translateX(4px);
}
.trade-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.trade-pair {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--card-text);
}
.trade-date {
  font-size: 0.85rem;
  color: var(--muted);
}
.trade-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 16px;
}
.trade-detail {
  display: flex;
  flex-direction: column;
}
.trade-detail-label {
  font-size: 0.75rem;
  color: var(--muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}
.trade-detail-value {
  font-size: 1rem;
  font-weight: 600;
  color: var(--card-text);
}
.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}
.badge.buy { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
.badge.sell { background: rgba(245, 87, 108, 0.15); color: #f5576c; }
.badge.open { background: rgba(52, 152, 219, 0.15); color: #3498db; }
.badge.closed { background: rgba(149, 165, 166, 0.15); color: #95a5a6; }
.badge.pending { background: rgba(255, 165, 0, 0.15); color: #ffa500; }
.profit-positive { color: #2ed573; }
.profit-negative { color: #f5576c; }
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}
.empty-state svg {
  width: 80px;
  height: 80px;
  opacity: 0.3;
  margin-bottom: 20px;
}
</style>

<div class="page-header">
  <h1 class="page-title">📊 Trade History</h1>
  <a href="/MY CASH/forex/add_trade.php" class="button primary">+ Add Trade</a>
</div>

<div class="filters-card">
  <form method="GET">
    <div class="filters-grid">
      <div class="filter-group">
        <label>Currency Pair</label>
        <select name="pair">
          <option value="">All Pairs</option>
          <?php foreach($pairs as $p): ?>
            <option value="<?=$p?>" <?=$filter_pair === $p ? 'selected' : ''?>><?=$p?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label>Trade Type</label>
        <select name="type">
          <option value="">All Types</option>
          <option value="buy" <?=$filter_type === 'buy' ? 'selected' : ''?>>Buy</option>
          <option value="sell" <?=$filter_type === 'sell' ? 'selected' : ''?>>Sell</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Status</label>
        <select name="status">
          <option value="">All Status</option>
          <option value="open" <?=$filter_status === 'open' ? 'selected' : ''?>>Open</option>
          <option value="closed" <?=$filter_status === 'closed' ? 'selected' : ''?>>Closed</option>
          <option value="pending" <?=$filter_status === 'pending' ? 'selected' : ''?>>Pending</option>
        </select>
      </div>

      <div class="filter-group">
        <label>From Date</label>
        <input type="date" name="from" value="<?=$filter_from?>">
      </div>

      <div class="filter-group">
        <label>To Date</label>
        <input type="date" name="to" value="<?=$filter_to?>">
      </div>
    </div>
    
    <div class="filter-actions">
      <a href="/MY CASH/forex/trades.php" class="button ghost">Clear</a>
      <button type="submit" class="button primary">Apply Filters</button>
    </div>
  </form>
</div>

<div class="trades-container">
  <?php if(empty($trades)): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 11l3 3L22 4"></path>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      <h3>No trades found</h3>
      <p>Try adjusting your filters or <a href="/MY CASH/forex/add_trade.php">add a new trade</a></p>
    </div>
  <?php else: ?>
    <?php foreach($trades as $trade): ?>
      <div class="trade-row" onclick="window.location='/MY CASH/pages/forex_trade_detail.php?id=<?=$trade['id']?>'" style="cursor:pointer">
        <div class="trade-header">
          <div>
            <div class="trade-pair"><?=htmlspecialchars($trade['currency_pair'])?></div>
            <div class="trade-date"><?=date('M d, Y H:i', strtotime($trade['entry_date']))?></div>
          </div>
          <div style="text-align:right">
            <span class="badge <?=strtolower($trade['trade_type'])?>"><?=ucfirst($trade['trade_type'])?></span>
            <span class="badge <?=strtolower($trade['status'])?>"><?=ucfirst($trade['status'])?></span>
          </div>
        </div>

        <div class="trade-details">
          <div class="trade-detail">
            <div class="trade-detail-label">Entry</div>
            <div class="trade-detail-value"><?=number_format($trade['entry_price'], 5)?></div>
          </div>

          <div class="trade-detail">
            <div class="trade-detail-label">Exit</div>
            <div class="trade-detail-value"><?=$trade['exit_price'] ? number_format($trade['exit_price'], 5) : '-'?></div>
          </div>

          <div class="trade-detail">
            <div class="trade-detail-label">Lot Size</div>
            <div class="trade-detail-value"><?=number_format($trade['lot_size'], 2)?></div>
          </div>

          <div class="trade-detail">
            <div class="trade-detail-label">Stop Loss</div>
            <div class="trade-detail-value"><?=$trade['stop_loss'] ? number_format($trade['stop_loss'], 5) : '-'?></div>
          </div>

          <div class="trade-detail">
            <div class="trade-detail-label">Take Profit</div>
            <div class="trade-detail-value"><?=$trade['take_profit'] ? number_format($trade['take_profit'], 5) : '-'?></div>
          </div>

          <div class="trade-detail">
            <div class="trade-detail-label">Profit/Loss</div>
            <div class="trade-detail-value <?=$trade['profit_loss'] >= 0 ? 'profit-positive' : 'profit-negative'?>">
              <?=$trade['profit_loss'] ? ($trade['profit_loss'] >= 0 ? '+' : '') . '$' . number_format($trade['profit_loss'], 2) : '-'?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
