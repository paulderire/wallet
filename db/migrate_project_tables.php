<?php
/**
 * Migration Script: Create all project-related tables
 * Creates project_members, project_tasks, project_milestones, etc.
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting migration for project-related tables...\n<br><br>";
    
    // Project team members table
    echo "Creating project_members table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_members (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          user_id INT UNSIGNED NULL,
          employee_id INT UNSIGNED NULL,
          role VARCHAR(100),
          responsibilities TEXT,
          can_edit TINYINT(1) DEFAULT 0,
          can_delete TINYINT(1) DEFAULT 0,
          added_by INT UNSIGNED,
          added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id),
          INDEX idx_user_id (user_id),
          INDEX idx_employee_id (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_members table created\n<br>";
    
    // Project tasks table
    echo "Creating project_tasks table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_tasks (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          parent_task_id INT UNSIGNED NULL,
          task_name VARCHAR(255) NOT NULL,
          description TEXT,
          status ENUM('todo', 'in_progress', 'review', 'completed', 'blocked') DEFAULT 'todo',
          priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
          assigned_to INT UNSIGNED NULL,
          assigned_to_employee INT UNSIGNED NULL,
          start_date DATE,
          due_date DATE,
          completed_date DATE NULL,
          estimated_hours DECIMAL(6,2) DEFAULT 0,
          actual_hours DECIMAL(6,2) DEFAULT 0,
          progress_percentage INT DEFAULT 0,
          order_position INT DEFAULT 0,
          created_by INT UNSIGNED,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id),
          INDEX idx_assigned_to (assigned_to),
          INDEX idx_status (status),
          INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_tasks table created\n<br>";
    
    // Project milestones table
    echo "Creating project_milestones table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_milestones (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          milestone_name VARCHAR(255) NOT NULL,
          description TEXT,
          due_date DATE NOT NULL,
          completed_date DATE NULL,
          status ENUM('pending', 'in_progress', 'completed', 'missed') DEFAULT 'pending',
          order_position INT DEFAULT 0,
          created_by INT UNSIGNED,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id),
          INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_milestones table created\n<br>";
    
    // Project comments table
    echo "Creating project_comments table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_comments (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          task_id INT UNSIGNED NULL,
          user_id INT UNSIGNED NULL,
          employee_id INT UNSIGNED NULL,
          comment_text TEXT NOT NULL,
          comment_type ENUM('comment', 'update', 'issue', 'solution') DEFAULT 'comment',
          is_internal TINYINT(1) DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id),
          INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_comments table created\n<br>";
    
    // Project time logs table
    echo "Creating project_time_logs table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_time_logs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          task_id INT UNSIGNED NULL,
          user_id INT UNSIGNED NULL,
          employee_id INT UNSIGNED NULL,
          log_date DATE NOT NULL,
          hours_spent DECIMAL(6,2) NOT NULL,
          description TEXT,
          billable TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id),
          INDEX idx_log_date (log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_time_logs table created\n<br>";
    
    // Project attachments table
    echo "Creating project_attachments table...\n<br>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_attachments (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          project_id INT UNSIGNED NOT NULL,
          task_id INT UNSIGNED NULL,
          file_name VARCHAR(255) NOT NULL,
          file_path VARCHAR(500) NOT NULL,
          file_type VARCHAR(50),
          file_size INT UNSIGNED,
          uploaded_by INT UNSIGNED NULL,
          uploaded_by_employee INT UNSIGNED NULL,
          uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_project_id (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ project_attachments table created\n<br>";
    
    echo "\n<br><strong>✓ All project tables created successfully!</strong>\n<br>";
    echo "Tables created: project_members, project_tasks, project_milestones, project_comments, project_time_logs, project_attachments\n<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n<br>";
    echo "SQL State: " . $e->getCode() . "\n<br>";
}
?>
