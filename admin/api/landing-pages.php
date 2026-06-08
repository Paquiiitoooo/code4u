<?php
/**
 * Landing Pages API Endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
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
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            getLandingPagesList($db);
            break;
        case 'single':
            getSingleLandingPage($db);
            break;
        case 'public':
            getPublicLandingPage($db);
            break;
        default:
            getLandingPagesList($db);
    }
}

function getLandingPagesList($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = "lp.status = :status";
        $params[':status'] = $status;
    }
    
    if ($search) {
        $where[] = "(lp.title LIKE :search OR lp.slug LIKE :search OR lp.description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $countSql = "SELECT COUNT(*) as total FROM landing_pages lp $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    $sql = "SELECT lp.*, a.full_name as created_by_name
            FROM landing_pages lp
            LEFT JOIN admins a ON lp.created_by = a.id
            $whereClause
            ORDER BY lp.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $pages = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $pages,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getSingleLandingPage($db) {
    $id = $_GET['id'] ?? $_GET['slug'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Page ID or slug required']);
        return;
    }
    
    $field = is_numeric($id) ? 'id' : 'slug';
    $sql = "SELECT lp.*, a.full_name as created_by_name
            FROM landing_pages lp
            LEFT JOIN admins a ON lp.created_by = a.id
            WHERE lp.$field = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $page = $stmt->fetch();
    
    if (!$page) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Landing page not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $page]);
}

function getPublicLandingPage($db) {
    $slug = $_GET['slug'] ?? null;
    if (!$slug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Slug required']);
        return;
    }
    
    $sql = "SELECT * FROM landing_pages WHERE slug = :slug AND status = 'published'";
    $stmt = $db->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Landing page not found']);
        return;
    }
    
    // Increment views
    $updateSql = "UPDATE landing_pages SET views = views + 1 WHERE id = :id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([':id' => $page['id']]);
    
    // Update statistics
    updateStatistic($db, 'landing_page_view', 1, ['slug' => $slug]);
    
    echo json_encode(['success' => true, 'data' => $page]);
}

function handlePost($db, $action) {
    createLandingPage($db);
}

function createLandingPage($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['title']) || empty($data['content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }
    
    $slug = $data['slug'] ?? generateSlug($data['title']);
    
    // Check if slug exists
    $checkSql = "SELECT id FROM landing_pages WHERE slug = :slug";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':slug' => $slug]);
    if ($checkStmt->fetch()) {
        $slug = $slug . '-' . time();
    }
    
    $adminId = getCurrentAdminId();
    
    $sql = "INSERT INTO landing_pages (slug, title, description, content, meta_title, meta_description,
                                      status, template, custom_css, custom_js, created_by, published_at)
            VALUES (:slug, :title, :description, :content, :meta_title, :meta_description,
                    :status, :template, :custom_css, :custom_js, :created_by, :published_at)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':slug' => $slug,
        ':title' => $data['title'],
        ':description' => $data['description'] ?? null,
        ':content' => $data['content'],
        ':meta_title' => $data['meta_title'] ?? null,
        ':meta_description' => $data['meta_description'] ?? null,
        ':status' => $data['status'] ?? 'draft',
        ':template' => $data['template'] ?? 'default',
        ':custom_css' => $data['custom_css'] ?? null,
        ':custom_js' => $data['custom_js'] ?? null,
        ':created_by' => $adminId,
        ':published_at' => ($data['status'] ?? 'draft') === 'published' ? date('Y-m-d H:i:s') : null
    ]);
    
    $pageId = $db->lastInsertId();
    
    logActivity($db, 'landing_page_created', 'landing_page', $pageId, "Landing page created: $slug");
    updateStatistic($db, 'landing_pages_created', 1);
    
    echo json_encode([
        'success' => true,
        'message' => 'Landing page created successfully',
        'data' => ['id' => $pageId, 'slug' => $slug]
    ]);
}

function handlePut($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? $data['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Page ID required']);
        return;
    }
    
    $allowedFields = ['title', 'description', 'content', 'meta_title', 'meta_description', 
                      'status', 'template', 'custom_css', 'custom_js', 'slug'];
    $updates = [];
    $params = [':id' => $id];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        return;
    }
    
    // If status changed to published, set published_at
    if (isset($data['status']) && $data['status'] === 'published') {
        $updates[] = "published_at = COALESCE(published_at, NOW())";
    }
    
    $sql = "UPDATE landing_pages SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    logActivity($db, 'landing_page_updated', 'landing_page', $id, "Landing page updated");
    
    echo json_encode(['success' => true, 'message' => 'Landing page updated successfully']);
}

function handleDelete($db, $action) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Page ID required']);
        return;
    }
    
    $sql = "DELETE FROM landing_pages WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    logActivity($db, 'landing_page_deleted', 'landing_page', $id, "Landing page deleted");
    
    echo json_encode(['success' => true, 'message' => 'Landing page deleted successfully']);
}

