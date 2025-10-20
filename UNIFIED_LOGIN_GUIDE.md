# Unified Login System Guide

## Overview
The MY CASH application now features a **unified login system** at `index.php` that automatically detects whether you're logging in as an **Admin/User** or an **Employee** and redirects you to the appropriate dashboard.

---

## 🎯 Key Features

### Single Entry Point
- **One login page** for everyone at: `http://localhost/MY%20CASH/`
- No need to remember different URLs for admin vs employee login
- Beautiful modal-based login experience on the landing page

### Smart Detection
The system automatically:
1. Checks if the email exists in the `users` table (Admin/User)
2. If not found, checks the `employees` table (Employee)
3. Verifies the password against the correct hash
4. Sets appropriate session variables
5. Redirects to the correct dashboard

### Automatic Redirection
- **Admin/Regular User** → `pages/dashboard.php`
- **Employee** → `employee/dashboard.php`

---

## 🚀 How to Use

### For Admins/Users
1. Visit `http://localhost/MY%20CASH/`
2. Click the **"Sign In"** button in the hero section
3. Enter your registered email and password
4. Click **"Sign In"**
5. You'll be automatically redirected to `pages/dashboard.php`

### For Employees
1. Visit `http://localhost/MY%20CASH/`
2. Click the **"Sign In"** button in the hero section
3. Enter your employee email and password
4. Click **"Sign In"**
5. You'll be automatically redirected to `employee/dashboard.php`

---

## 📋 Technical Implementation

### Session Variables Set

**For Admin/User Login:**
```php
$_SESSION['user_id'] = $user['id'];
```

**For Employee Login:**
```php
$_SESSION['employee_id'] = $employee['id'];
$_SESSION['employee_name'] = $employee['name'];
$_SESSION['employee_email'] = $employee['email'];
$_SESSION['employee_role'] = $employee['role'];
$_SESSION['employee_user_id'] = $employee['user_id'];
$_SESSION['employee_company'] = $employee['company_name'];
```

### Authentication Flow
```
1. User submits login form at index.php
   ↓
2. System queries users table by email
   ↓
3. User found? 
   → YES: Verify password → Set user_id session → Redirect to pages/dashboard.php
   → NO: Continue to step 4
   ↓
4. System queries employees table by email (with active status check)
   ↓
5. Employee found?
   → YES: Verify password → Set employee sessions → Update last_login → Redirect to employee/dashboard.php
   → NO: Show error "Invalid credentials or account not active"
```

### Database Queries

**Admin/User Query:**
```sql
SELECT * FROM users WHERE email = ?
```

**Employee Query:**
```sql
SELECT e.*, u.name as company_name 
FROM employees e 
LEFT JOIN users u ON e.user_id = u.id 
WHERE e.email = ? 
  AND e.status = 'active' 
  AND e.is_active = 1
```

---

## 🎨 User Interface

### Landing Page with Modal
- Beautiful gradient hero section with company branding
- **"Sign In"** button opens a modal overlay
- Modal features:
  - Clean, centered design
  - Email and password fields
  - Error messages displayed prominently
  - Info box explaining the unified login
  - Close button (X) and ESC key support
  - Click outside to close

### Modal Features
- **Responsive design** - works on all screen sizes
- **Smooth animations** - fade in and slide up effects
- **Auto-focus** - email field gets focus when modal opens
- **Keyboard accessible** - ESC key to close
- **Error persistence** - if login fails, modal stays open with error message

---

## 🔒 Security Features

### Password Verification
- Uses PHP's `password_verify()` function
- Compares against bcrypt hashes stored in database
- Different hash fields for different user types:
  - Users: `password` column
  - Employees: `password_hash` column

### Active Status Checks
**For Employees:**
- `status = 'active'` - Employee must be active in the system
- `is_active = 1` - Employee must have login enabled
- Both conditions must be true for login to succeed

### Session Management
- Sessions started before any output
- Separate session variables for users vs employees
- No session variable overlap
- Automatic redirect if already logged in

### Last Login Tracking
- For employees, `last_login` timestamp is updated on successful login
- Helps track employee activity and security

