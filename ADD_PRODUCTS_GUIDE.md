# 📦 STOCK MANAGEMENT GUIDE - Adding Products & Managing Inventory

## 🎯 Overview

This guide shows you how to add new stationery products to your inventory system so employees can select them when recording sales and reporting stock issues.

---

## 📝 Method 1: Using phpMyAdmin (Recommended for Multiple Items)

### Step 1: Access phpMyAdmin
1. Open browser and go to: `http://localhost/phpmyadmin`
2. Select your database (the one used in `includes/db.php`)
3. Find the **stationery_items** table in the left sidebar
4. Click on it to open

### Step 2: Add New Product
1. Click the **Insert** tab at the top
2. Fill in the form fields:

| Field | Description | Example |
|-------|-------------|---------|
| **id** | Leave blank (auto-generated) | - |
| **user_id** | Your user ID from users table | 1 |
| **item_name** | Product name | Correction Tape |
| **item_code** | Unique code for product | ST-011 |
| **category** | Product category | Office Supplies |
| **description** | Details about product | White correction tape, 5mm x 6m |
| **unit_price** | Selling price in RWF | 1200 |
| **cost_price** | Your purchase price | 800 |
| **current_stock** | Current quantity in stock | 25 |
| **minimum_stock** | Alert threshold (when to reorder) | 10 |
| **is_active** | 1 = active, 0 = inactive | 1 |
| **created_at** | Leave blank (auto-generated) | - |
| **updated_at** | Leave blank (auto-generated) | - |

3. Scroll down and click **Go** to save

### Step 3: Verify Product Added
1. Click the **Browse** tab
2. You should see your new product in the list
3. Note the auto-generated ID

---

## 📝 Method 2: Using SQL Query (Fast for Multiple Products)

### Add Single Product
```sql
INSERT INTO stationery_items 
(user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
VALUES 
(1, 'Highlighters (Pack of 4)', 'ST-011', 'Writing Instruments', 'Assorted colors - yellow, pink, green, blue', 1500, 1000, 40, 15, 1);
```

### Add Multiple Products at Once
```sql
INSERT INTO stationery_items 
(user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
VALUES 
(1, 'Highlighters (Pack of 4)', 'ST-011', 'Writing Instruments', 'Assorted colors', 1500, 1000, 40, 15, 1),
(1, 'Sticky Notes (100 sheets)', 'ST-012', 'Office Supplies', '76x76mm yellow sticky notes', 800, 500, 60, 25, 1),
(1, 'Binder Clips (Box of 12)', 'ST-013', 'Office Supplies', 'Medium size 32mm', 600, 400, 35, 20, 1),
(1, 'Rubber Bands (Box)', 'ST-014', 'Office Supplies', 'Assorted sizes', 300, 200, 45, 30, 1),
(1, 'Glue Stick', 'ST-015', 'Office Supplies', '40g solid glue stick', 500, 300, 50, 25, 1),
(1, 'Scissors', 'ST-016', 'Office Supplies', '8-inch office scissors', 2000, 1200, 20, 8, 1),
(1, 'Ruler (30cm)', 'ST-017', 'Office Supplies', 'Plastic transparent ruler', 400, 250, 55, 30, 1),
(1, 'Whiteboard Markers (Set of 4)', 'ST-018', 'Writing Instruments', 'Black, red, blue, green', 2500, 1600, 25, 10, 1),
(1, 'Tape Dispenser', 'ST-019', 'Office Supplies', 'Desktop tape dispenser', 3500, 2000, 12, 5, 1),
(1, 'Paper Clips (Box of 100)', 'ST-020', 'Office Supplies', 'Standard 28mm clips', 400, 250, 70, 40, 1);
```

**How to run SQL:**
1. In phpMyAdmin, select your database
2. Click the **SQL** tab
3. Paste the query above (change user_id to yours)
4. Click **Go**
5. Check for success message

---

## 📝 Method 3: Create Admin Product Management Page (Advanced)

I can create a beautiful UI page for you to manage products. This would include:
- ✅ Add new products via form
- ✅ Edit existing products
- ✅ View all products in a table
- ✅ Search and filter
- ✅ Activate/deactivate products
- ✅ Update stock levels
- ✅ Set reorder alerts

**Would you like me to create this page?**

---

## 🏷️ Product Categories

