<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
  header("Location: /MY CASH/pages/login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/inventory.php';

$user_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $alert_id = intval($_POST['alert_id'] ?? 0);
  
  try {
    if ($action === 'acknowledge') {
      $stmt = $conn->prepare("UPDATE low_stock_notifications 
                             SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW()
                             WHERE id = ?");
      $stmt->execute([$user_id, $alert_id]);
      $success_msg = "Alert acknowledged successfully!";
    } elseif ($action === 'resolve') {
      $notes = trim($_POST['resolution_notes'] ?? 'Stock replenished');
      $stmt = $conn->prepare("UPDATE low_stock_notifications 
                             SET is_resolved = 1, resolved_by = ?, resolved_at = NOW(), resolution_notes = ?
                             WHERE id = ?");
      $stmt->execute([$user_id, $notes, $alert_id]);
      $success_msg = "Alert resolved successfully!";
    }
  } catch (Exception $e) {
    $error_msg = $e->getMessage();
  }
}

// Get filters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? 'active';

// Fetch alerts
$alerts = getLowStockItems($conn, $user_id, $filter_type ?: null);

// Filter by status
if ($filter_status === 'active') {
  $alerts = array_filter($alerts, function($a) { return !$a['is_resolved']; });
} elseif ($filter_status === 'resolved') {
  // Need separate query for resolved
  $stmt = $conn->prepare("SELECT n.*, s.item_code, s.category 
                         FROM low_stock_notifications n
                         JOIN stationery_items s ON n.item_id = s.id
                         WHERE n.is_resolved = 1 AND s.user_id = ?
                         ORDER BY n.resolved_at DESC LIMIT 50");
  $stmt->execute([$user_id]);
  $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats = [
  'total' => count($alerts),
  'critical' => count(array_filter($alerts, fn($a) => $a['notification_type'] === 'critical')),
  'out_of_stock' => count(array_filter($alerts, fn($a) => $a['notification_type'] === 'out_of_stock')),
  'low' => count(array_filter($alerts, fn($a) => $a['notification_type'] === 'low'))
];
?>

<style>
  .alerts-page { max-width: 100%; padding: 24px; }
  .page-header { background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(220,38,38,0.05)); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid #ef4444; }
  .page-title { font-size: 1.8rem; font-weight: 800; color: #1a202c; margin-bottom: 8px; }
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
  .stat-card { background: white; border-radius: 16px; padding: 24px; border-left: 4px solid #e2e8f0; transition: all 0.3s; }
  .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
  .stat-card.critical { border-left-color: #ef4444; background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(220,38,38,0.02)); }
  .stat-card.out { border-left-color: #dc2626; background: linear-gradient(135deg, rgba(220,38,38,0.08), rgba(185,28,28,0.03)); }
  .stat-card.low { border-left-color: #f59e0b; background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(217,119,6,0.02)); }
  .stat-value { font-size: 2.2rem; font-weight: 800; color: #1a202c; margin-bottom: 8px; }
  .stat-label { font-size: 0.9rem; color: #718096; text-transform: uppercase; font-weight: 600; }
  .filters { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
  .filter-btn { padding: 10px 20px; border: 2px solid #e2e8f0; border-radius: 10px; background: white; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #4a5568; }
  .filter-btn:hover { border-color: #cbd5e0; background: #f7fafc; }
  .filter-btn.active { border-color: #ef4444; background: #ef4444; color: white; }
  .alerts-table { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
  .table { width: 100%; border-collapse: collapse; }
  .table th { text-align: left; padding: 12px; background: #f7fafc; font-size: 0.85rem; color: #718096; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
  .table td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; color: #2d3748; }
  .table tr:hover { background: #fafafa; }
  .badge { padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
  .badge.critical { background: rgba(239,68,68,0.15); color: #dc2626; }
  .badge.out { background: rgba(220,38,38,0.2); color: #991b1b; }
  .badge.low { background: rgba(245,158,11,0.15); color: #d97706; }
  .badge.acknowledged { background: rgba(59,130,246,0.15); color: #2563eb; }
  .badge.resolved { background: rgba(34,197,94,0.15); color: #16a34a; }
  .action-btns { display: flex; gap: 8px; }
  .btn-small { padding: 6px 12px; border-radius: 8px; border: none; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .btn-ack { background: #3b82f6; color: white; }
  .btn-resolve { background: #10b981; color: white; }
  .btn-small:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
  .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
  .empty-icon { font-size: 4rem; margin-bottom: 16px; }
</style>

<div class="alerts-page">
  <div class="page-header">
    <h1 class="page-title">🚨 Low Stock Alerts</h1>
    <p style="color:#718096;font-size:1rem">Monitor and manage inventory stock levels</p>
  </div>

  <?php if (isset($success_msg)): ?>
    <div style="background:#d1fae5;border:1px solid #10b981;color:#065f46;padding:16px;border-radius:12px;margin-bottom:20px">
      ✅ <?php echo htmlspecialchars($success_msg); ?>
    </div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['total']; ?></div>
      <div class="stat-label">Total Alerts</div>
    </div>
    <div class="stat-card critical">
      <div class="stat-value"><?php echo $stats['critical']; ?></div>
      <div class="stat-label">Critical Stock</div>
    </div>
    <div class="stat-card out">
      <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
      <div class="stat-label">Out of Stock</div>
    </div>
    <div class="stat-card low">
      <div class="stat-value"><?php echo $stats['low']; ?></div>
      <div class="stat-label">Low Stock</div>
    </div>
  </div>

  <div class="filters">
    <a href="?status=active" class="filter-btn <?php echo $filter_status === 'active' ? 'active' : ''; ?>">Active</a>
    <a href="?status=resolved" class="filter-btn <?php echo $filter_status === 'resolved' ? 'active' : ''; ?>">Resolved</a>
    <a href="?status=active&type=out_of_stock" class="filter-btn <?php echo $filter_type === 'out_of_stock' ? 'active' : ''; ?>">Out of Stock</a>
    <a href="?status=active&type=critical" class="filter-btn <?php echo $filter_type === 'critical' ? 'active' : ''; ?>">Critical</a>
    <a href="?status=active&type=low" class="filter-btn <?php echo $filter_type === 'low' ? 'active' : ''; ?>">Low</a>
  </div>

  <div class="alerts-table">
    <h2 style="margin-bottom:20px;font-size:1.3rem;font-weight:700">📋 Inventory Alerts</h2>
    
    <?php if (empty($alerts)): ?>
      <div class="empty-state">
        <div class="empty-icon">✅</div>
        <h3 style="margin-bottom:12px">No Alerts Found</h3>
        <p>All inventory levels are healthy!</p>
      </div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Code</th>
            <th>Category</th>
            <th>Current Stock</th>
            <th>Minimum</th>
            <th>Shortage</th>
            <th>Type</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alerts as $alert): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($alert['item_name']); ?></strong></td>
              <td><?php echo htmlspecialchars($alert['item_code']); ?></td>
              <td><?php echo htmlspecialchars($alert['category']); ?></td>
              <td style="font-weight:700;color:<?php echo $alert['current_stock'] == 0 ? '#dc2626' : '#f59e0b'; ?>">
                <?php echo $alert['current_stock']; ?>
              </td>
              <td><?php echo $alert['minimum_stock']; ?></td>
              <td style="color:#ef4444;font-weight:700"><?php echo $alert['shortage_amount']; ?></td>
              <td><span class="badge <?php echo $alert['notification_type']; ?>"><?php echo ucwords(str_replace('_', ' ', $alert['notification_type'])); ?></span></td>
              <td>
                <?php if ($alert['is_resolved']): ?>
                  <span class="badge resolved">Resolved</span>
                <?php elseif ($alert['is_acknowledged']): ?>
                  <span class="badge acknowledged">Acknowledged</span>
                <?php else: ?>
                  <span class="badge critical">Pending</span>
                <?php endif; ?>
              </td>
              <td><?php echo date('M d, Y', strtotime($alert['created_at'])); ?></td>
              <td>
                <?php if (!$alert['is_resolved']): ?>
                  <div class="action-btns">
                    <?php if (!$alert['is_acknowledged']): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="acknowledge">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <button type="submit" class="btn-small btn-ack">✓ Acknowledge</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="resolve">
                      <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                      <input type="hidden" name="resolution_notes" value="Stock replenished">
                      <button type="submit" class="btn-small btn-resolve">✓ Resolve</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span style="color:#16a34a;font-size:0.85rem">Resolved <?php echo date('M d', strtotime($alert['resolved_at'])); ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
