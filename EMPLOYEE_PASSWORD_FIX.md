# 🔧 Fix: Can't Set Employee Passwords

## ❌ Problem
Unable to set passwords for existing employees in the Setup Employee Access page.

## 🔍 Root Causes

### 1. Column Name Mismatch
The page was trying to fetch `name` column, but database has `first_name` and `last_name`.

### 2. Missing Password Columns
The employees table might be missing the required columns:
- `password_hash` - Stores encrypted password
- `is_active` - Controls if employee can login
- `email_verified` - Email verification status
- `last_login` - Tracks last login time

## ✅ Solutions Applied

### Fix #1: Updated SQL Query
**File:** `business/setup_employee_access.php`

**Before:**
```php
SELECT id, name, email, role, department, status, password_hash, is_active 
FROM employees WHERE user_id = ? ORDER BY name
```

**After:**
```php
SELECT id, first_name, last_name, 
       CONCAT(first_name, ' ', last_name) as full_name, 
       email, role, department, status, password_hash, is_active 
FROM employees WHERE user_id = ? 
ORDER BY first_name, last_name
```

### Fix #2: Updated Display Code
Changed all references from `$emp['name']` to `$emp['full_name']`

### Fix #3: Added Error Handling
Now shows specific error if employees can't be loaded

---

## 📋 Setup Steps (In Order)

### Step 1: Import Database Schema

You need to add the password columns to your employees table. Choose ONE method:

#### Method A: Run fix_employee_columns.sql (Recommended)
```sql
-- In phpMyAdmin, go to SQL tab and run:
-- File: db/fix_employee_columns.sql
```

This adds:
- ✅ `email_verified` column
- ✅ `password_hash` column
- ✅ `last_login` column
- ✅ `is_active` column
- ✅ `is_admin` column in users table

#### Method B: Run employee_portal_schema.sql (Full Portal)
```sql
-- If you want the complete employee portal:
-- File: db/employee_portal_schema.sql
```

This adds password columns PLUS task tracking tables.

#### Method C: Manual SQL (Quick Fix)
```sql
-- Run these one by one in phpMyAdmin:

ALTER TABLE employees ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email;
ALTER TABLE employees ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email_verified;
ALTER TABLE employees ADD COLUMN last_login DATETIME DEFAULT NULL AFTER password_hash;
ALTER TABLE employees ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_login;
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;
```

**Note:** If you get "Duplicate column" errors, that's okay - it means the columns already exist!

---

### Step 2: Verify Columns Exist

In phpMyAdmin:
1. Select your database
2. Click on `employees` table
3. Click "Structure" tab
4. Verify these columns exist:
   - ✅ `first_name`
   - ✅ `last_name`
   - ✅ `email`
   - ✅ `password_hash`
   - ✅ `is_active`

---

### Step 3: Make Yourself Admin

```sql
-- Replace YOUR_USER_ID with your actual user ID
UPDATE users SET is_admin = 1 WHERE id = YOUR_USER_ID;

-- Or if you know your email:
UPDATE users SET is_admin = 1 WHERE email = 'your@email.com';
```

---

### Step 4: Test the Setup Page

1. **Refresh the page** (Ctrl + Shift + R to clear cache)
2. Go to: `http://localhost/MY%20CASH/business/setup_employee_access.php`
3. You should see a table of employees
4. Each employee should have a "Set Password" or "Reset Password" button

---

## 🧪 Testing Checklist

### ✅ Check #1: Page Loads
- [ ] Page loads without errors
- [ ] You see the employee table
- [ ] Employees are listed with names

### ✅ Check #2: Employee Data Shows
- [ ] Employee names display correctly (First Last)
- [ ] Email addresses show (or "No email")
- [ ] Roles and departments show
- [ ] Login status shows (Active or No Password)

### ✅ Check #3: Set Password Works
- [ ] Click "Set Password" button
- [ ] Modal popup appears
- [ ] Employee name shows in modal
- [ ] Enter password (min 6 characters)
- [ ] Confirm password
- [ ] Submit form
- [ ] Success message appears
- [ ] Status changes to "✓ Active"

### ✅ Check #4: Employee Can Login
- [ ] Logout from admin account
- [ ] Go to: `http://localhost/MY%20CASH/`
- [ ] Click "Sign In"
- [ ] Enter employee email and password
- [ ] Should redirect to employee dashboard

---

## 🐛 Troubleshooting

### Error: "Unknown column 'name'"
**Cause:** Database has `first_name`/`last_name`, not `name`

**Solution:** 
✅ Already fixed in updated `setup_employee_access.php`
- Just refresh the page

### Error: "Unknown column 'password_hash'"
**Cause:** Missing password columns in employees table

**Solution:**
1. Run `db/fix_employee_columns.sql` in phpMyAdmin
2. Or run the manual ALTER TABLE commands above

### Error: "Access Denied" or redirects to dashboard
**Cause:** You're not set as admin

