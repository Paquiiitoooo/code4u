<?php
/**
 * Code4U V2 â€” Orders API
 * Handles: create, list, get, update_status, add_note, stats
 *
 * Endpoints (all via this single file):
 *   POST ?action=create          â€” public, creates a new order
 *   GET  ?action=list            â€” admin, paginated order list
 *   GET  ?action=get&id=X        â€” admin/public, single order detail
 *   POST ?action=update_status   â€” admin only
 *   POST ?action=add_note        â€” admin only
 *   GET  ?action=stats           â€” admin only
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// â”€â”€ Dependencies â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$adminDir = dirname(__DIR__);
require_once $adminDir . '/config/config.php';

// Reponses TOUJOURS en JSON : on coupe l'affichage des erreurs PHP (logguees seulement)
@ini_set('display_errors', '0');

// Connexion DB resiliente : ne casse jamais la reponse publique.
// Si la base est indisponible, on bascule sur un fallback fichier (aucun lead perdu).
$pdo = null;
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (Throwable $e) {
    error_log('[orders.php] Base indisponible, fallback fichier: ' . $e->getMessage());
    $pdo = null;
}

// â”€â”€ Input parsing â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$method   = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?? [];
$action   = $input['action'] ?? ($_GET['action'] ?? '');

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function san($v) {
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// â”€â”€ Router â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function quoteCatalog() {
    return [
        'vitrine' => [
            'name' => 'Site Vitrine',
            'base_price' => 599.00,
            'options' => [
                'pages' => ['name' => 'Pages supplementaires (+5 pages)', 'price' => 150.00],
                'blog' => ['name' => 'Blog integre', 'price' => 200.00],
                'animations' => ['name' => 'Animations avancees', 'price' => 100.00],
                'multilang' => ['name' => 'Multi-langues (FR/EN)', 'price' => 300.00],
                'booking' => ['name' => 'Formulaire de reservation', 'price' => 150.00],
                'gallery' => ['name' => 'Galerie photos professionnelle', 'price' => 100.00],
            ],
        ],
        'ecommerce' => [
            'name' => 'E-commerce',
            'base_price' => 1490.00,
            'options' => [
                'products500' => ['name' => 'Jusqu a 500 produits', 'price' => 300.00],
                'multipay' => ['name' => 'Paiements multiples', 'price' => 200.00],
                'loyalty' => ['name' => 'Programme fidelite', 'price' => 400.00],
                'multicurrency' => ['name' => 'Multi-devises', 'price' => 300.00],
                'pwa' => ['name' => 'App mobile PWA', 'price' => 500.00],
                'reviews' => ['name' => 'Module avis clients', 'price' => 150.00],
            ],
        ],
        'webapp' => [
            'name' => 'Application Web',
            'base_price' => 1199.00,
            'options' => [
                'auth' => ['name' => 'Authentification utilisateurs', 'price' => 200.00],
                'roles' => ['name' => 'Roles et permissions', 'price' => 300.00],
                'api' => ['name' => 'API REST complete', 'price' => 400.00],
                'analytics' => ['name' => 'Tableau de bord analytics', 'price' => 350.00],
                'notifications' => ['name' => 'Notifications email/SMS', 'price' => 250.00],
                'export' => ['name' => 'Export donnees (PDF/Excel)', 'price' => 200.00],
            ],
        ],
        'logiciel' => [
            'name' => 'Logiciel / Automatisation',
            'base_price' => 499.00,
            'options' => [
                'gui' => ['name' => 'Interface graphique (GUI)', 'price' => 300.00],
                'db' => ['name' => 'Connexion base de donnees', 'price' => 200.00],
                'excel' => ['name' => 'Traitement fichiers Excel/CSV', 'price' => 150.00],
                'email' => ['name' => 'Envoi emails automatiques', 'price' => 100.00],
                'pdf' => ['name' => 'Rapport PDF automatique', 'price' => 200.00],
                'deploy' => ['name' => 'Deploiement serveur', 'price' => 250.00],
            ],
        ],
    ];
}

function calculateQuote($input) {
    $catalog = quoteCatalog();
    $serviceType = $input['service_type'] ?? $input['service'] ?? '';
    if (!isset($catalog[$serviceType])) {
        respond(['success' => false, 'error' => 'Type de service invalide'], 422);
    }

    $deadline = $input['deadline'] ?? 'standard';
    $multipliers = ['standard' => 1.00, 'accelerated' => 1.30, 'urgent' => 1.50];
    if (!isset($multipliers[$deadline])) {
        respond(['success' => false, 'error' => 'Delai invalide'], 422);
    }

    $selectedOptions = [];
    $optionsPrice = 0.00;
    foreach (($input['options'] ?? []) as $selected) {
        $optionId = is_array($selected) ? ($selected['id'] ?? '') : $selected;
        if (!$optionId || !isset($catalog[$serviceType]['options'][$optionId])) {
            respond(['success' => false, 'error' => 'Option de devis invalide'], 422);
        }

        $option = $catalog[$serviceType]['options'][$optionId];
        $selectedOptions[] = ['id' => $optionId, 'name' => $option['name'], 'price' => $option['price']];
        $optionsPrice += $option['price'];
    }

    $basePrice = $catalog[$serviceType]['base_price'];
    $multiplier = $multipliers[$deadline];

    return [
        'service_type' => $serviceType,
        'service_name' => $catalog[$serviceType]['name'],
        'options' => $selectedOptions,
        'options_price' => $optionsPrice,
        'base_price' => $basePrice,
        'deadline' => $deadline,
        'deadline_multiplier' => $multiplier,
        'total_price' => round(($basePrice + $optionsPrice) * $multiplier, 2),
    ];
}

function dbTableExists($pdo, $table) {
    if (!$pdo) return false;
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table
    ");
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function erpTablesReady($pdo) {
    return dbTableExists($pdo, 'clients') && dbTableExists($pdo, 'devis') && dbTableExists($pdo, 'devis_lignes');
}

function erpGenerateCode($pdo, $table, $column, $prefix, $pad) {
    $stmt = $pdo->prepare("SELECT `$column` FROM `$table` WHERE `$column` LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = (string)($stmt->fetchColumn() ?: '');
    $num = 0;
    if (preg_match('/' . preg_quote($prefix, '/') . '-?(\d+)/', $last, $matches)) {
        $num = (int)$matches[1];
    }
    return $prefix . str_pad((string)($num + 1), $pad, '0', STR_PAD_LEFT);
}

function erpFindOrCreateClient($pdo, $client, $input) {
    $email = filter_var($client['email'] ?? $input['client_email'] ?? '', FILTER_SANITIZE_EMAIL);
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) return (int)$existingId;
    }

    $company = san($client['company'] ?? $input['client_company'] ?? '');
    $firstname = san($client['firstname'] ?? $input['client_firstname'] ?? '');
    $lastname = san($client['lastname'] ?? $input['client_lastname'] ?? '');
    $phone = san($client['phone'] ?? $input['client_phone'] ?? '');
    $type = $company ? 'entreprise' : 'particulier';
    $name = $lastname ?: ($company ?: $email);
    $code = erpGenerateCode($pdo, 'clients', 'code', 'CLI', 3);

    $stmt = $pdo->prepare("
        INSERT INTO clients (
            code, type, nom, prenom, raison_sociale, email, telephone, pays, notes, actif
        ) VALUES (
            :code, :type, :nom, :prenom, :raison_sociale, :email, :telephone, 'France', :notes, 1
        )
    ");
    $stmt->execute([
        ':code' => $code,
        ':type' => $type,
        ':nom' => $name,
        ':prenom' => $firstname ?: null,
        ':raison_sociale' => $company ?: null,
        ':email' => $email ?: null,
        ':telephone' => $phone ?: null,
        ':notes' => 'Client créé automatiquement depuis le simulateur Code4U.',
    ]);

    return (int)$pdo->lastInsertId();
}

function erpCreateEstimate($pdo, $input, $quote, $clientData) {
    if (!$pdo || !erpTablesReady($pdo)) return null;

    $pdo->beginTransaction();
    try {
        $clientId = erpFindOrCreateClient($pdo, $clientData, $input);
        $numero = erpGenerateCode($pdo, 'devis', 'numero', 'DEV-', 6);
        $today = date('Y-m-d');
        $validUntil = date('Y-m-d', strtotime('+30 days'));
        $total = (float)$quote['total_price'];
        $description = san($input['description'] ?? '');

        $notes = "Estimation créée automatiquement depuis le site Code4U.\n"
            . "Service : " . $quote['service_name'] . "\n"
            . "Délai : " . san($input['deadlineLabel'] ?? $quote['deadline']) . "\n"
            . ($description ? "Description client : " . $description : '');

        $stmt = $pdo->prepare("
            INSERT INTO devis (
                numero, client_id, date_devis, date_validite, statut,
                montant_ht, montant_tva, montant_ttc, remise, notes, conditions
            ) VALUES (
                :numero, :client_id, :date_devis, :date_validite, 'brouillon',
                :montant_ht, 0, :montant_ttc, 0, :notes, :conditions
            )
        ");
        $stmt->execute([
            ':numero' => $numero,
            ':client_id' => $clientId,
            ':date_devis' => $today,
            ':date_validite' => $validUntil,
            ':montant_ht' => $total,
            ':montant_ttc' => $total,
            ':notes' => $notes,
            ':conditions' => 'Devis généré depuis le simulateur en ligne. Montant à valider avant envoi officiel.',
        ]);
        $devisId = (int)$pdo->lastInsertId();

        $productId = null;
        if (dbTableExists($pdo, 'produits')) {
            $productStmt = $pdo->query("SELECT id FROM produits WHERE actif = 1 ORDER BY id ASC LIMIT 1");
            $productId = $productStmt ? $productStmt->fetchColumn() : null;
        }

        $lineStmt = $pdo->prepare("
            INSERT INTO devis_lignes (
                devis_id, produit_id, parent_id, type_ligne, libelle, description,
                quantite, prix_unitaire_ht, tva, ordre
            ) VALUES (
                :devis_id, :produit_id, :parent_id, :type_ligne, :libelle, :description,
                :quantite, :prix_unitaire_ht, 0, :ordre
            )
        ");

        $order = 0;
        $baseLine = [
            ':devis_id' => $devisId,
            ':produit_id' => $productId ?: null,
            ':parent_id' => null,
            ':type_ligne' => 'produit',
            ':libelle' => $quote['service_name'],
            ':description' => $description ?: null,
            ':quantite' => 1,
            ':prix_unitaire_ht' => (float)$quote['base_price'],
            ':ordre' => $order++,
        ];
        $lineStmt->execute($baseLine);
        $parentLineId = (int)$pdo->lastInsertId();

        foreach ($quote['options'] as $option) {
            $lineStmt->execute([
                ':devis_id' => $devisId,
                ':produit_id' => null,
                ':parent_id' => $parentLineId,
                ':type_ligne' => 'detail',
                ':libelle' => $option['name'],
                ':description' => null,
                ':quantite' => 1,
                ':prix_unitaire_ht' => (float)$option['price'],
                ':ordre' => $order++,
            ]);
        }

        if ((float)$quote['deadline_multiplier'] > 1) {
            $deadlineExtra = $total - ((float)$quote['base_price'] + (float)$quote['options_price']);
            if ($deadlineExtra > 0) {
                $lineStmt->execute([
                    ':devis_id' => $devisId,
                    ':produit_id' => null,
                    ':parent_id' => $parentLineId,
                    ':type_ligne' => 'detail',
                    ':libelle' => 'Majoration délai ' . san($input['deadlineLabel'] ?? $quote['deadline']),
                    ':description' => null,
                    ':quantite' => 1,
                    ':prix_unitaire_ht' => round($deadlineExtra, 2),
                    ':ordre' => $order++,
                ]);
            }
        }

        $pdo->commit();
        return ['client_id' => $clientId, 'devis_id' => $devisId, 'numero' => $numero];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

switch ($action) {
    case 'create':        createOrder($pdo, $input);                              break;
    case 'list':          listOrders($pdo);                                        break;
    case 'get':           getOrder($pdo, $input['id'] ?? $_GET['id'] ?? null);    break;
    case 'update_status': updateStatus($pdo, $input);                              break;
    case 'add_note':      addNote($pdo, $input);                                   break;
    case 'stats':         getStats($pdo);                                           break;
    default:              respond(['success' => false, 'error' => 'Action inconnue: ' . $action], 400);
}

// â”€â”€ createOrder â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function createOrder($pdo, $input) {
    $client = is_array($input['client'] ?? null) ? $input['client'] : $input;
    $quote = calculateQuote($input);

    // Required fields check
    $required = ['email'];
    foreach ($required as $field) {
        if (empty($client[$field]) && empty($input['client_' . $field])) {
            respond(['success' => false, 'error' => "Champ requis manquant: $field"], 422);
        }
    }

    // Validate email
    $clientEmail = $client['email'] ?? $input['client_email'] ?? '';
    if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Email invalide'], 422);
    }

    // Generate unique reference: CODE4U-YYYYMMDD-XXXX
    $reference = 'CODE4U-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));

    // Sanitize and cast all fields
    $serviceType   = $quote['service_type'];
    $serviceName   = $quote['service_name'];
    $options       = json_encode($quote['options'], JSON_UNESCAPED_UNICODE);
    $optionsPrice  = $quote['options_price'];
    $basePrice     = $quote['base_price'];
    $deadline      = $quote['deadline'];
    $multiplier    = $quote['deadline_multiplier'];
    $totalPrice    = $quote['total_price'];
    $description   = san($input['description'] ?? '');
    $firstname     = san($client['firstname'] ?? $input['client_firstname'] ?? '');
    $lastname      = san($client['lastname'] ?? $input['client_lastname'] ?? '');
    $email         = filter_var($clientEmail, FILTER_SANITIZE_EMAIL);
    $phone         = san($client['phone'] ?? $input['client_phone'] ?? '');
    $company       = san($client['company'] ?? $input['client_company'] ?? '');
    $website       = san($client['website'] ?? $input['client_website'] ?? '');
    $source        = san($client['source'] ?? $input['client_source'] ?? '');

    // Donnees utiles au workflow + fallback fichier
    $fallbackData = [
        'service' => $serviceName, 'total' => $totalPrice, 'firstname' => $firstname,
        'lastname' => $lastname, 'email' => $email, 'phone' => $phone, 'company' => $company,
        'website' => $website, 'source' => $source, 'deadline' => $deadline, 'description' => $description,
    ];

    // Base indisponible : on enregistre dans le fichier d'attente et on confirme quand meme.
    if ($pdo === null) {
        saveOrderFallback($reference, $fallbackData);
        respond([
            'success' => true, 'order_id' => null, 'reference' => $reference,
            'stored' => 'file', 'message' => 'Commande enregistree avec succes',
        ]);
    }

    try {
        $erpEstimate = erpCreateEstimate($pdo, $input, $quote, $client);
        if ($erpEstimate) {
            respond([
                'success' => true,
                'order_id' => null,
                'client_id' => $erpEstimate['client_id'],
                'devis_id' => $erpEstimate['devis_id'],
                'reference' => $erpEstimate['numero'],
                'stored' => 'erp',
                'message' => 'Estimation enregistrée dans l’ERP',
            ]);
        }
    } catch (Throwable $e) {
        error_log('[orders.php] ERP insert failed, legacy fallback: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                reference, service_type, service_name, options, options_price,
                base_price, deadline, deadline_multiplier, total_price, description,
                client_firstname, client_lastname, client_email, client_phone,
                client_company, client_website, client_source, status
            ) VALUES (
                :ref,    :stype,  :sname,  :opts,   :oprice,
                :bprice, :deadline, :mult, :tprice, :desc,
                :fname,  :lname,  :email,  :phone,
                :company, :website, :source, 'pending'
            )
        ");

        $stmt->execute([
            ':ref'      => $reference,
            ':stype'    => $serviceType,
            ':sname'    => $serviceName,
            ':opts'     => $options,
            ':oprice'   => $optionsPrice,
            ':bprice'   => $basePrice,
            ':deadline' => $deadline,
            ':mult'     => $multiplier,
            ':tprice'   => $totalPrice,
            ':desc'     => $description,
            ':fname'    => $firstname,
            ':lname'    => $lastname,
            ':email'    => $email,
            ':phone'    => $phone,
            ':company'  => $company,
            ':website'  => $website,
            ':source'   => $source,
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // Log initial history entry
        $pdo->prepare("
            INSERT INTO order_history (order_id, new_status, note)
            VALUES (?, 'pending', 'Commande crÃ©Ã©e via le site')
        ")->execute([$orderId]);

        // Trigger email workflows
        require_once dirname(__DIR__) . '/api/workflow.php';
        $orderData = [
            'id'               => $orderId,
            'reference'        => $reference,
            'service_name'     => $serviceName,
            'total_price'      => $totalPrice,
            'client_firstname' => $firstname,
            'client_lastname'  => $lastname,
            'client_email'     => $email,
            'client_phone'     => $phone,
            'client_company'   => $company,
            'options'          => $options,
            'description'      => $description,
        ];

        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'contact@code4u.fr';
        try {
            triggerWorkflow($pdo, $orderId, 'new_order_client', $email, $orderData);
            triggerWorkflow($pdo, $orderId, 'new_order_admin',  $adminEmail, $orderData);
        } catch (Throwable $e) {
            error_log('[orders.php] workflow ignore: ' . $e->getMessage());
        }

        respond([
            'success'   => true,
            'order_id'  => $orderId,
            'reference' => $reference,
            'message'   => 'Commande enregistrÃ©e avec succÃ¨s',
        ]);

    } catch (Throwable $e) {
        // Echec d'ecriture en base (table absente, etc.) : on ne perd pas le lead.
        error_log('[orders.php] createOrder fallback fichier: ' . $e->getMessage());
        saveOrderFallback($reference, $fallbackData);
        respond([
            'success' => true, 'order_id' => null, 'reference' => $reference,
            'stored' => 'file', 'message' => 'Commande enregistree avec succes',
        ]);
    }
}

/**
 * Fallback : enregistre la commande dans logs/orders_inbox.jsonl quand la base
 * n'est pas disponible. Permet de ne perdre aucune demande client.
 */
