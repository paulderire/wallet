<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['employee_role'] ?? '';
$user_id = $_SESSION['employee_user_id'] ?? 0;

$success_msg = '';
$error_msg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
      // Add new product
      $item_name = trim($_POST['item_name'] ?? '');
      $item_code = trim($_POST['item_code'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $unit_price = floatval($_POST['unit_price'] ?? 0);
      $cost_price = floatval($_POST['cost_price'] ?? 0);
      $current_stock = intval($_POST['current_stock'] ?? 0);
      $minimum_stock = intval($_POST['minimum_stock'] ?? 0);
      
      // Validation
      if (empty($item_name)) throw new Exception("Item name is required");
      if (empty($item_code)) throw new Exception("Item code is required");
      if (empty($category)) throw new Exception("Category is required");
      if ($unit_price <= 0) throw new Exception("Unit price must be greater than zero");
      
      // Check for duplicate item code
      $stmt = $conn->prepare("SELECT id FROM stationery_items WHERE item_code = ? AND user_id = ?");
      $stmt->execute([$item_code, $user_id]);
      if ($stmt->fetch()) {
        throw new Exception("Item code already exists. Please use a unique code.");
      }
      
      // Insert
      $stmt = $conn->prepare("INSERT INTO stationery_items 
        (user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
      
      $stmt->execute([$user_id, $item_name, $item_code, $category, $description, $unit_price, $cost_price, $current_stock, $minimum_stock]);
      
      $success_msg = "Product added successfully! Item Code: $item_code";
      $_POST = array(); // Clear form
      
    } elseif ($action === 'update_stock') {
      // Update stock
      $item_id = intval($_POST['item_id'] ?? 0);
      $adjustment = intval($_POST['adjustment'] ?? 0);
      $adjustment_type = $_POST['adjustment_type'] ?? 'add';
      $notes = trim($_POST['notes'] ?? '');
      
      // Get current stock
      $stmt = $conn->prepare("SELECT current_stock, item_name FROM stationery_items WHERE id = ? AND user_id = ?");
      $stmt->execute([$item_id, $user_id]);
      $item = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$item) throw new Exception("Product not found");
      
      $old_stock = $item['current_stock'];
      $new_stock = $old_stock;
      
      if ($adjustment_type === 'add') {
        $new_stock = $old_stock + $adjustment;
      } elseif ($adjustment_type === 'subtract') {
        $new_stock = max(0, $old_stock - $adjustment);
      } elseif ($adjustment_type === 'set') {
        $new_stock = $adjustment;
      }
      
      // Update stock
      $stmt = $conn->prepare("UPDATE stationery_items SET current_stock = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      $stmt->execute([$new_stock, $item_id, $user_id]);
      
      // Log the adjustment
      $log_description = "Stock adjusted from $old_stock to $new_stock";
      if ($notes) {
        $log_description .= " - Notes: $notes";
      }
      
      $stmt = $conn->prepare("INSERT INTO employee_tasks 
        (employee_id, user_id, task_date, task_time, title, description, category, status, transaction_type)
        VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, 'Stock Management', 'completed', 'other')");
      
      $stmt->execute([
        $employee_id,
        $user_id,
        "Stock Update: " . $item['item_name'],
        $log_description
      ]);
      
      $success_msg = "Stock updated successfully! {$item['item_name']}: $old_stock → $new_stock";
      
    } elseif ($action === 'quick_restock') {
      // Quick restock button
      $item_id = intval($_POST['item_id'] ?? 0);
      $quantity = intval($_POST['quantity'] ?? 0);
      
      $stmt = $conn->prepare("UPDATE stationery_items SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      $stmt->execute([$quantity, $item_id, $user_id]);
      
      $success_msg = "Added $quantity units to stock!";
    }
    
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Fetch all products
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM stationery_items WHERE user_id = ?";
$params = [$user_id];

if ($filter === 'active') {
  $query .= " AND is_active = 1";
} elseif ($filter === 'low_stock') {
  $query .= " AND current_stock <= minimum_stock AND is_active = 1";
} elseif ($filter === 'out_of_stock') {
  $query .= " AND current_stock = 0 AND is_active = 1";
}

if (!empty($search)) {
  $query .= " AND (item_name LIKE ? OR item_code LIKE ? OR category LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
}

$query .= " ORDER BY 
            CASE 
              WHEN current_stock = 0 THEN 1
              WHEN current_stock <= minimum_stock THEN 2
              ELSE 3
            END,
            category, item_name";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("SELECT 
  COUNT(*) as total_products,
  SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
  SUM(CASE WHEN current_stock <= minimum_stock AND is_active = 1 THEN 1 ELSE 0 END) as low_stock_items,
  SUM(CASE WHEN current_stock = 0 AND is_active = 1 THEN 1 ELSE 0 END) as out_of_stock,
  SUM(CASE WHEN is_active = 1 THEN current_stock ELSE 0 END) as total_stock
  FROM stationery_items WHERE user_id = ?");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Inventory - Employee Portal</title>
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
      max-width: 1600px;
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
    }
    
    .btn-secondary {
      background: rgba(102, 126, 234, 0.1);
      color: #667eea;
      border: 2px solid rgba(102, 126, 234, 0.2);
    }
    
    .btn-success {
      background: #10b981;
      color: white;
    }
    
    .btn-success:hover {
      background: #059669;
    }
    
    .btn-small {
      padding: 6px 12px;
      font-size: 12px;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }
    
    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }
    
    .stat-label {
      font-size: 12px;
      color: #718096;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 8px;
    }
    
    .stat-value {
      font-size: 32px;
      font-weight: 900;
      color: #1a202c;
    }
    
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 24px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      margin-bottom: 24px;
    }
    
    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: #1a202c;
      margin-bottom: 16px;
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
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
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
      margin-bottom: 6px;
      font-size: 13px;
    }
    
    .form-input, .form-select, .form-textarea {
      padding: 10px 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 14px;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .tab {
      padding: 12px 20px;
      background: none;
      border: none;
      font-weight: 600;
      font-size: 14px;
      color: #718096;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      transition: all 0.3s;
    }
    
    .tab.active {
      color: #667eea;
      border-bottom-color: #667eea;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    .filters {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .filter-btn {
      padding: 8px 16px;
      border-radius: 8px;
      border: 2px solid #e2e8f0;
      background: white;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      color: #2d3748;
    }
    
    .filter-btn.active {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-color: #667eea;
    }
    
    .search-box {
      flex: 1;
      min-width: 250px;
    }
    
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }
    
    .product-card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border-left: 4px solid #667eea;
      transition: all 0.3s;
    }
    
    .product-card:hover {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
      transform: translateY(-2px);
    }
    
    .product-card.out-of-stock {
      border-left-color: #ef4444;
      background: #fef2f2;
    }
    
    .product-card.low-stock {
      border-left-color: #f59e0b;
      background: #fffbeb;
    }
    
    .product-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 12px;
    }
    
    .product-name {
      font-weight: 700;
      font-size: 16px;
      color: #1a202c;
      margin-bottom: 4px;
    }
    
    .product-code {
      font-size: 11px;
      color: #718096;
      font-weight: 600;
    }
    
    .product-category {
      font-size: 11px;
      padding: 4px 8px;
      background: #eef2ff;
      color: #667eea;
      border-radius: 6px;
      font-weight: 600;
    }
    
    .product-stock {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 16px 0;
      padding: 12px;
      background: #f7fafc;
      border-radius: 8px;
    }
    
    .stock-number {
      font-size: 28px;
      font-weight: 900;
    }
    
    .stock-number.good {
      color: #10b981;
    }
    
    .stock-number.low {
      color: #f59e0b;
    }
    
    .stock-number.out {
      color: #ef4444;
    }
    
    .stock-label {
      font-size: 11px;
      color: #718096;
      text-transform: uppercase;
      font-weight: 600;
    }
    
    .product-price {
      display: flex;
      justify-content: space-between;
      margin: 12px 0;
      font-size: 13px;
    }
    
    .price-label {
      color: #718096;
    }
    
    .price-value {
      font-weight: 700;
      color: #1a202c;
    }
    
    .product-actions {
      display: flex;
      gap: 8px;
      margin-top: 16px;
    }
    
    .quick-adjust {
      display: flex;
      gap: 8px;
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px solid #e2e8f0;
    }
    
    .quick-btn {
      flex: 1;
      padding: 8px;
      border: 2px solid #e2e8f0;
      background: white;
      border-radius: 6px;
      font-weight: 600;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .quick-btn:hover {
      border-color: #667eea;
      background: #eef2ff;
      color: #667eea;
    }
    
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .header {
        flex-direction: column;
        gap: 16px;
      }
      
      .products-grid {
        grid-template-columns: 1fr;
      }
    }
    
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }
    
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background: white;
      padding: 32px;
      border-radius: 16px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    
    .modal-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 20px;
      color: #1a202c;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="header-title">📦 Inventory Management</h1>
        <p class="header-subtitle">Manage products and stock levels • <?php echo htmlspecialchars($employee_name); ?> • <?php echo htmlspecialchars($employee_role); ?></p>
      </div>
      <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">
        ← Back to Dashboard
      </a>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?php echo $stats['total_products']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">Active Products</div>
        <div class="stat-value"><?php echo $stats['active_products']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">Total Stock</div>
        <div class="stat-value"><?php echo number_format($stats['total_stock']); ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">⚠️ Low Stock</div>
        <div class="stat-value"><?php echo $stats['low_stock_items']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">🚫 Out of Stock</div>
        <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
      </div>
    </div>
    
    <!-- Tabs -->
    <div class="card">
      <div class="tabs">
        <button class="tab active" onclick="switchTab('view')">📋 View Inventory</button>
        <button class="tab" onclick="switchTab('add')">➕ Add Product</button>
      </div>
      
      <!-- View Inventory Tab -->
      <div id="tab-view" class="tab-content active">
        <?php if ($success_msg): ?>
          <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
          <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
          <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Products</a>
          <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
          <a href="?filter=low_stock" class="filter-btn <?php echo $filter === 'low_stock' ? 'active' : ''; ?>">Low Stock</a>
          <a href="?filter=out_of_stock" class="filter-btn <?php echo $filter === 'out_of_stock' ? 'active' : ''; ?>">Out of Stock</a>
          
          <form method="GET" class="search-box">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="search" class="form-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
          </form>
        </div>
        
        <!-- Products Grid -->
        <div class="products-grid">
          <?php foreach ($products as $product): 
            $stock_class = 'good';
            $card_class = '';
            if ($product['current_stock'] == 0) {
              $stock_class = 'out';
              $card_class = 'out-of-stock';
            } elseif ($product['current_stock'] <= $product['minimum_stock']) {
              $stock_class = 'low';
              $card_class = 'low-stock';
            }
          ?>
            <div class="product-card <?php echo $card_class; ?>">
              <div class="product-header">
                <div>
                  <div class="product-name"><?php echo htmlspecialchars($product['item_name']); ?></div>
                  <div class="product-code"><?php echo htmlspecialchars($product['item_code']); ?></div>
                </div>
                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
              </div>
              
              <div class="product-stock">
                <div>
                  <div class="stock-number <?php echo $stock_class; ?>"><?php echo $product['current_stock']; ?></div>
                  <div class="stock-label">In Stock</div>
                </div>
                <div style="margin-left: auto;">
                  <div style="font-size: 16px; font-weight: 700; color: #718096;"><?php echo $product['minimum_stock']; ?></div>
                  <div class="stock-label">Minimum</div>
                </div>
              </div>
              
              <div class="product-price">
                <span class="price-label">Selling Price:</span>
                <span class="price-value">RWF <?php echo number_format($product['unit_price'], 0); ?> <span style="color: #10b981; font-size: 13px;">($<?php echo number_format(rwf_to_usd($product['unit_price']), 2); ?>)</span></span>
              </div>
              
              <?php if ($product['cost_price'] > 0): ?>
              <div class="product-price">
                <span class="price-label">Cost Price:</span>
                <span class="price-value">RWF <?php echo number_format($product['cost_price'], 0); ?> <span style="color: #667eea; font-size: 13px;">($<?php echo number_format(rwf_to_usd($product['cost_price']), 2); ?>)</span></span>
              </div>
              <?php endif; ?>
              
              <div class="product-actions">
                <button class="btn btn-primary btn-small" onclick="openStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['item_name']); ?>', <?php echo $product['current_stock']; ?>)">
                  📊 Adjust Stock
                </button>
              </div>
              
              <div class="quick-adjust">
                <form method="POST" style="flex: 1;">
                  <input type="hidden" name="action" value="quick_restock">
                  <input type="hidden" name="item_id" value="<?php echo $product['id']; ?>">
                  <input type="hidden" name="quantity" value="10">
                  <button type="submit" class="quick-btn">+10</button>
                </form>
                <form method="POST" style="flex: 1;">
                  <input type="hidden" name="action" value="quick_restock">
                  <input type="hidden" name="item_id" value="<?php echo $product['id']; ?>">
                  <input type="hidden" name="quantity" value="50">
                  <button type="submit" class="quick-btn">+50</button>
                </form>
                <form method="POST" style="flex: 1;">
                  <input type="hidden" name="action" value="quick_restock">
                  <input type="hidden" name="item_id" value="<?php echo $product['id']; ?>">
                  <input type="hidden" name="quantity" value="100">
                  <button type="submit" class="quick-btn">+100</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- Add Product Tab -->
      <div id="tab-add" class="tab-content">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Item Name *</label>
              <input type="text" name="item_name" class="form-input" required placeholder="e.g., Highlighters (Pack of 4)">
            </div>
            
            <div class="form-group">
              <label class="form-label">Item Code *</label>
              <input type="text" name="item_code" class="form-input" required placeholder="e.g., ST-011">
            </div>
            
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <option value="Paper Products">Paper Products</option>
                <option value="Writing Instruments">Writing Instruments</option>
                <option value="Office Supplies">Office Supplies</option>
                <option value="Filing Supplies">Filing Supplies</option>
                <option value="Electronics">Electronics</option>
                <option value="Art Supplies">Art Supplies</option>
                <option value="Desk Accessories">Desk Accessories</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Unit Price (RWF) *</label>
              <input type="number" name="unit_price" class="form-input" required min="0" step="1" placeholder="e.g., 1500">
            </div>
            
            <div class="form-group">
              <label class="form-label">Cost Price (RWF)</label>
              <input type="number" name="cost_price" class="form-input" min="0" step="1" placeholder="e.g., 1000">
            </div>
            
            <div class="form-group">
              <label class="form-label">Current Stock</label>
              <input type="number" name="current_stock" class="form-input" min="0" value="0" placeholder="e.g., 50">
            </div>
            
            <div class="form-group">
              <label class="form-label">Minimum Stock</label>
              <input type="number" name="minimum_stock" class="form-input" min="0" value="10" placeholder="e.g., 10">
            </div>
            
            <div class="form-group full-width">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-input" placeholder="Optional description">
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary">
            ➕ Add Product
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Stock Adjustment Modal -->
  <div id="stockModal" class="modal">
    <div class="modal-content">
      <h2 class="modal-title">📊 Adjust Stock</h2>
      <form method="POST">
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="item_id" id="modal_item_id">
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Product</label>
          <input type="text" id="modal_item_name" class="form-input" readonly>
        </div>
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Current Stock</label>
          <input type="text" id="modal_current_stock" class="form-input" readonly>
        </div>
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Adjustment Type *</label>
          <select name="adjustment_type" class="form-select" required>
            <option value="add">➕ Add to Stock (Received new stock)</option>
            <option value="subtract">➖ Subtract from Stock (Sold/Used)</option>
            <option value="set">🎯 Set Exact Amount (Stock count)</option>
          </select>
        </div>
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Quantity *</label>
          <input type="number" name="adjustment" class="form-input" required min="0" placeholder="Enter quantity">
        </div>
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-input" rows="3" placeholder="Reason for adjustment..."></textarea>
        </div>
        
        <div style="display: flex; gap: 12px;">
          <button type="submit" class="btn btn-success">
            ✅ Update Stock
          </button>
          <button type="button" class="btn btn-secondary" onclick="closeStockModal()">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    function switchTab(tab) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
      });
      document.querySelectorAll('.tab').forEach(tabBtn => {
        tabBtn.classList.remove('active');
      });
      
      // Show selected tab
      document.getElementById('tab-' + tab).classList.add('active');
      event.target.classList.add('active');
    }
    
    function openStockModal(itemId, itemName, currentStock) {
      document.getElementById('modal_item_id').value = itemId;
      document.getElementById('modal_item_name').value = itemName;
      document.getElementById('modal_current_stock').value = currentStock;
      document.getElementById('stockModal').classList.add('active');
    }
    
    function closeStockModal() {
      document.getElementById('stockModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    document.getElementById('stockModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeStockModal();
      }
    });
  </script>
</body>
</html>
