# 📦 EMPLOYEE INVENTORY MANAGEMENT GUIDE

## ✅ What Changed

**IMPORTANT:** Product and inventory management is now **for EMPLOYEES**, not admins!

- ✅ Employees can add new products
- ✅ Employees can adjust stock levels
- ✅ Employees can view all inventory
- ✅ Employees can track low stock alerts
- ✅ Employees can manage stationery catalog

## 🚀 How to Access

### Method 1: From Dashboard
1. Login as employee at: `http://localhost/MY%20CASH/employee_login.php`
2. Click **📦 Manage Inventory** button (green button in header)

### Method 2: Direct Link
- Go to: `http://localhost/MY%20CASH/employee/manage_inventory.php`

## 📋 What Employees Can Do

### 1️⃣ View Inventory (Tab 1)

**Statistics Dashboard:**
- Total Products
- Active Products  
- Total Stock (all items combined)
- ⚠️ Low Stock items
- 🚫 Out of Stock items

**Filter Options:**
- All Products
- Active Only
- Low Stock (items at or below minimum)
- Out of Stock (zero stock items)

**Search:**
- Search by product name, code, or category
- Real-time filtering

**Product Cards Show:**
- Item name and code
- Category badge
- Current stock level (color-coded: 🟢 good / 🟡 low / 🔴 out)
- Minimum stock level
- Selling price
- Cost price (if entered)
- Quick-add buttons: +10, +50, +100

**Stock Adjustment:**
- Click "📊 Adjust Stock" button on any product
- Choose adjustment type:
  - ➕ **Add to Stock** - When new stock arrives
  - ➖ **Subtract from Stock** - When items are sold/used outside POS
  - 🎯 **Set Exact Amount** - For physical stock count
- Enter quantity
- Add notes (optional)
- System logs adjustment with employee name and timestamp

### 2️⃣ Add Product (Tab 2)

**Required Fields:**
- ✅ Item Name (e.g., "Highlighters (Pack of 4)")
- ✅ Item Code (e.g., "ST-011")
- ✅ Category (dropdown: 7 categories)
- ✅ Unit Price (selling price in RWF)

**Optional Fields:**
- Cost Price (purchase price for profit tracking)
- Current Stock (default: 0)
- Minimum Stock (default: 10)
- Description

**Product Naming Best Practices:**

✅ **GOOD Examples:**
- "A4 Paper (Ream 500 sheets)"
- "Blue Ballpoint Pens (Box of 12)"
- "Highlighters (Pack of 4 colors)"
- "Sticky Notes 3x3 inch (100 sheets)"

❌ **BAD Examples:**
- "Pens" (too vague)
- "Paper" (no specification)
- "Notebook" (missing size/pages)

**Item Code System:**

Format: `ST-XXX` (ST = Stationery, XXX = 001-999)

**Category Ranges:**
- `ST-001` to `ST-099` - Paper Products
- `ST-100` to `ST-199` - Writing Instruments
- `ST-200` to `ST-299` - Office Supplies
- `ST-300` to `ST-399` - Filing Supplies
- `ST-400` to `ST-499` - Electronics
- `ST-500` to `ST-599` - Art Supplies
- `ST-600` to `ST-699` - Desk Accessories

**Alternative Category-Based Codes:**
- `PAP-001` - Paper Products
- `PEN-001` - Writing Instruments
- `OFF-001` - Office Supplies
- `FIL-001` - Filing Supplies
- `ELE-001` - Electronics
- `ART-001` - Art Supplies
- `DSK-001` - Desk Accessories

### 3️⃣ Categories Available

1. **Paper Products**
   - Copy paper, printer paper, notebooks, notepads, sticky notes

2. **Writing Instruments**
   - Pens, pencils, markers, highlighters, crayons

3. **Office Supplies**
   - Staplers, tape, glue, scissors, rulers, calculators

4. **Filing Supplies**
   - Folders, binders, dividers, labels, filing cabinets

