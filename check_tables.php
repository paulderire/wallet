<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

echo "<h2>Database Table Check</h2>";
echo "<hr>";

try {
    // Check if transactions table exists
    echo "<h3>Checking 'transactions' table...</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'transactions'");
    
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✅ 'transactions' table EXISTS</p>";
        
        // Check structure
        echo "<h4>Table Structure:</h4>";
        $columns = $conn->query("DESCRIBE transactions");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>❌ 'transactions' table DOES NOT EXIST</p>";
        echo "<p>The table needs to be created. Click below to create it:</p>";
        echo "<form method='post'>";
        echo "<button type='submit' name='create_table' style='padding: 10px 20px; background: green; color: white; border: none; border-radius: 5px; cursor: pointer;'>Create Transactions Table</button>";
        echo "</form>";
    }
    
    // Check accounts table
    echo "<hr>";
    echo "<h3>Checking 'accounts' table...</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'accounts'");
    
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✅ 'accounts' table EXISTS</p>";
        
        // Check if balance column exists
        $columns = $conn->query("DESCRIBE accounts");
        $has_balance = false;
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            if ($col['Field'] === 'balance') {
                $has_balance = true;
            }
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if (!$has_balance) {
            echo "<p style='color: orange;'>⚠️ 'balance' column missing</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ 'accounts' table DOES NOT EXIST</p>";
    }
    
    // Handle table creation
    if (isset($_POST['create_table'])) {
        echo "<hr>";
        echo "<h3>Creating Tables...</h3>";
        
        // Create transactions table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS transactions (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              account_id INT UNSIGNED NOT NULL,
              type ENUM('deposit','withdraw') NOT NULL,
              amount DECIMAL(15,2) NOT NULL,
              notes TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p style='color: green;'>✅ Transactions table created successfully!</p>";
        
        // Ensure balance column exists
        try {
            $conn->exec("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            echo "<p style='color: green;'>✅ Accounts table updated with balance column!</p>";
        } catch (Exception $e) {
            // Column might already exist
        }
        
        echo "<p><a href='/MY CASH/backfill_business_account.php' style='padding: 10px 20px; background: blue; color: white; text-decoration: none; border-radius: 5px;'>Proceed to Backfill →</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/MY CASH/debug_business_account.php'>← Back to Debug</a></p>";
?>
