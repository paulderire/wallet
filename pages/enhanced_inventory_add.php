<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$csv_errors = [];
$csv_success_count = 0;

// Product templates for quick add
$product_templates = [
  'Pen - Blue' => ['category' => 'Writing', 'unit_price' => 500, 'cost_price' => 300, 'minimum_stock' => 20],
  'Pen - Black' => ['category' => 'Writing', 'unit_price' => 500, 'cost_price' => 300, 'minimum_stock' => 20],
  'Pen - Red' => ['category' => 'Writing', 'unit_price' => 500, 'cost_price' => 300, 'minimum_stock' => 15],
  'Notebook A4' => ['category' => 'Paper', 'unit_price' => 1500, 'cost_price' => 1000, 'minimum_stock' => 10],
  'Notebook A5' => ['category' => 'Paper', 'unit_price' => 1000, 'cost_price' => 700, 'minimum_stock' => 10],
  'Stapler' => ['category' => 'Office', 'unit_price' => 3000, 'cost_price' => 2000, 'minimum_stock' => 5],
  'Staples Box' => ['category' => 'Office', 'unit_price' => 800, 'cost_price' => 500, 'minimum_stock' => 15],
  'Glue Stick' => ['category' => 'Adhesives', 'unit_price' => 600, 'cost_price' => 400, 'minimum_stock' => 10],
  'Tape Roll' => ['category' => 'Adhesives', 'unit_price' => 1200, 'cost_price' => 800, 'minimum_stock' => 8],
  'Marker - Black' => ['category' => 'Writing', 'unit_price' => 800, 'cost_price' => 500, 'minimum_stock' => 12]
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'quick_add') {
      // Quick add single product (barcode or template)
      $item_name = trim($_POST['item_name'] ?? '');
      $item_code = trim($_POST['item_code'] ?? '');
      $barcode = trim($_POST['barcode'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $unit_price = floatval($_POST['unit_price'] ?? 0);
      $cost_price = floatval($_POST['cost_price'] ?? 0);
      $current_stock = intval($_POST['current_stock'] ?? 0);
      $minimum_stock = intval($_POST['minimum_stock'] ?? 10);
      
      if (empty($item_name)) throw new Exception("Item name is required");
      if (empty($item_code)) throw new Exception("Item code is required");
      if (empty($category)) throw new Exception("Category is required");
      if ($unit_price <= 0) throw new Exception("Unit price must be greater than zero");
      
      // Check for duplicate
      $stmt = $conn->prepare("SELECT id FROM stationery_items WHERE item_code = ? AND user_id = ?");
      $stmt->execute([$item_code, $user_id]);
      if ($stmt->fetch()) {
        throw new Exception("Item code already exists: $item_code");
      }
      
      // Insert
      $stmt = $conn->prepare("INSERT INTO stationery_items 
        (user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
      
      $stmt->execute([$user_id, $item_name, $item_code, $category, $description, $unit_price, $cost_price, $current_stock, $minimum_stock]);
      
      $success_msg = "✅ Product added successfully! Code: $item_code";
      
    } elseif ($action === 'batch_add') {
      // Batch add multiple products
      $batch_data = $_POST['batch'] ?? [];
      $added_count = 0;
      $batch_errors = [];
      
      foreach ($batch_data as $index => $item) {
        try {
          $item_name = trim($item['item_name'] ?? '');
          $item_code = trim($item['item_code'] ?? '');
          $category = trim($item['category'] ?? '');
          $unit_price = floatval($item['unit_price'] ?? 0);
          $cost_price = floatval($item['cost_price'] ?? 0);
          $current_stock = intval($item['current_stock'] ?? 0);
          $minimum_stock = intval($item['minimum_stock'] ?? 10);
          
          if (empty($item_name) || empty($item_code)) continue; // Skip empty rows
          
          // Check duplicate
          $stmt = $conn->prepare("SELECT id FROM stationery_items WHERE item_code = ? AND user_id = ?");
          $stmt->execute([$item_code, $user_id]);
          if ($stmt->fetch()) {
            $batch_errors[] = "Row " . ($index + 1) . ": Duplicate code '$item_code'";
            continue;
          }
          
          // Insert
          $stmt = $conn->prepare("INSERT INTO stationery_items 
            (user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
            VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, 1)");
          
          $stmt->execute([$user_id, $item_name, $item_code, $category, $unit_price, $cost_price, $current_stock, $minimum_stock]);
          $added_count++;
          
        } catch (Exception $e) {
          $batch_errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
        }
      }
      
      if ($added_count > 0) {
        $success_msg = "✅ Successfully added $added_count products!";
      }
      if (!empty($batch_errors)) {
        $error_msg = "Some errors occurred:<br>" . implode("<br>", $batch_errors);
      }
      
    } elseif ($action === 'csv_import') {
      // CSV import
      if (empty($_FILES['csv_file']['tmp_name'])) {
        throw new Exception("Please select a CSV file");
      }
      
      $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
      $header = fgetcsv($file); // Skip header row
      $row_num = 1;
      
      while (($data = fgetcsv($file)) !== false) {
        $row_num++;
        try {
          // CSV format: item_name, item_code, category, unit_price, cost_price, current_stock, minimum_stock
          if (count($data) < 4) continue; // Skip incomplete rows
          
          $item_name = trim($data[0] ?? '');
          $item_code = trim($data[1] ?? '');
          $category = trim($data[2] ?? '');
          $unit_price = floatval($data[3] ?? 0);
          $cost_price = floatval($data[4] ?? 0);
          $current_stock = intval($data[5] ?? 0);
          $minimum_stock = intval($data[6] ?? 10);
          
          if (empty($item_name) || empty($item_code)) continue;
          
          // Check duplicate
          $stmt = $conn->prepare("SELECT id FROM stationery_items WHERE item_code = ? AND user_id = ?");
          $stmt->execute([$item_code, $user_id]);
          if ($stmt->fetch()) {
            $csv_errors[] = "Row $row_num: Duplicate code '$item_code'";
            continue;
          }
          
          // Insert
          $stmt = $conn->prepare("INSERT INTO stationery_items 
            (user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
            VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, 1)");
          
          $stmt->execute([$user_id, $item_name, $item_code, $category, $unit_price, $cost_price, $current_stock, $minimum_stock]);
          $csv_success_count++;
          
        } catch (Exception $e) {
          $csv_errors[] = "Row $row_num: " . $e->getMessage();
        }
      }
      
      fclose($file);
      
      if ($csv_success_count > 0) {
        $success_msg = "✅ Successfully imported $csv_success_count products from CSV!";
      }
      if (!empty($csv_errors)) {
        $error_msg = "Import completed with some errors:<br>" . implode("<br>", array_slice($csv_errors, 0, 10));
        if (count($csv_errors) > 10) {
          $error_msg .= "<br>... and " . (count($csv_errors) - 10) . " more errors";
        }
      }
    }
    
  } catch (Exception $e) {
    $error_msg = "❌ Error: " . $e->getMessage();
  }
}

// Get existing categories for dropdown
$categories = [];
try {
  $stmt = $conn->prepare("SELECT DISTINCT category FROM stationery_items WHERE user_id = ? ORDER BY category");
  $stmt->execute([$user_id]);
  $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<style>
.inventory-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
  position: relative;
  overflow: hidden;
}
.inventory-header::before {
  content: '📦';
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 8rem;
  opacity: 0.1;
}
.inventory-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 12px 0;
  color: white;
}
.inventory-header p {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
}
.tabs {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
  border-bottom: 2px solid var(--border-weak);
  padding-bottom: 0;
}
.tab {
  padding: 16px 32px;
  background: transparent;
  border: none;
  color: var(--muted);
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  position: relative;
  transition: all 0.3s;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
}
.tab:hover {
  color: var(--card-text);
  background: rgba(var(--card-text-rgb), 0.03);
}
.tab.active {
  color: #6366f1;
  border-bottom-color: #6366f1;
}
.tab-content {
  display: none;
}
.tab-content.active {
  display: block;
}
.form-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 24px;
}
.form-card h3 {
  margin: 0 0 24px 0;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--card-text);
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}
.form-group {
  margin-bottom: 20px;
}
.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--card-text);
  font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--border-weak);
  border-radius: 12px;
  background: var(--card-bg);
  color: var(--card-text);
  font-size: 1rem;
  transition: all 0.3s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}
