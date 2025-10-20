-- Add admin role to users table
-- This migration adds an is_admin column to track which users have admin privileges

-- Add is_admin column to users table
ALTER TABLE users 
ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 
AFTER password;

-- You can manually set a user as admin using:
-- UPDATE users SET is_admin = 1 WHERE id = YOUR_USER_ID;
