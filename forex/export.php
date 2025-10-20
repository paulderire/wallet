<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];
$format = $_GET['format'] ?? 'csv';

// Fetch all trades
$trades = [];
try {
  $stmt = $conn->prepare("SELECT * FROM forex_trades WHERE user_id=? ORDER BY entry_date ASC");
  $stmt->execute([$user_id]);
  $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  die("Error fetching trades: " . $e->getMessage());
}

if ($format === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="forex_trades_' . date('Y-m-d') . '.csv"');
  
  $output = fopen('php://output', 'w');
  
  // Headers
  fputcsv($output, [
    'ID', 'Entry Date', 'Exit Date', 'Currency Pair', 'Trade Type', 
    'Entry Price', 'Exit Price', 'Lot Size', 'Stop Loss', 'Take Profit', 
    'Profit/Loss', 'Status', 'Notes'
  ]);
  
  // Data
  foreach ($trades as $trade) {
    fputcsv($output, [
      $trade['id'],
      $trade['entry_date'],
      $trade['exit_date'] ?? '',
      $trade['currency_pair'],
      $trade['trade_type'],
      $trade['entry_price'],
      $trade['exit_price'] ?? '',
      $trade['lot_size'],
      $trade['stop_loss'] ?? '',
      $trade['take_profit'] ?? '',
      $trade['profit_loss'] ?? '',
      $trade['status'],
      $trade['notes'] ?? ''
    ]);
  }
  
  fclose($output);
  exit;
}

if ($format === 'json') {
  header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="forex_trades_' . date('Y-m-d') . '.json"');
  
  echo json_encode([
    'exported_at' => date('Y-m-d H:i:s'),
    'total_trades' => count($trades),
    'trades' => $trades
  ], JSON_PRETTY_PRINT);
  exit;
}

// Default: show export options page
include __DIR__ . '/../includes/header.php';
?>

<style>
.export-container {
  max-width: 800px;
  margin: 40px auto;
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 40px;
}
.export-header {
  text-align: center;
  margin-bottom: 40px;
}
.export-header h1 {
  font-size: 2rem;
  font-weight: 800;
  color: var(--card-text);
  margin: 0 0 12px 0;
}
.export-header p {
  color: var(--muted);
  font-size: 1.1rem;
}
.export-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.export-option {
  background: rgba(var(--card-text-rgb), 0.03);
  border: 2px solid var(--border-weak);
  border-radius: 12px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  color: var(--card-text);
}
.export-option:hover {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.05);
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
}
.export-icon {
  font-size: 3rem;
  margin-bottom: 16px;
}
.export-title {
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 8px;
}
.export-desc {
  font-size: 0.9rem;
  color: var(--muted);
}
.stats-summary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 24px;
  color: white;
  text-align: center;
  margin-bottom: 32px;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-top: 20px;
}
.stat-item {
  text-align: center;
}
.stat-value {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 4px;
}
.stat-label {
  font-size: 0.85rem;
  opacity: 0.9;
}
</style>

<div class="export-container">
  <div class="export-header">
    <h1>📊 Export Trading Data</h1>
    <p>Download your forex trading history in your preferred format</p>
  </div>

  <div class="stats-summary">
    <h3 style="margin: 0 0 16px 0;">Ready to Export</h3>
    <div class="stats-grid">
      <div class="stat-item">
        <div class="stat-value"><?=count($trades)?></div>
        <div class="stat-label">Total Trades</div>
      </div>
      <div class="stat-item">
        <div class="stat-value">
          <?php
          $closed = array_filter($trades, fn($t) => $t['status'] === 'closed');
          echo count($closed);
          ?>
        </div>
        <div class="stat-label">Closed Trades</div>
      </div>
      <div class="stat-item">
        <div class="stat-value">
          <?php
          $total_pl = array_sum(array_column($trades, 'profit_loss'));
          echo ($total_pl >= 0 ? '+' : '') . '$' . number_format($total_pl, 2);
          ?>
        </div>
        <div class="stat-label">Total P/L</div>
      </div>
    </div>
  </div>

  <div class="export-options">
    <a href="?format=csv" class="export-option">
      <div class="export-icon">📄</div>
      <div class="export-title">CSV Format</div>
      <div class="export-desc">Excel-compatible spreadsheet format. Perfect for analysis and record keeping.</div>
    </a>

    <a href="?format=json" class="export-option">
      <div class="export-icon">📋</div>
      <div class="export-title">JSON Format</div>
      <div class="export-desc">Structured data format. Ideal for importing into other applications.</div>
    </a>
  </div>

  <div style="text-align: center; margin-top: 32px;">
    <a href="/MY CASH/forex/dashboard.php" class="button ghost">← Back to Dashboard</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
