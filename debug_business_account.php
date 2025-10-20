<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

$user_id = $_SESSION['user_id'];

echo "<h2>Business Account Debug</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<hr>";

// Check Business Account
echo "<h3>1. Business Account Check</h3>";
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND name = 'Business Account'");
$stmt->execute([$user_id]);
$businessAcc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($businessAcc) {
    echo "<p style='color: green;'>✅ Business Account EXISTS</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Balance</th><th>Currency</th></tr>";
    echo "<tr>";
    echo "<td>{$businessAcc['id']}</td>";
    echo "<td>{$businessAcc['name']}</td>";
    echo "<td>{$businessAcc['type']}</td>";
    echo "<td><strong>" . number_format($businessAcc['balance'], 2) . "</strong></td>";
    echo "<td>{$businessAcc['currency']}</td>";
    echo "</tr>";
    echo "</table>";
    
    // Check transactions
    echo "<h3>2. Transactions in Business Account</h3>";
    $transStmt = $conn->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 10");
    $transStmt->execute([$businessAcc['id']]);
    $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($transactions) > 0) {
        echo "<p>Found " . count($transactions) . " transactions</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Type</th><th>Amount</th><th>Notes</th><th>Created</th></tr>";
        foreach ($transactions as $trans) {
            echo "<tr>";
            echo "<td>{$trans['id']}</td>";
            echo "<td>{$trans['type']}</td>";
            echo "<td><strong>" . number_format($trans['amount'], 2) . "</strong></td>";
            echo "<td>" . htmlspecialchars($trans['notes']) . "</td>";
            echo "<td>{$trans['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>⚠️ No transactions found in Business Account</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Business Account DOES NOT EXIST</p>";
}

// Check employee sales
echo "<h3>3. Employee Sales Records</h3>";
$salesStmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total_amount) as total FROM employee_tasks WHERE user_id = ? AND transaction_type = 'sale'");
$salesStmt->execute([$user_id]);
$salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Total Sales:</strong> {$salesData['count']} records</p>";
echo "<p><strong>Total Amount:</strong> RWF " . number_format($salesData['total'], 2) . "</p>";

if ($salesData['count'] > 0) {
    echo "<h4>Recent Sales (Last 5):</h4>";
    $recentStmt = $conn->prepare("SELECT * FROM employee_tasks WHERE user_id = ? AND transaction_type = 'sale' ORDER BY task_date DESC, created_at DESC LIMIT 5");
    $recentStmt->execute([$user_id]);
    $recentSales = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Employee ID</th><th>Date</th><th>Amount</th><th>Payment</th><th>Customer</th></tr>";
    foreach ($recentSales as $sale) {
        echo "<tr>";
        echo "<td>{$sale['id']}</td>";
        echo "<td>{$sale['employee_id']}</td>";
        echo "<td>{$sale['task_date']}</td>";
        echo "<td><strong>" . number_format($sale['total_amount'], 2) . "</strong></td>";
        echo "<td>{$sale['payment_method']}</td>";
        echo "<td>" . htmlspecialchars($sale['customer_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Solution
echo "<hr>";
echo "<h3>🔧 Solution</h3>";

if (!$businessAcc) {
    echo "<p>Business Account doesn't exist yet. It will be created on the next sale.</p>";
} else if (count($transactions) == 0 && $salesData['count'] > 0) {
    echo "<p style='color: orange;'><strong>Issue Found:</strong> Sales exist but weren't allocated to Business Account.</p>";
    echo "<p>This is because the sales were recorded BEFORE the auto-allocation feature was implemented.</p>";
    echo "<h4>Options:</h4>";
    echo "<ol>";
    echo "<li><strong>Create New Sale:</strong> Have employee record a new sale to test auto-allocation</li>";
    echo "<li><strong>Backfill Data:</strong> <a href='/MY CASH/backfill_business_account.php' style='color: blue;'>Click here to allocate existing sales to Business Account</a></li>";
    echo "</ol>";
} else if ($businessAcc['balance'] == 0 && count($transactions) > 0) {
    echo "<p style='color: red;'><strong>Issue:</strong> Transactions exist but balance is 0. Database sync issue.</p>";
    echo "<p><a href='/MY CASH/fix_business_balance.php' style='color: blue;'>Click here to recalculate balance</a></p>";
} else if ($businessAcc['balance'] > 0) {
    echo "<p style='color: green;'>✅ Everything looks good! Business Account is working correctly.</p>";
}

echo "<hr>";
echo "<p><a href='/MY CASH/pages/accounts.php'>← Back to Accounts</a></p>";
?>
