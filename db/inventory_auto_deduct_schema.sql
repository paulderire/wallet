-- Inventory Auto-Deduction Schema
-- Creates tables for automatic stock deduction and movement tracking

-- Stock movements log table
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NULL,
  employee_id INT UNSIGNED NULL,
  movement_type ENUM('sale', 'purchase', 'adjustment', 'return', 'damage', 'transfer') NOT NULL DEFAULT 'sale',
  quantity INT NOT NULL,
  previous_stock INT NOT NULL,
  new_stock INT NOT NULL,
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  total_value DECIMAL(12,2) DEFAULT 0.00,
  notes TEXT,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_item (item_id),
  INDEX idx_transaction (transaction_id),
  INDEX idx_employee (employee_id),
  INDEX idx_movement_type (movement_type),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add quantity_sold column to employee_tasks if not exists
ALTER TABLE employee_tasks 
ADD COLUMN IF NOT EXISTS quantity_sold INT DEFAULT 1 AFTER items_sold;

-- Add item_id column to employee_tasks for product reference
ALTER TABLE employee_tasks
ADD COLUMN IF NOT EXISTS item_id INT UNSIGNED NULL AFTER quantity_sold;

-- Low stock notifications table (enhanced)
CREATE TABLE IF NOT EXISTS low_stock_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  current_stock INT NOT NULL,
  minimum_stock INT NOT NULL,
  shortage_amount INT NOT NULL,
  notification_type ENUM('low', 'critical', 'out_of_stock') NOT NULL DEFAULT 'low',
  is_acknowledged TINYINT(1) DEFAULT 0,
  acknowledged_by INT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  is_resolved TINYINT(1) DEFAULT 0,
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_item (item_id),
  INDEX idx_type (notification_type),
  INDEX idx_status (is_acknowledged, is_resolved),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add auto_deduct_enabled flag to stationery_items if not exists
ALTER TABLE stationery_items
ADD COLUMN IF NOT EXISTS auto_deduct_enabled TINYINT(1) DEFAULT 1 AFTER is_active;

-- Add last_restock_date to track inventory movements
ALTER TABLE stationery_items
ADD COLUMN IF NOT EXISTS last_restock_date DATETIME NULL AFTER auto_deduct_enabled;

-- Add reorder_point for smarter inventory management
ALTER TABLE stationery_items
ADD COLUMN IF NOT EXISTS reorder_point INT DEFAULT 20 AFTER minimum_stock;

-- Add lead_time_days for reorder calculations
ALTER TABLE stationery_items
ADD COLUMN IF NOT EXISTS lead_time_days INT DEFAULT 7 AFTER reorder_point;
