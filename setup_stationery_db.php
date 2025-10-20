<?php
/**
 * Stationery Business Database Setup Script
 * Run this file ONCE to create the required database tables
 * Access: http://localhost/MY CASH/setup_stationery_db.php
 */

// Include database connection
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Stationery Database Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
        }
        .success {
            background: #d4f4dd;
            color: #22543d;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            background: #fecaca;
            color: #7f1d1d;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #ef4444;
        }
        .info {
            background: #e0e7ff;
            color: #3730a3;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        .warning {
            background: #fef3c7;
            color: #78350f;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #f59e0b;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .step h3 {
            margin-top: 0;
            color: #1a202c;
        }
        pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🏪 Stationery Business Database Setup</h1>";
echo "<p class='subtitle'>Setting up inventory and transaction tracking tables...</p>";

$errors = [];
$success = [];

try {
    // Check if stationery_items table exists
    $check = $conn->query("SHOW TABLES LIKE 'stationery_items'");
    
    if ($check->rowCount() > 0) {
        echo "<div class='warning'>⚠️ Table 'stationery_items' already exists. Skipping creation...</div>";
    } else {
        // Create stationery_items table
        echo "<div class='step'><h3>📦 Creating stationery_items table...</h3>";
        
        $sql = "CREATE TABLE IF NOT EXISTS stationery_items (
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
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX idx_user (user_id),
          INDEX idx_category (category),
          INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        echo "<div class='success'>✅ Table 'stationery_items' created successfully!</div>";
        $success[] = "stationery_items table created";
        
        // Insert sample products
        echo "<h3>🎯 Inserting sample products...</h3>";
        
        $stmt = $conn->prepare("INSERT INTO stationery_items 
          (user_id, item_name, item_code, category, unit_price, cost_price, current_stock, minimum_stock, unit) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
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
        
        $inserted = 0;
        foreach ($products as $product) {
            try {
                $stmt->execute($product);
                $inserted++;
            } catch (Exception $e) {
                // Skip if already exists
            }
        }
        
        echo "<div class='success'>✅ Inserted $inserted sample products!</div>";
        $success[] = "$inserted sample products added";
        echo "</div>";
    }
    
    // Check inventory_alerts table
    echo "<div class='step'><h3>⚠️ Checking inventory_alerts table...</h3>";
    $check = $conn->query("SHOW TABLES LIKE 'inventory_alerts'");
    
    if ($check->rowCount() > 0) {
        echo "<div class='info'>✅ Table 'inventory_alerts' already exists.</div>";
    } else {
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
          FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX idx_employee_date (employee_id, alert_date),
          INDEX idx_status (status),
          INDEX idx_urgency (urgency),
          INDEX idx_alert_type (alert_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        echo "<div class='success'>✅ Table 'inventory_alerts' created successfully!</div>";
        $success[] = "inventory_alerts table created";
    }
    echo "</div>";
    
    // Check employee_tasks modifications
    echo "<div class='step'><h3>💼 Checking employee_tasks table modifications...</h3>";
    
    try {
        // Check if columns already exist
        $result = $conn->query("SHOW COLUMNS FROM employee_tasks LIKE 'payment_method'");
        
        if ($result->rowCount() > 0) {
            echo "<div class='info'>✅ employee_tasks table already has transaction tracking columns.</div>";
        } else {
            $sql = "ALTER TABLE employee_tasks 
              ADD COLUMN customer_name VARCHAR(255) DEFAULT NULL AFTER description,
              ADD COLUMN items_sold TEXT DEFAULT NULL AFTER customer_name,
              ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0.00 AFTER duration_minutes,
              ADD COLUMN payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'credit') DEFAULT 'cash' AFTER total_amount,
              ADD COLUMN transaction_type ENUM('sale', 'expense', 'stock_alert', 'other') DEFAULT 'sale' AFTER payment_method";
            
            $conn->exec($sql);
            echo "<div class='success'>✅ employee_tasks table updated with transaction tracking columns!</div>";
            $success[] = "employee_tasks table modified";
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<div class='info'>✅ Columns already exist in employee_tasks table.</div>";
        } else {
            throw $e;
        }
    }
    echo "</div>";
    
    // Summary
    echo "<div class='step'>";
    echo "<h2>🎉 Setup Complete!</h2>";
    
    if (count($success) > 0) {
        echo "<div class='success'>";
        echo "<strong>Successfully completed:</strong><ul>";
        foreach ($success as $item) {
            echo "<li>✅ $item</li>";
        }
        echo "</ul></div>";
    }
    
    echo "<h3>📋 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Delete this file</strong> for security (or rename to .txt)</li>";
    echo "<li><strong>Login as employee:</strong> <a href='/MY CASH/employee_login.php'>Employee Login</a></li>";
    echo "<li><strong>Access inventory:</strong> Dashboard → 📦 Manage Inventory button</li>";
    echo "<li><strong>Start adding products</strong> or use the 10 sample products already loaded</li>";
    echo "</ol>";
    
    echo "<h3>📦 Sample Products Loaded:</h3>";
    echo "<pre>";
    echo "ST-001 - A4 Paper (Ream 500 sheets) - RWF 5,000\n";
    echo "ST-002 - Blue Ballpoint Pens (Box of 12) - RWF 2,400\n";
    echo "ST-003 - Black Ballpoint Pens (Box of 12) - RWF 2,400\n";
    echo "ST-004 - Notebook A5 (100 Pages) - RWF 1,500\n";
    echo "ST-005 - Stapler Heavy Duty - RWF 2,500\n";
    echo "ST-006 - Staples Box (1000 pcs) - RWF 500\n";
    echo "ST-007 - Calculator Desktop 12-digit - RWF 8,000\n";
    echo "ST-008 - Manila Envelope A4 - RWF 100\n";
    echo "ST-009 - Plastic File Folder - RWF 800\n";
    echo "ST-010 - Permanent Marker Black - RWF 1,000\n";
    echo "</pre>";
    
    echo "<a href='/MY CASH/employee_login.php' class='btn'>🚀 Go to Employee Login</a>";
    echo "<a href='/MY CASH/employee/manage_inventory.php' class='btn' style='margin-left: 10px;'>📦 Manage Inventory</a>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Database Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🔧 Manual Setup Instructions:</h3>";
    echo "<ol>";
    echo "<li>Open <strong>phpMyAdmin</strong>: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "<li>Select your database: <strong>wallet_db</strong></li>";
    echo "<li>Click the <strong>Import</strong> tab</li>";
    echo "<li>Choose file: <code>C:\\xampp\\htdocs\\MY CASH\\db\\stationery_business_schema.sql</code></li>";
    echo "<li>Click <strong>Go</strong> button</li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>
</body>
</html>";
?>