.form-group textarea {
  resize: vertical;
  min-height: 80px;
}
.button {
  padding: 12px 28px;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
}
.button.primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}
.button.primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}
.button.secondary {
  background: rgba(var(--card-text-rgb), 0.08);
  color: var(--card-text);
}
.button.secondary:hover {
  background: rgba(var(--card-text-rgb), 0.12);
}
.button.ghost {
  background: transparent;
  color: var(--card-text);
  border: 2px solid var(--border-weak);
}
.button.ghost:hover {
  border-color: #6366f1;
  color: #6366f1;
}
.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-size: 0.95rem;
  line-height: 1.6;
}
.alert.success {
  background: rgba(46, 213, 115, 0.1);
  border: 1px solid rgba(46, 213, 115, 0.3);
  color: #2ed573;
}
.alert.error {
  background: rgba(245, 87, 108, 0.1);
  border: 1px solid rgba(245, 87, 108, 0.3);
  color: #f5576c;
}
.barcode-scanner {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
  border: 2px dashed #6366f1;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  margin-bottom: 24px;
}
.barcode-scanner-icon {
  font-size: 4rem;
  margin-bottom: 16px;
}
.barcode-input {
  font-size: 1.5rem !important;
  text-align: center;
  font-family: monospace;
  letter-spacing: 4px;
}
#video-container {
  position: relative;
  max-width: 500px;
  margin: 0 auto 20px;
  border-radius: 12px;
  overflow: hidden;
  display: none;
}
#video-preview {
  width: 100%;
  border-radius: 12px;
}
.batch-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}
.batch-table th {
  text-align: left;
  padding: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  border-bottom: 2px solid var(--border-weak);
  font-weight: 600;
  color: var(--muted);
  font-size: 0.85rem;
}
.batch-table td {
  padding: 8px;
  border-bottom: 1px solid var(--border-weak);
}
.batch-table input {
  padding: 8px 12px;
  font-size: 0.9rem;
}
.batch-row {
  background: rgba(var(--card-text-rgb), 0.01);
}
.batch-row:hover {
  background: rgba(var(--card-text-rgb), 0.03);
}
.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}
.template-chip {
  padding: 12px 16px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
  border: 2px solid transparent;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s;
  text-align: center;
  font-weight: 600;
  color: var(--card-text);
}
.template-chip:hover {
  border-color: #6366f1;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
}
.csv-info {
  background: rgba(99, 102, 241, 0.05);
  border: 1px solid rgba(99, 102, 241, 0.2);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}
