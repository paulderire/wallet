<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['employee_role'] ?? '';
$user_id = $_SESSION['employee_user_id'] ?? 0;

$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $item_name = trim($_POST['item_name'] ?? '');
    $current_quantity = intval($_POST['current_quantity'] ?? 0);
    $alert_type = trim($_POST['alert_type'] ?? 'low_stock');
    $urgency = trim($_POST['urgency'] ?? 'medium');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($item_name)) {
      throw new Exception("Item name is required");
    }
    
    if (!in_array($alert_type, ['out_of_stock', 'low_stock', 'damaged', 'expired'])) {
      throw new Exception("Invalid alert type");
    }
    
    if (!in_array($urgency, ['low', 'medium', 'high', 'critical'])) {
      throw new Exception("Invalid urgency level");
    }
    
    // Get current date and time
    $alert_date = date('Y-m-d');
    $alert_time = date('H:i:s');
    
    // Insert into inventory_alerts
    $stmt = $conn->prepare("INSERT INTO inventory_alerts 
      (employee_id, user_id, item_name, current_quantity, alert_type, urgency, notes, alert_date, alert_time, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    $stmt->execute([
      $employee_id,
      $user_id,
      $item_name,
      $current_quantity,
      $alert_type,
      $urgency,
      $notes,
      $alert_date,
      $alert_time
    ]);
    
    // Also create a task record for tracking
    $title = ucfirst(str_replace('_', ' ', $alert_type)) . ": " . $item_name;
    $description = "Alert Type: " . ucfirst(str_replace('_', ' ', $alert_type)) . "\n";
    $description .= "Current Quantity: " . $current_quantity . "\n";
    $description .= "Urgency: " . strtoupper($urgency) . "\n";
    if ($notes) {
      $description .= "Notes: " . $notes;
    }
    
    $stmt = $conn->prepare("INSERT INTO employee_tasks 
      (employee_id, user_id, task_date, title, description, category, status, transaction_type)
      VALUES (?, ?, ?, ?, ?, 'Stock Alert', 'pending', 'stock_alert')");
    
    $stmt->execute([
      $employee_id,
      $user_id,
      $alert_date,
      $title,
      $description
    ]);
    
    $urgency_msg = strtoupper($urgency);
    $success_msg = "Stock alert submitted successfully! ({$urgency_msg} priority) - Manager has been notified.";
    
    // Clear form
    $_POST = array();
    
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Fetch stationery items for quick selection
$stationery_items = [];
try {
  $stmt = $conn->prepare("SELECT * FROM stationery_items WHERE user_id = ? AND is_active = 1 ORDER BY item_name ASC");
  $stmt->execute([$user_id]);
  $stationery_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch recent alerts by this employee
$recent_alerts = [];
try {
  $stmt = $conn->prepare("SELECT * FROM inventory_alerts 
    WHERE employee_id = ? 
    ORDER BY alert_date DESC, alert_time DESC 
    LIMIT 5");
  $stmt->execute([$employee_id]);
  $recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Stock Issue - Stationery Business</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 24px 32px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .header-title {
      font-size: 28px;
      font-weight: 800;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .header-subtitle {
      color: #718096;
      font-size: 14px;
      margin-top: 4px;
    }
    
    .btn {
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      text-decoration: none;
      font-size: 14px;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-secondary {
      background: rgba(102, 126, 234, 0.1);
      color: #667eea;
      border: 2px solid rgba(102, 126, 234, 0.2);
    }
    
    .btn-secondary:hover {
      background: rgba(102, 126, 234, 0.2);
      transform: translateY(-2px);
    }
    
    .content-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 24px;
    }
    
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .card-title {
      font-size: 20px;
      font-weight: 700;
      color: #1a202c;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      font-size: 14px;
      font-weight: 600;
    }
    
    .alert-success {
      background: #d4f4dd;
      color: #22543d;
      border-left: 4px solid #10b981;
    }
    
    .alert-error {
      background: #fecaca;
      color: #7f1d1d;
      border-left: 4px solid #ef4444;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group.full-width {
      grid-column: 1 / -1;
    }
    
    .form-label {
      font-weight: 600;
      color: #1a202c;
      margin-bottom: 8px;
      font-size: 14px;
    }
    
    .form-label .required {
      color: #ef4444;
      margin-left: 4px;
    }
    
    .form-input, .form-select, .form-textarea {
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-family: 'Inter', sans-serif;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    .btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
      padding: 14px 28px;
      font-size: 16px;
      margin-top: 8px;
    }
    
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(239, 68, 68, 0.5);
    }
    
    .items-helper {
      background: #f7fafc;
      padding: 16px;
      border-radius: 10px;
      margin-top: 12px;
      border: 2px dashed #cbd5e0;
    }
    
    .items-helper-title {
      font-weight: 700;
      color: #2d3748;
      margin-bottom: 12px;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .items-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 8px;
    }
    
    .item-chip {
      background: white;
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid #e2e8f0;
      font-size: 12px;
    }
    
    .item-chip:hover {
      border-color: #ef4444;
      background: #fef2f2;
    }
    
    .item-name {
      font-weight: 600;
      color: #1a202c;
      display: block;
    }
    
    .item-stock {
      color: #718096;
      font-size: 11px;
    }
    
    .item-stock.low {
      color: #f59e0b;
      font-weight: 700;
    }
    
    .item-stock.critical {
      color: #ef4444;
      font-weight: 700;
    }
    
    .alert-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .alert-item {
      padding: 16px;
      border-radius: 12px;
      border-left: 4px solid;
      font-size: 13px;
    }
    
    .alert-item.pending {
      background: #fef9c3;
      border-left-color: #f59e0b;
    }
    
    .alert-item.acknowledged {
      background: #dbeafe;
      border-left-color: #3b82f6;
    }
    
    .alert-item.resolved {
      background: #d4f4dd;
      border-left-color: #10b981;
    }
    
    .alert-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    
    .alert-title {
      font-weight: 700;
      color: #1a202c;
    }
    
    .status-badge {
      padding: 2px 8px;
      border-radius: 8px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
    }
    
    .status-pending {
      background: #f59e0b;
      color: white;
    }
    
    .status-acknowledged {
      background: #3b82f6;
      color: white;
    }
    
    .status-resolved {
      background: #10b981;
      color: white;
    }
    
    .alert-meta {
      color: #718096;
      font-size: 11px;
    }
    
    .empty-state {
      text-align: center;
      padding: 32px 16px;
      color: #a0aec0;
    }
    
    @media (max-width: 1024px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
      }
      
      .items-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="header-title">⚠️ Report Stock Issue</h1>
        <p class="header-subtitle">Alert management about missing or low stock items</p>
      </div>
      <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">
        ← Back to Dashboard
      </a>
    </div>
    
    <div class="content-grid">
      <!-- Form Card -->
      <div class="card">
        <h2 class="card-title">🚨 New Stock Alert</h2>
        
        <?php if ($success_msg): ?>
          <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
          <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
          <div class="form-grid">
            <!-- Item Name -->
            <div class="form-group full-width">
              <label class="form-label">
                Item Name <span class="required">*</span>
              </label>
              <input 
                type="text" 
                name="item_name" 
                class="form-input" 
                required
                placeholder="e.g., A4 Paper, Blue Pens, Staplers"
                value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>"
              >
              
              <?php if (!empty($stationery_items)): ?>
                <div class="items-helper">
                  <div class="items-helper-title">Quick Select Item</div>
                  <div class="items-grid">
                    <?php foreach ($stationery_items as $item): 
                      $stock_class = '';
                      if ($item['current_stock'] <= $item['minimum_stock']) {
                        $stock_class = 'critical';
                      } elseif ($item['current_stock'] <= $item['minimum_stock'] * 1.5) {
                        $stock_class = 'low';
                      }
                    ?>
                      <div class="item-chip" onclick="selectItem('<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['current_stock']; ?>)">
                        <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                        <span class="item-stock <?php echo $stock_class; ?>">
                          Stock: <?php echo $item['current_stock']; ?>
                        </span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            
            <!-- Alert Type -->
            <div class="form-group">
              <label class="form-label">
                Alert Type <span class="required">*</span>
              </label>
              <select name="alert_type" class="form-select" required>
                <option value="out_of_stock">🚫 Out of Stock</option>
                <option value="low_stock">📉 Low Stock</option>
                <option value="damaged">💔 Damaged Items</option>
                <option value="expired">⏰ Expired Items</option>
              </select>
            </div>
            
            <!-- Urgency -->
            <div class="form-group">
              <label class="form-label">
                Urgency Level <span class="required">*</span>
              </label>
              <select name="urgency" class="form-select" required>
                <option value="low">🟢 Low</option>
                <option value="medium" selected>🟡 Medium</option>
                <option value="high">🟠 High</option>
                <option value="critical">🔴 Critical</option>
              </select>
            </div>
            
            <!-- Current Quantity -->
            <div class="form-group full-width">
              <label class="form-label">
                Current Quantity
              </label>
              <input 
                type="number" 
                name="current_quantity" 
                class="form-input" 
                min="0" 
                placeholder="e.g., 5 (leave 0 if completely out)"
                value="<?php echo htmlspecialchars($_POST['current_quantity'] ?? '0'); ?>"
              >
            </div>
            
            <!-- Notes -->
            <div class="form-group full-width">
              <label class="form-label">
                Additional Notes
              </label>
              <textarea 
                name="notes" 
                class="form-textarea"
                placeholder="Describe the issue, impact on sales, or any other relevant information..."
              ><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
          </div>
          
          <button type="submit" class="btn btn-danger">
            🚨 Submit Alert
          </button>
        </form>
      </div>
      
      <!-- Recent Alerts -->
      <div class="card">
        <h2 class="card-title">
          📋 Your Recent Alerts
        </h2>
        
        <?php if (empty($recent_alerts)): ?>
          <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 12px;">📝</div>
            <p>No alerts submitted yet</p>
          </div>
        <?php else: ?>
          <div class="alert-list">
            <?php foreach ($recent_alerts as $alert): ?>
              <div class="alert-item <?php echo $alert['status']; ?>">
                <div class="alert-header">
                  <div class="alert-title"><?php echo htmlspecialchars($alert['item_name']); ?></div>
                  <span class="status-badge status-<?php echo $alert['status']; ?>">
                    <?php echo $alert['status']; ?>
                  </span>
                </div>
                <div class="alert-meta">
                  <div style="margin-bottom: 4px;">
                    <strong><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></strong>
                    • <?php echo strtoupper($alert['urgency']); ?> priority
                  </div>
                  <?php if ($alert['current_quantity'] > 0): ?>
                    <div>Qty: <?php echo $alert['current_quantity']; ?></div>
                  <?php endif; ?>
                  <div>📅 <?php echo date('M j, g:i A', strtotime($alert['alert_date'] . ' ' . $alert['alert_time'])); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script>
    function selectItem(itemName, currentStock) {
      document.querySelector('input[name="item_name"]').value = itemName;
      document.querySelector('input[name="current_quantity"]').value = currentStock;
      
      // Auto-select urgency based on stock
      const urgencySelect = document.querySelector('select[name="urgency"]');
      if (currentStock === 0) {
        urgencySelect.value = 'critical';
        document.querySelector('select[name="alert_type"]').value = 'out_of_stock';
      } else if (currentStock < 10) {
        urgencySelect.value = 'high';
        document.querySelector('select[name="alert_type"]').value = 'low_stock';
      } else {
        urgencySelect.value = 'medium';
        document.querySelector('select[name="alert_type"]').value = 'low_stock';
      }
    }
  </script>
</body>
</html>
