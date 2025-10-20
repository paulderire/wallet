-- Employee Portal Database Schema
-- This creates tables for employee login, task tracking, and activity logging

-- First, add is_admin column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

-- Add login credentials to employees table
ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS last_login DATETIME DEFAULT NULL AFTER password_hash,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login;

-- Employee tasks/activities log
CREATE TABLE IF NOT EXISTS employee_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  task_date DATE NOT NULL,
  task_time TIME NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100) DEFAULT NULL,
  status ENUM('completed', 'in-progress', 'pending') DEFAULT 'pending',
  duration_minutes INT DEFAULT NULL,
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, task_date),
  INDEX idx_status (status),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task attachments/documents
CREATE TABLE IF NOT EXISTS task_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(100),
  file_size INT,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES employee_tasks(id) ON DELETE CASCADE,
  INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee notes and comments
CREATE TABLE IF NOT EXISTS employee_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED DEFAULT NULL,
  note_text TEXT NOT NULL,
  is_for_manager TINYINT(1) DEFAULT 0,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES employee_tasks(id) ON DELETE SET NULL,
  INDEX idx_employee (employee_id),
  INDEX idx_unread (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work log summary (for tracking hours)
CREATE TABLE IF NOT EXISTS work_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  clock_in TIME DEFAULT NULL,
  clock_out TIME DEFAULT NULL,
  total_hours DECIMAL(5,2) DEFAULT NULL,
  break_minutes INT DEFAULT 0,
  notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_employee_date (employee_id, log_date),
  INDEX idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task categories (predefined categories for consistency)
CREATE TABLE IF NOT EXISTS task_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_name VARCHAR(100) NOT NULL,
  color_code VARCHAR(7) DEFAULT '#667eea',
  icon VARCHAR(50) DEFAULT '📋',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_category (user_id, category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default task categories
INSERT INTO task_categories (user_id, category_name, color_code, icon) VALUES
(1, 'Sales', '#48bb78', '💰'),
(1, 'Customer Service', '#667eea', '🤝'),
(1, 'Production', '#ed8936', '🏭'),
(1, 'Administration', '#764ba2', '📋'),
(1, 'Marketing', '#f093fb', '📢'),
(1, 'Maintenance', '#718096', '🔧')
ON DUPLICATE KEY UPDATE category_name = category_name;

-- Employee notifications/reminders
CREATE TABLE IF NOT EXISTS employee_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT,
  type ENUM('reminder', 'task', 'announcement', 'warning') DEFAULT 'reminder',
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_unread (employee_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
