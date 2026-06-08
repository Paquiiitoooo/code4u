<?php
/**
 * Public Ticket API Endpoint
 * Allows customers to access their tickets using access_code
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            handleGetTicket($db);
            break;
        case 'POST':
            handlePostMessage($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log("Ticket public API error: " . $e->getMessage());
}

function handleGetTicket($db) {
    $accessCode = $_GET['access_code'] ?? null;
    
    if (!$accessCode) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Access code required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ensure access_code column exists
    if (function_exists('ensureAccessCodeColumn')) {
        ensureAccessCodeColumn($db);
    }
    
    // Get ticket by access_code
    $sql = "SELECT t.*, 
                   (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as message_count
            FROM tickets t
            WHERE t.access_code = :access_code
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['access_code' => $accessCode]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Get messages for this ticket
    $messagesSql = "SELECT tm.*, a.full_name as admin_name
                    FROM ticket_messages tm
                    LEFT JOIN admins a ON tm.sender_id = a.id AND tm.sender_type = 'admin'
                    WHERE tm.ticket_id = :ticket_id
                    ORDER BY tm.created_at ASC";
    
    $messagesStmt = $db->prepare($messagesSql);
    $messagesStmt->execute(['ticket_id' => $ticket['id']]);
    $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ticket['messages'] = $messages;
    
    echo json_encode([
        'success' => true,
        'data' => $ticket
    ], JSON_UNESCAPED_UNICODE);
}

function handlePostMessage($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $accessCode = $data['access_code'] ?? null;
    $message = $data['message'] ?? null;
    
    if (!$accessCode || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Access code and message required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ensure access_code column exists
    if (function_exists('ensureAccessCodeColumn')) {
        ensureAccessCodeColumn($db);
    }
    
    // Get ticket by access_code
    $sql = "SELECT id, status FROM tickets WHERE access_code = :access_code LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute(['access_code' => $accessCode]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if ($ticket['status'] === 'closed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot send message to closed ticket'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ensure is_read column exists
    if (function_exists('ensureIsReadColumn')) {
        ensureIsReadColumn($db);
    }
    if (function_exists('hasIsReadColumn')) {
        $hasIsRead = hasIsReadColumn($db);
    } else {
        $hasIsRead = false;
    }
    
    // Sanitize message
    if (!function_exists('sanitize')) {
        function sanitize($data) {
            if (is_array($data)) {
                return array_map('sanitize', $data);
            }
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Insert message
    if ($hasIsRead) {
        $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal, is_read)
                       VALUES (:ticket_id, 'customer', :message, 0, 0)";
    } else {
        $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
                       VALUES (:ticket_id, 'customer', :message, 0)";
    }
    
    $messageStmt = $db->prepare($messageSql);
    $messageStmt->execute([
        'ticket_id' => $ticket['id'],
        'message' => sanitize($message)
    ]);
    
    // Update ticket status if it was closed/resolved
    if (in_array($ticket['status'], ['closed', 'resolved'])) {
        $updateSql = "UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = :ticket_id";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute(['ticket_id' => $ticket['id']]);
    } else {
        // Just update updated_at
        $updateSql = "UPDATE tickets SET updated_at = NOW() WHERE id = :ticket_id";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute(['ticket_id' => $ticket['id']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ], JSON_UNESCAPED_UNICODE);
}

