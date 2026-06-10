<?php
/**
 * Main Configuration File for Code4U Admin Panel
 * 
 * CONFIGURATION WAMP LOCAL - DÉVELOPPEMENT
 */

// Force UTF-8 encoding
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// ============================================
// DÉTECTION DE L'ENVIRONNEMENT
// ============================================
$httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$hostName = explode(':', $httpHost)[0]; // retire le port éventuel (ex. localhost:8765)
$isLocal = in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)
           || str_contains($hostName, 'localhost');
define('IS_LOCAL', $isLocal);

// ============================================
// CONFIGURATION SITE
// ============================================
define('SITE_NAME', 'Code4U Admin Panel');

// URLs selon l'environnement
if (IS_LOCAL) {
    // Configuration WAMP local
    // Détection automatique du chemin de base depuis le script actuel
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_replace('/admin', '', dirname($scriptPath));
    
    // Si le projet est directement dans www/, basePath sera '/' ou ''
    // Si le projet est dans www/code4u/, basePath sera '/code4u'
    $basePath = rtrim($basePath, '/') ?: '';
    
    // Configuration manuelle si nécessaire (décommentez et ajustez)
    // $basePath = '/code4u'; // Si votre projet est dans www/code4u/
    // $basePath = ''; // Si votre projet est directement dans www/
    
    $baseUrl = 'http://localhost' . $basePath;
    define('SITE_URL', $baseUrl);
    define('BASE_PATH', $basePath);
} else {
    // Configuration production IONOS (projet à la racine)
    define('SITE_URL', 'https://code4u.fr');
    define('BASE_PATH', '');
}

define('ADMIN_URL', BASE_PATH . '/admin');
define('API_URL', BASE_PATH . '/admin/api');

// Security
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('SESSION_NAME', 'code4u_admin_session');
define('CSRF_TOKEN_NAME', 'csrf_token');

// File uploads
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir) && IS_LOCAL) {
    @mkdir($uploadDir, 0755, true);
}
define('UPLOAD_DIR', $uploadDir);
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// ============================================
// CONFIGURATION EMAIL
// ============================================
if (IS_LOCAL) {
    // Mode développement: emails loggés au lieu d'être envoyés
    define('EMAIL_DEV_MODE', true);
    define('EMAIL_LOG_FILE', __DIR__ . '/../../logs/email_logs.txt');
    
    // Configuration SMTP pour tests locaux (optionnel)
    define('SMTP_HOST', 'localhost');
    define('SMTP_PORT', 25);
    define('SMTP_USER', '');
    define('SMTP_PASS', '');
    define('SMTP_FROM_EMAIL', 'noreply@localhost');
    define('SMTP_FROM_NAME', 'Code4U Support [DEV]');
} else {
    // Configuration production IONOS
    define('EMAIL_DEV_MODE', false);
    define('SMTP_HOST', 'smtp.ionos.fr');
    define('SMTP_PORT', 587);
    // Auth SMTP possible avec contact@code4u.fr, expéditeur automatique noreply.
    define('SMTP_USER', 'contact@code4u.fr');
    define('SMTP_PASS', ''); // À configurer avec le mot de passe du compte contact@code4u.fr si SMTP est utilisé
    define('SMTP_FROM_EMAIL', 'noreply@code4u.fr');
    define('SMTP_FROM_NAME', 'Code4U Support');
}

// Ticket configuration
define('TICKET_PREFIX', 'TKT');
define('AUTO_ASSIGN_ENABLED', true);
define('TICKET_NOTIFICATION_EMAIL', 'contact@code4u.fr');

// Statistics
define('STATS_RETENTION_DAYS', 365);

// Timezone
date_default_timezone_set('Europe/Paris');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Include database configuration
require_once __DIR__ . '/database.php';

