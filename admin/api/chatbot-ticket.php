<?php
/**
 * Chatbot Ticket Creation API
 * Public endpoint for chatbot to create tickets
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log("Chatbot ticket API config error: " . $e->getMessage());
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    
    // Test database connection
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('Empty request body');
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!is_array($data)) {
        throw new Exception('Data must be an array');
    }

    // Validation
    $required = ['subject', 'description', 'customer_name', 'customer_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field $field is required"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Validate email
    if (!function_exists('isValidEmail') || !isValidEmail($data['customer_email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generate ticket number
    function generateTicketNumber($db) {
        $prefix = defined('TICKET_PREFIX') ? TICKET_PREFIX : 'TKT';
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

    try {
        $ticketNumber = generateTicketNumber($db);
    } catch (Exception $e) {
        error_log("Error generating ticket number: " . $e->getMessage());
        throw new Exception("Failed to generate ticket number: " . $e->getMessage());
    }

    // Always generate an access code (even if column doesn't exist)
    $accessCode = null;
    if (function_exists('generateAccessCode')) {
        try {
            $accessCode = generateAccessCode($db);
        } catch (Exception $e) {
            error_log("Error generating access code: " . $e->getMessage());
            // Generate a simple code as fallback
            $accessCode = strtoupper(substr(md5($ticketNumber . time()), 0, 6)) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
    } else {
        // Fallback if function doesn't exist
        $accessCode = strtoupper(substr(md5($ticketNumber . time()), 0, 6)) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Determine priority based on keywords
    $priority = 'medium';
    $description = strtolower($data['description']);
    if (strpos($description, 'urgent') !== false || strpos($description, 'urgence') !== false) {
        $priority = 'urgent';
    } elseif (strpos($description, 'important') !== false) {
        $priority = 'high';
    }

    // Ensure sanitize function exists
    if (!function_exists('sanitize')) {
        function sanitize($data) {
            if (is_array($data)) {
                return array_map('sanitize', $data);
            }
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
    }

    // Ensure access_code column exists
    if (function_exists('ensureAccessCodeColumn')) {
        ensureAccessCodeColumn($db);
    }

    // Insert ticket with access_code (column is now guaranteed to exist)
    $sql = "INSERT INTO tickets (ticket_number, access_code, subject, description, customer_name, customer_email, 
                                 customer_phone, status, priority, category, source)
            VALUES (:ticket_number, :access_code, :subject, :description, :customer_name, :customer_email,
                    :customer_phone, :status, :priority, :category, :source)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'ticket_number' => $ticketNumber,
        'access_code' => $accessCode,
        'subject' => sanitize($data['subject']),
        'description' => sanitize($data['description']),
        'customer_name' => sanitize($data['customer_name']),
        'customer_email' => sanitize($data['customer_email']),
        'customer_phone' => sanitize($data['customer_phone'] ?? ''),
        'status' => 'open',
        'priority' => $priority,
        'category' => $data['category'] ?? 'chatbot',
        'source' => 'chatbot'
    ]);
    
    if (!$result) {
        throw new Exception("Failed to execute INSERT statement");
    }

    $ticketId = $db->lastInsertId();

    if (!$ticketId) {
        throw new Exception("Failed to get ticket ID after insertion");
    }

    // Add initial message from customer
    // Ensure is_read column exists
    try {
        if (function_exists('ensureIsReadColumn')) {
            ensureIsReadColumn($db);
        }
        if (function_exists('hasIsReadColumn')) {
            $hasIsRead = hasIsReadColumn($db);
        } else {
            $hasIsRead = false;
        }
    } catch (Exception $e) {
        error_log("Error checking is_read column: " . $e->getMessage());
        $hasIsRead = false;
    }

    if ($hasIsRead) {
        $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal, is_read)
                       VALUES (:ticket_id, 'customer', :message, 0, 0)";
    } else {
        $messageSql = "INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
                       VALUES (:ticket_id, 'customer', :message, 0)";
    }

    try {
        $messageStmt = $db->prepare($messageSql);
        $messageStmt->execute([
            'ticket_id' => $ticketId,
            'message' => sanitize($data['description'])
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail ticket creation
        error_log("Error adding initial message: " . $e->getMessage());
    }

    // Update statistics (optional, don't fail if it doesn't work)
    try {
        if (function_exists('updateStatistic')) {
            updateStatistic($db, 'tickets_created', 1, ['source' => 'chatbot']);
        }
    } catch (Exception $e) {
        error_log("Error updating statistics: " . $e->getMessage());
    }

    // Send access code email to customer (optional, don't fail if it doesn't work)
    try {
        if (function_exists('sendEmailNotification') && $accessCode) {
            $emailSubject = "Votre code d'accès - Ticket $ticketNumber";
            
            // Use HTML template if function exists
            if (function_exists('generateTicketEmailHTML')) {
                $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://code4u.fr';
                $emailBody = generateTicketEmailHTML(
                    $data['customer_name'],
                    $ticketNumber,
                    $accessCode,
                    $siteUrl
                );
                sendEmailNotification($data['customer_email'], $emailSubject, $emailBody, true);
            } else {
                // Fallback to plain text
                $emailBody = "Bonjour " . sanitize($data['customer_name']) . ",\n\n";
                $emailBody .= "Votre ticket de support a été créé avec succès via notre chatbot.\n\n";
                $emailBody .= "Numéro de ticket : $ticketNumber\n";
                $emailBody .= "Code d'accès secret : $accessCode\n\n";
                $emailBody .= "⚠️ IMPORTANT : Conservez ce code secret. Il vous permettra d'accéder à votre ticket de manière sécurisée.\n\n";
                $emailBody .= "Pour consulter votre ticket et communiquer avec notre équipe, rendez-vous sur notre site et utilisez ce code d'accès.\n\n";
                $emailBody .= "Cordialement,\nL'équipe Code4U";
                
                sendEmailNotification($data['customer_email'], $emailSubject, $emailBody);
            }
        }
    } catch (Exception $e) {
        error_log("Error sending customer email: " . $e->getMessage());
    }

    // Send notification email to admin (optional)
    try {
        if (function_exists('sendEmailNotification') && defined('TICKET_NOTIFICATION_EMAIL') && !empty(TICKET_NOTIFICATION_EMAIL)) {
            $adminEmailSubject = "Nouveau ticket créé via Chatbot - $ticketNumber";
            $adminEmailBody = "Un nouveau ticket a été créé via le chatbot:\n\n";
            $adminEmailBody .= "Numéro: $ticketNumber\n";
            $adminEmailBody .= "Sujet: " . $data['subject'] . "\n";
            $adminEmailBody .= "Client: " . $data['customer_name'] . " (" . $data['customer_email'] . ")\n";
            $adminEmailBody .= "Priorité: $priority\n\n";
            $adminEmailBody .= "Description:\n" . $data['description'] . "\n";
            
            sendEmailNotification(TICKET_NOTIFICATION_EMAIL, $adminEmailSubject, $adminEmailBody);
        }
    } catch (Exception $e) {
        error_log("Error sending admin email: " . $e->getMessage());
    }

    $responseData = [
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber,
        'priority' => $priority
    ];

    // Only include access_code if it was generated
    if ($accessCode) {
        $responseData['access_code'] = $accessCode;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket créé avec succès',
        'data' => $responseData
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log("Chatbot ticket API DB error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log("Chatbot ticket API error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit;
}

