<?php
/**
 * Authentication Functions for Code4U Admin Panel
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Login function
 */
function login($db, $username, $password) {
    $sql = "SELECT * FROM admins WHERE username = :username OR email = :email LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'email' => $username
    ]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        return ['success' => false, 'message' => 'Identifiants incorrects'];
    }
    
    if (!password_verify($password, $admin['password'])) {
        return ['success' => false, 'message' => 'Identifiants incorrects'];
    }
    
    // Create session
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_name'] = $admin['full_name'];
    
    // Update last login
    $updateSql = "UPDATE admins SET last_login = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute(['id' => $admin['id']]);
    
    // Log activity
    logActivity($db, 'login', 'admin', $admin['id'], "Admin logged in");
    
    return ['success' => true, 'message' => 'Connexion réussie', 'admin' => $admin];
}

/**
 * Logout function
 */
function logout($db) {
    if (isset($_SESSION['admin_id'])) {
        logActivity($db, 'logout', 'admin', $_SESSION['admin_id'], "Admin logged out");
    }
    
    session_unset();
    session_destroy();
    
    return ['success' => true, 'message' => 'Déconnexion réussie'];
}

/**
 * Check if user has permission
 */
function hasPermission($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }
    
    $roleHierarchy = [
        'moderator' => 1,
        'admin' => 2,
        'super_admin' => 3
    ];
    
    $userRole = $_SESSION['admin_role'] ?? 'moderator';
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Require specific permission
 */
function requirePermission($requiredRole) {
    if (!hasPermission($requiredRole)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Accès refusé']));
    }
}

