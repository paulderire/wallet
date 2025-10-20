# 📦 Stationery Business System - Complete Setup Guide

## 🎯 Overview

The employee portal has been completely redesigned to support **stationery business operations**. Employees can now:

- 💰 **Record Daily Sales** - Track customer transactions with payment methods
- ⚠️ **Report Stock Issues** - Alert management about missing or low inventory
- 📊 **View Financial Summaries** - See daily/weekly sales by payment method
- 📦 **Track Inventory** - Monitor stock levels and get alerts

Managers can:
- 🚨 **View All Inventory Alerts** - Monitor stock issues across all employees
- ✅ **Respond to Alerts** - Acknowledge and resolve stock issues
- 📈 **Track Sales Performance** - View daily sales summaries by employee

---

## 🗄️ Database Setup

### Step 1: Import the Schema

**IMPORTANT:** You MUST import the new database schema before using the system!

1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Select your database (the one used in `includes/db.php`)
3. Click the **Import** tab
4. Click **Choose File** and select: `db/stationery_business_schema.sql`
5. Scroll down and click **Go**
6. Wait for success message: "Import has been successfully finished"

### Step 2: Verify Tables

After import, verify these tables exist:

✅ **employee_tasks** - Now includes new columns:
   - `customer_name` (VARCHAR)
   - `items_sold` (TEXT)
   - `total_amount` (DECIMAL)
   - `payment_method` (ENUM: cash, mobile_money, bank_transfer, credit)
   - `transaction_type` (ENUM: sale, expense, stock_alert, other)

✅ **inventory_alerts** - NEW TABLE
   - Stores stock alerts from employees
   - Tracks: item name, quantity, alert type, urgency, status
   - Status flow: pending → acknowledged → resolved

✅ **daily_sales_summary** - NEW TABLE
   - Aggregates daily sales by employee
   - Breaks down by payment method
   - Tracks opening/closing balances

✅ **stationery_items** - NEW TABLE
   - Catalog of stationery items
   - Includes: item name, code, category, prices, stock levels
   - Pre-loaded with 10 common items (A4 paper, pens, notebooks, etc.)

---

## 📁 New Files Created

### Employee Portal Files

1. **employee/dashboard.php** ✅
   - Redesigned for stationery business
   - Shows today's sales by payment method
   - Displays recent transactions
   - Lists pending stock alerts
   - Week's sales summary

2. **employee/record_transaction.php** ✅
   - Record sales transactions
   - Customer name + items sold
   - Payment method selection
   - Quick-add from stationery catalog
   - Auto-calculates totals

3. **employee/report_stock.php** ✅
   - Report missing/low stock items
   - Alert types: out_of_stock, low_stock, damaged, expired
   - Urgency levels: low, medium, high, critical
   - Quick-select from catalog
   - View recent alerts submitted

### Manager Files

4. **business/inventory_alerts.php** ✅
   - View all employee stock alerts
   - Filter by status, urgency, alert type
   - Acknowledge alerts
   - Mark as resolved
   - Reopen resolved alerts
   - Statistics dashboard

### Database Files

5. **db/stationery_business_schema.sql** ✅
   - Complete database schema
   - Table modifications + new tables
   - Sample data (10 stationery items)
   - Task categories update

---

## 🚀 Quick Start Guide

### For Employees

1. **Login** at `http://localhost/MY%20CASH/index.php`
   - Use your employee credentials (set by manager)

2. **Dashboard** - You'll see:
   - Today's total sales
   - Sales breakdown by payment method (cash, mobile money, bank, credit)
   - Recent transactions
   - Pending stock alerts
   - Week's total sales

3. **Record a Sale:**
   - Click **"💰 Record Sale"** button
   - Select transaction type (Sale/Expense/Other)
   - Choose payment method
   - Enter customer name
   - Enter total amount
   - Describe items sold (or use quick-add buttons)
   - Add notes if needed
   - Click **"💾 Record Transaction"**

4. **Report Stock Issue:**
   - Click **"⚠️ Report Stock Issue"** button
   - Enter or select item name from catalog
   - Choose alert type (Out of Stock, Low Stock, Damaged, Expired)
   - Select urgency (Low, Medium, High, Critical)
   - Enter current quantity (0 if completely out)
   - Add notes describing the issue
   - Click **"🚨 Submit Alert"**

