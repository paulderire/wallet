-- Employee Profile Extended Schema
-- Emergency contacts and document attachments for employees

CREATE TABLE IF NOT EXISTS `employee_emergency_contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `contact_name` VARCHAR(150) NOT NULL,
  `relationship` VARCHAR(100) NOT NULL COMMENT 'Spouse, Parent, Sibling, Friend, etc',
  `phone` VARCHAR(20) NOT NULL,
  `alternate_phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_primary` TINYINT(1) DEFAULT 0 COMMENT 'Primary emergency contact',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `document_type` VARCHAR(100) NOT NULL COMMENT 'ID Card, Passport, Contract, Certificate, etc',
  `document_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT DEFAULT NULL COMMENT 'File size in bytes',
  `file_type` VARCHAR(50) DEFAULT NULL COMMENT 'MIME type',
  `expiry_date` DATE DEFAULT NULL COMMENT 'For documents that expire',
  `notes` TEXT DEFAULT NULL,
  `uploaded_by` INT NOT NULL COMMENT 'User who uploaded',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
