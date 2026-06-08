<?php
/**
 * Notifications API
 * Returns unread chat messages and new tickets
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDB();

try {
    // Ensure is_read column exists
    ensureIsReadColumn($db);
    $hasIsRead = hasIsReadColumn($db);
    
    // Get unread chat messages count
    if ($hasIsRead) {
        $unreadChatsSql = "SELECT COUNT(DISTINCT t.id) as count
                           FROM tickets t
                           INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                           WHERE (t.source = 'chatbot' 
                                  OR t.subject LIKE '%Chat en direct%'
                                  OR t.subject LIKE '%chat direct%')
                           AND t.status != 'closed'
                           AND tm.sender_type = 'customer'
                           AND (tm.is_read = 0 OR tm.is_read IS NULL)";
    } else {
        $unreadChatsSql = "SELECT COUNT(DISTINCT t.id) as count
                           FROM tickets t
                           INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                           WHERE (t.source = 'chatbot' 
                                  OR t.subject LIKE '%Chat en direct%'
                                  OR t.subject LIKE '%chat direct%')
                           AND t.status != 'closed'
                           AND tm.sender_type = 'customer'";
    }
    
    $unreadChats = $db->query($unreadChatsSql)->fetch()['count'];
    
    // Get recent unread messages
    if ($hasIsRead) {
        $recentMessagesSql = "SELECT 
                                t.id as ticket_id,
                                t.ticket_number,
                                t.customer_name,
                                tm.message,
                                tm.created_at
                              FROM tickets t
                              INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                              WHERE (t.source = 'chatbot' 
                                     OR t.subject LIKE '%Chat en direct%'
                                     OR t.subject LIKE '%chat direct%')
                              AND t.status != 'closed'
                              AND tm.sender_type = 'customer'
                              AND (tm.is_read = 0 OR tm.is_read IS NULL)
                              ORDER BY tm.created_at DESC
                              LIMIT 5";
    } else {
        $recentMessagesSql = "SELECT 
                                t.id as ticket_id,
                                t.ticket_number,
                                t.customer_name,
                                tm.message,
                                tm.created_at
                              FROM tickets t
                              INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                              WHERE (t.source = 'chatbot' 
                                     OR t.subject LIKE '%Chat en direct%'
                                     OR t.subject LIKE '%chat direct%')
                              AND t.status != 'closed'
                              AND tm.sender_type = 'customer'
                              ORDER BY tm.created_at DESC
                              LIMIT 5";
    }
    
    $recentMessages = $db->query($recentMessagesSql)->fetchAll();
    
    // Get new tickets count (last 24h)
    $newTicketsSql = "SELECT COUNT(*) as count
                      FROM tickets
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      AND status != 'closed'";
    
    $newTickets = $db->query($newTicketsSql)->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'unread_chats' => (int)$unreadChats,
            'new_tickets' => (int)$newTickets,
            'recent_messages' => $recentMessages
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