### For Managers/Admins

1. **Login** at `http://localhost/MY%20CASH/index.php`
   - Use your admin credentials

2. **View Inventory Alerts:**
   - Navigate to **Business** → **Inventory Alerts**
   - Or visit: `http://localhost/MY%20CASH/business/inventory_alerts.php`

3. **Manage Alerts:**
   - Filter by status (Pending/Acknowledged/Resolved)
   - Filter by urgency (Critical/High/Medium/Low)
   - Filter by alert type (Out of Stock/Low Stock/Damaged/Expired)
   - Click **"👁️ Ack"** to acknowledge an alert
   - Click **"✅ Resolve"** to mark as resolved
   - Click **"↩️ Reopen"** to reopen if needed

---

## 📊 Features Breakdown

### Payment Methods Supported

1. **💵 Cash** - Physical cash payments
2. **📱 Mobile Money** - MTN MoMo, Airtel Money, etc.
3. **🏦 Bank Transfer** - Direct bank transfers
4. **💳 Credit** - Credit purchases (pay later)

### Alert Types

1. **🚫 Out of Stock** - Item completely depleted
2. **📉 Low Stock** - Running low, need restock soon
3. **💔 Damaged** - Items damaged, need replacement
4. **⏰ Expired** - Expired items need disposal

### Urgency Levels

1. **🔴 Critical** - Immediate action required (affects business operations)
2. **🟠 High** - Urgent, address within 24 hours
3. **🟡 Medium** - Important, address within 2-3 days
4. **🟢 Low** - Monitor, address when convenient

---

## 🔧 Configuration

### Stationery Catalog Management

The system comes pre-loaded with 10 common items. To add more products:

**📚 See Complete Guide:** `ADD_PRODUCTS_GUIDE.md`

**Quick Method:**
1. Open **phpMyAdmin**
2. Navigate to **stationery_items** table
3. Click **Insert** tab
4. Fill in:
   - `item_name` - Product name (e.g., "Highlighters (Pack of 4)")
   - `item_code` - Unique code (e.g., ST-011)
   - `category` - Office Supplies, Writing Instruments, etc.
   - `unit_price` - Selling price in RWF
   - `cost_price` - Purchase price in RWF
   - `current_stock` - Current quantity
   - `minimum_stock` - Alert threshold
   - `is_active` - 1 (active) or 0 (inactive)

**For bulk import or detailed instructions, see `ADD_PRODUCTS_GUIDE.md`**

### Pre-loaded Items

| Item | Code | Category | Price (RWF) | Min Stock |
|------|------|----------|-------------|-----------|
| A4 Paper (500 sheets) | ST-001 | Paper Products | 5,000 | 20 |
| Blue Pens (Box of 12) | ST-002 | Writing Instruments | 200 | 50 |
| Notebooks (A5) | ST-003 | Paper Products | 1,500 | 30 |
| Staplers | ST-004 | Office Supplies | 3,000 | 10 |
| Staples (Box) | ST-005 | Office Supplies | 500 | 20 |
| Calculators | ST-006 | Electronics | 8,000 | 5 |
| Envelopes (Pack of 50) | ST-007 | Paper Products | 2,000 | 15 |
| File Folders | ST-008 | Filing Supplies | 1,000 | 25 |
| Markers (Set of 4) | ST-009 | Writing Instruments | 2,500 | 20 |
| Correction Fluid | ST-010 | Office Supplies | 800 | 30 |

---

## 📈 Reporting & Analytics

### Daily Sales Summary

View by employee in `daily_sales_summary` table:
- Total sales per day
- Breakdown by payment method
- Transaction count
- Opening/closing balance tracking

### Inventory Alerts Tracking

Monitor in `inventory_alerts` table:
- Alert creation date/time
- Employee who reported
- Current status (pending/acknowledged/resolved)
- Urgency level
- Resolution timestamp

---

## 🎨 User Interface

### Design Features