---

## 🔄 Backward Compatibility

### Old Login Pages Still Work
The following pages still function but are considered legacy:

**`pages/login.php`** - Admin/User login only
- Still accessible at `http://localhost/MY%20CASH/pages/login.php`
- Only checks `users` table
- Redirects to `pages/dashboard.php`

**`employee_login.php`** - Employee login only
- Still accessible at `http://localhost/MY%20CASH/employee_login.php`
- Only checks `employees` table
- Redirects to `employee/dashboard.php`

Both files have been updated with notices recommending the unified login at index.php.

---

## 📁 Files Modified

### `index.php` (Main Changes)
**Lines 1-60:** Added unified authentication logic
- Database connection included
- Dual session checks (user_id and employee_id)
- Login form processing with smart detection
- Error handling

**Lines 660-900+:** Added login modal
- Modal HTML structure
- Form with email/password fields
- Info box explaining dual login
- JavaScript for modal controls

**Lines 650-850:** Added modal CSS styles
- Overlay with backdrop blur
- Centered modal card with glassmorphism
- Form styling matching site design
- Responsive breakpoints

### `employee_login.php`
- Added notice comment about unified login at top

### `pages/login.php`
- Added notice comment about unified login at top

---

## 🧪 Testing Checklist

### Test Admin Login
- [ ] Visit `http://localhost/MY%20CASH/`
- [ ] Click "Sign In" button
- [ ] Enter admin email and password
- [ ] Verify redirect to `pages/dashboard.php`
- [ ] Check admin features are accessible

### Test Employee Login
- [ ] Logout from admin account
- [ ] Visit `http://localhost/MY%20CASH/`
- [ ] Click "Sign In" button
- [ ] Enter employee email and password
- [ ] Verify redirect to `employee/dashboard.php`
- [ ] Check employee features are accessible

### Test Error Handling
- [ ] Try invalid email
- [ ] Try wrong password
- [ ] Try inactive employee account
- [ ] Verify error message displays correctly
- [ ] Verify modal stays open on error

### Test Modal Functionality
- [ ] Modal opens on "Sign In" button click
- [ ] Email field gets auto-focus
- [ ] Close button (X) works
- [ ] ESC key closes modal
- [ ] Click outside modal closes it
- [ ] Smooth animations working

### Test Session Management
- [ ] Login as admin, verify session persists across pages
- [ ] Logout, login as employee, verify separate session
- [ ] Try accessing employee dashboard as admin (should fail)
- [ ] Try accessing admin dashboard as employee (should fail)

---

## 🐛 Troubleshooting

### Issue: "Invalid credentials or account not active"
**For Users/Admins:**
- Verify email exists in `users` table
- Check password is correct
- Password must match bcrypt hash in `password` column

**For Employees:**
- Verify email exists in `employees` table
- Check `status = 'active'`
- Check `is_active = 1`
- Ensure password was set using `business/setup_employee_access.php`
- Password must match bcrypt hash in `password_hash` column

### Issue: Modal doesn't open
- Check browser console for JavaScript errors
- Verify `openLoginModal()` function exists in page source
- Clear browser cache and refresh

### Issue: Wrong dashboard after login
- Clear all sessions: `session_destroy()`
- Delete browser cookies
- Close all browser tabs
- Try logging in again

### Issue: Already logged in redirect loop
- Check if both `user_id` and `employee_id` are set in session
- Clear sessions and try again
- Check database for duplicate emails across tables

### Issue: Database connection error
- Verify `includes/db.php` exists and is correct
- Check MySQL service is running (XAMPP)
- Verify database credentials in `db.php`
- Ensure `users` and `employees` tables exist

---

## 🔧 Configuration

### Database Requirements

**Users Table:**
```sql
- id (INT PRIMARY KEY)
- email (VARCHAR UNIQUE)
- password (VARCHAR) -- bcrypt hash
- name (VARCHAR)
- is_admin (TINYINT) -- 0 or 1
```

