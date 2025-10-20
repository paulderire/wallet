-- ========================================
-- EMPLOYEE ATTENDANCE TRACKING SYSTEM
-- ========================================

CREATE TABLE IF NOT EXISTS `employee_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_time` TIME NULL,
  `check_out_time` TIME NULL,
  `status` ENUM('present', 'absent', 'late', 'half_day', 'on_leave') DEFAULT 'present',
  `work_hours` DECIMAL(5,2) DEFAULT NULL,
  `notes` TEXT NULL,
  `checked_in_by` INT NULL COMMENT 'User ID who marked check-in',
  `checked_out_by` INT NULL COMMENT 'User ID who marked check-out',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_date` (`attendance_date`),
  INDEX `idx_status` (`status`),
  UNIQUE KEY `unique_employee_date` (`employee_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `work_start_time` TIME DEFAULT '08:00:00',
  `work_end_time` TIME DEFAULT '17:00:00',
  `late_threshold_minutes` INT DEFAULT 15 COMMENT 'Minutes after work_start_time to mark as late',
  `half_day_hours` DECIMAL(4,2) DEFAULT 4.00,
  `full_day_hours` DECIMAL(4,2) DEFAULT 8.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `attendance_settings` (`work_start_time`, `work_end_time`, `late_threshold_minutes`, `half_day_hours`, `full_day_hours`)
VALUES ('08:00:00', '17:00:00', 15, 4.00, 8.00)
ON DUPLICATE KEY UPDATE id=id;
