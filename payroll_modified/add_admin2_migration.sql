-- ============================================================
-- MIGRATION SCRIPT: Add admin2 role to existing payroll_db
-- Run this in phpMyAdmin or MySQL terminal if your database
-- already exists with the old 'admin' user.
-- ============================================================

USE payroll_db;

-- Step 1: Update users table to support new roles
ALTER TABLE users 
  MODIFY COLUMN role ENUM('superadmin', 'admin2', 'admin') DEFAULT 'admin2';

-- Step 2: Rename existing admin role to superadmin
UPDATE users SET role = 'superadmin' WHERE username = 'admin' AND role = 'admin';

-- Step 3: Change role column to final values only
ALTER TABLE users 
  MODIFY COLUMN role ENUM('superadmin', 'admin2') DEFAULT 'admin2';

-- Step 4: Insert admin2 user (payroll officer - limited access)
-- Password is: admin2023
INSERT IGNORE INTO users (username, password, full_name, email, role, status)
VALUES (
  'admin2',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
  'Payroll Officer',
  'payroll@payroll.gov',
  'admin2',
  'active'
);

-- NOTE: The password hash above is a placeholder.
-- After running this migration, visit your site once so config.php
-- auto-creates the admin2 user with the correct hashed password.
-- OR run this PHP to get the correct hash:
--   php -r "echo password_hash('admin2023', PASSWORD_DEFAULT);"
-- Then replace the hash above and re-run just the INSERT.

-- Step 5: Verify
SELECT id, username, full_name, role, status FROM users;
