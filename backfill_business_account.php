<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

$user_id = $_SESSION['user_id'];

echo "<h2>Backfill Business Account</h2>";
echo "<p>This will allocate all existing sales to your Business Account</p>";
echo "<hr>";

try {
    $conn->beginTransaction();
    
    // Step 1: Check if Business Account exists
    echo "<h3>Step 1: Check Business Account</h3>";
    $accStmt = $conn->prepare("SELECT id FROM accounts WHERE user_id = ? AND name = 'Business Account' LIMIT 1");
    $accStmt->execute([$user_id]);
    $businessAccount = $accStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$businessAccount) {
        echo "<p>Creating Business Account...</p>";
        $createAcc = $conn->prepare("INSERT INTO accounts (user_id, name, type, balance, currency) VALUES (?, 'Business Account', 'Business', 0, 'RWF')");
        $createAcc->execute([$user_id]);
        $business_account_id = $conn->lastInsertId();
        echo "<p style='color: green;'>✅ Business Account created with ID: $business_account_id</p>";
    } else {
        $business_account_id = $businessAccount['id'];
        echo "<p style='color: green;'>✅ Business Account exists with ID: $business_account_id</p>";
    }
    
    // Step 2: Get all sales that haven't been allocated
    echo "<h3>Step 2: Find Existing Sales</h3>";
    $salesStmt = $conn->prepare("
        SELECT et.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employee_tasks et
        LEFT JOIN employees e ON et.employee_id = e.id
        WHERE et.user_id = ? AND et.transaction_type = 'sale'
        ORDER BY et.task_date ASC, et.created_at ASC
    ");
    $salesStmt->execute([$user_id]);
    $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found <strong>" . count($sales) . "</strong> sales to allocate</p>";
    
    if (count($sales) == 0) {
        echo "<p style='color: orange;'>No sales to allocate. Business Account is up to date!</p>";
        $conn->rollBack();
        echo "<p><a href='/MY CASH/pages/accounts.php'>← Back to Accounts</a></p>";
        exit;
    }
    
    // Step 3: Create transactions for each sale
    echo "<h3>Step 3: Creating Transactions</h3>";
    $totalAllocated = 0;
    $transCount = 0;
    
    echo "<table border='1' cellpadding='5' style='margin-top: 10px;'>";
    echo "<tr><th>#</th><th>Date</th><th>Employee</th><th>Amount</th><th>Payment</th><th>Customer</th></tr>";
    
    $transStmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, notes, created_at) VALUES (?, 'deposit', ?, ?, ?)");
    
    foreach ($sales as $sale) {
        $employee_name = $sale['employee_name'] ?: 'Unknown';
        $customer_name = $sale['customer_name'] ?: 'Walk-in';
        $payment_method = ucwords(str_replace('_', ' ', $sale['payment_method']));
        
        $transNotes = "Sale by " . $employee_name . " - Customer: " . $customer_name . " - Payment: " . $payment_method;
        
        // Use the original sale date as transaction created_at for historical accuracy
        $sale_datetime = $sale['task_date'] . ' ' . ($sale['created_at'] ? date('H:i:s', strtotime($sale['created_at'])) : '12:00:00');
        
        $transStmt->execute([
            $business_account_id,
            $sale['total_amount'],
            $transNotes,
            $sale_datetime
        ]);
        
        $totalAllocated += $sale['total_amount'];
        $transCount++;
        
        echo "<tr>";
        echo "<td>$transCount</td>";
        echo "<td>{$sale['task_date']}</td>";
        echo "<td>" . htmlspecialchars($employee_name) . "</td>";
        echo "<td><strong>RWF " . number_format($sale['total_amount'], 2) . "</strong></td>";
        echo "<td>{$payment_method}</td>";
        echo "<td>" . htmlspecialchars($customer_name) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Step 4: Update Business Account balance
    echo "<h3>Step 4: Update Business Account Balance</h3>";
    $balStmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
    $balStmt->execute([$totalAllocated, $business_account_id]);
    
    echo "<p style='color: green;'><strong>✅ Success!</strong></p>";
    echo "<p>Created <strong>$transCount</strong> transactions</p>";
    echo "<p>Total allocated: <strong>RWF " . number_format($totalAllocated, 2) . "</strong></p>";
    echo "<p>Business Account balance updated to: <strong>RWF " . number_format($totalAllocated, 2) . "</strong></p>";
    
    $conn->commit();
    
    echo "<hr>";
    echo "<h3>🎉 Backfill Complete!</h3>";
    echo "<p>All existing sales have been allocated to your Business Account.</p>";
    echo "<p><a href='/MY CASH/pages/accounts.php' style='padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>View Business Account →</a></p>";
    echo "<p><a href='/MY CASH/debug_business_account.php'>Run Debug Again</a></p>";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>No changes were made to the database.</p>";
}
?>
