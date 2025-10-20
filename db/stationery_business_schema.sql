-- Stationery Business Employee Transaction Tracking
-- This converts the employee portal into a financial transaction and inventory alert system

-- Modify employee_tasks table to track financial transactions
ALTER TABLE employee_tasks 
ADD COLUMN customer_name VARCHAR(255) DEFAULT NULL AFTER description,
ADD COLUMN items_sold TEXT DEFAULT NULL AFTER customer_name,
ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0.00 AFTER duration_minutes,
ADD COLUMN payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'credit') DEFAULT 'cash' AFTER total_amount,
ADD COLUMN transaction_type ENUM('sale', 'expense', 'stock_alert', 'other') DEFAULT 'sale' AFTER payment_method;

-- Create inventory alerts table for missing/low stock items
CREATE TABLE IF NOT EXISTS inventory_alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  alert_date DATE NOT NULL,
  alert_time TIME NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  current_quantity INT DEFAULT 0,
  alert_type ENUM('out_of_stock', 'low_stock', 'damaged', 'expired', 'other') NOT NULL,
  urgency ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  notes TEXT,
  status ENUM('pending', 'acknowledged', 'resolved') DEFAULT 'pending',
  resolved_by INT DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, alert_date),
  INDEX idx_status (status),
  INDEX idx_urgency (urgency),
  INDEX idx_alert_type (alert_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create daily sales summary table
CREATE TABLE IF NOT EXISTS daily_sales_summary (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  sale_date DATE NOT NULL,
  total_sales DECIMAL(12,2) DEFAULT 0.00,
  total_cash DECIMAL(12,2) DEFAULT 0.00,
  total_mobile_money DECIMAL(12,2) DEFAULT 0.00,
  total_bank_transfer DECIMAL(12,2) DEFAULT 0.00,
  total_credit DECIMAL(12,2) DEFAULT 0.00,
  total_transactions INT DEFAULT 0,
  opening_balance DECIMAL(12,2) DEFAULT 0.00,
  closing_balance DECIMAL(12,2) DEFAULT 0.00,
  notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_employee_date (employee_id, sale_date),
  INDEX idx_employee_date (employee_id, sale_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create stationery items catalog (optional - for quick selection)
CREATE TABLE IF NOT EXISTS stationery_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  item_code VARCHAR(50) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  cost_price DECIMAL(10,2) DEFAULT 0.00,
  current_stock INT DEFAULT 0,
  minimum_stock INT DEFAULT 10,
  unit VARCHAR(50) DEFAULT 'piece',
  description TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_category (category),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample stationery items (optional)
INSERT INTO stationery_items (user_id, item_name, category, unit_price, cost_price, current_stock, minimum_stock, unit) VALUES
(1, 'A4 Paper (Ream)', 'Paper Products', 5000, 4000, 50, 10, 'ream'),
(1, 'Pen - Blue', 'Writing Tools', 200, 150, 100, 20, 'piece'),
(1, 'Pen - Black', 'Writing Tools', 200, 150, 100, 20, 'piece'),
(1, 'Notebook - 100 Pages', 'Books & Notebooks', 1500, 1000, 30, 10, 'piece'),
(1, 'Stapler', 'Office Supplies', 2500, 2000, 15, 5, 'piece'),
(1, 'Staples Box', 'Office Supplies', 500, 300, 25, 10, 'box'),
(1, 'Calculator', 'Electronics', 8000, 6000, 10, 3, 'piece'),
(1, 'Envelope - A4', 'Envelopes & Files', 100, 70, 200, 50, 'piece'),
(1, 'File Folder', 'Envelopes & Files', 800, 600, 40, 15, 'piece'),
(1, 'Marker - Permanent', 'Writing Tools', 1000, 700, 30, 10, 'piece');

-- Update task_categories to match stationery business
INSERT INTO task_categories (user_id, category_name, description, color_code, icon) VALUES
(1, 'Sales Transaction', 'Daily sales and customer transactions', '#10b981', '💰'),
(1, 'Stock Alert', 'Low stock or out of stock notifications', '#ef4444', '⚠️'),
(1, 'Expense', 'Business expenses and costs', '#f59e0b', '💳'),
(1, 'Customer Order', 'Special orders or bulk orders', '#3b82f6', '📦'),
(1, 'Returns/Refunds', 'Product returns or refunds', '#8b5cf6', '↩️'),
(1, 'Maintenance', 'Shop maintenance and repairs', '#6b7280', '🔧')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);
