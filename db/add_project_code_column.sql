-- Add project_code column to projects table if it doesn't exist
-- Run this SQL script in your database to fix the error

-- First, check if the column exists and add it if it doesn't
SET @dbname = DATABASE();
SET @tablename = 'projects';
SET @columnname = 'project_code';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column already exists' AS msg;",
  "ALTER TABLE projects ADD COLUMN project_code VARCHAR(50) NOT NULL AFTER project_name;"
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add unique constraint if it doesn't exist
SET @constraintStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND INDEX_NAME = 'unique_project_code'
  ) > 0,
  "SELECT 'Constraint already exists' AS msg;",
  "ALTER TABLE projects ADD UNIQUE KEY unique_project_code (user_id, project_code);"
));

PREPARE addConstraintIfNotExists FROM @constraintStatement;
EXECUTE addConstraintIfNotExists;
DEALLOCATE PREPARE addConstraintIfNotExists;