**Employees Table:**
```sql
- id (INT PRIMARY KEY)
- email (VARCHAR UNIQUE)
- password_hash (VARCHAR) -- bcrypt hash
- name (VARCHAR)
- role (VARCHAR)
- status (ENUM: 'active', 'inactive')
- is_active (TINYINT) -- 0 or 1
- user_id (INT FOREIGN KEY)
- last_login (DATETIME)
```

### Required Files
- `index.php` - Main landing/login page
- `includes/db.php` - Database connection
- `pages/dashboard.php` - Admin/user dashboard
- `employee/dashboard.php` - Employee dashboard
- `pages/register.php` - New user registration

---

## 📊 Comparison: Before vs After

### Before (Separate Logins)
| User Type | Login URL | Redirect |
|-----------|-----------|----------|
| Admin/User | `pages/login.php` | `pages/dashboard.php` |
| Employee | `employee_login.php` | `employee/dashboard.php` |

**Issues:**
- Users had to remember different URLs
- Confusing for first-time users
- Two separate login pages to maintain

### After (Unified Login)
| User Type | Login URL | Redirect |
|-----------|-----------|----------|
| Admin/User | `index.php` | `pages/dashboard.php` |
| Employee | `index.php` | `employee/dashboard.php` |

**Benefits:**
- ✅ Single entry point for everyone
- ✅ Automatic user type detection
- ✅ Better user experience
- ✅ Professional appearance
- ✅ Easier to maintain
- ✅ Consistent branding

---

## 🎓 Best Practices

### For Administrators
1. Always use the main page (`index.php`) for login
2. Set employee passwords using `business/setup_employee_access.php`
3. Ensure employees have `is_active = 1` before they can login
4. Monitor `last_login` timestamps for security

### For Employees
1. Use the main page (`index.php`) for login
2. Contact your admin if password not set
3. Ensure your status is 'active'
4. Use the same email registered in the system

### For Developers
1. Always check the correct session variable:
   - Admin pages: `$_SESSION['user_id']`
   - Employee pages: `$_SESSION['employee_id']`
2. Don't mix session variables
3. Use prepared statements for all queries
4. Hash passwords with `password_hash()` before storing
5. Verify with `password_verify()` during login

---

## 🚦 Quick Start

### Setup Steps

1. **Import Database Schemas**
   ```bash
   # In phpMyAdmin or MySQL CLI
   mysql -u root my_cash_db < db/add_admin_role.sql
   mysql -u root my_cash_db < db/employee_portal_schema.sql
   ```

2. **Set Admin User**
   ```sql
   UPDATE users SET is_admin = 1 WHERE email = 'your-admin@email.com';
   ```

3. **Create Employee Accounts**
   - Login as admin
   - Go to Business > Employees
   - Add employees
   - Go to Business > Setup Employee Access
   - Set passwords for employees

4. **Test Login**
   - Visit `http://localhost/MY%20CASH/`
   - Click "Sign In"
   - Test with admin credentials
   - Logout and test with employee credentials

---

## 📝 Notes

- The system automatically detects user type based on email lookup
- No manual selection needed ("Login as Admin" vs "Login as Employee")
- Both authentication systems use bcrypt password hashing
- Sessions are completely separate for security
- Old login pages remain functional for backward compatibility
- Modal can be closed with X button, ESC key, or click outside
- Error messages persist in modal to help users correct mistakes

---

## 🆘 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the Employee Portal Guide (`EMPLOYEE_PORTAL_GUIDE.md`)
3. Check browser console for JavaScript errors
4. Verify database tables and data
5. Ensure XAMPP MySQL service is running

---

## ✅ Success Indicators

You'll know the unified login is working when:
- ✅ Modal opens smoothly when clicking "Sign In"
- ✅ Admin login redirects to admin dashboard
- ✅ Employee login redirects to employee dashboard
- ✅ Wrong credentials show error in modal
- ✅ Sessions persist correctly
- ✅ No JavaScript errors in console
- ✅ Modal closes properly with all methods

---

**Last Updated:** October 13, 2025  
**Version:** 1.0  
**Author:** MY CASH Development Team