function saveOrderFallback($reference, $data) {
    try {
        $dir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $row = array_merge(['reference' => $reference, 'received_at' => date('c')], $data);
        @file_put_contents(
            $dir . '/orders_inbox.jsonl',
            json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    } catch (Throwable $e) {
        error_log('[orders.php] fallback fichier impossible: ' . $e->getMessage());
    }
}

// â”€â”€ listOrders â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€


// listOrders

function listOrders($pdo) {
    if (!isAdmin()) {
        respond(['success' => false, 'error' => 'Non autorise'], 401);
    }

    $status  = $_GET['status'] ?? '';
    $search  = $_GET['search'] ?? '';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($status && $status !== 'all') {
        $where[]           = 'status = :status';
        $params[':status'] = $status;
    }
    if ($search) {
        $where[]           = '(client_email LIKE :search OR client_lastname LIKE :search OR client_firstname LIKE :search OR reference LIKE :search OR client_company LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Per-status counts for tab badges
    $counts    = [];
    $countRows = $pdo->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($countRows as $r) {
        $counts[$r['status']] = (int)$r['c'];
    }
    $counts['all'] = array_sum($counts);

    // Total matching rows for pagination
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM orders $whereStr");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    // Fetch current page
    $stmt = $pdo->prepare("
        SELECT id, reference, service_type, service_name, total_price,
               client_firstname, client_lastname, client_email, client_phone,
               client_company, status, created_at
        FROM orders
        $whereStr
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'orders'  => $orders,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total / $perPage),
        'counts'  => $counts,
    ]);
}