.csv-info h4 {
  margin: 0 0 12px 0;
  color: #6366f1;
  font-size: 1.1rem;
}
.csv-info code {
  background: rgba(0, 0, 0, 0.05);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.9rem;
}
.file-upload {
  position: relative;
  display: inline-block;
  cursor: pointer;
}
.file-upload input[type="file"] {
  display: none;
}
.file-upload-label {
  padding: 12px 28px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: inline-block;
}
.file-upload-label:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}
.add-row-btn {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
  border: 2px dashed #6366f1;
  padding: 12px 24px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
  margin-bottom: 20px;
}
.add-row-btn:hover {
  background: rgba(99, 102, 241, 0.2);
}
</style>

<div class="inventory-header">
  <h1>📦 Enhanced Inventory Add</h1>
  <p>Add products quickly with barcode scanning, batch entry, or CSV import</p>
</div>

<?php if ($success_msg): ?>
  <div class="alert success"><?= $success_msg ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
  <div class="alert error"><?= $error_msg ?></div>
<?php endif; ?>

<div class="tabs">
  <button class="tab active" onclick="switchTab('quick')">📱 Quick Add</button>
  <button class="tab" onclick="switchTab('batch')">📋 Batch Entry</button>
  <button class="tab" onclick="switchTab('csv')">📂 CSV Import</button>
</div>

