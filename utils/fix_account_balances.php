<?php
/**
 * Utility to recalculate account balances from transactions
 * Run this file directly in your browser to fix account balance discrepancies
 * URL: http://localhost/MY CASH/utils/fix_account_balances.php
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ 
    die('Please login first: <a href="/MY CASH/pages/login.php">Login</a>'); 
}

include __DIR__ . '/../includes/db.php';
$user_id = $_SESSION['user_id'];

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Account Balances</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:1000px;margin:40px auto;padding:20px}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0}";
echo "th,td{padding:12px;text-align:left;border:1px solid #ddd}";
echo "th{background:#667eea;color:white}";
echo ".success{color:#2ed573;font-weight:bold}";
echo ".warning{color:#ffa500;font-weight:bold}";
echo ".error{color:#f5576c;font-weight:bold}";
echo "</style></head><body>";

echo "<h1>🔧 Account Balance Recalculation Utility</h1>";
echo "<p>This will recalculate all account balances based on their transactions.</p>";

try {
    // Get all accounts for this user
    $stmt = $conn->prepare("SELECT id, name, balance FROM accounts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "<p class='warning'>⚠️ No accounts found for your user ID: $user_id</p>";
        echo "</body></html>";
        exit;
    }
    
    echo "<h3>Processing " . count($accounts) . " accounts...</h3>";
    echo "<table>";
    echo "<tr><th>Account</th><th>Old Balance</th><th>Calculated Balance</th><th>Difference</th><th>Status</th></tr>";
    
    $conn->beginTransaction();
    $totalFixed = 0;
    
    foreach ($accounts as $account) {
        $account_id = $account['id'];
        $account_name = htmlspecialchars($account['name']);
        $old_balance = floatval($account['balance'] ?? 0);
        
        // Calculate balance from transactions
        $txStmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN LOWER(type) = 'deposit' THEN amount ELSE 0 END) as deposits,
                SUM(CASE WHEN LOWER(type) IN ('withdraw', 'withdrawal') THEN amount ELSE 0 END) as withdrawals
            FROM transactions 
            WHERE account_id = ?
        ");
        $txStmt->execute([$account_id]);
        $result = $txStmt->fetch(PDO::FETCH_ASSOC);
        
        $deposits = floatval($result['deposits'] ?? 0);
        $withdrawals = floatval($result['withdrawals'] ?? 0);
        $calculated_balance = $deposits - $withdrawals;
        
        $difference = $calculated_balance - $old_balance;
        
        // Update the account balance
        $updateStmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $updateStmt->execute([$calculated_balance, $account_id]);
        
        if (abs($difference) > 0.01) {
            $totalFixed++;
            $status = "<span class='success'>✓ FIXED</span>";
            $diff_color = "class='warning'";
        } else {
            $status = "<span class='success'>✓ OK</span>";
            $diff_color = "class='success'";
        }
        
        echo "<tr>";
        echo "<td><strong>$account_name</strong></td>";
        echo "<td>" . number_format($old_balance, 0) . " RWF</td>";
        echo "<td>" . number_format($calculated_balance, 0) . " RWF</td>";
        echo "<td $diff_color>" . number_format($difference, 0) . " RWF</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    $conn->commit();
    
    echo "</table>";
    
    if ($totalFixed > 0) {
        echo "<h3 class='success'>✓ Fixed $totalFixed account(s) successfully!</h3>";
    } else {
        echo "<h3 class='success'>✓ All account balances were already correct!</h3>";
    }
    
    echo "<p><a href='/MY CASH/pages/dashboard.php' style='display:inline-block;padding:12px 24px;background:#667eea;color:white;text-decoration:none;border-radius:8px;margin-top:20px'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h3 class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}

echo "</body></html>";
?>
