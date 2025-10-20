-- ========================================
-- FOREX TRADING JOURNAL TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS `forex_trades` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `currency_pair` VARCHAR(10) NOT NULL,
  `trade_type` ENUM('buy', 'sell') NOT NULL,
  `entry_price` DECIMAL(10,5) NOT NULL,
  `exit_price` DECIMAL(10,5) DEFAULT NULL,
  `stop_loss` DECIMAL(10,5) DEFAULT NULL,
  `take_profit` DECIMAL(10,5) DEFAULT NULL,
  `lot_size` DECIMAL(10,2) NOT NULL,
  `risk_percentage` DECIMAL(5,2) DEFAULT NULL,
  `profit_loss` DECIMAL(10,2) DEFAULT NULL,
  `status` ENUM('open', 'closed', 'pending') DEFAULT 'open',
  `strategy_used` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `entry_date` DATETIME NOT NULL,
  `exit_date` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_pair` (`currency_pair`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forex_strategies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `strategy_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `rules` TEXT DEFAULT NULL,
  `indicators_used` TEXT DEFAULT NULL,
  `risk_management` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forex_watchlist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `currency_pair` VARCHAR(10) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `alert_price` DECIMAL(10,5) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  UNIQUE KEY `unique_user_pair` (`user_id`, `currency_pair`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- BUSINESS MANAGEMENT SYSTEM TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL COMMENT 'Business owner/manager',
  `employee_id` VARCHAR(50) UNIQUE NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) UNIQUE DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `role` VARCHAR(100) DEFAULT NULL,
  `hire_date` DATE NOT NULL,
  `manager_id` INT DEFAULT NULL,
  `salary` DECIMAL(12,2) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `project_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `deadline` DATE DEFAULT NULL,
  `status` ENUM('not started', 'in progress', 'completed', 'on hold', 'cancelled') DEFAULT 'not started',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `budget` DECIMAL(12,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `project_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `role_in_project` VARCHAR(100) DEFAULT NULL,
  `assigned_date` DATE NOT NULL,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_project_employee` (`project_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `task_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL COMMENT 'Employee ID',
  `status` ENUM('to do', 'in progress', 'completed', 'blocked') DEFAULT 'to do',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `due_date` DATE DEFAULT NULL,
  `completed_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `base_salary` DECIMAL(12,2) NOT NULL,
  `bonuses` DECIMAL(12,2) DEFAULT 0,
  `deductions` DECIMAL(12,2) DEFAULT 0,
  `net_pay` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE DEFAULT NULL,
  `payment_status` ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_period` (`pay_period_start`, `pay_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `clock_in` TIME DEFAULT NULL,
  `clock_out` TIME DEFAULT NULL,
  `hours_worked` DECIMAL(4,2) DEFAULT NULL,
  `status` ENUM('present', 'absent', 'late', 'half-day', 'leave') DEFAULT 'present',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_employee_date` (`employee_id`, `date`),
  INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type` ENUM('vacation', 'sick', 'personal', 'unpaid', 'other') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `days_requested` INT NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approved_by` INT DEFAULT NULL COMMENT 'Manager/Admin user ID',
  `approval_date` DATE DEFAULT NULL,
  `comments` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `department_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `manager_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  UNIQUE KEY `unique_user_dept` (`user_id`, `department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `performance_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `reviewer_id` INT DEFAULT NULL COMMENT 'Manager conducting review',
  `review_date` DATE NOT NULL,
  `review_period_start` DATE DEFAULT NULL,
  `review_period_end` DATE DEFAULT NULL,
  `rating` ENUM('excellent', 'good', 'satisfactory', 'needs improvement', 'poor') DEFAULT 'satisfactory',
  `strengths` TEXT DEFAULT NULL,
  `weaknesses` TEXT DEFAULT NULL,
  `goals` TEXT DEFAULT NULL,
  `comments` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
