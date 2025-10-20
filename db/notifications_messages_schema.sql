-- Global Notifications and Messages System Schema
-- Run this to create notifications and messages tables for the entire app

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'Admin/User receiving notification',
  employee_id INT UNSIGNED NULL COMMENT 'Employee receiving notification',
  type VARCHAR(50) NOT NULL COMMENT 'success, info, warning, error, task, payment, etc',
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  action_url VARCHAR(500) NULL COMMENT 'Optional link to related page',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL COMMENT 'Optional expiry for temporary notifications',
  icon VARCHAR(50) NULL COMMENT 'Optional icon class or emoji',
  priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  INDEX idx_user (user_id, is_read),
  INDEX idx_employee (employee_id, is_read),
  INDEX idx_created (created_at),
  INDEX idx_type (type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Messages table (like announcements, alerts)
CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id INT UNSIGNED NULL COMMENT 'Who sent (admin/user)',
  sender_type ENUM('user', 'employee', 'system') NOT NULL DEFAULT 'system',
  recipient_id INT UNSIGNED NULL COMMENT 'Specific recipient or NULL for broadcast',
  recipient_type ENUM('user', 'employee', 'all') NOT NULL DEFAULT 'all',
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  category VARCHAR(50) NULL COMMENT 'announcement, alert, update, etc',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  INDEX idx_recipient (recipient_type, recipient_id, is_read),
  INDEX idx_created (created_at),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences (optional - for user control)
CREATE TABLE IF NOT EXISTS notification_preferences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  employee_id INT UNSIGNED NULL,
  notification_type VARCHAR(50) NOT NULL COMMENT 'Which type of notification',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_type (user_id, notification_type),
  UNIQUE KEY unique_employee_type (employee_id, notification_type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
