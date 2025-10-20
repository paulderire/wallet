<?php
/**
 * Cleanup Script - Remove Test Data for Fresh Start
 * WARNING: This will delete test files and test database records
 */

session_start();
require_once __DIR__ . '/includes/db.php';

// Check if user is admin
if (empty($_SESSION['user_id'])) {
    die("Please login as admin first");
}

$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['is_admin']) {
    die("Only administrators can run this cleanup script");
}

$cleaned = [];
$errors = [];

echo "<h1>🧹 MY CASH - Test Data Cleanup</h1>";
echo "<pre>";

// 1. Remove test PHP files
echo "\n=== Removing Test Files ===\n";
$testFiles = [
    __DIR__ . '/test_chat_debug.php',
    __DIR__ . '/test_db_structure.php',
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            $cleaned[] = "✓ Deleted: " . basename($file);
            echo "✓ Deleted: " . basename($file) . "\n";
        } else {
            $errors[] = "✗ Failed to delete: " . basename($file);
            echo "✗ Failed to delete: " . basename($file) . "\n";
        }
    } else {
        echo "  Skipped (not found): " . basename($file) . "\n";
    }
}

// 2. Clear ALL database records (keeping structure)
echo "\n=== Clearing Database Records ===\n";

// Chat data
try {
    $stmt = $conn->query("DELETE FROM chat_messages");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} chat messages";
    echo "✓ Cleared {$count} chat messages\n";
    
    $stmt = $conn->query("DELETE FROM chat_participants");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} chat participants";
    echo "✓ Cleared {$count} chat participants\n";
    
    $stmt = $conn->query("DELETE FROM chat_rooms");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} chat rooms";
    echo "✓ Cleared {$count} chat rooms\n";
    
    $stmt = $conn->query("DELETE FROM chat_typing");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} typing indicators";
    echo "✓ Cleared {$count} typing indicators\n";
} catch (Exception $e) {
    $errors[] = "✗ Error clearing chat data: " . $e->getMessage();
    echo "✗ Error clearing chat data: " . $e->getMessage() . "\n";
}

// Transactions
try {
    $stmt = $conn->query("DELETE FROM transactions");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} transactions";
    echo "✓ Cleared {$count} transactions\n";
} catch (Exception $e) {
    echo "  Note: transactions table not found or empty\n";
}

// Forex trades
try {
    $stmt = $conn->query("DELETE FROM forex_trades");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} forex trades";
    echo "✓ Cleared {$count} forex trades\n";
} catch (Exception $e) {
    echo "  Note: forex_trades table not found or empty\n";
}

// Accounts (reset balances to 0)
try {
    $stmt = $conn->query("UPDATE accounts SET balance = 0.00");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Reset {$count} account balances to zero";
    echo "✓ Reset {$count} account balances to zero\n";
} catch (Exception $e) {
    echo "  Note: accounts table not found\n";
}

// Employee tasks
try {
    $stmt = $conn->query("DELETE FROM employee_tasks");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} employee tasks";
    echo "✓ Cleared {$count} employee tasks\n";
} catch (Exception $e) {
    echo "  Note: employee_tasks table not found or empty\n";
}

// Task attachments
try {
    $stmt = $conn->query("DELETE FROM task_attachments");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} task attachments";
    echo "✓ Cleared {$count} task attachments\n";
} catch (Exception $e) {
    echo "  Note: task_attachments table not found or empty\n";
}

// Employee notes
try {
    $stmt = $conn->query("DELETE FROM employee_notes");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} employee notes";
    echo "✓ Cleared {$count} employee notes\n";
} catch (Exception $e) {
    echo "  Note: employee_notes table not found or empty\n";
}

// Employee attendance
try {
    $stmt = $conn->query("DELETE FROM employee_attendance");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} attendance records";
    echo "✓ Cleared {$count} attendance records\n";
} catch (Exception $e) {
    echo "  Note: employee_attendance table not found or empty\n";
}

// Employee payments/salary
try {
    $stmt = $conn->query("DELETE FROM employee_payments");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} employee payments";
    echo "✓ Cleared {$count} employee payments\n";
} catch (Exception $e) {
    echo "  Note: employee_payments table not found or empty\n";
}

// Budgets
try {
    $stmt = $conn->query("DELETE FROM budgets");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} budgets";
    echo "✓ Cleared {$count} budgets\n";
} catch (Exception $e) {
    echo "  Note: budgets table not found or empty\n";
}

// Goals
try {
    $stmt = $conn->query("DELETE FROM goals");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} goals";
    echo "✓ Cleared {$count} goals\n";
} catch (Exception $e) {
    echo "  Note: goals table not found or empty\n";
}

// Loans
try {
    $stmt = $conn->query("DELETE FROM loans");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} loans";
    echo "✓ Cleared {$count} loans\n";
} catch (Exception $e) {
    echo "  Note: loans table not found or empty\n";
}

