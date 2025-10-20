<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$response = ['success' => false, 'data' => null, 'error' => null];

// Check authentication
$is_employee = !empty($_SESSION['employee_id']);
$is_user = !empty($_SESSION['user_id']);

if (!$is_employee && !$is_user) {
    $response['error'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

$sender_type = $is_employee ? 'employee' : 'user';
$sender_id = $is_employee ? $_SESSION['employee_id'] : $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_rooms':
            // Get chat rooms for current user
            $stmt = $conn->prepare("
                SELECT DISTINCT r.*, 
                    (SELECT COUNT(*) FROM chat_messages cm 
                     WHERE cm.room_id = r.id 
                     AND cm.sender_type != ? 
                     AND cm.sender_id != ?
                     AND cm.is_read = 0) as unread_count,
                    (SELECT cm.message FROM chat_messages cm 
                     WHERE cm.room_id = r.id 
                     ORDER BY cm.created_at DESC LIMIT 1) as last_message,
                    (SELECT cm.created_at FROM chat_messages cm 
                     WHERE cm.room_id = r.id 
                     ORDER BY cm.created_at DESC LIMIT 1) as last_message_time
                FROM chat_rooms r
                JOIN chat_participants p ON r.id = p.room_id
                WHERE " . ($is_employee ? "p.employee_id = ?" : "p.user_id = ?") . "
                ORDER BY last_message_time DESC
            ");
            $stmt->execute([$sender_type, $sender_id, $sender_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;

        case 'get_messages':
            $room_id = intval($_GET['room_id'] ?? 0);
            
            // Verify participant
            $check_stmt = $conn->prepare("
                SELECT id FROM chat_participants 
                WHERE room_id = ? AND " . ($is_employee ? "employee_id = ?" : "user_id = ?")
            );
            $check_stmt->execute([$room_id, $sender_id]);
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Access denied');
            }
            
            // Get messages
            $stmt = $conn->prepare("
                SELECT m.*,
                    CASE 
                        WHEN m.sender_type = 'user' THEN u.name
                        WHEN m.sender_type = 'employee' THEN CONCAT(e.first_name, ' ', e.last_name)
                    END as sender_name
                FROM chat_messages m
                LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
                LEFT JOIN employees e ON m.sender_type = 'employee' AND m.sender_id = e.id
                WHERE m.room_id = ?
                ORDER BY m.created_at ASC
                LIMIT 100
            ");
            $stmt->execute([$room_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark messages as read
            $mark_stmt = $conn->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = ? 
                AND sender_type != ? 
                AND sender_id != ?
                AND is_read = 0
            ");
            $mark_stmt->execute([$room_id, $sender_type, $sender_id]);
            
            $response['success'] = true;
            break;

        case 'send_message':
            $room_id = intval($_POST['room_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                throw new Exception('Message cannot be empty');
            }
            
            // Verify participant
            $check_stmt = $conn->prepare("
                SELECT id FROM chat_participants 
                WHERE room_id = ? AND " . ($is_employee ? "employee_id = ?" : "user_id = ?")
            );
            $check_stmt->execute([$room_id, $sender_id]);
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Access denied');
            }
            
            // Insert message
            $stmt = $conn->prepare("
                INSERT INTO chat_messages (room_id, sender_type, sender_id, message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$room_id, $sender_type, $sender_id, $message]);
            
            $response['data'] = ['message_id' => $conn->lastInsertId()];
            $response['success'] = true;
            break;

        case 'create_room':
            $target_type = $_POST['target_type'] ?? ''; // 'user' or 'employee'
            $target_id = intval($_POST['target_id'] ?? 0);
            
            // Check if room already exists
            $check_stmt = $conn->prepare("
                SELECT r.id FROM chat_rooms r
                JOIN chat_participants p1 ON r.id = p1.room_id
                JOIN chat_participants p2 ON r.id = p2.room_id
                WHERE r.type = 'direct'
                AND ((p1." . ($is_employee ? "employee_id" : "user_id") . " = ? AND p2." . ($target_type === 'employee' ? "employee_id" : "user_id") . " = ?)
                OR (p2." . ($is_employee ? "employee_id" : "user_id") . " = ? AND p1." . ($target_type === 'employee' ? "employee_id" : "user_id") . " = ?))
                LIMIT 1
            ");
            $check_stmt->execute([$sender_id, $target_id, $sender_id, $target_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $response['data'] = ['room_id' => $existing['id']];
                $response['success'] = true;
                break;
            }
            
            // Create new room
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO chat_rooms (name, type, created_by) VALUES (?, 'direct', ?)");
            $stmt->execute(['Direct Chat', $sender_id]);
            $room_id = $conn->lastInsertId();
            
            // Add participants
            $part_stmt = $conn->prepare("
                INSERT INTO chat_participants (room_id, " . ($is_employee ? "employee_id" : "user_id") . ") 
                VALUES (?, ?)
            ");
            $part_stmt->execute([$room_id, $sender_id]);
            
            $part_stmt2 = $conn->prepare("
                INSERT INTO chat_participants (room_id, " . ($target_type === 'employee' ? "employee_id" : "user_id") . ") 
                VALUES (?, ?)
            ");
            $part_stmt2->execute([$room_id, $target_id]);
            
            $conn->commit();
            
            $response['data'] = ['room_id' => $room_id];
            $response['success'] = true;
            break;

        case 'typing':
            $room_id = intval($_POST['room_id'] ?? 0);
            
            // Update or insert typing status
            $stmt = $conn->prepare("
                INSERT INTO chat_typing (room_id, sender_type, sender_id, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            $stmt->execute([$room_id, $sender_type, $sender_id]);
            
            // Clean old typing statuses (older than 5 seconds)
            $clean_stmt = $conn->prepare("
                DELETE FROM chat_typing 
                WHERE updated_at < DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ");
            $clean_stmt->execute();
            
            $response['success'] = true;
            break;

        case 'get_typing':
            $room_id = intval($_GET['room_id'] ?? 0);
            
            // Get who's typing (exclude self)
            $stmt = $conn->prepare("
                SELECT t.*,
                    CASE 
                        WHEN t.sender_type = 'user' THEN u.name
                        WHEN t.sender_type = 'employee' THEN CONCAT(e.first_name, ' ', e.last_name)
                    END as sender_name
                FROM chat_typing t
                LEFT JOIN users u ON t.sender_type = 'user' AND t.sender_id = u.id
                LEFT JOIN employees e ON t.sender_type = 'employee' AND t.sender_id = e.id
                WHERE t.room_id = ?
                AND NOT (t.sender_type = ? AND t.sender_id = ?)
                AND t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ");
            $stmt->execute([$room_id, $sender_type, $sender_id]);
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;

        case 'get_unread_count':
            // Total unread messages across all rooms
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM chat_messages m
                JOIN chat_participants p ON m.room_id = p.room_id
                WHERE " . ($is_employee ? "p.employee_id = ?" : "p.user_id = ?") . "
                AND m.sender_type != ?
                AND m.sender_id != ?
                AND m.is_read = 0
            ");
            $stmt->execute([$sender_id, $sender_type, $sender_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['data'] = ['count' => $result['total']];
            $response['success'] = true;
            break;

        case 'get_available_people':
            // Get available people for creating new chats
            $type = $_GET['type'] ?? '';
            
            if ($is_employee) {
                // Employee - get admins and other employees
                $query = "
                    SELECT id, name as full_name, email, 'Admin' as position, 'user' as participant_type
                    FROM users 
                    WHERE is_admin = 1
                    UNION
                    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, role as position, 'employee' as participant_type
                    FROM employees 
                    WHERE status = 'active' AND id != :current_id
                    ORDER BY full_name ASC
                ";
                $stmt = $conn->prepare($query);
                $stmt->execute(['current_id' => $sender_id]);
            } else {
                // User/Admin - get employees and other users
                try {
                    // Try to get both employees and users
                    $query = "
                        SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, role as position, 'employee' as participant_type
                        FROM employees 
                        WHERE status = 'active'
                        UNION
                        SELECT id, name as full_name, email, 
                               CASE WHEN is_admin = 1 THEN 'Admin' ELSE 'User' END as position,
                               'user' as participant_type
                        FROM users 
                        WHERE id != :current_id
                        ORDER BY full_name ASC
                    ";
                    $stmt = $conn->prepare($query);
                    $stmt->execute(['current_id' => $sender_id]);
                } catch (PDOException $e) {
                    // Fallback: if employees table doesn't exist, just get users
                    $query = "
                        SELECT id, name as full_name, email, 
                               CASE WHEN is_admin = 1 THEN 'Admin' ELSE 'User' END as position,
                               'user' as participant_type
                        FROM users 
                        WHERE id != :current_id
                        ORDER BY full_name ASC
                    ";
                    $stmt = $conn->prepare($query);
                    $stmt->execute(['current_id' => $sender_id]);
                }
            }
            
            $people = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['data'] = $people;
            $response['success'] = true;
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
