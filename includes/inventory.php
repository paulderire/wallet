<?php
/**
 * Inventory Management Functions
 * Handles automatic stock deduction, alerts, and movement tracking
 */

if (!isset($conn)) {
    die("Database connection required. Include db.php first.");
}

/**
 * Auto-create inventory tables if they don't exist
 */
function ensureInventoryTables($conn) {
    try {
        $schema = file_get_contents(__DIR__ . '/../db/inventory_auto_deduct_schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
    } catch (Exception $e) {
        // Tables might already exist, that's okay
    }
}

/**
 * Deduct stock for a sale transaction
 * 
 * @param PDO $conn Database connection
 * @param int $item_id Product ID
 * @param int $quantity Quantity sold
 * @param int $transaction_id Transaction reference
 * @param int $employee_id Employee who made the sale
 * @param string $notes Optional notes
 * @return array ['success' => bool, 'message' => string, 'new_stock' => int]
 */
function deductStock($conn, $item_id, $quantity, $transaction_id = null, $employee_id = null, $notes = '') {
    try {
        // Get current product details
        $stmt = $conn->prepare("SELECT id, item_name, current_stock, minimum_stock, reorder_point, auto_deduct_enabled, unit_price 
                                FROM stationery_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found', 'new_stock' => 0];
        }
        
        // Check if auto-deduct is enabled
        if (empty($product['auto_deduct_enabled'])) {
            return ['success' => false, 'message' => 'Auto-deduct disabled for this product', 'new_stock' => $product['current_stock']];
        }
        
        $previous_stock = $product['current_stock'];
        $new_stock = max(0, $previous_stock - $quantity); // Don't allow negative stock
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE stationery_items SET current_stock = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_stock, $item_id]);
        
        // Log stock movement
        $total_value = $quantity * ($product['unit_price'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO stock_movements 
                                (item_id, transaction_id, employee_id, movement_type, quantity, previous_stock, new_stock, unit_price, total_value, notes)
                                VALUES (?, ?, ?, 'sale', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $item_id, 
            $transaction_id, 
            $employee_id, 
            $quantity, 
            $previous_stock, 
            $new_stock,
            $product['unit_price'],
            $total_value,
            $notes
        ]);
        
        // Check for low stock and create alert if needed
        checkAndCreateLowStockAlert($conn, $item_id, $new_stock, $product['minimum_stock'], $product['reorder_point'] ?? $product['minimum_stock'], $product['item_name']);
        
        return [
            'success' => true, 
            'message' => 'Stock deducted successfully', 
            'new_stock' => $new_stock,
            'was_low_before' => $previous_stock <= $product['minimum_stock'],
            'is_low_now' => $new_stock <= $product['minimum_stock']
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deducting stock: ' . $e->getMessage(), 'new_stock' => 0];
    }
}

/**
 * Add stock (for restocking/returns)
 */
function addStock($conn, $item_id, $quantity, $movement_type = 'purchase', $employee_id = null, $notes = '') {
    try {
        // Get current product details
        $stmt = $conn->prepare("SELECT id, item_name, current_stock, unit_price FROM stationery_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        $previous_stock = $product['current_stock'];
        $new_stock = $previous_stock + $quantity;
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE stationery_items 
                                SET current_stock = ?, last_restock_date = NOW(), updated_at = NOW() 
                                WHERE id = ?");
        $stmt->execute([$new_stock, $item_id]);
        
        // Log stock movement
        $total_value = $quantity * ($product['unit_price'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO stock_movements 
                                (item_id, employee_id, movement_type, quantity, previous_stock, new_stock, unit_price, total_value, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $item_id,
            $employee_id,
            $movement_type,
            $quantity,
            $previous_stock,
            $new_stock,
            $product['unit_price'],
            $total_value,
            $notes
        ]);
        
        // Resolve low stock alert if stock is now sufficient
        resolveStockAlert($conn, $item_id);
        
        return [
            'success' => true,
            'message' => 'Stock added successfully',
            'new_stock' => $new_stock
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding stock: ' . $e->getMessage()];
    }
}

/**
 * Check stock levels and create alert if low
 */
function checkAndCreateLowStockAlert($conn, $item_id, $current_stock, $minimum_stock, $reorder_point, $item_name) {
    try {
        $notification_type = 'low';
        
        if ($current_stock == 0) {
            $notification_type = 'out_of_stock';
        } elseif ($current_stock <= ($minimum_stock / 2)) {
            $notification_type = 'critical';
        }
        
        // Only create alert if stock is at or below reorder point
        if ($current_stock <= $reorder_point) {
            // Check if unresolved alert already exists
            $stmt = $conn->prepare("SELECT id FROM low_stock_notifications 
                                   WHERE item_id = ? AND is_resolved = 0 
                                   ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$item_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing alert
                $stmt = $conn->prepare("UPDATE low_stock_notifications 
                                       SET current_stock = ?, notification_type = ?, shortage_amount = ?, updated_at = NOW()
                                       WHERE id = ?");
                $stmt->execute([$current_stock, $notification_type, max(0, $minimum_stock - $current_stock), $existing['id']]);
            } else {
                // Create new alert
                $stmt = $conn->prepare("INSERT INTO low_stock_notifications 
                                       (item_id, item_name, current_stock, minimum_stock, shortage_amount, notification_type)
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $item_id,
                    $item_name,
                    $current_stock,
                    $minimum_stock,
                    max(0, $minimum_stock - $current_stock),
                    $notification_type
                ]);
            }
        }
    } catch (Exception $e) {
        // Silently fail - don't break the transaction
    }
}

/**
 * Resolve stock alert when stock is replenished
 */
function resolveStockAlert($conn, $item_id) {
    try {
        // Get current stock and minimum
        $stmt = $conn->prepare("SELECT current_stock, minimum_stock FROM stationery_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['current_stock'] > $product['minimum_stock']) {
            // Auto-resolve low stock alerts
            $stmt = $conn->prepare("UPDATE low_stock_notifications 
                                   SET is_resolved = 1, resolved_at = NOW(), resolution_notes = 'Auto-resolved: stock replenished'
                                   WHERE item_id = ? AND is_resolved = 0");
            $stmt->execute([$item_id]);
        }
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Get low stock items
 */
function getLowStockItems($conn, $user_id = null, $notification_type = null) {
    try {
        $sql = "SELECT n.*, s.item_code, s.category, s.unit_price, s.reorder_point 
                FROM low_stock_notifications n
                JOIN stationery_items s ON n.item_id = s.id
                WHERE n.is_resolved = 0";
        
        $params = [];
        
        if ($user_id) {
            $sql .= " AND s.user_id = ?";
            $params[] = $user_id;
        }
        
        if ($notification_type) {
            $sql .= " AND n.notification_type = ?";
            $params[] = $notification_type;
        }
        
        $sql .= " ORDER BY 
                  CASE n.notification_type 
                    WHEN 'out_of_stock' THEN 1
                    WHEN 'critical' THEN 2
                    WHEN 'low' THEN 3
                  END,
                  n.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get stock movement history
 */
function getStockMovements($conn, $item_id = null, $limit = 50) {
    try {
        $sql = "SELECT sm.*, s.item_name, s.item_code, e.full_name as employee_name
                FROM stock_movements sm
                JOIN stationery_items s ON sm.item_id = s.id
                LEFT JOIN employees e ON sm.employee_id = e.id
                WHERE 1=1";
        
        $params = [];
        
        if ($item_id) {
            $sql .= " AND sm.item_id = ?";
            $params[] = $item_id;
        }
        
        $sql .= " ORDER BY sm.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get stock statistics
 */
function getStockStatistics($conn, $user_id = null) {
    try {
        $where = $user_id ? "WHERE user_id = ?" : "";
        $params = $user_id ? [$user_id] : [];
        
        $stmt = $conn->prepare("SELECT 
                                COUNT(*) as total_products,
                                SUM(current_stock) as total_stock_units,
                                SUM(current_stock * unit_price) as total_stock_value,
                                SUM(CASE WHEN current_stock <= minimum_stock THEN 1 ELSE 0 END) as low_stock_count,
                                SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                                SUM(CASE WHEN current_stock > minimum_stock THEN 1 ELSE 0 END) as healthy_stock_count
                                FROM stationery_items $where");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [
            'total_products' => 0,
            'total_stock_units' => 0,
            'total_stock_value' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'healthy_stock_count' => 0
        ];
    }
}

// Auto-initialize tables when this file is included
ensureInventoryTables($conn);
