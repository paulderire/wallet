<?php
/**
 * Migration Script: Add project_code column to projects table
 * Run this file once to fix the "Unknown column 'project_code'" error
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting migration...\n<br>";
    
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM projects LIKE 'project_code'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "Adding project_code column...\n<br>";
        $conn->exec("ALTER TABLE projects ADD COLUMN project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_name");
        echo "✓ project_code column added successfully!\n<br>";
        
        // Generate unique codes for existing projects
        echo "Generating codes for existing projects...\n<br>";
        $stmt = $conn->query("SELECT id, user_id FROM projects WHERE project_code = '' OR project_code IS NULL");
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updateStmt = $conn->prepare("UPDATE projects SET project_code = ? WHERE id = ?");
        foreach ($projects as $project) {
            $code = 'PROJ-' . str_pad($project['id'], 4, '0', STR_PAD_LEFT);
            $updateStmt->execute([$code, $project['id']]);
        }
        echo "✓ Generated codes for " . count($projects) . " existing projects\n<br>";
    } else {
        echo "✓ project_code column already exists\n<br>";
    }
    
    // Check if unique constraint exists
    $stmt = $conn->query("SHOW KEYS FROM projects WHERE Key_name = 'unique_project_code'");
    $constraintExists = $stmt->rowCount() > 0;
    
    if (!$constraintExists && $columnExists) {
        echo "Adding unique constraint...\n<br>";
        try {
            $conn->exec("ALTER TABLE projects ADD UNIQUE KEY unique_project_code (user_id, project_code)");
            echo "✓ Unique constraint added successfully!\n<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠ Warning: Duplicate project codes found. Fixing...\n<br>";
                
                // Find and fix duplicates
                $stmt = $conn->query("
                    SELECT user_id, project_code, COUNT(*) as cnt 
                    FROM projects 
                    GROUP BY user_id, project_code 
                    HAVING cnt > 1
                ");
                $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($duplicates as $dup) {
                    $stmt = $conn->prepare("SELECT id FROM projects WHERE user_id = ? AND project_code = ? ORDER BY id");
                    $stmt->execute([$dup['user_id'], $dup['project_code']]);
                    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Keep first, rename others
                    array_shift($ids); // Remove first ID
                    $updateStmt = $conn->prepare("UPDATE projects SET project_code = ? WHERE id = ?");
                    foreach ($ids as $id) {
                        $newCode = $dup['project_code'] . '-' . $id;
                        $updateStmt->execute([$newCode, $id]);
                    }
                }
                
                echo "✓ Fixed duplicate codes\n<br>";
                
                // Try adding constraint again
                $conn->exec("ALTER TABLE projects ADD UNIQUE KEY unique_project_code (user_id, project_code)");
                echo "✓ Unique constraint added successfully!\n<br>";
            } else {
                throw $e;
            }
        }
    } else {
        echo "✓ Unique constraint already exists\n<br>";
    }
    
    echo "\n<br><strong>✓ Migration completed successfully!</strong>\n<br>";
    echo "You can now use the projects functionality without errors.\n<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n<br>";
    echo "Please check your database connection and try again.\n<br>";
}
?>