### Suggested Categories:
1. **Paper Products** - A4 paper, notebooks, envelopes, etc.
2. **Writing Instruments** - Pens, pencils, markers, highlighters
3. **Office Supplies** - Staplers, clips, tape, glue, scissors
4. **Filing Supplies** - Folders, binders, dividers, labels
5. **Electronics** - Calculators, batteries, USB drives
6. **Art Supplies** - Colored pencils, crayons, paint
7. **Desk Accessories** - Organizers, pen holders, trays
8. **Stationery Sets** - Gift sets, combo packs

---

## 💡 Product Naming Best Practices

### Good Names:
- ✅ "Blue Pens (Box of 12)" - Specific and clear
- ✅ "A4 Paper (500 sheets)" - Includes quantity
- ✅ "Highlighters (Pack of 4)" - Mentions pack size
- ✅ "Sticky Notes (100 sheets)" - Clear description

### Avoid:
- ❌ "Pens" - Too vague
- ❌ "Paper" - Not specific enough
- ❌ "Items" - No description
- ❌ "Stock" - Unclear

---

## 🔢 Item Code System

### Recommended Format:
```
ST-XXX
│  │
│  └── Sequential number (001, 002, 003...)
└────── Prefix (ST = Stationery)
```

### Examples:
- ST-001 to ST-099: Paper Products
- ST-100 to ST-199: Writing Instruments
- ST-200 to ST-299: Office Supplies
- ST-300 to ST-399: Filing Supplies
- ST-400 to ST-499: Electronics

### Or use categories:
- PAP-001: Paper products
- PEN-001: Pens
- OFF-001: Office supplies

---

## 💰 Pricing Guidelines

### Unit Price vs Cost Price

**Cost Price** (What you pay):
- Your purchase price from supplier
- Include shipping if applicable
- Used to calculate profit

**Unit Price** (What customer pays):
- Your selling price
- Should be higher than cost price
- Common markup: 25% to 50%

### Example:
```
Cost Price: 1,000 RWF
Markup: 50%
Unit Price: 1,500 RWF
Profit: 500 RWF per unit
```

---

## 📊 Stock Level Settings

### Current Stock
- Actual quantity you have right now
- Gets updated when you sell items
- Manually update when you receive new stock

### Minimum Stock
- Alert threshold
- When stock reaches this level, system alerts you
- Formula: `Minimum = (Daily Sales × Lead Time) + Safety Stock`

### Example:
```
Product: Blue Pens
Daily Sales: 5 boxes
Supplier Lead Time: 3 days
Safety Stock: 15 boxes (3 days extra)

Minimum Stock = (5 × 3) + 15 = 30 boxes
```

---

## 🔄 Updating Stock Levels

### When You Receive New Stock
```sql
-- Add 50 units to A4 Paper stock
UPDATE stationery_items 
SET current_stock = current_stock + 50,
    updated_at = NOW()
WHERE item_code = 'ST-001';
```

### When Stock is Sold (Manual Update)
```sql
-- Subtract 10 units from Blue Pens stock
UPDATE stationery_items 
SET current_stock = current_stock - 10,
    updated_at = NOW()
WHERE item_code = 'ST-002';
```

### Set New Stock Level
```sql
-- Set Notebooks stock to exactly 40
UPDATE stationery_items 
SET current_stock = 40,
    updated_at = NOW()
WHERE item_code = 'ST-003';
```

---

## 🎨 Complete Product Addition Example

### Adding "Permanent Markers (Pack of 6)"

**Via phpMyAdmin Insert:**
```
user_id: 1
item_name: Permanent Markers (Pack of 6)
item_code: ST-021
category: Writing Instruments
description: Black permanent markers, fine tip, 6-pack
unit_price: 3000
cost_price: 2000
current_stock: 30
minimum_stock: 12
is_active: 1
```

**Via SQL:**
```sql
INSERT INTO stationery_items 
(user_id, item_name, item_code, category, description, unit_price, cost_price, current_stock, minimum_stock, is_active)
VALUES 
(1, 'Permanent Markers (Pack of 6)', 'ST-021', 'Writing Instruments', 'Black permanent markers, fine tip, 6-pack', 3000, 2000, 30, 12, 1);
```

