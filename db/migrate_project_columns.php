<?php
/**
 * Migration Script: Add missing columns to projects table
 * Adds client_name, client_email, client_phone, and other missing fields
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting migration for projects table...\n<br>";
    
    // Get current columns
    $stmt = $conn->query("SHOW COLUMNS FROM projects");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "Current columns: " . implode(', ', $existingColumns) . "\n<br><br>";
    
    // Define columns that should exist
    $columnsToAdd = [
        'client_name' => "ALTER TABLE projects ADD COLUMN client_name VARCHAR(255) NULL AFTER budget",
        'client_email' => "ALTER TABLE projects ADD COLUMN client_email VARCHAR(255) NULL AFTER client_name",
        'client_phone' => "ALTER TABLE projects ADD COLUMN client_phone VARCHAR(50) NULL AFTER client_email",
        'created_by' => "ALTER TABLE projects ADD COLUMN created_by INT UNSIGNED NULL AFTER client_phone",
        'spent_amount' => "ALTER TABLE projects ADD COLUMN spent_amount DECIMAL(12,2) DEFAULT 0 AFTER budget",
        'progress_percentage' => "ALTER TABLE projects ADD COLUMN progress_percentage INT DEFAULT 0 AFTER spent_amount",
        'archived' => "ALTER TABLE projects ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER updated_at",
        'archived_at' => "ALTER TABLE projects ADD COLUMN archived_at TIMESTAMP NULL AFTER archived"
    ];
    
    $added = 0;
    $skipped = 0;
    
    foreach ($columnsToAdd as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            echo "Adding column: $column...\n<br>";
            $conn->exec($sql);
            echo "✓ Added $column\n<br>";
            $added++;
        } else {
            echo "✓ Column $column already exists\n<br>";
            $skipped++;
        }
    }
    
    echo "\n<br><strong>✓ Migration completed!</strong>\n<br>";
    echo "Added: $added columns\n<br>";
    echo "Skipped: $skipped columns (already existed)\n<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "\n<br>";
}
?>