<!-- Quick Add Tab -->
<div class="tab-content active" id="quick-tab">
  <div class="form-card">
    <h3>⚡ Quick Add with Barcode Scanner</h3>
    
    <!-- Product Templates -->
    <div style="margin-bottom: 32px;">
      <label style="display:block;margin-bottom:12px;font-weight:600;color:var(--card-text);">🎯 Quick Templates (Click to use)</label>
      <div class="template-grid">
        <?php foreach ($product_templates as $template_name => $template_data): ?>
          <div class="template-chip" onclick="useTemplate('<?= htmlspecialchars($template_name) ?>', <?= htmlspecialchars(json_encode($template_data)) ?>)">
            <?= htmlspecialchars($template_name) ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    
    <!-- Barcode Scanner -->
    <div class="barcode-scanner">
      <div class="barcode-scanner-icon">📷</div>
      <h4 style="margin:0 0 16px 0;color:#6366f1">Barcode Scanner</h4>
      <div style="margin-bottom:20px;">
        <button type="button" class="button primary" onclick="startCamera()" id="start-camera-btn">
          📸 Start Camera
        </button>
        <button type="button" class="button secondary" onclick="stopCamera()" id="stop-camera-btn" style="display:none;">
          ⏹ Stop Camera
        </button>
      </div>
      <div id="video-container">
        <video id="video-preview" autoplay></video>
      </div>
      <p style="margin:16px 0 8px 0;color:var(--muted)">Or enter barcode manually:</p>
      <input type="text" id="barcode-input" class="barcode-input" placeholder="Scan or type barcode" 
             style="max-width:400px;margin:0 auto;display:block;" />
    </div>
    
    <!-- Quick Add Form -->
    <form method="POST" action="">
      <input type="hidden" name="action" value="quick_add">
      
      <div class="form-grid">
        <div class="form-group">
          <label>Item Name *</label>
          <input type="text" name="item_name" id="quick-item-name" required>
        </div>
        
        <div class="form-group">
          <label>Item Code *</label>
          <input type="text" name="item_code" id="quick-item-code" required 
                 placeholder="e.g., ST-001">
        </div>
        
        <div class="form-group">
          <label>Barcode</label>
          <input type="text" name="barcode" id="quick-barcode" placeholder="Optional">
        </div>
        
        <div class="form-group">
          <label>Category *</label>
          <input type="text" name="category" id="quick-category" required list="category-list">
          <datalist id="category-list">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>">
            <?php endforeach; ?>
            <option value="Writing">
            <option value="Paper">
            <option value="Office">
            <option value="Adhesives">
          </datalist>
        </div>
        
        <div class="form-group">
          <label>Unit Price (RWF) *</label>
          <input type="number" name="unit_price" id="quick-unit-price" required min="0" step="0.01">
        </div>
        
        <div class="form-group">
          <label>Cost Price (RWF)</label>
          <input type="number" name="cost_price" id="quick-cost-price" min="0" step="0.01" value="0">
        </div>
        
        <div class="form-group">
          <label>Current Stock</label>
          <input type="number" name="current_stock" min="0" value="0">
        </div>
        
        <div class="form-group">
          <label>Minimum Stock</label>
          <input type="number" name="minimum_stock" id="quick-minimum-stock" min="0" value="10">
        </div>
      </div>
      
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Optional product description"></textarea>
      </div>
      
      <button type="submit" class="button primary">✅ Add Product</button>
      <a href="/MY CASH/employee/manage_inventory.php" class="button ghost">Cancel</a>
    </form>
  </div>
</div>

