# 🎉 EMPLOYEE PORTAL SYSTEM - COMPLETE GUIDE

## 📋 Overview
Complete employee activity tracking and task management system integrated into MY CASH application.

## 🗄️ Database Setup

### Step 1: Import the Schema
Run this SQL file in phpMyAdmin or MySQL:
```
db/employee_portal_schema.sql
```

This will create:
- ✅ `is_admin` column in users table
- ✅ Employee login fields (password_hash, last_login, is_active)
- ✅ `employee_tasks` - Daily task/activity tracking
- ✅ `task_attachments` - File uploads (photos, docs, receipts)
- ✅ `employee_notes` - Communication and notes
- ✅ `work_logs` - Clock in/out and hours tracking
- ✅ `task_categories` - Pre-defined task types
- ✅ `employee_notifications` - Reminders and announcements

### Step 2: Make Yourself Admin
```sql
UPDATE users SET is_admin = 1 WHERE id = YOUR_USER_ID;
```

## 🔐 Setup Process (For Admins)

### 1. Add Employees (If Not Already Added)
- Go to: **Business Management → Employees**
- Click "Add Employee"
- Fill in: Name, Email, Phone, Role, Department, Salary
- Make sure EMAIL is provided (required for login)

### 2. Set Up Employee Login Credentials
- Go to: `/MY%20CASH/business/setup_employee_access.php`
- For each employee, click "Set Password"
- Enter a password (min 6 characters)
- Employee will now be able to login!

### 3. Share Login Details with Employees
**Employee Login URL:** 
```
http://localhost/MY%20CASH/employee_login.php
```

**Credentials:**
- Email: employee@company.com (their email from employees table)
- Password: (the password you set)

## 👤 Employee Features

### 1. Employee Dashboard (`employee/dashboard.php`)
**Features:**
- Welcome message with employee name and role
- Weekly statistics:
  * Total tasks this week
  * Completed tasks
  * In-progress tasks
  * Pending tasks
  * Total hours worked
- Today's tasks list
- Notifications panel
- Pending tasks counter

### 2. Add Task (`employee/add_task.php`)
**Form Fields:**
- ✅ Date & Time (when task was done)
- ✅ Task Title (required)
- ✅ Description (detailed info)
- ✅ Category dropdown (Sales, Customer Service, Production, etc.)
- ✅ Duration in minutes
- ✅ Status (Pending, In Progress, Completed)
- ✅ Priority (Low, Medium, High)
- ✅ File Attachments (upload photos, documents, receipts)

**File Upload:**
- Supports: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX
- Multiple files allowed
- Files saved in: `assets/uploads/tasks/`

### 3. Task History (`employee/tasks.php`)
**Features:**
- View all past tasks
- Filter by:
  * Date range
  * Category
  * Status
  * Search by keywords
- Edit or delete tasks
- View attached files
- Sort by date, priority, etc.

### 4. Reports & Analytics (`employee/reports.php`)
**Analytics Include:**
- Daily summary of completed tasks
- Weekly breakdown by category
- Monthly performance charts
- Hours worked per day/week/month
- Completed vs pending ratio
- Most productive categories
- Task completion trends

## 🎯 Use Cases

### Daily Operation Logging
**Example: Sales Person**
```
Date: October 13, 2025
Time: 10:30 AM
Title: Met with Client ABC Corp
Description: Discussed Q4 product orders, showed new catalog
Category: Sales
Duration: 45 minutes
Status: Completed
Attachments: order_form.pdf, meeting_notes.jpg
```

### Task Tracking
**Example: Production Worker**
```
Title: Assembled 50 units of Product X
Category: Production
Duration: 180 minutes (3 hours)
Status: Completed
Priority: High
```

### Customer Service Log
**Example: Support Agent**
```
Title: Resolved customer complaint #1234
Description: Customer had payment issue, reset their account
Category: Customer Service
Duration: 20 minutes
Status: Completed
Attachments: screenshot.png
```

## 🔒 Security Features

### Separate Login System
- Employees login at `/employee_login.php`
- Separate from admin/user login
- Employees can ONLY see their own data
- No access to business financials or other employees' tasks

### Access Control
- Each employee only sees their own tasks
- Cannot view other employees' activities
- Admin can view all employees (future feature)
- Secure password hashing (bcrypt)

### Session Management
- `$_SESSION['employee_id']` - Employee identifier
- `$_SESSION['employee_name']` - Display name
- `$_SESSION['employee_role']` - Job role
- `$_SESSION['employee_user_id']` - Company owner ID

## 📊 Database Structure

