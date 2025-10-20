-- Employee Chat System Schema
-- Real-time messaging between employees and admins

CREATE TABLE IF NOT EXISTS `chat_rooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('direct', 'group', 'support') DEFAULT 'direct',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL COMMENT 'Admin user',
  `employee_id` INT DEFAULT NULL COMMENT 'Employee',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` TIMESTAMP NULL,
  FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE,
  INDEX `idx_room` (`room_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_employee` (`employee_id`),
  UNIQUE KEY `unique_participant` (`room_id`, `user_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT NOT NULL,
  `sender_type` ENUM('user', 'employee') NOT NULL,
  `sender_id` INT NOT NULL COMMENT 'user_id or employee_id based on sender_type',
  `message` TEXT NOT NULL,
  `attachment` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE,
  INDEX `idx_room_time` (`room_id`, `created_at`),
  INDEX `idx_sender` (`sender_type`, `sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_typing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT NOT NULL,
  `sender_type` ENUM('user', 'employee') NOT NULL,
  `sender_id` INT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_typing` (`room_id`, `sender_type`, `sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