<!-- Batch Entry Tab -->
<div class="tab-content" id="batch-tab">
  <div class="form-card">
    <h3>📋 Batch Entry - Add Multiple Products</h3>
    <p style="color:var(--muted);margin-bottom:24px;">Fill in the table below to add multiple products at once</p>
    
    <form method="POST" action="">
      <input type="hidden" name="action" value="batch_add">
      
      <table class="batch-table" id="batch-table">
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Item Name *</th>
            <th>Item Code *</th>
            <th>Category *</th>
            <th>Unit Price *</th>
            <th>Cost Price</th>
            <th>Stock</th>
            <th>Min Stock</th>
            <th style="width:50px;"></th>
          </tr>
        </thead>
        <tbody id="batch-tbody">
          <!-- Initial 5 rows -->
          <?php for ($i = 0; $i < 5; $i++): ?>
            <tr class="batch-row">
              <td><?= $i + 1 ?></td>
              <td><input type="text" name="batch[<?= $i ?>][item_name]" placeholder="Product name"></td>
              <td><input type="text" name="batch[<?= $i ?>][item_code]" placeholder="ST-00<?= $i + 1 ?>"></td>
              <td><input type="text" name="batch[<?= $i ?>][category]" placeholder="Category" list="category-list"></td>
              <td><input type="number" name="batch[<?= $i ?>][unit_price]" placeholder="0" min="0" step="0.01"></td>
              <td><input type="number" name="batch[<?= $i ?>][cost_price]" placeholder="0" min="0" step="0.01"></td>
              <td><input type="number" name="batch[<?= $i ?>][current_stock]" placeholder="0" min="0" value="0" style="width:80px;"></td>
              <td><input type="number" name="batch[<?= $i ?>][minimum_stock]" placeholder="10" min="0" value="10" style="width:80px;"></td>
              <td><button type="button" onclick="removeRow(this)" style="background:transparent;border:none;color:#f5576c;cursor:pointer;font-size:1.2rem;">×</button></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
      
      <button type="button" class="add-row-btn" onclick="addBatchRow()">+ Add Another Row</button>
      
      <div style="margin-top:24px;">
        <button type="submit" class="button primary">✅ Add All Products</button>
        <button type="button" class="button secondary" onclick="clearBatchTable()">Clear Table</button>
      </div>
    </form>
  </div>
</div>

<!-- CSV Import Tab -->
<div class="tab-content" id="csv-tab">
  <div class="form-card">
    <h3>📂 CSV Import - Bulk Upload</h3>
    
    <div class="csv-info">
      <h4>📋 CSV Format Requirements</h4>
      <p style="margin-bottom:12px;">Your CSV file must have the following columns in order:</p>
      <ol style="margin:0;padding-left:20px;">
        <li><code>item_name</code> - Product name (required)</li>
        <li><code>item_code</code> - Unique code (required)</li>
        <li><code>category</code> - Product category (required)</li>
        <li><code>unit_price</code> - Selling price (required)</li>
        <li><code>cost_price</code> - Purchase price (optional, default 0)</li>
        <li><code>current_stock</code> - Initial stock (optional, default 0)</li>
        <li><code>minimum_stock</code> - Minimum threshold (optional, default 10)</li>
      </ol>
      <p style="margin-top:16px;margin-bottom:8px;"><strong>Example CSV:</strong></p>
      <pre style="background:rgba(0,0,0,0.05);padding:12px;border-radius:8px;overflow-x:auto;"><code>item_name,item_code,category,unit_price,cost_price,current_stock,minimum_stock
Blue Pen,ST-001,Writing,500,300,50,20
Red Pen,ST-002,Writing,500,300,40,20
Notebook A4,ST-003,Paper,1500,1000,30,10</code></pre>
      <a href="#" onclick="downloadSampleCSV(); return false;" class="button ghost" style="margin-top:12px;">
        ⬇ Download Sample CSV
      </a>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data">
      <input type="hidden" name="action" value="csv_import">
      
      <div class="form-group">
        <label>Select CSV File</label>
        <div class="file-upload">
          <input type="file" name="csv_file" id="csv-file" accept=".csv" required onchange="showFileName(this)">
          <label for="csv-file" class="file-upload-label">
            📁 Choose CSV File
          </label>
          <span id="file-name" style="margin-left:16px;color:var(--muted);"></span>
        </div>
      </div>
      
      <button type="submit" class="button primary">🚀 Import Products</button>
    </form>
  </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
  document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
  
  event.target.classList.add('active');
  document.getElementById(tabName + '-tab').classList.add('active');
}

// Template usage
function useTemplate(name, data) {
  document.getElementById('quick-item-name').value = name;
  document.getElementById('quick-item-code').value = 'ST-' + Date.now().toString().substr(-6);
  document.getElementById('quick-category').value = data.category;
  document.getElementById('quick-unit-price').value = data.unit_price;
  document.getElementById('quick-cost-price').value = data.cost_price;
  document.getElementById('quick-minimum-stock').value = data.minimum_stock;
  
  // Scroll to form
  document.getElementById('quick-item-name').scrollIntoView({behavior: 'smooth', block: 'center'});
  document.getElementById('quick-item-name').focus();
}