// â”€â”€ getOrder â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function getOrder($pdo, $id) {
    if (!isAdmin()) {
        respond(['success' => false, 'error' => 'Non autorise'], 401);
    }

    if (!$id) {
        respond(['success' => false, 'error' => 'ID manquant'], 400);
    }

    // Accept numeric id or reference string (e.g. CODE4U-20250513-AB12)
    $field = is_numeric($id) ? 'id' : 'reference';
    $stmt  = $pdo->prepare("SELECT * FROM orders WHERE $field = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        respond(['success' => false, 'error' => 'Commande introuvable'], 404);
    }

    // Decode options for convenience
    if (!empty($order['options'])) {
        $order['options_decoded'] = json_decode($order['options'], true);
    }

    // Fetch full status history
    $histStmt = $pdo->prepare("SELECT * FROM order_history WHERE order_id = :id ORDER BY changed_at ASC");
    $histStmt->execute([':id' => $order['id']]);
    $order['history'] = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    respond(['success' => true, 'order' => $order]);
}

// â”€â”€ updateStatus â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function updateStatus($pdo, $input) {
    if (!isAdmin()) {
        respond(['success' => false, 'error' => 'Non autorisÃ©'], 401);
    }

    $orderId   = (int)($input['id'] ?? 0);
    $newStatus = san($input['status'] ?? '');
    $note      = san($input['note'] ?? '');

    $validStatuses = ['pending', 'contacted', 'quote_sent', 'in_progress', 'review', 'completed', 'cancelled'];
    if (!$orderId || !in_array($newStatus, $validStatuses)) {
        respond(['success' => false, 'error' => 'DonnÃ©es invalides'], 422);
    }

    try {
        // Fetch current order
        $oldStmt = $pdo->prepare("SELECT status, client_email, client_firstname, reference FROM orders WHERE id = :id");
        $oldStmt->execute([':id' => $orderId]);
        $order = $oldStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            respond(['success' => false, 'error' => 'Commande introuvable'], 404);
        }

        $oldStatus = $order['status'];

        // Build optional timestamp columns
        $extra  = [];
        $params = [':status' => $newStatus, ':id' => $orderId];

        if ($newStatus === 'contacted' && $oldStatus === 'pending') {
            $extra[] = 'contacted_at = NOW()';
        }
        if ($newStatus === 'completed') {
            $extra[] = 'completed_at = NOW()';
        }

        $extraStr = $extra ? ', ' . implode(', ', $extra) : '';
        $pdo->prepare("UPDATE orders SET status = :status $extraStr WHERE id = :id")->execute($params);

        // History entry
        $pdo->prepare("
            INSERT INTO order_history (order_id, old_status, new_status, note)
            VALUES (:oid, :old, :new, :note)
        ")->execute([
            ':oid'  => $orderId,
            ':old'  => $oldStatus,
            ':new'  => $newStatus,
            ':note' => $note,
        ]);

        respond(['success' => true, 'message' => 'Statut mis Ã  jour']);

    } catch (PDOException $e) {
        error_log('[orders.php] updateStatus error: ' . $e->getMessage());
        respond(['success' => false, 'error' => 'Erreur serveur'], 500);
    }
}

