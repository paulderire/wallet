-- Employee Payments System Schema
-- This schema handles salary payments, bonuses, deductions, and payment history

-- Create employee_payments table
CREATE TABLE IF NOT EXISTS employee_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  user_id INT NOT NULL,
  payment_type ENUM('salary', 'bonus', 'advance', 'deduction') NOT NULL DEFAULT 'salary',
  amount DECIMAL(15,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_month VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
  status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cash', 'bank_transfer', 'mobile_money', 'check') DEFAULT 'cash',
  reference_number VARCHAR(100) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employee (employee_id),
  INDEX idx_user (user_id),
  INDEX idx_payment_date (payment_date),
  INDEX idx_payment_month (payment_month),
  INDEX idx_status (status),
  INDEX idx_type (payment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_schedules table (for recurring salary payments)
CREATE TABLE IF NOT EXISTS payment_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  user_id INT NOT NULL,
  schedule_type ENUM('monthly', 'bi-weekly', 'weekly') NOT NULL DEFAULT 'monthly',
  base_salary DECIMAL(15,2) NOT NULL,
  payment_day INT NOT NULL DEFAULT 1 COMMENT 'Day of month for payment (1-31)',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employee (employee_id),
  INDEX idx_user (user_id),
  INDEX idx_active (is_active),
  INDEX idx_type (schedule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_items table (for itemized deductions/bonuses)
CREATE TABLE IF NOT EXISTS payment_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  item_type ENUM('bonus', 'deduction', 'allowance', 'overtime') NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payment (payment_id),
  INDEX idx_type (item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
