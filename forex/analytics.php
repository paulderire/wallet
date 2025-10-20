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

// Calculate comprehensive statistics
$stats = [
  'total_trades' => 0,
  'wins' => 0,
  'losses' => 0,
  'total_profit' => 0,
  'win_rate' => 0,
  'avg_win' => 0,
  'avg_loss' => 0,
  'risk_reward' => 0,
  'best_trade' => 0,
  'worst_trade' => 0
];

try {
  $stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='closed' AND profit_loss > 0 THEN 1 ELSE 0 END) as wins,
    SUM(CASE WHEN status='closed' AND profit_loss < 0 THEN 1 ELSE 0 END) as losses,
    SUM(COALESCE(profit_loss, 0)) as total_profit,
    AVG(CASE WHEN profit_loss > 0 THEN profit_loss END) as avg_win,
    AVG(CASE WHEN profit_loss < 0 THEN ABS(profit_loss) END) as avg_loss,
    MAX(profit_loss) as best,
    MIN(profit_loss) as worst
    FROM forex_trades WHERE user_id=? AND status='closed'");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  
  $stats['total_trades'] = $result['total'] ?? 0;
  $stats['wins'] = $result['wins'] ?? 0;
  $stats['losses'] = $result['losses'] ?? 0;
  $stats['total_profit'] = $result['total_profit'] ?? 0;
  $stats['avg_win'] = $result['avg_win'] ?? 0;
  $stats['avg_loss'] = $result['avg_loss'] ?? 0;
  $stats['best_trade'] = $result['best'] ?? 0;
  $stats['worst_trade'] = $result['worst'] ?? 0;
  $stats['win_rate'] = ($stats['wins'] + $stats['losses']) > 0 ? round(($stats['wins'] / ($stats['wins'] + $stats['losses'])) * 100, 1) : 0;
  $stats['risk_reward'] = $stats['avg_loss'] > 0 ? round($stats['avg_win'] / $stats['avg_loss'], 2) : 0;
} catch (Exception $e) {}