5. **Electronics**
   - Calculators, desk lamps, paper shredders, label makers

6. **Art Supplies**
   - Paints, brushes, canvases, drawing paper, art sets

7. **Desk Accessories**
   - Pen holders, desk organizers, calendars, mouse pads

## 🔢 Pricing Guidelines

### Cost Price vs Unit Price

**Cost Price** (optional):
- What you paid to buy the product
- Example: 1,000 RWF

**Unit Price** (required):
- What customers pay
- Example: 1,500 RWF

**Profit Margin Calculation:**
```
Margin = ((Unit Price - Cost Price) / Cost Price) × 100
Example: ((1,500 - 1,000) / 1,000) × 100 = 50%
```

**Typical Markup Ranges:**
- Low margin items: 25-30% (competitive products)
- Standard items: 35-50% (most stationery)
- Specialty items: 50-100% (unique/imported items)

**Pricing Formula:**
```
Unit Price = Cost Price × (1 + Markup%)
Example: 1,000 × 1.5 = 1,500 RWF (50% markup)
```

## 📊 Stock Level Settings

### Current Stock
- How many units you have right now
- Example: 50 boxes

### Minimum Stock
- Trigger point for low stock alert
- When current stock ≤ minimum, item shows as "low stock"

**Calculation Formula:**
```
Minimum Stock = (Daily Sales × Lead Time) + Safety Stock

Example:
- Daily Sales: 5 boxes
- Lead Time: 3 days (time to restock)
- Safety Stock: 15 boxes (buffer)
- Minimum = (5 × 3) + 15 = 30 boxes
```

**Quick Guidelines:**
- Fast-moving items: Higher minimum (30-50 units)
- Slow-moving items: Lower minimum (5-10 units)
- Seasonal items: Adjust based on season

## 🎯 Daily Workflow for Employees

### Morning Routine:
1. Login to employee portal
2. Check **Low Stock** filter
3. Note items that need reordering
4. Report critical items to manager

### When New Stock Arrives:
1. Go to **Manage Inventory**
2. Find the product (use search)
3. Click **📊 Adjust Stock**
4. Select "➕ Add to Stock"
5. Enter quantity received
6. Add note: "Received from supplier [Name]"
7. Click **Update Stock**

### After Sales (End of Day):
1. If you sold items **outside** the POS system (direct sales, manual transactions)
2. Go to **Manage Inventory**
3. Click **📊 Adjust Stock**
4. Select "➖ Subtract from Stock"
5. Enter quantity sold
6. Add note: "Manual sale to [Customer]"

Note: Sales recorded through "Record Transaction" automatically update stock!

### Weekly Stock Count:
1. Physically count each product
2. Go to **Manage Inventory**
3. For each product, click **📊 Adjust Stock**
4. Select "🎯 Set Exact Amount"
5. Enter actual count
6. Add note: "Weekly stock count"

### When Running Low on Stock:
1. Check **Low Stock** filter
2. For critical items (out of stock or very low):
   - Go to **Report Stock Issue** page
   - Submit alert to manager
3. Manager will see alert and arrange reorder

## 🔍 Quick-Add Buttons

Each product card has 3 quick buttons:
- **+10** - Add 10 units (quick restocking)
- **+50** - Add 50 units (medium delivery)
- **+100** - Add 100 units (bulk delivery)

Use these for fast stock updates when receiving standard quantities.

## 📈 Stock Level Color Codes

🟢 **Green (Good Stock):**
- Current stock > minimum stock
- No action needed

🟡 **Yellow (Low Stock):**
- Current stock ≤ minimum stock
- Reorder soon

🔴 **Red (Out of Stock):**
- Current stock = 0
- **URGENT:** Cannot sell, need immediate reorder

## 🔗 Integration with Other Features

### 1. Record Transaction
- When recording a sale, products appear as quick-add buttons
- Clicking a product adds it to the sale
- Stock is automatically reduced (if stock tracking enabled)

