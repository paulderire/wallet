# 🚀 Stationery Business System - Quick Reference

## 🔑 Employee Quick Guide

### Login
- URL: `http://localhost/MY%20CASH/index.php`
- Use credentials provided by manager

### Daily Tasks

#### 1️⃣ Record a Sale
1. Click **"💰 Record Sale"** on dashboard
2. Fill in:
   - Transaction Type: **Sale**
   - Payment Method: Cash/Mobile Money/Bank/Credit
   - Customer Name
   - Total Amount (RWF)
   - Items Sold (or use quick-add buttons)
3. Click **"💾 Record Transaction"**

#### 2️⃣ Report Stock Issue
1. Click **"⚠️ Report Stock Issue"** on dashboard
2. Fill in:
   - Item Name (or click quick-select)
   - Alert Type: Out of Stock/Low Stock/Damaged/Expired
   - Urgency: Low/Medium/High/Critical
   - Current Quantity
   - Notes (describe the issue)
3. Click **"🚨 Submit Alert"**

### Dashboard Overview
- **Today's Sales**: Total amount and breakdown by payment method
- **Recent Transactions**: Your last 10 transactions
- **Stock Alerts**: Your pending alerts
- **Week's Total**: 7-day sales summary

---

## 👔 Manager Quick Guide

### View Inventory Alerts
- URL: `http://localhost/MY%20CASH/business/inventory_alerts.php`

### Manage Alerts

#### Filter Alerts
- **By Status**: Pending / Acknowledged / Resolved
- **By Urgency**: Critical / High / Medium / Low
- **By Type**: Out of Stock / Low Stock / Damaged / Expired

#### Take Action
- **👁️ Acknowledge**: Mark that you've seen the alert
- **✅ Resolve**: Mark issue as fixed
- **↩️ Reopen**: Reopen a resolved alert

### Dashboard Statistics
- Total Alerts
- Pending Count
- Critical Alerts
- High Priority Alerts
- Acknowledged Count
- Resolved Count

---

## 💳 Payment Methods

| Icon | Method | Description |
|------|--------|-------------|
| 💵 | Cash | Physical cash payments |
| 📱 | Mobile Money | MTN MoMo, Airtel Money, etc. |
| 🏦 | Bank Transfer | Direct bank transfers |
| 💳 | Credit | Credit purchases (pay later) |

---

## ⚠️ Alert Urgency Levels

| Level | Icon | When to Use | Response Time |
|-------|------|-------------|---------------|
| 🔴 Critical | Critical | Completely out, affecting sales | Immediate |
| 🟠 High | High | Very low, will run out soon | Within 24 hours |
| 🟡 Medium | Medium | Below minimum stock | 2-3 days |
| 🟢 Low | Low | Monitor situation | When convenient |

---

## 🎯 Best Practices

### For Employees

✅ **Record sales immediately** after each transaction
✅ **Be specific** when describing items sold
✅ **Use correct payment method** for accurate tracking
✅ **Report stock issues** as soon as you notice them
✅ **Set appropriate urgency** levels on alerts
✅ **Add notes** to explain unusual situations

❌ **Don't delay** recording transactions
❌ **Don't ignore** low stock situations
❌ **Don't forget** to include customer names on sales

### For Managers

✅ **Check alerts daily** (especially critical/high)
✅ **Acknowledge alerts** to show you're aware
✅ **Resolve promptly** to keep employees informed
✅ **Use filters** to prioritize critical issues
✅ **Review sales summaries** weekly

❌ **Don't ignore** pending alerts
❌ **Don't forget** to mark alerts as resolved
❌ **Don't delay** restocking critical items

---

## 🔢 Common Workflows

### Morning Routine (Employee)
1. Login to system
2. Review yesterday's uncompleted alerts
3. Check stock levels on popular items
4. Prepare to record today's sales

### During Business Hours (Employee)
1. Record each sale immediately
2. Note any stock running low
3. Report critical stock issues urgently
4. Track payment methods accurately

### End of Day (Employee)
1. Review today's total sales
2. Submit any remaining stock alerts
3. Check all transactions recorded
4. Report any discrepancies

### Daily Review (Manager)
1. Check critical/high urgency alerts
2. Review pending alerts from all employees
3. Acknowledge new alerts
4. Order stock for critical items
5. Resolve fixed issues
6. Review daily sales by employee

---

## 📊 Pre-loaded Stationery Items

| Item | Price (RWF) | Min Stock |
|------|-------------|-----------|
| A4 Paper (500 sheets) | 5,000 | 20 |
| Blue Pens (Box of 12) | 200 | 50 |
| Notebooks (A5) | 1,500 | 30 |
| Staplers | 3,000 | 10 |
| Staples (Box) | 500 | 20 |
| Calculators | 8,000 | 5 |
| Envelopes (Pack of 50) | 2,000 | 15 |
| File Folders | 1,000 | 25 |
| Markers (Set of 4) | 2,500 | 20 |
| Correction Fluid | 800 | 30 |

---

## 🆘 Quick Troubleshooting

### "Can't see my transaction"
- Refresh the page
- Check you're logged in as correct employee
- Verify transaction date is today

### "Quick-add buttons not showing"
- Database schema not imported
- Import `db/stationery_business_schema.sql`

### "Manager can't see my alerts"
- Alert saved to database but manager needs to refresh
- Check filters aren't hiding your alert
- Verify alert status is "pending"

### "Wrong sales total"
- Double-check amount entered
- Verify payment method selected correctly
- Check if multiple transactions recorded by accident

---

## 📱 Mobile Access

The system is **fully responsive**:
- ✅ Works on phones and tablets
- ✅ Touch-friendly buttons
- ✅ Optimized for small screens
- ✅ Same features as desktop

---

## 🔐 Security Reminders

- 🔒 **Always logout** when leaving the computer
- 🔒 **Don't share** login credentials
- 🔒 **Report suspicious** activity to manager
- 🔒 **Use secure passwords** (manager sets these)

---

## 📞 Need Help?

1. **Database Issues**: Check phpMyAdmin, verify schema imported
2. **Login Problems**: Contact manager to reset password
3. **Missing Features**: Verify you're using correct page URLs
4. **Data Not Saving**: Check database connection in `includes/db.php`

---

## ✅ First Time Setup Checklist

### Manager Tasks
- [ ] Import `db/stationery_business_schema.sql` in phpMyAdmin
- [ ] Add employee records via `business/add_employee.php`
- [ ] Set employee passwords via `business/setup_employee_access.php`
- [ ] Add/update stationery items in catalog
- [ ] Test employee login
- [ ] Test alert system

### Employee Tasks
- [ ] Login with credentials from manager
- [ ] Familiarize with dashboard
- [ ] Record a test sale
- [ ] Try quick-add buttons
- [ ] Submit a test stock alert
- [ ] Review your recent transactions

---

**Remember**: The system is designed to make your daily operations easier. Record transactions promptly, report stock issues immediately, and communicate with your team! 🎉
