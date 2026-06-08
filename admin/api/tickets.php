<?php
/**
 * Tickets API Endpoint
 * Handles CRUD operations for tickets
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// Get action from URL or POST data
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action);
            break;
        case 'PUT':
        case 'PATCH':
            handlePut($db, $action);
            break;
        case 'DELETE':
            handleDelete($db, $action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            getTicketsList($db);
            break;
        case 'single':
            getSingleTicket($db);
            break;
        case 'stats':
            getTicketsStats($db);
            break;
        default:
            getTicketsList($db);
    }
}

function getTicketsList($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? null;
    $priority = $_GET['priority'] ?? null;
    $search = $_GET['search'] ?? null;
    $accessCode = $_GET['access_code'] ?? null; // Recherche par code secret pour espace membre
    
    $where = [];
    $params = [];
    
    // Recherche par code secret (pour espace membre public - plus sécurisé)
    if ($accessCode) {
        $where[] = "t.access_code = :access_code";
        $params['access_code'] = $accessCode;
    }
    
    if ($status) {
        $where[] = "t.status = :status";
        $params['status'] = $status;
    }
    
    if ($priority) {
        $where[] = "t.priority = :priority";
        $params['priority'] = $priority;
    }
    
    if ($search) {
        $where[] = "(t.subject LIKE :search OR t.customer_name LIKE :search OR t.customer_email LIKE :search OR t.ticket_number LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    // Handle archived/closed tickets
    $archived = isset($_GET['archived']) && $_GET['archived'] === '1';
    
    if ($archived) {
        // Show only closed tickets
        $where[] = "t.status = 'closed'";
    } else {
        // Exclude closed tickets by default
        $where[] = "t.status != 'closed'";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM tickets t $whereClause";
    $countStmt = $db->prepare($countSql);
    $countParams = [];
    foreach ($params as $key => $value) {
        $countParams[':' . $key] = $value;
    }
    if (!empty($countParams)) {
        $countStmt->execute($countParams);
    } else {
        $countStmt->execute();
    }
    $total = $countStmt->fetch()['total'];
    
    // Get tickets
    $sql = "SELECT t.*, 
                   a.full_name as assigned_name,
                   (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as message_count,
                   (SELECT MAX(created_at) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as last_message_at
            FROM tickets t
            LEFT JOIN admins a ON t.assigned_to = a.id
            $whereClause
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    // Convert params for execute
    $executeParams = [];
    foreach ($params as $key => $value) {
        $executeParams[':' . $key] = $value;
    }
    $executeParams[':limit'] = $limit;
    $executeParams[':offset'] = $offset;
    
    $stmt = $db->prepare($sql);
    foreach ($executeParams as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    
    $tickets = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $tickets,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getSingleTicket($db) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ticket ID required']);
        return;
    }
    
    $sql = "SELECT t.*, 
                   a.full_name as assigned_name,
                   a.email as assigned_email
            FROM tickets t
            LEFT JOIN admins a ON t.assigned_to = a.id
            WHERE t.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get messages
    $messagesSql = "SELECT tm.*, a.full_name as sender_name, a.email as sender_email
                    FROM ticket_messages tm
                    LEFT JOIN admins a ON tm.sender_id = a.id AND tm.sender_type = 'admin'
                    WHERE tm.ticket_id = :ticket_id
                    ORDER BY tm.created_at ASC";
    
    $messagesStmt = $db->prepare($messagesSql);
    $messagesStmt->execute(['ticket_id' => $id]);
    $messages = $messagesStmt->fetchAll();
    
    $ticket['messages'] = $messages;
    
    echo json_encode(['success' => true, 'data' => $ticket], JSON_UNESCAPED_UNICODE);
}

function getTicketsStats($db) {
    $stats = [];
    
    // Total tickets
    $sql = "SELECT COUNT(*) as total FROM tickets";
    $stmt = $db->query($sql);
    $stats['total'] = $stmt->fetch()['total'];
    
    // By status
    $sql = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
    $stmt = $db->query($sql);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // By priority
    $sql = "SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority";
    $stmt = $db->query($sql);
    $stats['by_priority'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent tickets (last 7 days)
    $sql = "SELECT COUNT(*) as count FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stmt = $db->query($sql);
    $stats['recent_7_days'] = $stmt->fetch()['count'];
    
    // Average resolution time
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours 
            FROM tickets 
            WHERE resolved_at IS NOT NULL";
    $stmt = $db->query($sql);
    $stats['avg_resolution_hours'] = round($stmt->fetch()['avg_hours'] ?? 0, 1);
    
    echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
}

function handlePost($db, $action) {
    switch ($action) {
        case 'create':
            createTicket($db);
            break;
        case 'message':
            addMessage($db);
            break;
        default:
            createTicket($db);
    }
}

function createTicket($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $required = ['subject', 'description', 'customer_name', 'customer_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field $field is required"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Generate ticket number and access code
    $ticketNumber = generateTicketNumber($db);
    $accessCode = generateAccessCode($db);
    
    // Insert ticket
    $sql = "INSERT INTO tickets (ticket_number, access_code, subject, description, customer_name, customer_email, 
                                 customer_phone, status, priority, category, source)
            VALUES (:ticket_number, :access_code, :subject, :description, :customer_name, :customer_email,
                    :customer_phone, :status, :priority, :category, :source)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'ticket_number' => $ticketNumber,
        'access_code' => $accessCode,
        'subject' => sanitize($data['subject']),
        'description' => sanitize($data['description']),
        'customer_name' => sanitize($data['customer_name']),
        'customer_email' => sanitize($data['customer_email']),
        'customer_phone' => sanitize($data['customer_phone'] ?? ''),
        'status' => $data['status'] ?? 'open',
        'priority' => $data['priority'] ?? 'medium',
        'category' => $data['category'] ?? null,
        'source' => $data['source'] ?? 'form'
    ]);
    
    $ticketId = $db->lastInsertId();
    
    // Send access code email to customer
    $emailSubject = "Votre code d'accès - Ticket $ticketNumber";
    $emailBody = "Bonjour " . sanitize($data['customer_name']) . ",\n\n";
    $emailBody .= "Votre ticket de support a été créé avec succès.\n\n";
    $emailBody .= "Numéro de ticket : $ticketNumber\n";
    $emailBody .= "Code d'accès secret : $accessCode\n\n";
    $emailBody .= "Pour consulter votre ticket, rendez-vous sur notre site et utilisez ce code d'accès.\n\n";
    $emailBody .= "⚠️ IMPORTANT : Conservez ce code secret. Il vous permettra d'accéder à votre ticket de manière sécurisée.\n\n";
    $emailBody .= "Cordialement,\nL'équipe Code4U";
    
    sendEmailNotification($data['customer_email'], $emailSubject, $emailBody);
    
    // Add initial message
    if (!empty($data['description'])) {
        $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message)
                       VALUES (:ticket_id, 'customer', :message)";
        $messageStmt = $db->prepare($messageSql);
        $messageStmt->execute([
            'ticket_id' => $ticketId,
            'message' => sanitize($data['description'])
        ]);
    }
    
    // Log activity
    logActivity($db, 'ticket_created', 'ticket', $ticketId, "Ticket #$ticketNumber created");
    
    // Update statistics
    updateStatistic($db, 'tickets_created', 1);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket created successfully',
        'data' => [
            'id' => $ticketId, 
            'ticket_number' => $ticketNumber,
            'access_code' => $accessCode
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function addMessage($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ticket_id']) || empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ticket ID and message required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $adminId = getCurrentAdminId();
    
    $sql = "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, is_internal)
            VALUES (:ticket_id, :sender_type, :sender_id, :message, :is_internal)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'ticket_id' => $data['ticket_id'],
        'sender_type' => $adminId ? 'admin' : 'customer',
        'sender_id' => $adminId,
        'message' => sanitize($data['message']),
        'is_internal' => $data['is_internal'] ?? 0
    ]);
    
    // Update ticket updated_at
    $updateSql = "UPDATE tickets SET updated_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute(['id' => $data['ticket_id']]);
    
    // Log activity if admin
    if ($adminId) {
        logActivity($db, 'ticket_message_added', 'ticket', $data['ticket_id'], "Admin added message");
    }
    
    echo json_encode(['success' => true, 'message' => 'Message added successfully'], JSON_UNESCAPED_UNICODE);
}

function handlePut($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? $data['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ticket ID required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $allowedFields = ['status', 'priority', 'assigned_to', 'category'];
    $updates = [];
    $params = ['id' => $id];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[$field] = $data[$field] === '' ? null : $data[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid fields to update'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // If status is resolved/closed, set resolved_at
    if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])) {
        $updates[] = "resolved_at = NOW()";
    }
    
    $sql = "UPDATE tickets SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    // Convert params keys to include colon for PDO
    $executeParams = [];
    foreach ($params as $key => $value) {
        $executeParams[':' . ltrim($key, ':')] = $value;
    }
    $stmt->execute($executeParams);
    
    // Log activity
    logActivity($db, 'ticket_updated', 'ticket', $id, "Ticket updated");
    
    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully'], JSON_UNESCAPED_UNICODE);
}

function handleDelete($db, $action) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ticket ID required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // Delete associated messages first
        $deleteMessagesSql = "DELETE FROM ticket_messages WHERE ticket_id = :id";
        $deleteMessagesStmt = $db->prepare($deleteMessagesSql);
        $deleteMessagesStmt->execute(['id' => $id]);
        
        // Delete the ticket
        $sql = "DELETE FROM tickets WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        // Log activity
        logActivity($db, 'ticket_deleted', 'ticket', $id, "Ticket deleted");
        
        echo json_encode(['success' => true, 'message' => 'Ticket supprimé avec succès'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("Error deleting ticket: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du ticket'], JSON_UNESCAPED_UNICODE);
    }
}

function generateTicketNumber($db) {
    $prefix = TICKET_PREFIX;
    $year = date('Y');
    $sql = "SELECT ticket_number FROM tickets WHERE ticket_number LIKE :pattern ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute(['pattern' => "$prefix-$year-%"]);
    $last = $stmt->fetch();
    
    if ($last) {
        $lastNum = (int)substr($last['ticket_number'], -6);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return sprintf("%s-%s-%06d", $prefix, $year, $newNum);
}

