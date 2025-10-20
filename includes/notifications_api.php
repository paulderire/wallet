<?php
/**
 * Global Notifications & Messages API
 * Handles notifications and system messages across the entire app
 */

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Check authentication
$is_employee = !empty($_SESSION['employee_id']);
$is_user = !empty($_SESSION['user_id']);

if (!$is_employee && !$is_user) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$employee_id = $_SESSION['employee_id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    switch ($action) {
        // ============ NOTIFICATIONS ============
        
        case 'get_notifications':
            // Get notifications for current user/employee
            $limit = intval($_GET['limit'] ?? 20);
            $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $where = [];
            $params = [];
            
            if ($is_employee) {
                $where[] = "employee_id = ?";
                $params[] = $employee_id;
            } else {
                $where[] = "user_id = ?";
                $params[] = $user_id;
            }
            
            if ($unread_only) {
                $where[] = "is_read = 0";
            }
            
            // Filter expired notifications
            $where[] = "(expires_at IS NULL OR expires_at > NOW())";
            
            $query = "
                SELECT * FROM notifications 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY 
                    CASE priority 
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    created_at DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;
            
        case 'get_unread_count':
            // Get count of unread notifications
            if ($is_employee) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE employee_id = ? 
                    AND is_read = 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$employee_id]);
            } else {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE user_id = ? 
                    AND is_read = 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$user_id]);
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['data'] = ['count' => (int)$result['count']];
            $response['success'] = true;
            break;
            
        case 'mark_notification_read':
            // Mark a notification as read
            $notification_id = intval($_POST['notification_id'] ?? 0);
            
            if ($is_employee) {
                $stmt = $conn->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE id = ? AND employee_id = ?
                ");
                $stmt->execute([$notification_id, $employee_id]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$notification_id, $user_id]);
            }
            
            $response['success'] = true;
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read
            if ($is_employee) {
                $stmt = $conn->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE employee_id = ? AND is_read = 0
                ");
                $stmt->execute([$employee_id]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_id = ? AND is_read = 0
                ");
                $stmt->execute([$user_id]);
            }
            
            $response['data'] = ['count' => $stmt->rowCount()];
            $response['success'] = true;
            break;
            
        case 'delete_notification':
            // Delete a notification
            $notification_id = intval($_POST['notification_id'] ?? 0);
            
            if ($is_employee) {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND employee_id = ?");
                $stmt->execute([$notification_id, $employee_id]);
            } else {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
            }
            
            $response['success'] = true;
            break;
            
        case 'create_notification':
            // Create a new notification (admin only for now)
            if (!$is_user) {
                throw new Exception('Only admins can create notifications');
            }
            
            $target_employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
            $target_user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
            $type = trim($_POST['type'] ?? 'info');
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $action_url = trim($_POST['action_url'] ?? '');
            $priority = trim($_POST['priority'] ?? 'medium');
            $icon = trim($_POST['icon'] ?? '');
            
            if (empty($title) || empty($message)) {
                throw new Exception('Title and message are required');
            }
            
            $stmt = $conn->prepare("
                INSERT INTO notifications 
                (user_id, employee_id, type, title, message, action_url, priority, icon)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $target_user_id,
                $target_employee_id,
                $type,
                $title,
                $message,
                $action_url ?: null,
                $priority,
                $icon ?: null
            ]);
            
            $response['data'] = ['notification_id' => $conn->lastInsertId()];
            $response['success'] = true;
            break;
            
        // ============ MESSAGES ============
        
        case 'get_messages':
            // Get system messages for current user
            $limit = intval($_GET['limit'] ?? 20);
            $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $where = [];
            $params = [];
            
            // Messages for specific recipient or broadcast (recipient_type = 'all')
            if ($is_employee) {
                $where[] = "(recipient_type = 'employee' AND recipient_id = ?) OR recipient_type = 'all'";
                $params[] = $employee_id;
            } else {
                $where[] = "(recipient_type = 'user' AND recipient_id = ?) OR recipient_type = 'all'";
                $params[] = $user_id;
            }
            
            if ($unread_only) {
                $where[] = "is_read = 0";
            }
            
            // Filter expired messages
            $where[] = "(expires_at IS NULL OR expires_at > NOW())";
            
            $query = "
                SELECT m.*,
                    CASE 
                        WHEN m.sender_type = 'user' THEN u.name
                        WHEN m.sender_type = 'employee' THEN CONCAT(e.first_name, ' ', e.last_name)
                        ELSE 'System'
                    END as sender_name
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
                LEFT JOIN employees e ON m.sender_type = 'employee' AND m.sender_id = e.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY 
                    CASE priority 
                        WHEN 'high' THEN 1
                        WHEN 'medium' THEN 2
                        WHEN 'low' THEN 3
                    END,
                    created_at DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;
            
        case 'mark_message_read':
            // Mark a message as read
            $message_id = intval($_POST['message_id'] ?? 0);
            
            $stmt = $conn->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$message_id]);
            
            $response['success'] = true;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
