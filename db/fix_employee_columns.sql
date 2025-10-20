-- Fix Employees Table for Password Management
-- Run this if you're having issues with setup_employee_access.php

-- Add password and login columns to employees table (if they don't exist)
-- Note: If columns already exist, these will fail silently or show warnings (safe to ignore)

-- Add email_verified column
ALTER TABLE employees ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email;

-- Add password_hash column  
ALTER TABLE employees ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email_verified;

-- Add last_login column
ALTER TABLE employees ADD COLUMN last_login DATETIME DEFAULT NULL AFTER password_hash;

-- Add is_active column
ALTER TABLE employees ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login;

-- Make sure is_admin exists in users table
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

-- Verify columns were added (check output)
DESCRIBE employees;
