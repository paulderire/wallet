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

// Fetch trading statistics
$total_trades = 0; $total_profit = 0; $win_count = 0; $loss_count = 0;
$open_trades = 0; $total_volume = 0;

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as total, 
    SUM(CASE WHEN status='closed' AND profit_loss > 0 THEN 1 ELSE 0 END) as wins,
    SUM(CASE WHEN status='closed' AND profit_loss < 0 THEN 1 ELSE 0 END) as losses,
    SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open_count,
    SUM(COALESCE(profit_loss, 0)) as total_pl,
    SUM(lot_size) as volume
    FROM forex_trades WHERE user_id=?");
  $stmt->execute([$user_id]);
  $stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_trades = $stats['total'] ?? 0;
  $win_count = $stats['wins'] ?? 0;
  $loss_count = $stats['losses'] ?? 0;
  $open_trades = $stats['open_count'] ?? 0;
  $total_profit = $stats['total_pl'] ?? 0;
  $total_volume = $stats['volume'] ?? 0;
} catch (Exception $e) {}

$win_rate = ($win_count + $loss_count) > 0 ? round(($win_count / ($win_count + $loss_count)) * 100, 1) : 0;

// Advanced Analytics
$avg_win = 0; $avg_loss = 0; $profit_factor = 0; $max_drawdown = 0;
$total_wins_amount = 0; $total_losses_amount = 0;

try {
  $stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN profit_loss > 0 THEN profit_loss ELSE 0 END) as total_wins,
    SUM(CASE WHEN profit_loss < 0 THEN ABS(profit_loss) ELSE 0 END) as total_losses
    FROM forex_trades WHERE user_id=? AND status='closed'");
  $stmt->execute([$user_id]);
  $adv = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_wins_amount = $adv['total_wins'] ?? 0;
  $total_losses_amount = $adv['total_losses'] ?? 0;
  
  $avg_win = $win_count > 0 ? $total_wins_amount / $win_count : 0;
  $avg_loss = $loss_count > 0 ? $total_losses_amount / $loss_count : 0;
  $profit_factor = $total_losses_amount > 0 ? $total_wins_amount / $total_losses_amount : ($total_wins_amount > 0 ? 999 : 0);
} catch (Exception $e) {}

// Equity curve data for chart
$equity_data = [];
$cumulative_pl = 0;
try {
  $stmt = $conn->prepare("SELECT entry_date, profit_loss FROM forex_trades WHERE user_id=? AND status='closed' ORDER BY entry_date ASC");
  $stmt->execute([$user_id]);
  $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($trades as $trade) {
    $cumulative_pl += $trade['profit_loss'];
    $equity_data[] = ['date' => $trade['entry_date'], 'equity' => $cumulative_pl];
  }
} catch (Exception $e) {}

