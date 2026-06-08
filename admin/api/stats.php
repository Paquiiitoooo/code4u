<?php
/**
 * Statistics API Endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            getDashboardStats($db);
            break;
        case 'tickets':
            getTicketsStats($db);
            break;
        case 'landing_pages':
            getLandingPagesStats($db);
            break;
        case 'activity':
            getActivityStats($db);
            break;
        case 'chart':
            getChartData($db);
            break;
        default:
            getDashboardStats($db);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getDashboardStats($db) {
    $stats = getDashboardStats($db);
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getTicketsStats($db) {
    $stats = [];
    
    // Total by status
    $sql = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
    $stmt = $db->query($sql);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total by priority
    $sql = "SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority";
    $stmt = $db->query($sql);
    $stats['by_priority'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tickets created per day (last 30 days)
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM tickets 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    $stmt = $db->query($sql);
    $stats['daily_creation'] = $stmt->fetchAll();
    
    // Average resolution time
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours 
            FROM tickets 
            WHERE resolved_at IS NOT NULL";
    $stmt = $db->query($sql);
    $stats['avg_resolution_hours'] = round($stmt->fetch()['avg_hours'] ?? 0, 1);
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getLandingPagesStats($db) {
    $stats = [];
    
    // Total by status
    $sql = "SELECT status, COUNT(*) as count FROM landing_pages GROUP BY status";
    $stmt = $db->query($sql);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total views and conversions
    $sql = "SELECT SUM(views) as total_views, SUM(conversions) as total_conversions FROM landing_pages";
    $stmt = $db->query($sql);
    $stats['totals'] = $stmt->fetch();
    
    // Top performing pages
    $sql = "SELECT id, title, slug, views, conversions,
                   CASE WHEN views > 0 THEN (conversions / views * 100) ELSE 0 END as conversion_rate
            FROM landing_pages
            WHERE status = 'published'
            ORDER BY views DESC
            LIMIT 10";
    $stmt = $db->query($sql);
    $stats['top_pages'] = $stmt->fetchAll();
    
    // Views per day (last 30 days)
    $sql = "SELECT DATE(created_at) as date, SUM(views) as views
            FROM landing_pages
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    $stmt = $db->query($sql);
    $stats['daily_views'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getActivityStats($db) {
    $stats = [];
    
    // Activity by action (last 30 days)
    $sql = "SELECT action, COUNT(*) as count 
            FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY count DESC";
    $stmt = $db->query($sql);
    $stats['by_action'] = $stmt->fetchAll();
    
    // Activity per day (last 30 days)
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    $stmt = $db->query($sql);
    $stats['daily_activity'] = $stmt->fetchAll();
    
    // Most active admins
    $sql = "SELECT a.full_name, COUNT(*) as count
            FROM activity_logs al
            JOIN admins a ON al.admin_id = a.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY al.admin_id
            ORDER BY count DESC
            LIMIT 10";
    $stmt = $db->query($sql);
    $stats['active_admins'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getChartData($db) {
    $type = $_GET['type'] ?? 'tickets';
    $period = $_GET['period'] ?? '30'; // days
    
    $data = [];
    
    switch ($type) {
        case 'tickets':
            $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                    FROM tickets 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :period DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':period' => $period]);
            $data = $stmt->fetchAll();
            break;
            
        case 'landing_views':
            $sql = "SELECT DATE(created_at) as date, SUM(views) as count 
                    FROM landing_pages 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :period DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':period' => $period]);
            $data = $stmt->fetchAll();
            break;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

