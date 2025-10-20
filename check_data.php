<?php
session_start();
if(!isset($_SESSION['user_id'])){ 
    die("Please <a href='/MY CASH/pages/login.php'>login as admin</a> first");
}
include __DIR__ . '/includes/db.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

echo "<h2>Database Data Check</h2>";
echo "<p><strong>Today's Date:</strong> $today</p>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<hr>";

// First, check employees table structure
echo "<h3>0. Employees Table Structure</h3>";
$stmt = $conn->query("DESCRIBE employees");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
}
echo "</table>";

// Check employee_tasks table
echo "<h3>1. Employee Tasks (Sales)</h3>";
$stmt = $conn->prepare("SELECT 
    et.id,
    et.employee_id,
    et.task_date,
    et.transaction_type,
    et.total_amount,
    et.payment_method
    FROM employee_tasks et
    WHERE et.user_id = ? AND et.transaction_type = 'sale'
    ORDER BY et.task_date DESC
    LIMIT 10");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($tasks) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Employee ID</th><th>Date</th><th>Amount</th><th>Payment</th></tr>";
    foreach ($tasks as $task) {
        echo "<tr>";
        echo "<td>{$task['id']}</td>";
        echo "<td>{$task['employee_id']}</td>";
        echo "<td>{$task['task_date']}</td>";
        echo "<td>RWF " . number_format($task['total_amount'], 0) . "</td>";
        echo "<td>{$task['payment_method']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>⚠️ No sales records found in employee_tasks</p>";
}

// Check today's sales specifically
echo "<h3>2. Today's Sales (Date: $today)</h3>";
$stmt = $conn->prepare("SELECT 
    COUNT(*) as count,
    SUM(total_amount) as total,
    SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash
    FROM employee_tasks 
    WHERE user_id = ? AND task_date = ? AND transaction_type = 'sale'");
$stmt->execute([$user_id, $today]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Count:</strong> {$today_stats['count']}</p>";
echo "<p><strong>Total:</strong> RWF " . number_format($today_stats['total'], 0) . "</p>";
echo "<p><strong>Cash:</strong> RWF " . number_format($today_stats['cash'], 0) . "</p>";

// Check inventory alerts
echo "<h3>3. Inventory Alerts</h3>";
$stmt = $conn->prepare("SELECT 
    ia.id,
    ia.employee_id,
    ia.item_name,
    ia.alert_date,
    ia.urgency,
    ia.status
    FROM inventory_alerts ia
    WHERE ia.user_id = ?
    ORDER BY ia.alert_date DESC
    LIMIT 10");
$stmt->execute([$user_id]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($alerts) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Employee ID</th><th>Item</th><th>Date</th><th>Urgency</th><th>Status</th></tr>";
    foreach ($alerts as $alert) {
        echo "<tr>";
        echo "<td>{$alert['id']}</td>";
        echo "<td>{$alert['employee_id']}</td>";
        echo "<td>{$alert['item_name']}</td>";
        echo "<td>{$alert['alert_date']}</td>";
        echo "<td>{$alert['urgency']}</td>";
        echo "<td>{$alert['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>⚠️ No inventory alerts found</p>";
}

// Check employees
echo "<h3>4. Employees</h3>";
$stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($employees) > 0) {
    // Get column names from first row
    $cols = array_keys($employees[0]);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    foreach ($cols as $col) {
        echo "<th>$col</th>";
    }
    echo "</tr>";
    foreach ($employees as $emp) {
        echo "<tr>";
        foreach ($cols as $col) {
            echo "<td>{$emp[$col]}</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>⚠️ No employees found</p>";
}

echo "<hr>";
echo "<p><a href='/MY CASH/business/financial_dashboard.php'>Go to Financial Dashboard</a></p>";
echo "<p><a href='/MY CASH/generate_test_data.php'>Generate Test Data</a></p>";
?>