// Barcode scanner
let stream = null;
let barcodeInput = document.getElementById('barcode-input');

barcodeInput.addEventListener('input', function() {
  if (this.value.length > 0) {
    document.getElementById('quick-barcode').value = this.value;
    document.getElementById('quick-item-code').value = this.value;
  }
});

async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = document.getElementById('video-preview');
    video.srcObject = stream;
    document.getElementById('video-container').style.display = 'block';
    document.getElementById('start-camera-btn').style.display = 'none';
    document.getElementById('stop-camera-btn').style.display = 'inline-block';
  } catch (err) {
    alert('❌ Camera access denied or not available.\n\nPlease use manual barcode entry instead.');
  }
}

function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    document.getElementById('video-container').style.display = 'none';
    document.getElementById('start-camera-btn').style.display = 'inline-block';
    document.getElementById('stop-camera-btn').style.display = 'none';
  }
}

// Batch entry
let batchRowCount = 5;

function addBatchRow() {
  const tbody = document.getElementById('batch-tbody');
  const newRow = document.createElement('tr');
  newRow.className = 'batch-row';
  newRow.innerHTML = `
    <td>${batchRowCount + 1}</td>
    <td><input type="text" name="batch[${batchRowCount}][item_name]" placeholder="Product name"></td>
    <td><input type="text" name="batch[${batchRowCount}][item_code]" placeholder="ST-00${batchRowCount + 1}"></td>
    <td><input type="text" name="batch[${batchRowCount}][category]" placeholder="Category" list="category-list"></td>
    <td><input type="number" name="batch[${batchRowCount}][unit_price]" placeholder="0" min="0" step="0.01"></td>
    <td><input type="number" name="batch[${batchRowCount}][cost_price]" placeholder="0" min="0" step="0.01"></td>
    <td><input type="number" name="batch[${batchRowCount}][current_stock]" placeholder="0" min="0" value="0" style="width:80px;"></td>
    <td><input type="number" name="batch[${batchRowCount}][minimum_stock]" placeholder="10" min="0" value="10" style="width:80px;"></td>
    <td><button type="button" onclick="removeRow(this)" style="background:transparent;border:none;color:#f5576c;cursor:pointer;font-size:1.2rem;">×</button></td>
  `;
  tbody.appendChild(newRow);
  batchRowCount++;
}

function removeRow(btn) {
  if (confirm('Remove this row?')) {
    btn.closest('tr').remove();
    // Renumber rows
    document.querySelectorAll('#batch-tbody tr').forEach((row, index) => {
      row.querySelector('td:first-child').textContent = index + 1;
    });
  }
}

function clearBatchTable() {
  if (confirm('Clear all batch entries?')) {
    document.getElementById('batch-tbody').querySelectorAll('input').forEach(input => {
      if (input.type === 'number' && (input.name.includes('stock') || input.name.includes('minimum'))) {
        input.value = input.name.includes('minimum') ? '10' : '0';
      } else {
        input.value = '';
      }
    });
  }
}

// CSV functions
function showFileName(input) {
  const fileName = input.files[0]?.name || '';
  document.getElementById('file-name').textContent = fileName ? `Selected: ${fileName}` : '';
}

function downloadSampleCSV() {
  const csvContent = `item_name,item_code,category,unit_price,cost_price,current_stock,minimum_stock
Blue Pen,ST-001,Writing,500,300,50,20
Red Pen,ST-002,Writing,500,300,40,20
Black Pen,ST-003,Writing,500,300,45,20
Notebook A4,ST-004,Paper,1500,1000,30,10
Notebook A5,ST-005,Paper,1000,700,35,10
Stapler,ST-006,Office,3000,2000,15,5
Staples Box,ST-007,Office,800,500,25,15
Glue Stick,ST-008,Adhesives,600,400,20,10
Tape Roll,ST-009,Adhesives,1200,800,18,8
Marker Black,ST-010,Writing,800,500,22,12`;
  
  const blob = new Blob([csvContent], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'inventory_sample.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
