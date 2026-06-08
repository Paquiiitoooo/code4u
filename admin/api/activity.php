<?php
/**
 * Activity Logs API Endpoint
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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $sql = "SELECT al.*, a.full_name as admin_name, a.username as admin_username
            FROM activity_logs al
            LEFT JOIN admins a ON al.admin_id = a.id
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $activities = $stmt->fetchAll();
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM activity_logs";
    $countStmt = $db->query($countSql);
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $activities,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

