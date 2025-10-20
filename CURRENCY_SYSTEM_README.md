# RWF Currency System - Implementation Guide

## Overview
The MY CASH app displays all amounts in **Rwandan Francs (RWF)** with USD equivalents shown below.

**IMPORTANT**: All amounts in the database are already in RWF. The system does NOT convert them - it only adds USD conversion for display.

## Exchange Rate
- **Current Rate**: 1,500 RWF = 1 USD
- **Location**: Defined in `includes/currency_helper.php` and `includes/footer.php`
- **How to Update**: Change the conversion rate in both files

## How It Works

### 1. Display Only (No Conversion)
Your existing RWF amounts stay exactly the same. The system just calculates and shows the USD equivalent:

**Database**: `1300000` (RWF)
**Display**: 
```
RWF 1,300,000
~$1,000.00
```

### 2. Display Format
- **Primary**: Original RWF amount (no decimals, comma separated)
- **Secondary**: USD equivalent (2 decimals, with ~ prefix)
- **Styling**: USD amount is smaller and slightly faded

### 3. Files Modified

#### `includes/currency_helper.php` (NEW)
PHP helper functions for currency conversion:
- `format_rwf_amount($amount)` - Formats RWF with USD below
- `rwf_to_usd($rwf)` - Converts RWF to USD (for display only)
- Exchange rate constant: `RWF_TO_USD_RATE`

#### `includes/footer.php` (UPDATED)
JavaScript now:
- Detects all `.amount` elements with `data-currency` and `data-amount`
- **Keeps original RWF amounts unchanged**
- Calculates USD equivalent (divides by 1,300)
- Displays dual-currency format
- Adds `data-amount-rwf` and `data-amount-usd` attributes

#### `assets/css/style.css` (UPDATED)
New CSS classes:
- `.dual-currency` - Container for dual-currency display
- `.primary-amount` - RWF amount styling
- `.secondary-amount` - USD amount styling (smaller, faded)

## Usage

### In Existing Code
No changes needed! Your existing amounts work as-is:
```php
<span class="amount" data-currency="RWF" data-amount="1300000">1,300,000</span>
```

Automatically displays as:
```
RWF 1,300,000
~$1,000.00
```

### For New Code

**Standard pattern:**
```php
<span class="amount" data-currency="RWF" data-amount="<?=$balance?>">
    <?=number_format($balance,0)?>
</span>
```

**Using PHP helper:**
```php
<?php
require_once __DIR__ . '/../includes/currency_helper.php';
echo format_rwf_amount($balance);
?>
```

## Visual Example

### Before:
```
Balance: 1,250,000
```

### After:
```
Balance: RWF 1,250,000
         ~$961.54
```

## Updating Exchange Rate

The exchange rate appears in TWO files. Update both:

### 1. `includes/footer.php` (Line ~15)
```javascript
const RWF_TO_USD = 1 / 1300; // Change 1300 to new rate
```

### 2. `includes/currency_helper.php` (Line ~8)
```php
define('RWF_TO_USD_RATE', 1 / 1300); // Change 1300 to new rate
```

**Example** for 1 USD = 1,350 RWF:
```javascript
const RWF_TO_USD = 1 / 1350;
```
```php
define('RWF_TO_USD_RATE', 1 / 1350);
```

## Testing

1. Visit any page with amounts (e.g., Dashboard)
2. Check that amounts show:
   - RWF value on top (no decimals)
   - USD value below (with decimals, prefixed with ~)
3. Verify calculations are correct

## Benefits

✨ **User-Friendly**: Local currency first
💰 **Transparent**: USD equivalent always visible
🔄 **Automatic**: Works with existing data
⚡ **Fast**: Client-side conversion
🎨 **Clean**: Professional dual-currency display
📱 **Responsive**: Works on all screen sizes

## Notes

- RWF amounts are rounded (no decimals) as is standard
- USD amounts show 2 decimals for precision
- The `~` symbol indicates "approximately"
- Exchange rate is fixed (not live API) - update manually as needed