// â”€â”€ addNote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function addNote($pdo, $input) {
    if (!isAdmin()) {
        respond(['success' => false, 'error' => 'Non autorisÃ©'], 401);
    }

    $orderId = (int)($input['id'] ?? 0);
    $note    = san($input['note'] ?? '');

    if (!$orderId || !$note) {
        respond(['success' => false, 'error' => 'DonnÃ©es manquantes'], 422);
    }

    $pdo->prepare("UPDATE orders SET admin_notes = :note WHERE id = :id")
        ->execute([':note' => $note, ':id' => $orderId]);

    respond(['success' => true, 'message' => 'Note enregistrÃ©e']);
}

// â”€â”€ getStats â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function getStats($pdo) {
    if (!isAdmin()) {
        respond(['success' => false, 'error' => 'Non autorise'], 401);
    }

    $total      = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pending    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $inProgress = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'in_progress'")->fetchColumn();
    $completed  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
    $revenue    = (float)$pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed'")->fetchColumn();
    $avgOrder   = (float)$pdo->query("SELECT COALESCE(AVG(total_price), 0) FROM orders")->fetchColumn();
    $thisMonth  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();

    respond([
        'success'     => true,
        'total'       => $total,
        'pending'     => $pending,
        'in_progress' => $inProgress,
        'completed'   => $completed,
        'revenue'     => round($revenue,  2),
        'avg_order'   => round($avgOrder, 2),
        'this_month'  => $thisMonth,
    ]);
}