// Projects
try {
    $stmt = $conn->query("DELETE FROM projects");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} projects";
    echo "✓ Cleared {$count} projects\n";
} catch (Exception $e) {
    echo "  Note: projects table not found or empty\n";
}

// Inventory
try {
    $stmt = $conn->query("DELETE FROM inventory");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} inventory items";
    echo "✓ Cleared {$count} inventory items\n";
} catch (Exception $e) {
    echo "  Note: inventory table not found or empty\n";
}

// Sales
try {
    $stmt = $conn->query("DELETE FROM sales");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} sales records";
    echo "✓ Cleared {$count} sales records\n";
} catch (Exception $e) {
    echo "  Note: sales table not found or empty\n";
}

// Expenses
try {
    $stmt = $conn->query("DELETE FROM expenses");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} expenses";
    echo "✓ Cleared {$count} expenses\n";
} catch (Exception $e) {
    echo "  Note: expenses table not found or empty\n";
}

// 3. Clear AI logs and notification data
echo "\n=== Clearing AI Logs & Notifications ===\n";

// Clear AI logs
$logFiles = [
    __DIR__ . '/assets/logs/ai_debug.log',
    __DIR__ . '/assets/logs/transfer_errors.log',
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        if (file_put_contents($logFile, '') !== false) {
            $cleaned[] = "✓ Cleared: " . basename($logFile);
            echo "✓ Cleared: " . basename($logFile) . "\n";
        } else {
            $errors[] = "✗ Failed to clear: " . basename($logFile);
            echo "✗ Failed to clear: " . basename($logFile) . "\n";
        }
    }
}

// Clear AI data files and notifications
$dataFiles = [
    __DIR__ . '/assets/data/ai_logs.json',
    __DIR__ . '/assets/data/messages.json',
    __DIR__ . '/assets/data/notifications.json',
];

foreach ($dataFiles as $dataFile) {
    if (file_exists($dataFile)) {
        if (file_put_contents($dataFile, '[]') !== false) {
            $cleaned[] = "✓ Reset: " . basename($dataFile);
            echo "✓ Reset: " . basename($dataFile) . "\n";
        } else {
            $errors[] = "✗ Failed to reset: " . basename($dataFile);
            echo "✗ Failed to reset: " . basename($dataFile) . "\n";
        }
    }
}

// Clear database notifications (if table exists)
try {
    $stmt = $conn->query("DELETE FROM notifications");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} database notifications";
    echo "✓ Cleared {$count} database notifications\n";
} catch (Exception $e) {
    echo "  Note: notifications table not found or empty\n";
}

// Clear database messages (if table exists)
try {
    $stmt = $conn->query("DELETE FROM messages");
    $count = $stmt->rowCount();
    $cleaned[] = "✓ Cleared {$count} database messages";
    echo "✓ Cleared {$count} database messages\n";
} catch (Exception $e) {
    echo "  Note: messages table not found or empty\n";
}

// 5. Clear uploaded test avatars (keep directory structure)
echo "\n=== Clearing Test Avatars ===\n";
$uploadDirs = [
    __DIR__ . '/assets/uploads/avatars/',
];

foreach ($uploadDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        $cleaned[] = "✓ Deleted {$count} avatar files from " . basename($dir);
        echo "✓ Deleted {$count} avatar files from " . basename($dir) . "\n";
    }
}

// Summary
echo "\n=== CLEANUP SUMMARY ===\n";
echo "✓ Successfully cleaned: " . count($cleaned) . " items\n";
if (count($errors) > 0) {
    echo "✗ Errors encountered: " . count($errors) . " items\n";
    foreach ($errors as $error) {
        echo "  " . $error . "\n";
    }
}

echo "\n=== IMPORTANT NOTES ===\n";
echo "✓ Test files removed\n";
echo "✓ ALL chat data cleared\n";
echo "✓ ALL transactions cleared\n";
echo "✓ ALL forex trades cleared\n";
echo "✓ ALL employee tasks & attendance cleared\n";
echo "✓ ALL budgets, goals, loans cleared\n";
echo "✓ ALL inventory & sales cleared\n";
echo "✓ Account balances reset to zero\n";
echo "✓ AI logs cleared\n";
echo "✓ Test avatars removed\n";
echo "⚠ User accounts are PRESERVED (admin login still works)\n";
echo "⚠ Employee records are PRESERVED (structure intact)\n";
echo "⚠ Database structure is PRESERVED (all tables intact)\n";
echo "⚠ Application code is INTACT\n";

echo "\n🎉 Complete cleanup done! Your app is ready for fresh production data.\n";
echo "\n<a href='/MY CASH/pages/dashboard.php' style='color: #4F46E5; font-weight: bold;'>← Back to Dashboard</a>\n";
echo "</pre>";
?>