// Performance by pair
$pair_performance = [];
try {
  $stmt = $conn->prepare("SELECT currency_pair, COUNT(*) as trades, SUM(COALESCE(profit_loss, 0)) as profit FROM forex_trades WHERE user_id=? AND status='closed' GROUP BY currency_pair ORDER BY profit DESC LIMIT 10");
  $stmt->execute([$user_id]);
  $pair_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Monthly performance
$monthly_performance = [];
try {
  $stmt = $conn->prepare("SELECT DATE_FORMAT(entry_date, '%Y-%m') as month, SUM(COALESCE(profit_loss, 0)) as profit, COUNT(*) as trades FROM forex_trades WHERE user_id=? AND status='closed' GROUP BY month ORDER BY month DESC LIMIT 12");
  $stmt->execute([$user_id]);
  $monthly_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.analytics-header {
  text-align: center;
  margin-bottom: 40px;
}
.analytics-title {
  font-size: 2.5rem;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 0 0 12px 0;
}
.analytics-subtitle {
  color: var(--muted);
  font-size: 1.1rem;
}
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.metric-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  transition: all 0.3s;
}
.metric-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(var(--card-text-rgb), 0.15);
}
.metric-icon {
  font-size: 2.5rem;
  margin-bottom: 12px;
}
.metric-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--card-text);
  margin-bottom: 8px;
}
.metric-label {
  font-size: 0.85rem;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 600;
}
.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}
.chart-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 28px;
}
.chart-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--card-text);
  margin: 0 0 24px 0;
}
.performance-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.performance-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  margin-bottom: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1px solid var(--border-weak);
  border-radius: 10px;
  transition: all 0.3s;
}
.performance-item:hover {
  background: rgba(167, 139, 250, 0.08);
  border-color: #a78bfa;
}
.performance-pair {
  font-weight: 700;
  color: var(--card-text);
  font-size: 1.1rem;
}
.performance-trades {
  font-size: 0.85rem;
  color: var(--muted);
  margin-top: 4px;
}
.performance-profit {
  font-size: 1.3rem;
  font-weight: 800;
}
.profit-positive { color: #2ed573; }
.profit-negative { color: #f5576c; }
.monthly-chart {
  display: grid;
  gap: 12px;
}
.monthly-bar {
  display: flex;
  align-items: center;
  gap: 12px;
}
.monthly-label {
  min-width: 80px;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--card-text);
}
.monthly-bar-container {
  flex: 1;
  height: 32px;
  background: rgba(var(--card-text-rgb), 0.05);
  border-radius: 8px;
  overflow: hidden;
  position: relative;
}
.monthly-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea, #764ba2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-right: 12px;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
  min-width: 60px;
  transition: width 0.5s ease;
}
.monthly-bar-fill.negative {
  background: linear-gradient(90deg, #f5576c, #fa709a);
}
@media (max-width: 768px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="analytics-header">
  <h1 class="analytics-title">📊 Trading Analytics</h1>
  <p class="analytics-subtitle">Comprehensive performance analysis and insights</p>
</div>

<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-icon">💰</div>
    <div class="metric-value <?=$stats['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative'?>">
      <?=$stats['total_profit'] >= 0 ? '+' : ''?>$<?=number_format($stats['total_profit'], 2)?>
    </div>
    <div class="metric-label">Total Profit/Loss</div>
  </div>

  <div class="metric-card">
    <div class="metric-icon">🎯</div>
    <div class="metric-value"><?=$stats['win_rate']?>%</div>
    <div class="metric-label">Win Rate</div>
  </div>

  <div class="metric-card">
    <div class="metric-icon">📈</div>
    <div class="metric-value profit-positive">$<?=number_format($stats['avg_win'], 2)?></div>
    <div class="metric-label">Average Win</div>
  </div>

  <div class="metric-card">
    <div class="metric-icon">📉</div>
    <div class="metric-value profit-negative">$<?=number_format($stats['avg_loss'], 2)?></div>
    <div class="metric-label">Average Loss</div>
  </div>

  <div class="metric-card">
    <div class="metric-icon">⚖️</div>
    <div class="metric-value"><?=$stats['risk_reward']?></div>
    <div class="metric-label">Risk/Reward Ratio</div>
  </div>

  <div class="metric-card">
    <div class="metric-icon">⭐</div>
    <div class="metric-value profit-positive">$<?=number_format($stats['best_trade'], 2)?></div>
    <div class="metric-label">Best Trade</div>
  </div>
</div>

<div class="charts-grid">
  <div class="chart-card">
    <h2 class="chart-title">🏆 Top Performing Pairs</h2>
    <?php if(empty($pair_performance)): ?>
      <p style="text-align:center;color:var(--muted);padding:40px 0">No closed trades yet</p>
    <?php else: ?>
      <ul class="performance-list">
        <?php foreach($pair_performance as $pair): ?>
          <li class="performance-item">
            <div>
              <div class="performance-pair"><?=htmlspecialchars($pair['currency_pair'])?></div>
              <div class="performance-trades"><?=$pair['trades']?> trades</div>
            </div>
            <div class="performance-profit <?=$pair['profit'] >= 0 ? 'profit-positive' : 'profit-negative'?>">
              <?=$pair['profit'] >= 0 ? '+' : ''?>$<?=number_format($pair['profit'], 2)?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="chart-card">
    <h2 class="chart-title">📅 Monthly Performance</h2>
    <?php if(empty($monthly_performance)): ?>
      <p style="text-align:center;color:var(--muted);padding:40px 0">No closed trades yet</p>
    <?php else: ?>
      <div class="monthly-chart">
        <?php 
        $max_profit = max(array_column($monthly_performance, 'profit'));
        foreach($monthly_performance as $month): 
          $width = $max_profit > 0 ? (abs($month['profit']) / $max_profit) * 100 : 0;
        ?>
          <div class="monthly-bar">
            <div class="monthly-label"><?=date('M Y', strtotime($month['month'] . '-01'))?></div>
            <div class="monthly-bar-container">
              <div class="monthly-bar-fill <?=$month['profit'] < 0 ? 'negative' : ''?>" style="width:<?=$width?>%">
                <?=$month['profit'] >= 0 ? '+' : ''?>$<?=number_format($month['profit'], 0)?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="chart-card">
  <h2 class="chart-title">📊 Trading Summary</h2>
  <div class="metrics-grid">
    <div class="metric-card">
      <div class="metric-icon">📝</div>
      <div class="metric-value"><?=$stats['total_trades']?></div>
      <div class="metric-label">Total Closed Trades</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon">✅</div>
      <div class="metric-value profit-positive"><?=$stats['wins']?></div>
      <div class="metric-label">Winning Trades</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon">❌</div>
      <div class="metric-value profit-negative"><?=$stats['losses']?></div>
      <div class="metric-label">Losing Trades</div>
    </div>
    <div class="metric-card">
      <div class="metric-icon">💎</div>
      <div class="metric-value profit-negative">$<?=number_format(abs($stats['worst_trade']), 2)?></div>
      <div class="metric-label">Worst Trade</div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