- **Purple Gradient Theme** - Modern, professional look
- **Glassmorphism Cards** - Frosted glass effect with blur
- **Responsive Design** - Works on desktop, tablet, mobile
- **Real-time Statistics** - Live updates of sales and alerts
- **Color-coded Urgency** - Visual priority indicators
- **Quick-add Buttons** - Fast item selection from catalog

### Icons Used

- 💰 Sales & Transactions
- ⚠️ Alerts & Warnings
- 📊 Statistics & Analytics
- 💵 Cash Payments
- 📱 Mobile Money
- 🏦 Bank Transfers
- 💳 Credit
- ✅ Completed/Resolved
- 🔴 Critical Urgency
- 📦 Stock/Inventory

---

## ✅ Testing Checklist

After setup, test these workflows:

### Employee Workflow

- [ ] Login as employee
- [ ] View dashboard with empty state
- [ ] Record first sale (cash payment)
- [ ] Record sale with mobile money
- [ ] Use quick-add buttons for items
- [ ] View transaction in dashboard
- [ ] Report out-of-stock item (critical)
- [ ] Report low-stock item (medium)
- [ ] View alerts in dashboard

### Manager Workflow

- [ ] Login as admin
- [ ] Navigate to inventory alerts page
- [ ] See pending alerts from employee
- [ ] Filter by urgency (critical)
- [ ] Acknowledge an alert
- [ ] Resolve an alert
- [ ] Filter by status (resolved)
- [ ] Reopen a resolved alert
- [ ] View statistics summary

---

## 🐛 Troubleshooting

### "Table doesn't exist" Error

**Problem:** inventory_alerts, daily_sales_summary, or stationery_items not found

**Solution:**
1. You didn't import `db/stationery_business_schema.sql`
2. Open phpMyAdmin
3. Import the schema file
4. Refresh the page

### "Unknown column" Error

**Problem:** employee_tasks missing new columns (customer_name, items_sold, etc.)

**Solution:**
1. The schema import failed or was incomplete
2. Re-import `db/stationery_business_schema.sql`
3. Check for error messages during import
4. Verify columns exist in employee_tasks table

### Quick-add Buttons Not Working

**Problem:** No items showing in quick-add section

**Solution:**
1. Check if stationery_items table has data
2. Run this query in phpMyAdmin:
   ```sql
   SELECT * FROM stationery_items WHERE is_active = 1;
   ```
3. If empty, re-import the schema (it includes sample data)

### Can't See Sales Data

**Problem:** Dashboard shows zero sales even after recording

**Solution:**
1. Check transaction was saved: `SELECT * FROM employee_tasks WHERE transaction_type = 'sale'`
2. Verify employee_id matches logged-in employee
3. Check task_date is today's date
4. Clear browser cache and refresh

---

## 🔐 Security Notes

- ✅ All forms use POST method for data submission
- ✅ SQL injection prevention with prepared statements
- ✅ Session validation on every page
- ✅ Employee/Admin role separation
- ✅ Input validation and sanitization
- ✅ HTML output escaping with `htmlspecialchars()`

---

## 📞 Support

### File Locations

```
MY CASH/
├── employee/
│   ├── dashboard.php          (Main employee dashboard)
│   ├── record_transaction.php (Sales recording form)
│   └── report_stock.php       (Stock alert form)
├── business/
│   ├── inventory_alerts.php   (Manager alert view)
│   └── add_employee.php       (Add employees)
├── db/
│   └── stationery_business_schema.sql (Database schema)
└── includes/
    └── db.php                 (Database connection)
```

### Database Tables

```
employees              (Employee information)
employee_tasks         (Modified: sales transactions)
inventory_alerts       (New: stock alerts)
daily_sales_summary    (New: sales aggregation)
stationery_items       (New: product catalog)
```

---

## 🎉 You're All Set!

The stationery business system is now ready to use. Your employees can start recording transactions and reporting stock issues immediately after you import the database schema.

**Next Steps:**
1. ✅ Import `db/stationery_business_schema.sql` in phpMyAdmin
2. ✅ Add employee login passwords via `business/setup_employee_access.php`
3. ✅ Test employee login and record a test transaction
4. ✅ Test stock alert submission
5. ✅ Test manager alert view and status updates
6. ✅ Add your actual stationery items to the catalog
7. ✅ Train employees on the new system

**Happy Managing! 🚀**
