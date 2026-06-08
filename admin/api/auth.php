<?php
/**
 * Authentication API Endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'login';
$db = getDB();

try {
    switch ($action) {
        case 'login':
            handleLogin($db);
            break;
        case 'logout':
            handleLogout($db);
            break;
        case 'check':
            handleCheck();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleLogin($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }
    
    $result = login($db, $username, $password);
    echo json_encode($result);
}

function handleLogout($db) {
    logout($db);
    // Redirect to login page instead of returning JSON
    header('Location: ../login.php');
    exit;
}

function handleCheck() {
    if (isAuthenticated()) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'email' => $_SESSION['admin_email'],
                'role' => $_SESSION['admin_role'],
                'name' => $_SESSION['admin_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'authenticated' => false]);
    }
}

