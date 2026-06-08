<?php
/**
 * Contact Form Handler for Code4U
 * 
 * CONFIGURATION WAMP LOCAL - DÉVELOPPEMENT
 * This file handles form submissions from the contact form.
 */

// Détection de l'environnement local
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
           strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

// Configuration email selon l'environnement
if ($isLocal) {
    define('EMAIL_DEV_MODE', true);
    define('EMAIL_LOG_FILE', __DIR__ . '/logs/email_logs.txt');
} else {
    define('EMAIL_DEV_MODE', false);
}

// Headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$service = isset($_POST['service']) ? trim($_POST['service']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Le nom est requis';
}

if (empty($email)) {
    $errors[] = 'L\'email est requis';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L\'email n\'est pas valide';
}

if (empty($service)) {
    $errors[] = 'Le type de projet est requis';
}

if (empty($message)) {
    $errors[] = 'Le message est requis';
}

// If validation errors, return them
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Email configuration
$to = $isLocal ? 'contact@localhost' : 'contact@code4u.fr';
$subject = "Nouveau contact depuis Code4U - " . $email;

// Email body
$emailBody = "
Nom : {$name}
Email : {$email}" . 
($phone ? "\nTéléphone : {$phone}" : "") . "
Type de projet : {$service}

Message :
{$message}

---
Ce message a été envoyé depuis le site Code4U.
" . ($isLocal ? "\n[EN MODE DÉVELOPPEMENT - Email non envoyé]" : "");

// Fonction d'envoi d'email adaptée pour le développement local
function sendContactEmail($to, $subject, $body) {
    if (EMAIL_DEV_MODE) {
        // Mode développement: logger l'email
        $logDir = dirname(EMAIL_LOG_FILE);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logContent = str_repeat('=', 80) . "\n";
        $logContent .= "DATE: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "TO: " . $to . "\n";
        $logContent .= "SUBJECT: " . $subject . "\n";
        $logContent .= str_repeat('-', 80) . "\n";
        $logContent .= $body . "\n";
        $logContent .= str_repeat('=', 80) . "\n\n";
        
        @file_put_contents(EMAIL_LOG_FILE, $logContent, FILE_APPEND | LOCK_EX);
        return true;
    } else {
        // Mode production: envoyer l'email
        $headers = [
            'From: Code4U <noreply@code4u.fr>',
            'Reply-To: ' . ($_POST['email'] ?? 'noreply@code4u.fr'),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

// Send email
$mailSent = sendContactEmail($to, $subject, $emailBody);

// Prepare response
if ($mailSent) {
    // Success response
    $responseMessage = $isLocal 
        ? 'Message enregistré avec succès ! (Mode développement - voir logs/email_logs.txt)'
        : 'Message envoyé avec succès ! Nous vous répondrons rapidement.';
    
    echo json_encode([
        'success' => true,
        'message' => $responseMessage
    ]);
} else {
    // Error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'envoi. Veuillez réessayer ou nous contacter directement.'
    ]);
}

// Log contact entry
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logEntry = date('Y-m-d H:i:s') . " | {$name} | {$email} | {$service}\n";
@file_put_contents($logDir . '/contact_logs.txt', $logEntry, FILE_APPEND | LOCK_EX);
?>