**Result:**
- ✅ Employees can now select this product
- ✅ Appears in quick-add buttons
- ✅ Shows current stock: 30
- ✅ Will alert when stock drops below 12
- ✅ Unit price: 3,000 RWF
- ✅ Profit margin: 1,000 RWF per pack

---

## 🗑️ Deactivating Products (Don't Sell Anymore)

### Instead of deleting, deactivate:
```sql
-- Deactivate a product
UPDATE stationery_items 
SET is_active = 0,
    updated_at = NOW()
WHERE item_code = 'ST-010';
```

### Reactivate later:
```sql
-- Reactivate a product
UPDATE stationery_items 
SET is_active = 1,
    updated_at = NOW()
WHERE item_code = 'ST-010';
```

**Why not delete?**
- Preserves sales history
- Can reactivate if you stock it again
- Keeps transaction records intact

---

## 📋 View All Your Products

### See all active products:
```sql
SELECT item_code, item_name, category, current_stock, minimum_stock, unit_price
FROM stationery_items
WHERE is_active = 1
ORDER BY category, item_name;
```

### See low stock items:
```sql
SELECT item_code, item_name, current_stock, minimum_stock
FROM stationery_items
WHERE current_stock <= minimum_stock 
AND is_active = 1
ORDER BY current_stock ASC;
```

### See products by category:
```sql
SELECT item_name, current_stock, unit_price
FROM stationery_items
WHERE category = 'Writing Instruments' 
AND is_active = 1
ORDER BY item_name;
```

---

## ✅ Product Addition Checklist

Before adding a product, have these ready:

- [ ] Product name (clear and descriptive)
- [ ] Unique item code (ST-XXX format)
- [ ] Category (from standard list)
- [ ] Description (optional but helpful)
- [ ] Unit price (selling price in RWF)
- [ ] Cost price (what you paid)
- [ ] Current stock quantity
- [ ] Minimum stock level (reorder point)
- [ ] Set is_active = 1

---

## 🔧 Bulk Import from Excel/CSV

### If you have many products in Excel:

1. **Export to CSV** from Excel
2. **In phpMyAdmin:**
   - Select stationery_items table
   - Click **Import** tab
   - Choose your CSV file
   - Match columns correctly
   - Click **Go**

### CSV Format:
```csv
user_id,item_name,item_code,category,description,unit_price,cost_price,current_stock,minimum_stock,is_active
1,"Highlighters (Pack of 4)","ST-011","Writing Instruments","Assorted colors",1500,1000,40,15,1
1,"Sticky Notes","ST-012","Office Supplies","76x76mm yellow",800,500,60,25,1
```

---

## 🎯 After Adding Products

### What happens:

1. **Employee Dashboard:**
   - New products appear in quick-add buttons
   - Stock levels shown with color coding

2. **Record Transaction:**
   - Employees can select from updated catalog
   - Clicking item auto-fills name and price

3. **Report Stock:**
   - New products available for stock alerts
   - Current stock levels displayed

4. **Manager View:**
   - All products trackable
   - Low stock alerts for new items

---

## 💡 Pro Tips

### Tip 1: Start with common items
- Add your top 20 selling items first
- Add more as needed

### Tip 2: Set realistic minimum stock
- Track sales for 1-2 weeks
- Calculate daily average
- Set minimum = (daily avg × lead time) + buffer

### Tip 3: Use consistent naming
- Always include pack size
- Use same format for similar items
- Makes searching easier

### Tip 4: Regular stock counts
- Do physical count weekly or monthly
- Update current_stock in database
- Catch discrepancies early

### Tip 5: Price reviews
- Review prices monthly
- Update based on supplier changes
- Keep profit margin consistent

---

## 🆘 Troubleshooting

### Product not showing in employee forms
- Check `is_active = 1`
- Verify `user_id` matches
- Clear browser cache
- Refresh the page

### Wrong stock level
- Run manual update SQL
- Check for duplicate entries
- Verify item_code is unique

### Can't add product
- Check all required fields filled
- Ensure item_code is unique
- Verify user_id exists in users table

---

## 🎉 You're Ready!

You can now:
- ✅ Add new stationery products
- ✅ Set prices and stock levels
- ✅ Organize by categories
- ✅ Update stock quantities
- ✅ Deactivate discontinued items
- ✅ Track inventory effectively

**Need a UI to manage this? Let me know and I'll create a beautiful product management page!** 🚀
