-- Schema additions for Transactions workflow
-- Create transactions table
CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  type ENUM('deposit','withdraw') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: ensure accounts table has a balance column
ALTER TABLE accounts
  ADD COLUMN IF NOT EXISTS `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00;
