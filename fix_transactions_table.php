<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

echo "<h2>Fix Transactions Table</h2>";
echo "<p>Adding missing columns to transactions table...</p>";
echo "<hr>";

try {
    // Check current structure
    echo "<h3>Current Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE transactions");
    $existing_columns = [];
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $col['Field'];
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Adding Missing Columns...</h3>";
    $changes_made = false;
    
    // Add 'type' column if missing
    if (!in_array('type', $existing_columns)) {
        echo "<p>Adding 'type' column...</p>";
        $conn->exec("ALTER TABLE transactions ADD COLUMN type ENUM('deposit','withdraw') NOT NULL DEFAULT 'deposit' AFTER account_id");
        echo "<p style='color: green;'>✅ Added 'type' column</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: green;'>✅ 'type' column already exists</p>";
    }
    
    // Add 'amount' column if missing
    if (!in_array('amount', $existing_columns)) {
        echo "<p>Adding 'amount' column...</p>";
        $conn->exec("ALTER TABLE transactions ADD COLUMN amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER type");
        echo "<p style='color: green;'>✅ Added 'amount' column</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: green;'>✅ 'amount' column already exists</p>";
    }
    
    // Add 'notes' column if missing
    if (!in_array('notes', $existing_columns)) {
        echo "<p>Adding 'notes' column...</p>";
        $conn->exec("ALTER TABLE transactions ADD COLUMN notes TEXT NULL AFTER amount");
        echo "<p style='color: green;'>✅ Added 'notes' column</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: green;'>✅ 'notes' column already exists</p>";
    }
    
    // Add 'created_at' column if missing
    if (!in_array('created_at', $existing_columns)) {
        echo "<p>Adding 'created_at' column...</p>";
        $conn->exec("ALTER TABLE transactions ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER notes");
        echo "<p style='color: green;'>✅ Added 'created_at' column</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: green;'>✅ 'created_at' column already exists</p>";
    }
    
    // Show updated structure
    echo "<hr>";
    echo "<h3>Updated Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE transactions");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($changes_made) {
        echo "<hr>";
        echo "<h3 style='color: green;'>🎉 Table Fixed Successfully!</h3>";
        echo "<p>The transactions table now has all required columns.</p>";
    } else {
        echo "<hr>";
        echo "<h3 style='color: green;'>✅ Table Already Correct!</h3>";
        echo "<p>All required columns already exist.</p>";
    }
    
    echo "<p><a href='/MY CASH/backfill_business_account.php' style='padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>Proceed to Backfill Business Account →</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/MY CASH/check_tables.php'>← Back to Table Check</a></p>";
?>
