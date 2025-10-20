# 🔔 Global Notifications & Messages System - Setup Guide

## ✨ What's Been Created

I've built a complete notifications and messaging system that works across your entire app for both admins and employees.

---

## 📁 Files Created

1. **`setup_notifications.php`** - Database setup script
2. **`includes/notifications_api.php`** - Backend API for notifications/messages
3. **`includes/notification_widget.php`** - Bell icon dropdown widget
4. **`db/notifications_messages_schema.sql`** - Database schema documentation
5. **`cleanup_test_data.php`** - Enhanced cleanup script (removes all test data)

---

## 🚀 Setup Instructions

### Step 1: Create Database Tables
1. Open: **http://localhost/MY CASH/setup_notifications.php**
2. This creates two tables:
   - `notifications` - Individual notifications for users/employees
   - `messages` - System-wide messages and announcements

### Step 2: Clean Test Data (Optional)
1. Open: **http://localhost/MY CASH/cleanup_test_data.php**
2. This removes:
   - All test notifications and messages
   - All transactions, forex trades, employee tasks
   - All chat data, budgets, goals, loans
   - AI logs and test files
   - **Preserves**: User accounts, employee records, database structure

### Step 3: Use the System
The notification bell icon is already added to your header and works automatically!

---

## 🎯 Features

### For All Users (Admin & Employees):
- ✅ **Bell Icon in Header** - Shows unread count badge
- ✅ **Dropdown Panel** - Click bell to see notifications
- ✅ **Real-time Updates** - Auto-refreshes every 30 seconds
- ✅ **Mark as Read** - Individual or "Mark all read"
- ✅ **Action URLs** - Click notification to navigate
- ✅ **Priority Levels** - Urgent, High, Medium, Low
- ✅ **Icons & Types** - Success, Info, Warning, Error, Task, Payment, etc.
- ✅ **Time Stamps** - Shows "2m ago", "5h ago", etc.

### API Endpoints:

**`includes/notifications_api.php`**

| Action | Method | Description |
|--------|--------|-------------|
| `get_notifications` | GET | Get user's notifications (limit, unread_only) |
| `get_unread_count` | GET | Get count of unread notifications |
| `mark_notification_read` | POST | Mark single notification as read |
| `mark_all_read` | POST | Mark all notifications as read |
| `delete_notification` | POST | Delete a notification |
| `create_notification` | POST | Create new notification (admin only) |
| `get_messages` | GET | Get system messages |
| `mark_message_read` | POST | Mark message as read |

---

## 💡 Usage Examples

### Create a Notification (PHP):
```php
// Example: Notify employee about new task
$formData = [
    'action' => 'create_notification',
    'employee_id' => 3,
    'type' => 'task',
    'title' => 'New Task Assigned',
    'message' => 'You have been assigned to Project Alpha',
    'action_url' => '/MY CASH/pages/tasks.php?id=123',
    'priority' => 'high',
    'icon' => '📋'
];

// Send to API
$ch = curl_init('http://localhost/MY CASH/includes/notifications_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
```

### Or insert directly:
```php
$stmt = $conn->prepare("
    INSERT INTO notifications 
    (employee_id, type, title, message, action_url, priority, icon)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    3, // employee_id
    'task',
    'New Task Assigned',
    'You have been assigned to Project Alpha',
    '/MY CASH/pages/tasks.php?id=123',
    'high',
    '📋'
]);
```

### JavaScript Example:
```javascript
// Get notifications
fetch('/MY CASH/includes/notifications_api.php?action=get_notifications&limit=10')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log('Notifications:', data.data);
        }
    });

// Mark as read
const formData = new FormData();
formData.append('action', 'mark_notification_read');
formData.append('notification_id', 123);

fetch('/MY CASH/includes/notifications_api.php', {
    method: 'POST',
    body: formData
}).then(r => r.json());
```

---

## 🎨 Notification Types & Icons

| Type | Icon | Use Case |
|------|------|----------|
| `success` | ✅ | Success messages |
| `info` | ℹ️ | General information |
| `warning` | ⚠️ | Warnings |
| `error` | ❌ | Error alerts |
| `task` | 📋 | Task assignments |
| `payment` | 💰 | Payment notifications |
| `message` | 💬 | New messages |
| `alert` | 🔔 | General alerts |

---

## 🔧 Customization

### Change Poll Interval:
Edit `includes/notification_widget.php` line ~280:
```javascript
// Change from 30 seconds to 60 seconds
setInterval(updateBadge, 60000);
```

### Add New Notification Types:
Edit `includes/notification_widget.php` function `getIconForType()`:
```javascript
const icons = {
    success: '✅',
    info: 'ℹ️',
    // Add your custom types here
    'invoice': '🧾',
    'delivery': '📦'
};
```

---

## ✅ What Works Now

1. **Header Widget** - Bell icon with badge shows in header for logged-in users
2. **Database Tables** - Run setup_notifications.php to create
3. **API Ready** - All endpoints functional
4. **Auto-refresh** - Updates every 30 seconds
5. **Dark Mode** - Fully themed for light/dark modes
6. **Responsive** - Works on mobile devices
7. **Both User Types** - Admins and employees supported

---

## 🎯 Next Steps

1. Run **setup_notifications.php** to create tables
2. (Optional) Run **cleanup_test_data.php** to clear test data
3. Test by creating a sample notification via PHP/MySQL
4. Integrate into your workflows (tasks, payments, etc.)

---

## 📝 Database Schema

### `notifications` Table:
- `id` - Auto increment primary key
- `user_id` - Admin/user receiving notification (nullable)
- `employee_id` - Employee receiving notification (nullable)
- `type` - Notification type (success, info, warning, error, task, payment, etc.)
- `title` - Notification title (255 chars)
- `message` - Notification message (text)
- `action_url` - Optional URL to navigate to
- `is_read` - Read status (0 or 1)
- `created_at` - Creation timestamp
- `read_at` - When marked as read
- `icon` - Optional emoji/icon
- `priority` - low, medium, high, urgent

### `messages` Table:
- `id` - Auto increment primary key
- `sender_id` - Who sent (admin/user/employee)
- `sender_type` - user, employee, or system
- `recipient_id` - Specific recipient or NULL for broadcast
- `recipient_type` - user, employee, or all
- `subject` - Message subject (255 chars)
- `message` - Message body (text)
- `category` - announcement, alert, update, etc.
- `is_read` - Read status
- `created_at` - Creation timestamp
- `read_at` - When read
- `priority` - low, medium, high

---

## 🆘 Troubleshooting

**Bell icon not showing?**
- Make sure you're logged in
- Check browser console for errors
- Verify notification_widget.php is included in header.php

**No notifications loading?**
- Run setup_notifications.php to create tables
- Check database has notifications for your user_id/employee_id
- Check browser console network tab for API errors

**Badge not updating?**
- Clear browser cache
- Check JavaScript console for errors
- Verify API endpoint is accessible

---

## 🎉 You're All Set!

Your app now has a professional notification system! Users will see notifications in real-time with a beautiful dropdown interface.

**Open these URLs:**
1. http://localhost/MY CASH/setup_notifications.php (setup)
2. http://localhost/MY CASH/cleanup_test_data.php (cleanup - optional)
3. http://localhost/MY CASH/pages/dashboard.php (test it!)
