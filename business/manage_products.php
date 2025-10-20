<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: /MY CASH/pages/login.php");
  exit;
}

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
      $new_stock = intval($_POST['new_stock'] ?? 0);
      
      $stmt = $conn->prepare("UPDATE stationery_items SET current_stock = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      $stmt->execute([$new_stock, $item_id, $user_id]);
      
      $success_msg = "Stock updated successfully!";
      
    } elseif ($action === 'toggle_active') {
      // Toggle active status
      $item_id = intval($_POST['item_id'] ?? 0);
      $is_active = intval($_POST['is_active'] ?? 0);
      
      $new_status = $is_active == 1 ? 0 : 1;
      $stmt = $conn->prepare("UPDATE stationery_items SET is_active = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      $stmt->execute([$new_status, $item_id, $user_id]);
      
      $status_text = $new_status == 1 ? 'activated' : 'deactivated';
      $success_msg = "Product $status_text successfully!";
      
    } elseif ($action === 'update_price') {
      // Update prices
      $item_id = intval($_POST['item_id'] ?? 0);
      $unit_price = floatval($_POST['unit_price'] ?? 0);
      $cost_price = floatval($_POST['cost_price'] ?? 0);
      
      $stmt = $conn->prepare("UPDATE stationery_items SET unit_price = ?, cost_price = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      $stmt->execute([$unit_price, $cost_price, $item_id, $user_id]);
      
      $success_msg = "Prices updated successfully!";
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
} elseif ($filter === 'inactive') {
  $query .= " AND is_active = 0";
} elseif ($filter === 'low_stock') {
  $query .= " AND current_stock <= minimum_stock AND is_active = 1";
}

if (!empty($search)) {
  $query .= " AND (item_name LIKE ? OR item_code LIKE ? OR category LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
}

$query .= " ORDER BY category, item_name";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("SELECT 
  COUNT(*) as total_products,
  SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
  SUM(CASE WHEN current_stock <= minimum_stock AND is_active = 1 THEN 1 ELSE 0 END) as low_stock_items,
  SUM(CASE WHEN current_stock = 0 AND is_active = 1 THEN 1 ELSE 0 END) as out_of_stock
  FROM stationery_items WHERE user_id = ?");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<style>
  :root {
    --prod-bg-start: #667eea;
    --prod-bg-end: #764ba2;
    --prod-card-bg: rgba(255, 255, 255, 0.98);
    --prod-text-primary: #1e293b;
    --prod-text-secondary: #64748b;
    --prod-border: rgba(226, 232, 240, 0.8);
    --prod-shadow: rgba(0, 0, 0, 0.1);
    --prod-shadow-hover: rgba(0, 0, 0, 0.2);
  }

  [data-theme="dark"] {
    --prod-bg-start: #1a1a2e;
    --prod-bg-end: #16213e;
    --prod-card-bg: rgba(30, 30, 46, 0.95);
    --prod-text-primary: #e2e8f0;
    --prod-text-secondary: #94a3b8;
    --prod-border: rgba(71, 85, 105, 0.3);
    --prod-shadow: rgba(0, 0, 0, 0.3);
    --prod-shadow-hover: rgba(0, 0, 0, 0.5);
  }

  #app-main {
    background: linear-gradient(135deg, var(--prod-bg-start) 0%, var(--prod-bg-end) 100%);
    min-height: calc(100vh - 64px);
    padding: 32px;
  }

  .prod-wrapper {
    max-width: 1400px;
    margin: 0 auto;
  }

  .header {
    background: var(--prod-card-bg);
    backdrop-filter: blur(20px);
    padding: 28px 36px;
    border-radius: 20px;
    box-shadow: 0 8px 32px var(--prod-shadow);
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--prod-border);
  }

  .header-title {
    font-size: 2rem;
    font-weight: 900;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  [data-theme="dark"] .header-title {
    background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .header-subtitle {
    color: var(--prod-text-secondary);
    font-size: 0.875rem;
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
  
  .btn-small {
    padding: 6px 12px;
    font-size: 12px;
  }
  
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }
  
  .stat-card {
    background: var(--prod-card-bg);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 16px var(--prod-shadow);
    border: 1px solid var(--prod-border);
  }
  
  .stat-label {
    font-size: 12px;
    color: var(--prod-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  
  .stat-value {
    font-size: 32px;
    font-weight: 900;
    color: var(--prod-text-primary);
  }
  
  .card {
    background: var(--prod-card-bg);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px var(--prod-shadow);
    margin-bottom: 24px;
    border: 1px solid var(--prod-border);
  }
  
  .card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--prod-text-primary);
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
    color: var(--prod-text-primary);
    margin-bottom: 6px;
    font-size: 13px;
  }
  
  .form-input, .form-select, .form-textarea {
    padding: 10px 14px;
    border: 2px solid var(--prod-border);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    background: var(--prod-card-bg);
    color: var(--prod-text-primary);
  }
  
  .form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
    border: 2px solid var(--prod-border);
    background: var(--prod-card-bg);
    color: var(--prod-text-primary);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
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
    
  .products-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  
  .products-table thead {
    background: var(--prod-border);
  }
  
  .products-table th {
    padding: 12px;
    text-align: left;
    font-weight: 700;
    color: var(--prod-text-primary);
    border-bottom: 2px solid var(--prod-border);
    font-size: 11px;
    text-transform: uppercase;
  }
  
  .products-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--prod-border);
    color: var(--prod-text-primary);
  }
  
  .products-table tr:hover {
    background: rgba(102, 126, 234, 0.05);
  }
  
  [data-theme="dark"] .products-table thead {
    background: rgba(71, 85, 105, 0.2);
  }
  
  .status-badge {
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
  }
  
  .status-active {
    background: #d4f4dd;
    color: #22543d;
  }
  
  .status-inactive {
    background: #fee;
    color: #7f1d1d;
  }
  
  .stock-level {
    font-weight: 700;
  }
  
  .stock-good {
    color: #10b981;
  }
  
  .stock-low {
    color: #f59e0b;
  }
  
  .stock-out {
    color: #ef4444;
  }
  
  @media (max-width: 768px) {
    #app-main {
      padding: 16px;
    }
    
    .form-grid {
      grid-template-columns: 1fr;
    }
    
    .header {
      flex-direction: column;
      gap: 16px;
    }
  }
  </style>

  <div class="prod-wrapper">
    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="header-title">📦 Manage Products</h1>
        <p class="header-subtitle">Add, edit, and manage your stationery inventory</p>
      </div>
      <a href="/MY CASH/business/employees.php" class="btn btn-secondary">
        ← Back to Employee Hub
      </a>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?php echo $stats['total_products']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">✅ Active</div>
        <div class="stat-value"><?php echo $stats['active_products']; ?></div>
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
    
    <!-- Add Product Form -->
    <div class="card">
      <h2 class="card-title">➕ Add New Product</h2>
      
      <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
      <?php endif; ?>
      
      <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
      <?php endif; ?>
      
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
    
    <!-- Products List -->
    <div class="card">
      <h2 class="card-title">📋 All Products</h2>
      
      <!-- Filters -->
      <div class="filters">
        <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Products</a>
        <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active Only</a>
        <a href="?filter=inactive" class="filter-btn <?php echo $filter === 'inactive' ? 'active' : ''; ?>">Inactive Only</a>
        <a href="?filter=low_stock" class="filter-btn <?php echo $filter === 'low_stock' ? 'active' : ''; ?>">Low Stock</a>
        
        <form method="GET" class="search-box">
          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
          <input type="text" name="search" class="form-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
      </div>
      
      <!-- Table -->
      <div style="overflow-x: auto;">
        <table class="products-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Category</th>
              <th>Stock</th>
              <th>Min</th>
              <th>Unit Price</th>
              <th>Cost Price</th>
              <th>Margin</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): 
              $stock_class = 'stock-good';
              if ($product['current_stock'] == 0) {
                $stock_class = 'stock-out';
              } elseif ($product['current_stock'] <= $product['minimum_stock']) {
                $stock_class = 'stock-low';
              }
              
              $margin = $product['cost_price'] > 0 ? (($product['unit_price'] - $product['cost_price']) / $product['cost_price']) * 100 : 0;
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($product['item_code']); ?></strong></td>
                <td><?php echo htmlspecialchars($product['item_name']); ?></td>
                <td><?php echo htmlspecialchars($product['category']); ?></td>
                <td class="stock-level <?php echo $stock_class; ?>"><?php echo $product['current_stock']; ?></td>
                <td><?php echo $product['minimum_stock']; ?></td>
                <td>RWF <?php echo number_format($product['unit_price'], 0); ?></td>
                <td>RWF <?php echo number_format($product['cost_price'], 0); ?></td>
                <td><?php echo number_format($margin, 1); ?>%</td>
                <td>
                  <span class="status-badge status-<?php echo $product['is_active'] == 1 ? 'active' : 'inactive'; ?>">
                    <?php echo $product['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                  </span>
                </td>
                <td>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="item_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="is_active" value="<?php echo $product['is_active']; ?>">
                    <button type="submit" class="btn btn-secondary btn-small">
                      <?php echo $product['is_active'] == 1 ? '❌' : '✅'; ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
