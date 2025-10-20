<?php
$conn = new PDO("mysql:host=localhost;dbname=wallet_db", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>Checking forex_trades table...</h2>";

$result = $conn->query("SHOW TABLES LIKE 'forex_trades'");

if ($result->rowCount() > 0) {
    echo "<p style='color:green'>✅ Table EXISTS</p>";
    
    $cols = $conn->query("DESCRIBE forex_trades");
    echo "<table border='1'><tr><th>Column</th><th>Type</th></tr>";
    while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ Table DOES NOT EXIST</p>";
    echo "<p>Creating it now...</p>";
    
    $conn->exec("CREATE TABLE forex_trades (
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
        INDEX idx_user_date (user_id, trade_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "<p style='color:green'>✅ Table created!</p>";
    echo "<p><a href='/MY CASH/pages/forex_journal.php'>Go to Forex Journal</a></p>";
}
?>
