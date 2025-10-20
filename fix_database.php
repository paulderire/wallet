<?php
/**
 * DIRECT DATABASE SETUP - Run this file once
 * Access: http://localhost/MY CASH/fix_database.php
 */

$host = "localhost";
$dbname = "wallet_db";
$username = "root";
$password = "";

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Fix Database</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4f4dd; color: #22543d; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #fecaca; color: #7f1d1d; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e0e7ff; color: #3730a3; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h1 { color: #667eea; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Database Fix Tool</h1>";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Connected to database: <strong>$dbname</strong></div>";
    
    // Check if employee_tasks table exists, if not create it
    echo "<h2>Step 1: Checking employee_tasks table...</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'employee_tasks'");
    
    if ($result->rowCount() == 0) {
        echo "<div class='info'>📝 Creating employee_tasks table...</div>";
        
        $conn->exec("CREATE TABLE employee_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            task_date DATE NOT NULL,
            task_time TIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100) DEFAULT NULL,
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            duration_minutes INT DEFAULT 0,
            customer_name VARCHAR(255) DEFAULT NULL,
            items_sold TEXT DEFAULT NULL,
            total_amount DECIMAL(12,2) DEFAULT 0.00,
            payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'credit') DEFAULT 'cash',
            transaction_type ENUM('sale', 'expense', 'stock_alert', 'other') DEFAULT 'sale',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_user (user_id),
            INDEX idx_date (task_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "<div class='success'>✅ employee_tasks table created!</div>";
    } else {
        echo "<div class='info'>✅ employee_tasks table exists</div>";
        
        // Check if it has transaction columns
        $check = $conn->query("SHOW COLUMNS FROM employee_tasks LIKE 'payment_method'");
        if ($check->rowCount() == 0) {
            echo "<div class='info'>Adding transaction columns...</div>";
            try {
                $conn->exec("ALTER TABLE employee_tasks 
                    ADD COLUMN customer_name VARCHAR(255) DEFAULT NULL,
                    ADD COLUMN items_sold TEXT DEFAULT NULL,
                    ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0.00,
                    ADD COLUMN payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'credit') DEFAULT 'cash',
                    ADD COLUMN transaction_type ENUM('sale', 'expense', 'stock_alert', 'other') DEFAULT 'sale'");
                echo "<div class='success'>✅ Transaction columns added!</div>";
            } catch (Exception $e) {
                echo "<div class='info'>Columns already exist or error: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Drop stationery_items if exists (to start fresh)
    echo "<h2>Step 2: Cleaning up old stationery_items table...</h2>";
    try {
        $conn->exec("DROP TABLE IF EXISTS stationery_items");
        echo "<div class='info'>🗑️ Removed old stationery_items table (if existed)</div>";
    } catch (Exception $e) {
        echo "<div class='info'>ℹ️ No old table to remove</div>";
    }
    
    // Create stationery_items table WITHOUT foreign keys
    echo "<h2>Step 3: Creating stationery_items table...</h2>";
    
    $sql = "CREATE TABLE stationery_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        item_code VARCHAR(50) DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        cost_price DECIMAL(10,2) DEFAULT 0.00,
        current_stock INT DEFAULT 0,
        minimum_stock INT DEFAULT 10,
        unit VARCHAR(50) DEFAULT 'piece',
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_category (category),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "<div class='success'>✅ Table 'stationery_items' created successfully!</div>";
    
    // Insert sample data
    echo "<h2>Step 4: Adding sample products...</h2>";
    
    $products = [
        [1, 'A4 Paper (Ream 500 sheets)', 'ST-001', 'Paper Products', 5000, 4000, 50, 10, 'ream'],
        [1, 'Blue Ballpoint Pens (Box of 12)', 'ST-002', 'Writing Instruments', 2400, 1800, 100, 20, 'box'],
        [1, 'Black Ballpoint Pens (Box of 12)', 'ST-003', 'Writing Instruments', 2400, 1800, 100, 20, 'box'],
        [1, 'Notebook A5 (100 Pages)', 'ST-004', 'Paper Products', 1500, 1000, 30, 10, 'piece'],
        [1, 'Stapler Heavy Duty', 'ST-005', 'Office Supplies', 2500, 2000, 15, 5, 'piece'],
        [1, 'Staples Box (1000 pcs)', 'ST-006', 'Office Supplies', 500, 300, 25, 10, 'box'],
        [1, 'Calculator Desktop 12-digit', 'ST-007', 'Electronics', 8000, 6000, 10, 3, 'piece'],
        [1, 'Manila Envelope A4', 'ST-008', 'Filing Supplies', 100, 70, 200, 50, 'piece'],
        [1, 'Plastic File Folder', 'ST-009', 'Filing Supplies', 800, 600, 40, 15, 'piece'],
        [1, 'Permanent Marker Black', 'ST-010', 'Writing Instruments', 1000, 700, 30, 10, 'piece']
    ];
    
    $stmt = $conn->prepare("INSERT INTO stationery_items 
        (user_id, item_name, item_code, category, unit_price, cost_price, current_stock, minimum_stock, unit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    foreach ($products as $product) {
        $stmt->execute($product);
        $count++;
    }
    
    echo "<div class='success'>✅ Added $count sample products!</div>";
    
    // Show the products
    echo "<h2>Step 5: Verifying products...</h2>";
    $result = $conn->query("SELECT item_code, item_name, unit_price FROM stationery_items ORDER BY item_code");
    $items = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    foreach ($items as $item) {
        echo $item['item_code'] . " - " . $item['item_name'] . " - RWF " . number_format($item['unit_price'], 0) . "\n";
    }
    echo "</pre>";
    
    // Now check and create inventory_alerts table
    echo "<h2>Step 6: Creating inventory_alerts table...</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS inventory_alerts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        alert_date DATE NOT NULL,
        alert_time TIME NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        current_quantity INT DEFAULT 0,
        alert_type ENUM('out_of_stock', 'low_stock', 'damaged', 'expired', 'other') NOT NULL,
        urgency ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        notes TEXT,
        status ENUM('pending', 'acknowledged', 'resolved') DEFAULT 'pending',
        resolved_by INT DEFAULT NULL,
        resolved_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employee_date (employee_id, alert_date),
        INDEX idx_status (status),
        INDEX idx_urgency (urgency),
        INDEX idx_alert_type (alert_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "<div class='success'>✅ Table 'inventory_alerts' ready!</div>";
    
    // Create daily_sales_summary table
    echo "<h2>Step 7: Creating daily_sales_summary table...</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS daily_sales_summary (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        sale_date DATE NOT NULL,
        total_sales DECIMAL(12,2) DEFAULT 0.00,
        total_cash DECIMAL(12,2) DEFAULT 0.00,
        total_mobile_money DECIMAL(12,2) DEFAULT 0.00,
        total_bank_transfer DECIMAL(12,2) DEFAULT 0.00,
        total_credit DECIMAL(12,2) DEFAULT 0.00,
        total_transactions INT DEFAULT 0,
        opening_balance DECIMAL(12,2) DEFAULT 0.00,
        closing_balance DECIMAL(12,2) DEFAULT 0.00,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_employee_date (employee_id, sale_date),
        INDEX idx_employee_date (employee_id, sale_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "<div class='success'>✅ Table 'daily_sales_summary' ready!</div>";
    
    // Create forex_trades table
    echo "<h2>Step 8: Creating forex_trades table...</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS forex_trades (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "<div class='success'>✅ Table 'forex_trades' ready!</div>";
    
    // Success summary
    echo "<h2>🎉 All Done!</h2>";
    echo "<div class='success'>";
    echo "<strong>Database is now ready!</strong><br><br>";
    echo "✅ employee_tasks table created/updated<br>";
    echo "✅ stationery_items table created<br>";
    echo "✅ 10 sample products added<br>";
    echo "✅ inventory_alerts table ready<br>";
    echo "✅ daily_sales_summary table ready<br>";
    echo "✅ forex_trades table ready<br>";
    echo "</div>";
    
    echo "<h3>📋 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>DELETE THIS FILE</strong> (fix_database.php) for security</li>";
    echo "<li>Go to: <a href='/MY CASH/employee/manage_inventory.php'>Manage Inventory</a></li>";
    echo "<li>Try the new <a href='/MY CASH/pages/forex_journal.php'>Forex Trading Journal</a></li>";
    echo "<li>Login as employee and start managing products</li>";
    echo "</ol>";
    
    echo "<a href='/MY CASH/employee/manage_inventory.php' class='btn'>📦 Open Inventory Management</a>";
    echo "<a href='/MY CASH/pages/forex_journal.php' class='btn' style='margin-left: 10px; background: #8b5cf6;'>📊 Forex Journal</a>";
    echo "<a href='/MY CASH/employee_login.php' class='btn' style='margin-left: 10px;'>🚪 Employee Login</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<h3>🔍 Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP Apache and MySQL are running</li>";
    echo "<li>Check database name is 'wallet_db' in phpMyAdmin</li>";
    echo "<li>Try restarting MySQL service</li>";
    echo "</ol>";
}

echo "</div></body></html>";
?>