### employee_tasks
```
- id: Task unique ID
- employee_id: Which employee
- user_id: Which company/business owner
- task_date: Date task was performed
- task_time: Time task was performed
- title: Task name
- description: Detailed description
- category: Type of work (Sales, Production, etc.)
- status: pending/in-progress/completed
- duration_minutes: How long it took
- priority: low/medium/high
```

### task_attachments
```
- id: Attachment ID
- task_id: Which task
- file_name: Original filename
- file_path: Storage location
- file_type: File extension
- file_size: Size in bytes
```

### work_logs
```
- id: Log ID
- employee_id: Which employee
- log_date: Date of work
- clock_in: Start time
- clock_out: End time
- total_hours: Calculated hours worked
- break_minutes: Break time
- notes: Additional notes
```

## 🎨 Admin Pages Created

1. **Setup Employee Access** (`business/setup_employee_access.php`)
   - View all employees
   - Set/reset employee passwords
   - See login status (active/inactive)
   - Bulk password management

2. **Employee Management** (`business/employees.php`)
   - View all employees
   - Search and filter
   - Employee cards with details
   - Existing feature enhanced

## 🚀 Testing the System

### Test as Admin:
1. Login to main app: `http://localhost/MY%20CASH/pages/login.php`
2. Go to Business Management → Setup Employee Access
3. Set password for an employee

### Test as Employee:
1. Go to: `http://localhost/MY%20CASH/employee_login.php`
2. Login with employee email and password
3. Add a task with all details
4. Upload a file
5. View dashboard statistics
6. Check notifications

## 📁 Files Created

### Database:
- `db/employee_portal_schema.sql` - Complete database schema
- `db/add_admin_role.sql` - Admin role setup

### Employee Portal:
- `employee_login.php` - Employee login page
- `employee/dashboard.php` - Employee home page
- `employee/add_task.php` - Add new task/activity
- `employee/logout.php` - Logout handler

### Admin Tools:
- `business/setup_employee_access.php` - Password management
- `business/employees.php` - Enhanced employee management
- `business/projects.php` - Project management
- `business/payroll.php` - Payroll tracking

### Frontend Assets:
- Upload directory: `assets/uploads/tasks/` (auto-created)

## 🎯 Next Steps (Future Enhancements)

### Not Yet Implemented (but database ready):
1. **Task History Page** - View/edit all past tasks
2. **Reports & Analytics** - Charts and performance insights
3. **Notes/Comments** - Add notes for managers
4. **Clock In/Out** - Time tracking with work_logs table
5. **Admin View** - Managers view all employee tasks
6. **Task Approval** - Manager approves employee tasks
7. **Export Reports** - PDF/Excel export

### To Implement These:
The database tables are ready! You just need to create the UI pages:
- `employee/tasks.php` - Task history with filters
- `employee/reports.php` - Analytics dashboard
- `employee/notes.php` - Communication center
- `business/employee_reports.php` - Admin view of all activities

## ⚙️ Configuration

### Upload Settings
Edit `employee/add_task.php` if you need to:
- Change max file size (default: 10MB per file)
- Allowed file types
- Upload directory location

### Task Categories
Categories are stored in `task_categories` table.
Default categories:
- 💰 Sales
- 🤝 Customer Service
- 🏭 Production
- 📋 Administration
- 📢 Marketing
- 🔧 Maintenance

To add more categories:
```sql
INSERT INTO task_categories (user_id, category_name, color_code, icon) 
VALUES (YOUR_USER_ID, 'IT Support', '#3b82f6', '💻');
```

## 🐛 Troubleshooting

### Employee Can't Login
- Check if email exists in employees table
- Check if password was set (password_hash column not null)
- Check if employee status is 'active'
- Check if is_active = 1

### Files Not Uploading
- Check folder permissions: `assets/uploads/tasks/` (should be 755)
- Check PHP upload_max_filesize in php.ini
- Check post_max_size in php.ini

### Missing Database Tables
- Run `db/employee_portal_schema.sql` in phpMyAdmin
- Check for errors in SQL execution

## 📞 Support

### Common Questions:

**Q: Can employees see each other's tasks?**
A: No, employees only see their own tasks.

**Q: Can admin see all employee tasks?**
A: Not yet, but database structure supports it. Create admin view pages.

**Q: How do I delete a task?**
A: Implement delete functionality in tasks.php (database supports cascade delete).

**Q: Can I customize task categories?**
A: Yes, modify task_categories table or add UI for category management.

---

## 🎉 You're All Set!

Your employee portal is ready to use! Employees can now:
✅ Login with their credentials
✅ Track daily activities
✅ Upload documents and photos
✅ View their weekly performance
✅ Manage their task history

**Happy tracking! 📊**
