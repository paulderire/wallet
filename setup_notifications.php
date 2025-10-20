<?php
/**
 * Setup Notifications and Messages Database Tables
 * Run this file once to create the necessary tables
 */

require_once __DIR__ . '/includes/db.php';

echo "<h1>Setting up Notifications & Messages System</h1>";
echo "<pre>";

try {
    // Create notifications table
    echo "Creating notifications table...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            employee_id INT UNSIGNED NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            action_url VARCHAR(500) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            icon VARCHAR(50) NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            INDEX idx_user (user_id, is_read),
            INDEX idx_employee (employee_id, is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Notifications table created!\n\n";
    
    // Create messages table
    echo "Creating messages table...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NULL,
            sender_type ENUM('user', 'employee', 'system') NOT NULL DEFAULT 'system',
            recipient_id INT UNSIGNED NULL,
            recipient_type ENUM('user', 'employee', 'all') NOT NULL DEFAULT 'all',
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            category VARCHAR(50) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            INDEX idx_recipient (recipient_type, recipient_id, is_read),
            INDEX idx_created (created_at),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Messages table created!\n\n";
    
    echo "✅ SUCCESS! Database tables created.\n\n";
    echo "You can now use:\n";
    echo "- /MY CASH/includes/notifications_api.php for notifications API\n";
    echo "- Notification widget in header (bell icon)\n";
    echo "- /MY CASH/pages/notifications.php to view all notifications\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
echo '<br><a href="/MY CASH/pages/dashboard.php">← Back to Dashboard</a>';
?>