**Solution:**
```sql
UPDATE users SET is_admin = 1 WHERE id = YOUR_USER_ID;
```

### Page shows "No employees found"
**Cause:** No employees in database OR employees belong to different user_id

**Solution:**
```sql
-- Check if employees exist:
SELECT id, first_name, last_name, email, user_id FROM employees;

-- If they exist but have wrong user_id, update them:
UPDATE employees SET user_id = YOUR_USER_ID WHERE user_id != YOUR_USER_ID;
```

### Modal doesn't open when clicking button
**Cause:** JavaScript error or modal HTML missing

**Solution:**
1. Check browser console for errors (F12)
2. Make sure you refreshed the page after update
3. Clear browser cache (Ctrl + Shift + R)

### Success message but password not set
**Cause:** Database update failed

**Solution:**
```sql
-- Check if password_hash column is writable:
UPDATE employees SET password_hash = 'test' WHERE id = 1;

-- If error, check column exists:
DESCRIBE employees;
```

### Employee still can't login after setting password
**Possible Causes:**
1. **Status not 'active':**
   ```sql
   UPDATE employees SET status = 'active' WHERE id = EMPLOYEE_ID;
   ```

2. **is_active not set to 1:**
   ```sql
   UPDATE employees SET is_active = 1 WHERE id = EMPLOYEE_ID;
   ```

3. **No email address:**
   ```sql
   -- Employee needs email to login:
   UPDATE employees SET email = 'employee@company.com' WHERE id = EMPLOYEE_ID;
   ```

4. **Wrong password:**
   - Just reset password again using the form

---

## 📊 Database Verification Queries

### Check Employee Table Structure
```sql
DESCRIBE employees;
```

**Expected columns:**
- id
- user_id
- employee_id
- first_name ✅
- last_name ✅
- email ✅
- email_verified
- password_hash ✅
- last_login
- is_active ✅
- phone
- address
- date_of_birth
- department
- role
- hire_date
- salary
- status
- created_at
- updated_at

### Check Employees List
```sql
SELECT id, CONCAT(first_name, ' ', last_name) as name, 
       email, password_hash, is_active, status 
FROM employees 
WHERE user_id = YOUR_USER_ID;
```

### Check Admin Status
```sql
SELECT id, name, email, is_admin FROM users WHERE id = YOUR_USER_ID;
```

**Expected:** `is_admin` should be `1`

### Manually Set Employee Password
```sql
-- If you want to set password directly in database:
UPDATE employees 
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    is_active = 1 
WHERE id = EMPLOYEE_ID;

-- This sets password to: password
```

---

## 🎯 Quick Fix Summary

### If you're seeing errors, do this:

1. **Run SQL to add columns:**
   ```sql
   ALTER TABLE employees ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL;
   ALTER TABLE employees ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;
   ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
   ```

2. **Make yourself admin:**
   ```sql
   UPDATE users SET is_admin = 1 WHERE id = YOUR_USER_ID;
   ```

3. **Refresh the page:**
   - Press Ctrl + Shift + R

4. **Try setting password again**

---

## 📁 Files Modified

### `business/setup_employee_access.php`
**Lines 53-60:** Updated SQL query
- Uses `first_name`, `last_name` instead of `name`
- Creates `full_name` with CONCAT
- Added error handling

**Line 304:** Updated display
- Uses `full_name` instead of `name`

**Line 316:** Updated modal
- Uses `full_name` instead of `name`

### Files Created

1. **`db/fix_employee_columns.sql`** (NEW)
   - Quick SQL to add missing columns
   - Safe to run multiple times
   - Shows table structure at end

---

## ✅ Success Indicators

You'll know it's working when:

- ✅ Page loads without errors
- ✅ Employees appear in table with full names
- ✅ "Set Password" buttons are visible
- ✅ Clicking button opens modal
- ✅ Can enter and submit password
- ✅ Success message appears
- ✅ Status changes to "✓ Active"
- ✅ Employee can login at index.php

---

## 🚀 After Fixing

Once passwords are set:

1. **Employee Login URL:**
   ```
   http://localhost/MY%20CASH/
   ```
   (Unified login - works for both admin and employees)

2. **Share with employees:**
   - Email: their-email@company.com
   - Password: (password you set)

3. **Employees can:**
   - Login to their dashboard
   - Add daily tasks/activities
   - Upload files and photos
   - View their weekly statistics
   - Track their work hours

---

## 💡 Tips

1. **Use Strong Passwords**
   - Minimum 6 characters
   - Mix letters and numbers
   - Different password for each employee

2. **Email is Required for Login**
   - Employees without email can't login
   - Add email when creating employee

3. **Status Must be Active**
   - Only 'active' employees can login
   - Use 'inactive' to temporarily disable
   - Use 'terminated' for permanently disabled

4. **Test Before Sharing**
   - Always test employee login before giving credentials
   - Make sure employee dashboard loads correctly

---

**Last Updated:** October 13, 2025  
**Status:** ✅ Fixed  
**Priority:** High - Critical for employee access
