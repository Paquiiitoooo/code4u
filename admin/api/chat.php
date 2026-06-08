<?php
/**
 * Chat Support API
 * Handles real-time chat between customers and admins
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication only for admin actions
// Customer actions (sending messages) don't require auth
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$requiresAuth = in_array($action, ['list', 'read']); // Only list and read require auth

if ($requiresAuth && !isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDB();

// Ensure is_read column exists for chat functionality
ensureIsReadColumn($db);

try {
    switch ($action) {
        case 'list':
            getChatList($db);
            break;
        case 'messages':
            // Messages can be accessed by customers (no auth) or admins
            getChatMessages($db);
            break;
        case 'message':
            // Sending messages - customers can send without auth
            sendChatMessage($db);
            break;
        case 'read':
            markChatAsRead($db);
            break;
        case 'close':
            closeChat($db);
            break;
        case 'check_inactive':
            checkInactiveChats($db);
            break;
        default:
            getChatList($db);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function getChatList($db) {
    // Get active chat conversations from tickets with chatbot source
    // Include all tickets created via chatbot or requesting advisor
    try {
        // Ensure is_read column exists
        ensureIsReadColumn($db);
        $hasIsRead = hasIsReadColumn($db);
        
        // Build unread_count query based on column existence
        if ($hasIsRead) {
            $unreadCountSql = "COALESCE((SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id AND sender_type = 'customer' AND (is_read = 0 OR is_read IS NULL)), 0)";
        } else {
            // If column doesn't exist, count all customer messages as unread
            $unreadCountSql = "(SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id AND sender_type = 'customer')";
        }
        
        $sql = "SELECT 
                    t.id,
                    t.ticket_number,
                    t.customer_name,
                    t.customer_email,
                    t.customer_phone,
                    t.created_at,
                    t.status,
                    t.priority,
                    (SELECT message FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    COALESCE((SELECT created_at FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1), t.created_at) as last_message_at,
                    $unreadCountSql as unread_count,
                    (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
                FROM tickets t
                WHERE (t.source = 'chatbot' 
                       OR t.subject LIKE '%conseiller%' 
                       OR t.subject LIKE '%chat support%'
                       OR t.subject LIKE '%Chat en direct%'
                       OR t.subject LIKE '%chat direct%')
                AND t.status != 'closed'
                ORDER BY 
                    COALESCE((SELECT created_at FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1), t.created_at) DESC
                LIMIT 100";
        
        $stmt = $db->query($sql);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure all fields are properly set
        foreach ($chats as &$chat) {
            $chat['last_message'] = $chat['last_message'] ?? 'Nouvelle conversation';
            $chat['last_message_at'] = $chat['last_message_at'] ?? $chat['created_at'];
            $chat['unread_count'] = (int)($chat['unread_count'] ?? 0);
            $chat['message_count'] = (int)($chat['message_count'] ?? 0);
        }
        
        echo json_encode(['success' => true, 'data' => $chats], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("Error in getChatList: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des conversations', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function getChatMessages($db) {
    $ticketId = $_GET['id'] ?? null;
    
    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chat ID required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Query messages - accessible by both customers and admins
    // Include initial ticket description as first customer message if no messages exist
    $sql = "SELECT 
                tm.id,
                tm.message,
                tm.sender_type,
                tm.created_at,
                CASE 
                    WHEN tm.sender_type = 'admin' THEN COALESCE(a.full_name, 'Conseiller')
                    ELSE t.customer_name
                END as sender_name
            FROM ticket_messages tm
            JOIN tickets t ON tm.ticket_id = t.id
            LEFT JOIN admins a ON tm.sender_id = a.id
            WHERE tm.ticket_id = :ticket_id
            ORDER BY tm.created_at ASC";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no messages but ticket exists, include the initial description
        if (empty($messages)) {
            $ticketSql = "SELECT description, customer_name, created_at FROM tickets WHERE id = :ticket_id";
            $ticketStmt = $db->prepare($ticketSql);
            $ticketStmt->execute(['ticket_id' => $ticketId]);
            $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ticket && !empty($ticket['description'])) {
                $messages[] = [
                    'id' => 0,
                    'message' => $ticket['description'],
                    'sender_type' => 'customer',
                    'created_at' => $ticket['created_at'],
                    'sender_name' => $ticket['customer_name']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $messages], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("Error in getChatMessages: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des messages', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function sendChatMessage($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['chat_id']) || empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chat ID and message required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ensure is_read column exists
    ensureIsReadColumn($db);
    $hasIsRead = hasIsReadColumn($db);
    
    $ticketId = $data['chat_id'];
    $message = sanitize($data['message']);
    $senderType = $data['sender_type'] ?? 'admin';
    
    // If customer message, no auth required
    if ($senderType === 'customer') {
        if ($hasIsRead) {
            $sql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal, is_read)
                    VALUES (:ticket_id, 'customer', :message, 0, 0)";
        } else {
            $sql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
                    VALUES (:ticket_id, 'customer', :message, 0)";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'ticket_id' => $ticketId,
            'message' => $message
        ]);
    } else {
        // Admin message requires auth
        if (!isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $adminId = $_SESSION['admin_id'];
        if ($hasIsRead) {
            $sql = "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, is_internal, is_read)
                    VALUES (:ticket_id, 'admin', :sender_id, :message, 0, 1)";
        } else {
            $sql = "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, is_internal)
                    VALUES (:ticket_id, 'admin', :sender_id, :message, 0)";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'ticket_id' => $ticketId,
            'sender_id' => $adminId,
            'message' => $message
        ]);
    }
    
    // Update ticket status if needed
    $updateSql = "UPDATE tickets SET status = 'in_progress', updated_at = NOW() WHERE id = :ticket_id AND status = 'open'";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute(['ticket_id' => $ticketId]);
    
    // Log activity (only for admin messages)
    if ($senderType === 'admin' && isAuthenticated()) {
        logActivity($db, 'chat_message_sent', 'ticket', $ticketId, "Admin sent chat message");
    }
    
    echo json_encode(['success' => true, 'message' => 'Message sent'], JSON_UNESCAPED_UNICODE);
}

function markChatAsRead($db) {
    $ticketId = $_GET['id'] ?? null;
    
    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chat ID required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ensure is_read column exists
    ensureIsReadColumn($db);
    
    // Mark customer messages as read (if column exists)
    if (hasIsReadColumn($db)) {
        try {
            $sql = "UPDATE ticket_messages SET is_read = 1 
                    WHERE ticket_id = :ticket_id AND sender_type = 'customer' AND (is_read = 0 OR is_read IS NULL)";
            $stmt = $db->prepare($sql);
            $stmt->execute(['ticket_id' => $ticketId]);
        } catch (PDOException $e) {
            error_log("Error marking messages as read: " . $e->getMessage());
        }
    }
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

function closeChat($db) {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $ticketId = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chat ID required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Check if ticket exists and is not already closed
        $checkSql = "SELECT id, status FROM tickets WHERE id = :ticket_id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute(['ticket_id' => $ticketId]);
        $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ticket not found'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        if ($ticket['status'] === 'closed') {
            echo json_encode(['success' => true, 'message' => 'Chat already closed'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Add automatic closure message
        ensureIsReadColumn($db);
        $hasIsRead = hasIsReadColumn($db);
        
        $closureMessage = "Votre demande a été clôturée par le conseiller. Si vous avez d'autres questions, n'hésitez pas à créer une nouvelle conversation.";
        
        if ($hasIsRead) {
            $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal, is_read)
                           VALUES (:ticket_id, 'system', :message, 0, 1)";
        } else {
            $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
                           VALUES (:ticket_id, 'system', :message, 0)";
        }
        
        $messageStmt = $db->prepare($messageSql);
        $messageStmt->execute([
            'ticket_id' => $ticketId,
            'message' => $closureMessage
        ]);
        
        // Update ticket status to closed
        $updateSql = "UPDATE tickets SET status = 'closed', resolved_at = NOW(), updated_at = NOW() WHERE id = :ticket_id";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute(['ticket_id' => $ticketId]);
        
        // Log activity
        logActivity($db, 'chat_closed', 'ticket', $ticketId, "Admin closed chat conversation");
        
        echo json_encode(['success' => true, 'message' => 'Chat closed successfully'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("Error closing chat: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la fermeture', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function checkInactiveChats($db) {
    // This function checks for chats that should be auto-closed
    // Called periodically by the frontend or a cron job
    
    try {
        // Find chats where:
        // 1. Last message is from customer
        // 2. Last message is more than 10 minutes old
        // 3. Status is not closed
        // 4. Source is chatbot
        
        $sql = "SELECT t.id, t.ticket_number, t.customer_name
                FROM tickets t
                WHERE t.status != 'closed'
                AND (t.source = 'chatbot' 
                     OR t.subject LIKE '%conseiller%' 
                     OR t.subject LIKE '%chat support%'
                     OR t.subject LIKE '%Chat en direct%'
                     OR t.subject LIKE '%chat direct%')
                AND EXISTS (
                    SELECT 1 FROM ticket_messages tm
                    WHERE tm.ticket_id = t.id
                    AND tm.sender_type = 'customer'
                    AND tm.created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                    AND tm.created_at = (
                        SELECT MAX(created_at) 
                        FROM ticket_messages 
                        WHERE ticket_id = t.id
                    )
                )
                AND NOT EXISTS (
                    SELECT 1 FROM ticket_messages tm2
                    WHERE tm2.ticket_id = t.id
                    AND tm2.sender_type IN ('admin', 'system')
                    AND tm2.created_at > (
                        SELECT MAX(created_at) 
                        FROM ticket_messages 
                        WHERE ticket_id = t.id 
                        AND sender_type = 'customer'
                    )
                )";
        
        $stmt = $db->query($sql);
        $inactiveChats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $closedCount = 0;
        ensureIsReadColumn($db);
        $hasIsRead = hasIsReadColumn($db);
        
        foreach ($inactiveChats as $chat) {
            // Add automatic closure message
            $closureMessage = "Cette conversation prend fin automatiquement car il n'y a pas eu de réponse depuis plus de 10 minutes. Si vous avez d'autres questions, n'hésitez pas à créer une nouvelle conversation.";
            
            if ($hasIsRead) {
                $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal, is_read)
                               VALUES (:ticket_id, 'system', :message, 0, 1)";
            } else {
                $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
                               VALUES (:ticket_id, 'system', :message, 0)";
            }
            
            $messageStmt = $db->prepare($messageSql);
            $messageStmt->execute([
                'ticket_id' => $chat['id'],
                'message' => $closureMessage
            ]);
            
            // Update ticket status to closed
            $updateSql = "UPDATE tickets SET status = 'closed', resolved_at = NOW(), updated_at = NOW() WHERE id = :ticket_id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute(['ticket_id' => $chat['id']]);
            
            $closedCount++;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Checked inactive chats",
            'closed_count' => $closedCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("Error checking inactive chats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

