<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

echo "<h2>Check Accounts Table Structure</h2>";
echo "<hr>";

try {
    // Check accounts table structure
    echo "<h3>Accounts Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE accounts");
    $existing_columns = [];
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $col['Field'];
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>Required Columns Check:</h3>";
    $required = ['id', 'user_id', 'name', 'type', 'balance', 'currency'];
    $missing = [];
    
    foreach ($required as $col) {
        if (in_array($col, $existing_columns)) {
            echo "<p style='color: green;'>✅ '$col' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ '$col' is MISSING</p>";
            $missing[] = $col;
        }
    }
    
    if (count($missing) > 0) {
        echo "<hr>";
        echo "<h3>Fix Missing Columns</h3>";
        echo "<form method='post'>";
        echo "<button type='submit' name='fix_columns' style='padding: 10px 20px; background: green; color: white; border: none; border-radius: 5px; cursor: pointer;'>Add Missing Columns</button>";
        echo "</form>";
    } else {
        echo "<hr>";
        echo "<p style='color: green;'><strong>✅ All required columns exist!</strong></p>";
        echo "<p><a href='/MY CASH/backfill_business_account.php' style='padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>Proceed to Backfill →</a></p>";
    }
    
    // Handle fixing columns
    if (isset($_POST['fix_columns'])) {
        echo "<hr>";
        echo "<h3>Adding Missing Columns...</h3>";
        
        if (!in_array('type', $existing_columns)) {
            $conn->exec("ALTER TABLE accounts ADD COLUMN type VARCHAR(50) DEFAULT 'Savings' AFTER name");
            echo "<p style='color: green;'>✅ Added 'type' column</p>";
        }
        
        if (!in_array('balance', $existing_columns)) {
            $conn->exec("ALTER TABLE accounts ADD COLUMN balance DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER type");
            echo "<p style='color: green;'>✅ Added 'balance' column</p>";
        }
        
        if (!in_array('currency', $existing_columns)) {
            $conn->exec("ALTER TABLE accounts ADD COLUMN currency VARCHAR(3) DEFAULT 'RWF' AFTER balance");
            echo "<p style='color: green;'>✅ Added 'currency' column</p>";
        }
        
        echo "<p style='color: green;'><strong>🎉 Columns added successfully!</strong></p>";
        echo "<p><a href='/MY CASH/backfill_business_account.php' style='padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>Proceed to Backfill →</a></p>";
        echo "<p><a href='/MY CASH/check_accounts_table.php'>Refresh this page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/MY CASH/debug_business_account.php'>← Back to Debug</a></p>";
?>
