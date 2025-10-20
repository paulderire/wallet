<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login</a> first");
}
include __DIR__ . '/includes/db.php';

echo "<h2>Setup Forex Trading System</h2>";
echo "<hr>";

try {
    // Create forex_trades table
    echo "<h3>Creating forex_trades table...</h3>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS forex_trades (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          trade_date DATE NOT NULL,
          trade_time TIME NOT NULL,
          pair VARCHAR(10) NOT NULL,
          type ENUM('buy','sell') NOT NULL,
          entry_price DECIMAL(10,5) NOT NULL,
          exit_price DECIMAL(10,5) NOT NULL,
          lot_size DECIMAL(10,2) NOT NULL,
          profit_loss DECIMAL(15,2) NOT NULL,
          notes TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX idx_user_date (user_id, trade_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p style='color: green;'>✅ forex_trades table created successfully!</p>";
    
    // Check if table was created
    $result = $conn->query("DESCRIBE forex_trades");
    echo "<h4>Table Structure:</h4>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($col = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>🎉 Forex Trading System Ready!</h3>";
    echo "<p><strong>What's included:</strong></p>";
    echo "<ul>";
    echo "<li>📊 <strong>Forex Journal</strong> - Track all your forex trades with detailed statistics</li>";
    echo "<li>💰 <strong>Forex Account</strong> - Automatically syncs balance from all trade P/L</li>";
    echo "<li>📈 <strong>Live Stats</strong> - Total profit, loss, win rate, and more</li>";
    echo "<li>🔄 <strong>Auto-Sync</strong> - Balance updates automatically with each trade</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<div style='display:flex;gap:12px;margin-top:20px'>";
    echo "<a href='/MY CASH/pages/forex_journal.php' style='padding: 12px 24px; background: #8b5cf6; color: white; text-decoration: none; border-radius: 8px; font-weight:600'>📊 Open Forex Journal →</a>";
    echo "<a href='/MY CASH/pages/accounts.php' style='padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight:600'>💳 View Accounts →</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "<p style='color: green;'>✅ Table already exists! System is ready.</p>";
        echo "<hr>";
        echo "<div style='display:flex;gap:12px;margin-top:20px'>";
        echo "<a href='/MY CASH/pages/forex_journal.php' style='padding: 12px 24px; background: #8b5cf6; color: white; text-decoration: none; border-radius: 8px; font-weight:600'>📊 Open Forex Journal →</a>";
        echo "<a href='/MY CASH/pages/accounts.php' style='padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight:600'>💳 View Accounts →</a>";
        echo "</div>";
    }
}
?>
