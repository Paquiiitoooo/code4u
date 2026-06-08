<?php
/**
 * Database Configuration for Code4U Admin Panel
 * 
 * CONFIGURATION WAMP LOCAL - DÉVELOPPEMENT
 * IMPORTANT: Configure these values according to your hosting environment
 */

// ============================================
// CONFIGURATION BASE DE DONNÉES WAMP
// ============================================
// WAMP utilise généralement par défaut:
// - Host: localhost (ou 127.0.0.1)
// - User: root
// - Password: '' (vide par défaut)
// - Database: code4u (à créer via phpMyAdmin)
// ============================================

// The public site and the ERP use the same database.
// Create admin/config/local.php from local.example.php to override credentials per environment.
$localConfig = [];
$localConfigFile = __DIR__ . '/local.php';
if (is_file($localConfigFile)) {
    $loadedConfig = require $localConfigFile;
    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

// Database configuration
if (IS_LOCAL) {
    // Configuration WAMP local
    define('DB_HOST', $localConfig['db_host'] ?? '127.0.0.1');
    define('DB_NAME', $localConfig['db_name'] ?? 'erp_code4u');
    define('DB_USER', $localConfig['db_user'] ?? 'erp_user');
    define('DB_PASS', $localConfig['db_pass'] ?? 'admin123');
} else {
    // Configuration production (VPS) : base ERP locale au serveur.
    // Le mot de passe réel est fourni par admin/config/local.php (hors dépôt).
    // Ne JAMAIS committer de mot de passe ici.
    define('DB_HOST', $localConfig['db_host'] ?? 'localhost');
    define('DB_NAME', $localConfig['db_name'] ?? 'erp_code4u');
    define('DB_USER', $localConfig['db_user'] ?? 'erp_code4u');
    define('DB_PASS', $localConfig['db_pass'] ?? '');
}
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Europe/Paris');

// ============================================
// ENVIRONNEMENT DE DÉVELOPPEMENT
// ============================================
// Détection automatique de l'environnement (réutilise IS_LOCAL défini par config.php,
// qui gère déjà le port éventuel dans HTTP_HOST, ex. localhost:8765).
$isLocal = defined('IS_LOCAL') ? IS_LOCAL : false;
define('ENVIRONMENT', $isLocal ? 'development' : 'production');

if (ENVIRONMENT === 'development') {
    // Error reporting pour développement local
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
} else {
    // Error reporting pour production
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
}

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Force UTF-8
            $this->connection->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            $this->connection->exec("SET CHARACTER SET utf8mb4");
        } catch (PDOException $e) {
            // Ensure logs directory exists
            $logDir = dirname(__DIR__) . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/php_errors.log';
            error_log("Database connection error: " . $e->getMessage(), 3, $logFile);

            // Ne pas die() ici : cela enverrait du texte brut et casserait les
            // réponses JSON des API (ex. espace client -> "JSON.parse: unexpected
            // character"). On lève une exception pour que chaque appelant la gère
            // (les API renvoient un JSON d'erreur, les pages HTML un 500 propre).
            $detail = (ENVIRONMENT === 'development')
                ? 'Erreur de connexion à la base de données : ' . $e->getMessage()
                : 'Erreur de connexion à la base de données.';
            throw new RuntimeException($detail, 0, $e);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