### 2. Report Stock Issue
- Products from inventory appear in the stock alert form
- Current stock levels shown
- Color-coded warnings displayed

### 3. Dashboard Statistics
- Low stock count updates in real-time
- Today's sales reflect inventory movement

### 4. Manager Alerts
- Stock alerts you submit go to manager's inventory alerts page
- Manager can see which employee reported the issue
- Status tracking: pending → acknowledged → resolved

## 💡 Best Practices for Employees

### DO ✅
- ✅ Use descriptive product names with pack sizes
- ✅ Keep item codes unique and organized
- ✅ Enter cost price for profit tracking
- ✅ Set realistic minimum stock levels
- ✅ Add notes when adjusting stock
- ✅ Do weekly physical stock counts
- ✅ Report low stock before it runs out
- ✅ Update stock immediately when receiving delivery

### DON'T ❌
- ❌ Use vague names like "Pens" or "Paper"
- ❌ Create duplicate item codes
- ❌ Set minimum stock too low (causes stockouts)
- ❌ Forget to update stock after manual sales
- ❌ Wait until completely out of stock to alert manager
- ❌ Adjust stock without adding notes
- ❌ Ignore yellow/red stock warnings

## 🎓 Example: Adding New Product

Let's add "Sticky Notes 3x3 inch (100 sheets)" to inventory:

**Step 1: Click "➕ Add Product" Tab**

**Step 2: Fill Form**
- Item Name: `Sticky Notes 3x3 inch (100 sheets)`
- Item Code: `ST-012` (next available code)
- Category: `Office Supplies`
- Unit Price: `800` RWF
- Cost Price: `500` RWF (optional)
- Current Stock: `60` packs
- Minimum Stock: `15` packs
- Description: `Yellow sticky notes, 3x3 inch, 100 sheets per pack`

**Step 3: Click "➕ Add Product"**

**Result:**
- Product added to inventory
- Appears in product list
- Shows in quick-add buttons on transaction page
- Available for stock alerts
- Profit margin: ((800-500)/500)×100 = 60%

## 🛠️ Troubleshooting

### Product not showing in inventory?
- Check if you're logged in as employee
- Refresh the page
- Check filter - switch to "All Products"
- Search for product by name or code

### Can't add product - "Item code already exists"?
- Each code must be unique
- Check existing products for duplicates
- Use the next available code (e.g., if ST-012 exists, use ST-013)

### Stock level not updating after sale?
- If you used "Record Transaction" page, stock updates automatically
- If manual sale outside system, use "📊 Adjust Stock" → "Subtract"
- Check if product exists in inventory (some old transactions may not have stock tracking)

### Quick-add buttons not working?
- Make sure JavaScript is enabled
- Hard refresh the page (Ctrl + F5)
- Check browser console for errors

### Want to remove a product?
- **Don't delete!** Instead, set Current Stock to 0
- Products with zero stock still show in history
- Preserves sales records and reports

## 📞 Need Help?

1. **Low stock issues**: Use "Report Stock Issue" page
2. **Product questions**: Ask your manager
3. **System errors**: Contact IT/administrator
4. **Training**: Refer to this guide or ask experienced colleagues

## 🎯 Quick Reference

**Access Inventory:**
- Dashboard → 📦 Manage Inventory button
- OR: `http://localhost/MY%20CASH/employee/manage_inventory.php`

**Add Product:**
- Tab 2 → Fill form → Add Product

**Adjust Stock:**
- Find product → 📊 Adjust Stock → Choose type → Enter qty → Update

**Quick Restock:**
- Find product → +10 / +50 / +100 buttons

**Check Low Stock:**
- Click "Low Stock" filter → See yellow/red items

**Physical Count:**
- Product → 📊 Adjust → Set Exact Amount → Enter count

---

**🎉 You're all set! Employees now have full control over product and inventory management!**
