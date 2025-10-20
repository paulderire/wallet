<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';
include __DIR__ . '/../includes/inventory.php'; // Auto stock deduction

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['employee_role'] ?? '';
$user_id = $_SESSION['employee_user_id'] ?? 0;

$success_msg = '';
$error_msg = '';
$stock_alerts = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $items_sold = trim($_POST['items_sold'] ?? '');
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $transaction_type = trim($_POST['transaction_type'] ?? 'sale');
    $notes = trim($_POST['notes'] ?? '');
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity_sold = intval($_POST['quantity_sold'] ?? 1);
    
    // Validation
    if (empty($customer_name) && $transaction_type === 'sale') {
      throw new Exception("Customer name is required for sales");
    }
    
    if (empty($items_sold)) {
      throw new Exception("Please describe the items sold or transaction details");
    }
    
    if ($total_amount <= 0) {
      throw new Exception("Total amount must be greater than zero");
    }
    
    if (!in_array($payment_method, ['cash', 'mobile_money', 'bank_transfer', 'credit'])) {
      throw new Exception("Invalid payment method");
    }
    
    if (!in_array($transaction_type, ['sale', 'expense', 'stock_alert', 'other'])) {
      throw new Exception("Invalid transaction type");
    }
    
    // Generate transaction title
    $title = ucfirst($transaction_type);
    if ($transaction_type === 'sale') {
      $title = "Sale to " . $customer_name;
    } elseif ($transaction_type === 'expense') {
      $title = "Expense: " . $items_sold;
    }
    
    // Get current date and time
    $task_date = date('Y-m-d');
    
    // Insert into employee_tasks
    $stmt = $conn->prepare("INSERT INTO employee_tasks 
      (employee_id, user_id, task_date, title, description, category, status, 
       customer_name, items_sold, total_amount, payment_method, transaction_type)
      VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?)");
    
    $stmt->execute([
      $employee_id,
      $user_id,
      $task_date,
      $title,
      $notes,
      'Sales Transaction',
      $customer_name,
      $items_sold,
      $total_amount,
      $payment_method,
      $transaction_type
    ]);
    
    // Update or create daily sales summary
    $stmt = $conn->prepare("INSERT INTO daily_sales_summary 
      (employee_id, user_id, sale_date, total_sales, total_cash, total_mobile_money, total_bank_transfer, total_credit, total_transactions)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
      ON DUPLICATE KEY UPDATE
        total_sales = total_sales + VALUES(total_sales),
        total_cash = total_cash + VALUES(total_cash),
        total_mobile_money = total_mobile_money + VALUES(total_mobile_money),
        total_bank_transfer = total_bank_transfer + VALUES(total_bank_transfer),
        total_credit = total_credit + VALUES(total_credit),
        total_transactions = total_transactions + 1");
    
    $cash = $payment_method === 'cash' ? $total_amount : 0;
    $mobile = $payment_method === 'mobile_money' ? $total_amount : 0;
    $bank = $payment_method === 'bank_transfer' ? $total_amount : 0;
    $credit = $payment_method === 'credit' ? $total_amount : 0;
    
    $stmt->execute([
      $employee_id,
      $user_id,
      $task_date,
      $total_amount,
      $cash,
      $mobile,
      $bank,
      $credit
    ]);
    
    // Automatically allocate sales to Business Account
    try {
      // Check if Business Account exists for this user
      $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Business Account' LIMIT 1");
      $accStmt->execute([$user_id]);
      $businessAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
      
      // If Business Account doesn't exist, create it
      if (!$businessAccount) {
        $createAcc = $conn->prepare("INSERT INTO accounts (user_id, name, type, balance, currency) VALUES (?, 'Business Account', 'Business', 0, 'RWF')");
        $createAcc->execute([$user_id]);
        $business_account_id = $conn->lastInsertId();
      } else {
        $business_account_id = $businessAccount['id'];
      }
      
      // Add deposit transaction to Business Account
      $transStmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, notes) VALUES (?, 'deposit', ?, ?)");
      $transNotes = "Sale by " . $employee_name . " - Customer: " . ($customer_name ?: 'Walk-in') . " - Payment: " . ucwords(str_replace('_', ' ', $payment_method));
      $transStmt->execute([$business_account_id, $total_amount, $transNotes]);
      
      // Update account balance
      $balStmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
      $balStmt->execute([$total_amount, $business_account_id]);
      
    } catch (Exception $e) {
      // Log error but don't stop the sale
      error_log("Business account allocation error: " . $e->getMessage());
    }
    
    // Auto-deduct stock if item_id is provided and transaction is a sale
    if ($transaction_type === 'sale' && $item_id > 0 && $quantity_sold > 0) {
      $transaction_id = $conn->lastInsertId(); // Get the transaction ID we just created
      $deduct_result = deductStock($conn, $item_id, $quantity_sold, $transaction_id, $employee_id, "Sale to " . $customer_name);
      
      if ($deduct_result['success']) {
        $stock_alerts[] = "✅ Stock deducted: " . $quantity_sold . " units. New stock: " . $deduct_result['new_stock'];
        
        if ($deduct_result['is_low_now'] && !$deduct_result['was_low_before']) {
          $stock_alerts[] = "⚠️ LOW STOCK ALERT: This item is now below minimum stock level!";
        } elseif ($deduct_result['new_stock'] == 0) {
          $stock_alerts[] = "🚨 OUT OF STOCK: This item is now completely out of stock!";
        }
      } else {
        $stock_alerts[] = "⚠️ Stock deduction failed: " . $deduct_result['message'];
      }
    }
    
    $success_msg = "Transaction recorded successfully! Total: RWF " . number_format($total_amount, 0) . " ($" . number_format(rwf_to_usd($total_amount), 2) . ") - Added to Business Account";
    if (!empty($stock_alerts)) {
      $success_msg .= "<br><br><strong>Inventory Updates:</strong><br>" . implode("<br>", $stock_alerts);
    }
    
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Record Transaction - Stationery Business</title>
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
      max-width: 900px;
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
    
    .btn-secondary {
      background: rgba(102, 126, 234, 0.1);
      color: #667eea;
      border: 2px solid rgba(102, 126, 234, 0.2);
    }
    
    .btn-secondary:hover {
      background: rgba(102, 126, 234, 0.2);
      transform: translateY(-2px);
    }
    
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
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
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      padding: 14px 28px;
      font-size: 16px;
      margin-top: 8px;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
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
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
      border-color: #667eea;
      background: #eef2ff;
    }
    
    .item-name {
      font-weight: 600;
      color: #1a202c;
      display: block;
    }
    
    .item-price {
      color: #10b981;
      font-size: 11px;
      font-weight: 700;
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
        <h1 class="header-title">💰 Record Transaction</h1>
        <p class="header-subtitle">Record sales, expenses, and other transactions</p>
      </div>
      <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">
        ← Back to Dashboard
      </a>
    </div>
    
    <!-- Form Card -->
    <div class="card">
      <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
      <?php endif; ?>
      
      <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="">
        <!-- Hidden fields for stock tracking -->
        <input type="hidden" name="item_id" id="item_id" value="0">
        <input type="hidden" name="quantity_sold" id="quantity_sold" value="1">
        
        <div class="form-grid">
          <!-- Transaction Type -->
          <div class="form-group">
            <label class="form-label">
              Transaction Type <span class="required">*</span>
            </label>
            <select name="transaction_type" class="form-select" required onchange="toggleCustomerField(this.value)">
              <option value="sale">💵 Sale</option>
              <option value="expense">💸 Expense</option>
              <option value="other">📋 Other</option>
            </select>
          </div>
          
          <!-- Payment Method -->
          <div class="form-group">
            <label class="form-label">
              Payment Method <span class="required">*</span>
            </label>
            <select name="payment_method" class="form-select" required>
              <option value="cash">💵 Cash</option>
              <option value="mobile_money">📱 Mobile Money</option>
              <option value="bank_transfer">🏦 Bank Transfer</option>
              <option value="credit">💳 Credit</option>
            </select>
          </div>
          
          <!-- Customer Name -->
          <div class="form-group" id="customer_group">
            <label class="form-label">
              Customer Name <span class="required">*</span>
            </label>
            <input 
              type="text" 
              name="customer_name" 
              class="form-input" 
              placeholder="e.g., John Doe"
              value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
            >
          </div>
          
          <!-- Total Amount -->
          <div class="form-group">
            <label class="form-label">
              Total Amount (RWF) <span class="required">*</span>
            </label>
            <input 
              type="number" 
              name="total_amount" 
              class="form-input" 
              min="0" 
              step="1" 
              required
              placeholder="e.g., 5000"
              value="<?php echo htmlspecialchars($_POST['total_amount'] ?? ''); ?>"
            >
          </div>
          
          <!-- Items Sold -->
          <div class="form-group full-width">
            <label class="form-label">
              Items / Transaction Details <span class="required">*</span>
            </label>
            <textarea 
              name="items_sold" 
              class="form-textarea" 
              required
              placeholder="e.g., 2x A4 Paper, 5x Blue Pens, 1x Calculator"
            ><?php echo htmlspecialchars($_POST['items_sold'] ?? ''); ?></textarea>
            
              <?php if (!empty($stationery_items)): ?>
              <div class="items-helper">
                <div class="items-helper-title">Quick Add Items</div>
                <div class="items-grid">
                  <?php foreach ($stationery_items as $item): ?>
                    <div class="item-chip" onclick="addItemToList('<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['unit_price']; ?>, <?php echo $item['id']; ?>, <?php echo $item['current_stock']; ?>)">
                      <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                      <span class="item-price">RWF <?php echo number_format($item['unit_price'], 0); ?> (Stock: <?php echo $item['current_stock']; ?>)</span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Notes -->
          <div class="form-group full-width">
            <label class="form-label">
              Additional Notes
            </label>
            <textarea 
              name="notes" 
              class="form-textarea" 
              style="min-height: 80px;"
              placeholder="Any additional information about this transaction..."
            ><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
          💾 Record Transaction
        </button>
      </form>
    </div>
  </div>
  
  <script>
    function toggleCustomerField(transactionType) {
      const customerGroup = document.getElementById('customer_group');
      const customerInput = customerGroup.querySelector('input');
      
      if (transactionType === 'sale') {
        customerGroup.style.display = 'flex';
        customerInput.required = true;
      } else {
        customerGroup.style.display = 'none';
        customerInput.required = false;
        customerInput.value = '';
      }
    }
    
    function addItemToList(itemName, itemPrice, itemId, currentStock) {
      const textarea = document.querySelector('textarea[name="items_sold"]');
      const currentValue = textarea.value.trim();
      
      // Check stock availability
      if (currentStock <= 0) {
        alert('⚠️ OUT OF STOCK: ' + itemName + '\nPlease restock before selling.');
        return;
      }
      
      // Add item to list
      if (currentValue) {
        textarea.value = currentValue + ', 1x ' + itemName;
      } else {
        textarea.value = '1x ' + itemName;
      }
      
      // Update total amount
      const totalInput = document.querySelector('input[name="total_amount"]');
      const currentTotal = parseFloat(totalInput.value) || 0;
      totalInput.value = currentTotal + itemPrice;
      
      // Set item ID and quantity for auto stock deduction
      document.getElementById('item_id').value = itemId;
      document.getElementById('quantity_sold').value = 1;
      
      // Show stock warning if low
      if (currentStock <= 5) {
        alert('⚠️ LOW STOCK WARNING: ' + itemName + '\nOnly ' + currentStock + ' units remaining!');
      }
      
      // Focus on textarea
      textarea.focus();
    }
  </script>
</body>
</html>