// Monthly performance
$monthly_data = [];
try {
  $stmt = $conn->prepare("SELECT 
    DATE_FORMAT(entry_date, '%Y-%m') as month,
    SUM(profit_loss) as total_pl,
    COUNT(*) as trades,
    SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as wins
    FROM forex_trades WHERE user_id=? AND status='closed'
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
    ORDER BY month DESC LIMIT 12");
  $stmt->execute([$user_id]);
  $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Currency pair performance
$pair_performance = [];
try {
  $stmt = $conn->prepare("SELECT 
    currency_pair,
    COUNT(*) as trades,
    SUM(profit_loss) as total_pl,
    SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as wins
    FROM forex_trades WHERE user_id=? AND status='closed'
    GROUP BY currency_pair
    ORDER BY total_pl DESC LIMIT 10");
  $stmt->execute([$user_id]);
  $pair_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Recent trades
$recent_trades = [];
try {
  $stmt = $conn->prepare("SELECT * FROM forex_trades WHERE user_id=? ORDER BY entry_date DESC LIMIT 10");
  $stmt->execute([$user_id]);
  $recent_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.forex-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
  position: relative;
  overflow: hidden;
}
.forex-header::before {
  content: '💹';
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 8rem;
  opacity: 0.1;
}
.forex-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 12px 0;
  color: white;
}
.forex-header p {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 28px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}
.stat-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 40px rgba(var(--card-text-rgb), 0.15);
}
.stat-card.profit::before { background: linear-gradient(90deg, #2ed573, #11998e); }
.stat-card.winrate::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card.volume::before { background: linear-gradient(90deg, #ffa500, #ff6348); }
.stat-card.trades::before { background: linear-gradient(90deg, #3498db, #2980b9); }
.stat-icon {
  font-size: 2.5rem;
  opacity: 0.2;
  position: absolute;
  right: 20px;
  top: 20px;
}
.stat-label {
  font-size: 0.9rem;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 12px;
}
.stat-value {
  font-size: 2.4rem;
  font-weight: 800;
  color: var(--card-text);
  margin-bottom: 8px;
}
.stat-sub {
  font-size: 0.9rem;
  color: var(--muted);
}
.trades-table {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
}
.trades-table h2 {
  margin: 0 0 20px 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--card-text);
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th {
  text-align: left;
  padding: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  font-size: 0.85rem;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 600;
  border-bottom: 2px solid var(--border-weak);
}
.data-table td {
  padding: 16px 12px;
  border-bottom: 1px solid var(--border-weak);
  color: var(--card-text);
}
.data-table tr:hover {
  background: rgba(var(--card-text-rgb), 0.02);
}
.badge {
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
.profit-positive { color: #2ed573; font-weight: 700; }
.profit-negative { color: #f5576c; font-weight: 700; }
.action-buttons {
  display: flex;
  gap: 12px;
  margin-bottom: 32px;
}
.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.quick-stat {
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
}
.quick-stat-value {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--card-text);
}
.quick-stat-label {
  font-size: 0.8rem;
  color: var(--muted);
  margin-top: 4px;
}
.chart-container {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
}
.chart-container h2 {
  margin: 0 0 20px 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--card-text);
}
.chart-wrapper {
  position: relative;
  height: 300px;
  margin-bottom: 16px;
}
.performance-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}
.monthly-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid var(--border-weak);
}
.monthly-item:last-child {
  border-bottom: none;
}
.monthly-month {
  font-weight: 600;
  color: var(--card-text);
}
.monthly-stats {
  text-align: right;
}
.pair-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid var(--border-weak);
}
.pair-item:last-child {
  border-bottom: none;
}
.pair-name {
  font-weight: 700;
  color: var(--card-text);
}
.pair-stats {
  text-align: right;
  font-size: 0.85rem;
  color: var(--muted);
}
.advanced-metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.metric-card {
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}
.metric-value {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--card-text);
  margin-bottom: 4px;
}
.metric-label {
  font-size: 0.8rem;
  color: var(--muted);
  text-transform: uppercase;
}
.metric-card.excellent .metric-value { color: #2ed573; }
.metric-card.good .metric-value { color: #667eea; }
.metric-card.warning .metric-value { color: #ffa500; }
.metric-card.poor .metric-value { color: #f5576c; }
.export-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 8px;
  color: var(--card-text);
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}
.export-button:hover {
  background: rgba(var(--card-text-rgb), 0.05);
  transform: translateY(-2px);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="forex-header">
  <h1>📈 Forex Trading Journal</h1>
  <p>Track your trades, analyze performance, and improve your strategy</p>
</div>

<div class="action-buttons">
  <a href="/MY CASH/forex/add_trade.php" class="button primary">+ Add New Trade</a>
  <a href="/MY CASH/forex/trades.php" class="button ghost">View All Trades</a>
  <a href="/MY CASH/forex/analytics.php" class="button ghost">Analytics</a>
</div>

<div class="stats-grid">
  <div class="stat-card profit">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Total Profit/Loss</div>
    <div class="stat-value <?=$total_profit >= 0 ? 'profit-positive' : 'profit-negative'?>">
      <?=$total_profit >= 0 ? '+' : ''?>$<?=number_format($total_profit, 2)?>
    </div>
    <div class="stat-sub"><?=$total_trades?> total trades</div>
  </div>

  <div class="stat-card winrate">
    <div class="stat-icon">🎯</div>
    <div class="stat-label">Win Rate</div>
    <div class="stat-value"><?=$win_rate?>%</div>
    <div class="stat-sub"><?=$win_count?> wins, <?=$loss_count?> losses</div>
  </div>

  <div class="stat-card volume">
    <div class="stat-icon">📊</div>
    <div class="stat-label">Total Volume</div>
    <div class="stat-value"><?=number_format($total_volume, 2)?></div>
    <div class="stat-sub">Lots traded</div>
  </div>

  <div class="stat-card trades">
    <div class="stat-icon">📈</div>
    <div class="stat-label">Open Trades</div>
    <div class="stat-value"><?=$open_trades?></div>
    <div class="stat-sub">Currently active</div>
  </div>
</div>

<div class="advanced-metrics">
  <div class="metric-card <?=$profit_factor >= 2 ? 'excellent' : ($profit_factor >= 1.5 ? 'good' : ($profit_factor >= 1 ? 'warning' : 'poor'))?>">
    <div class="metric-value"><?=number_format($profit_factor, 2)?></div>
    <div class="metric-label">Profit Factor</div>
  </div>
  <div class="metric-card <?=$avg_win >= 100 ? 'excellent' : ($avg_win >= 50 ? 'good' : 'warning')?>">
    <div class="metric-value">$<?=number_format($avg_win, 2)?></div>
    <div class="metric-label">Avg Win</div>
  </div>
  <div class="metric-card <?=$avg_loss <= 50 ? 'excellent' : ($avg_loss <= 100 ? 'good' : 'poor')?>">
    <div class="metric-value">$<?=number_format($avg_loss, 2)?></div>
    <div class="metric-label">Avg Loss</div>
  </div>
  <div class="metric-card <?=$win_rate >= 60 ? 'excellent' : ($win_rate >= 50 ? 'good' : ($win_rate >= 40 ? 'warning' : 'poor'))?>">
    <div class="metric-value"><?=$win_rate?>%</div>
    <div class="metric-label">Win Rate</div>
  </div>
  <div class="metric-card">
    <div class="metric-value">$<?=number_format($total_wins_amount, 2)?></div>
    <div class="metric-label">Gross Profit</div>
  </div>
  <div class="metric-card">
    <div class="metric-value">$<?=number_format($total_losses_amount, 2)?></div>
    <div class="metric-label">Gross Loss</div>
  </div>
</div>

<?php if (!empty($equity_data)): ?>
<div class="chart-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>📈 Equity Curve</h2>
    <a href="/MY CASH/forex/export.php" class="export-button">
      📊 Export Data
    </a>
  </div>
  <div class="chart-wrapper">
    <canvas id="equityChart"></canvas>
  </div>
</div>
<?php endif; ?>

<div class="performance-grid">
  <?php if (!empty($monthly_data)): ?>
  <div class="chart-container">
    <h2>📅 Monthly Performance</h2>
    <?php foreach($monthly_data as $month): 
      $month_winrate = $month['trades'] > 0 ? round(($month['wins'] / $month['trades']) * 100, 1) : 0;
    ?>
      <div class="monthly-item">
        <div>
          <div class="monthly-month"><?=date('M Y', strtotime($month['month'] . '-01'))?></div>
          <div style="font-size: 0.8rem; color: var(--muted);"><?=$month['trades']?> trades · <?=$month_winrate?>% win rate</div>
        </div>
        <div class="monthly-stats">
          <div class="<?=$month['total_pl'] >= 0 ? 'profit-positive' : 'profit-negative'?>" style="font-size: 1.2rem;">
            <?=$month['total_pl'] >= 0 ? '+' : ''?>$<?=number_format($month['total_pl'], 2)?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($pair_performance)): ?>
  <div class="chart-container">
    <h2>💱 Currency Pair Performance</h2>
    <?php foreach($pair_performance as $pair): 
      $pair_winrate = $pair['trades'] > 0 ? round(($pair['wins'] / $pair['trades']) * 100, 1) : 0;
    ?>
      <div class="pair-item">
        <div>
          <div class="pair-name"><?=htmlspecialchars($pair['currency_pair'])?></div>
          <div class="pair-stats"><?=$pair['trades']?> trades · <?=$pair_winrate?>% win rate</div>
        </div>
        <div class="<?=$pair['total_pl'] >= 0 ? 'profit-positive' : 'profit-negative'?>" style="font-size: 1.2rem;">
          <?=$pair['total_pl'] >= 0 ? '+' : ''?>$<?=number_format($pair['total_pl'], 2)?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="quick-stats">
  <div class="quick-stat">
    <div class="quick-stat-value"><?=$total_trades?></div>
    <div class="quick-stat-label">Total Trades</div>
  </div>
  <div class="quick-stat">
    <div class="quick-stat-value profit-positive">$<?=number_format($avg_win, 2)?></div>
    <div class="quick-stat-label">Avg Win</div>
  </div>
  <div class="quick-stat">
    <div class="quick-stat-value profit-negative">$<?=number_format($avg_loss, 2)?></div>
    <div class="quick-stat-label">Avg Loss</div>
  </div>
  <div class="quick-stat">
    <div class="quick-stat-value"><?=$avg_loss > 0 ? number_format($avg_win / $avg_loss, 2) : 0?></div>
    <div class="quick-stat-label">Risk/Reward</div>
  </div>
</div>

<div class="trades-table">
  <h2>📋 Recent Trades</h2>
  <?php if(empty($recent_trades)): ?>
    <p style="text-align:center;color:var(--muted);padding:40px 0">
      No trades yet. <a href="/MY CASH/forex/add_trade.php">Add your first trade</a>
    </p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Pair</th>
          <th>Type</th>
          <th>Entry</th>
          <th>Exit</th>
          <th>Lots</th>
          <th>P/L</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($recent_trades as $trade): ?>
          <tr>
            <td><?=date('M d, Y', strtotime($trade['entry_date']))?></td>
            <td><strong><?=htmlspecialchars($trade['currency_pair'])?></strong></td>
            <td><span class="badge <?=strtolower($trade['trade_type'])?>"><?=ucfirst($trade['trade_type'])?></span></td>
            <td><?=number_format($trade['entry_price'], 5)?></td>
            <td><?=$trade['exit_price'] ? number_format($trade['exit_price'], 5) : '-'?></td>
            <td><?=number_format($trade['lot_size'], 2)?></td>
            <td class="<?=$trade['profit_loss'] >= 0 ? 'profit-positive' : 'profit-negative'?>">
              <?=$trade['profit_loss'] ? ($trade['profit_loss'] >= 0 ? '+' : '') . '$' . number_format($trade['profit_loss'], 2) : '-'?>
            </td>
            <td><span class="badge <?=strtolower($trade['status'])?>"><?=ucfirst($trade['status'])?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
// Equity data from PHP
const equityData = <?=json_encode($equity_data)?>;

// Initialize Equity Chart
if (equityData.length > 0) {
  const ctx = document.getElementById('equityChart');
  if (ctx) {
    const labels = equityData.map(d => {
      const date = new Date(d.date);
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    const values = equityData.map(d => d.equity);
    
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Cumulative P/L',
          data: values,
          borderColor: values[values.length - 1] >= 0 ? '#2ed573' : '#f5576c',
          backgroundColor: values[values.length - 1] >= 0 ? 'rgba(46, 213, 115, 0.1)' : 'rgba(245, 87, 108, 0.1)',
          tension: 0.4,
          fill: true,
          borderWidth: 3,
          pointRadius: 4,
          pointBackgroundColor: '#fff',
          pointBorderWidth: 2,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
              size: 14,
              weight: 'bold'
            },
            bodyFont: {
              size: 13
            },
            callbacks: {
              label: function(context) {
                return 'Equity: $' + context.parsed.y.toFixed(2);
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            },
            ticks: {
              callback: function(value) {
                return '$' + value.toFixed(0);
              }
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
