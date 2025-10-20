<?php
/**
 * GENERATE TEST DATA - Run this once to create sample sales and alerts
 * Access: http://localhost/MY CASH/generate_test_data.php
 * Then delete this file for security
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ 
  die("Please login as admin first at: <a href='/MY CASH/pages/login.php'>Login Page</a>");
}

include __DIR__ . '/includes/db.php';
$user_id = $_SESSION['user_id'];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Generate Test Data</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4f4dd; color: #22543d; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #fecaca; color: #7f1d1d; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e0e7ff; color: #3730a3; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #667eea; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧪 Generate Test Data</h1>";

try {
    // Step 1: Check if employees exist
    echo "<h2>Step 1: Checking for employees...</h2>";
    $stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = ? AND status = 'active' LIMIT 3");
    $stmt->execute([$user_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($employees)) {
        echo "<div class='error'>❌ No active employees found! Please add employees first at: 
        <a href='/MY CASH/business/add_employee.php'>Add Employee</a></div>";
        echo "</div></body></html>";
        exit;
    }
    
    echo "<div class='success'>✅ Found " . count($employees) . " active employee(s)</div>";
    echo "<pre>";
    foreach ($employees as $emp) {
        echo "ID: " . $emp['id'] . " - " . $emp['name'] . " (" . $emp['role'] . ") - " . $emp['email'] . "\n";
    }
    echo "</pre>";
    
    // Step 2: Generate sales transactions for today
    echo "<h2>Step 2: Generating sales transactions...</h2>";
    
    $today = date('Y-m-d');
    $transactions_created = 0;
    
    $payment_methods = ['cash', 'mobile_money', 'bank_transfer', 'credit'];
    $customers = ['John Doe', 'Jane Smith', 'Bob Wilson', 'Alice Brown', 'Charlie Davis'];
    
    foreach ($employees as $emp) {
        // Generate 3-5 random sales per employee
        $num_sales = rand(3, 5);
        
        for ($i = 0; $i < $num_sales; $i++) {
            $customer = $customers[array_rand($customers)];
            $amount = rand(5000, 50000); // Random amount between 5,000 and 50,000
            $payment = $payment_methods[array_rand($payment_methods)];
            
            $items = [
                "A4 Paper (2 reams), Blue Pens (1 box)",
                "Notebooks (5 pcs), Markers (2 sets)",
                "Calculator, Stapler, Tape",
                "Files (10 pcs), Envelopes (1 pack)",
                "Sticky Notes (3 packs), Highlighters"
            ];
            $item = $items[array_rand($items)];
            
            $stmt = $conn->prepare("INSERT INTO employee_tasks 
                (employee_id, user_id, task_date, title, description, category, status, 
                 customer_name, items_sold, total_amount, payment_method, transaction_type)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, 'sale')");
            
            $stmt->execute([
                $emp['id'],
                $user_id,
                $today,
                "Sale to " . $customer,
                "Test transaction " . ($i + 1),
                'Sales Transaction',
                $customer,
                $item,
                $amount,
                $payment
            ]);
            
            $transactions_created++;
            
            // Update daily sales summary
            $cash = $payment === 'cash' ? $amount : 0;
            $mobile = $payment === 'mobile_money' ? $amount : 0;
            $bank = $payment === 'bank_transfer' ? $amount : 0;
            $credit = $payment === 'credit' ? $amount : 0;
            
            $stmt = $conn->prepare("INSERT INTO daily_sales_summary 
                (employee_id, user_id, sale_date, total_sales, total_cash, total_mobile_money, total_bank_transfer, total_credit, total_transactions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    total_sales = total_sales + VALUES(total_sales),
                    total_cash = total_cash + VALUES(total_cash),
                    total_mobile_money = total_mobile_money + VALUES(total_mobile_money),
                    total_bank_transfer = total_bank_transfer + VALUES(total_bank_transfer),
                    total_credit = total_credit + VALUES(total_credit),
                    total_transactions = total_transactions + 1");
            
            $stmt->execute([$emp['id'], $user_id, $today, $amount, $cash, $mobile, $bank, $credit]);
        }
        
        echo "<div class='info'>Created $num_sales sales for: " . $emp['name'] . "</div>";
    }
    
    echo "<div class='success'>✅ Created $transactions_created total sales transactions!</div>";
    
    // Step 3: Generate inventory alerts
    echo "<h2>Step 3: Generating inventory alerts...</h2>";
    
    $alerts_created = 0;
    $alert_items = [
        ['A4 Paper', 5, 'low_stock', 'high'],
        ['Blue Pens', 0, 'out_of_stock', 'critical'],
        ['Staplers', 2, 'low_stock', 'medium'],
        ['Calculators', 0, 'out_of_stock', 'critical'],
        ['Notebooks', 8, 'low_stock', 'medium'],
        ['Markers', 3, 'damaged', 'high']
    ];
    
    foreach ($employees as $index => $emp) {
        // Each employee reports 1-2 alerts
        $num_alerts = rand(1, 2);
        
        for ($i = 0; $i < $num_alerts; $i++) {
            $alert = $alert_items[($index + $i) % count($alert_items)];
            
            $alert_time = date('H:i:s', strtotime('-' . rand(1, 6) . ' hours'));
            
            $stmt = $conn->prepare("INSERT INTO inventory_alerts 
                (employee_id, user_id, item_name, current_quantity, alert_type, urgency, notes, alert_date, alert_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $notes = "Urgent: Need to reorder soon!";
            if ($alert[2] === 'out_of_stock') {
                $notes = "CRITICAL: Completely out of stock. Customers asking for this item.";
            }
            
            $stmt->execute([
                $emp['id'],
                $user_id,
                $alert[0],
                $alert[1],
                $alert[2],
                $alert[3],
                $notes,
                $today,
                $alert_time
            ]);
            
            $alerts_created++;
        }
        
        echo "<div class='info'>Created $num_alerts alert(s) for: " . $emp['name'] . "</div>";
    }
    
    echo "<div class='success'>✅ Created $alerts_created inventory alerts!</div>";
    
    // Summary
    echo "<h2>🎉 Test Data Generated Successfully!</h2>";
    echo "<div class='success'>";
    echo "<strong>Summary:</strong><br>";
    echo "✅ Employees: " . count($employees) . "<br>";
    echo "✅ Sales Transactions: $transactions_created<br>";
    echo "✅ Inventory Alerts: $alerts_created<br>";
    echo "</div>";
    
    echo "<h3>📋 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>View Business Dashboard:</strong> <a href='/MY CASH/business/financial_dashboard.php'>Financial Dashboard</a></li>";
    echo "<li><strong>View as Employee:</strong> <a href='/MY CASH/employee_login.php'>Employee Login</a></li>";
    echo "<li><strong>Delete this file</strong> for security (generate_test_data.php)</li>";
    echo "</ol>";
    
    echo "<a href='/MY CASH/business/financial_dashboard.php' class='btn'>📊 View Financial Dashboard</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
