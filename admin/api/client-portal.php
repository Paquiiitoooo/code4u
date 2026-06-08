<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

@ini_set('display_errors', '0');

// Filet de sécurité : si une erreur fatale survient (parse error, mémoire, etc.),
// on garantit malgré tout une réponse JSON pour que le front ne plante pas sur
// JSON.parse. Les erreurs gérées passent par le try/catch plus bas.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[client-portal.php] Fatal: ' . $error['message']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Erreur serveur (portail client).']);
    }
});

$adminDir = dirname(__DIR__);
require_once $adminDir . '/config/config.php';

// config.php -> database.php réactive display_errors en développement : on le
// reforce à off ici pour qu'aucun warning PHP ne corrompe le corps JSON.
@ini_set('display_errors', '0');

function portalRespond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function portalInput() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function portalTableExists(PDO $db, $table) {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table
    ");
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function portalHasTables(PDO $db, array $tables) {
    foreach ($tables as $table) {
        if (!portalTableExists($db, $table)) {
            return false;
        }
    }
    return true;
}

function portalCurrentClient(PDO $db) {
    $clientId = (int)($_SESSION['client_id'] ?? 0);
    if (!$clientId || !portalHasTables($db, ['clients', 'client_portal_accounts'])) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            c.id,
            COALESCE(NULLIF(c.raison_sociale, ''), NULLIF(CONCAT_WS(' ', c.prenom, c.nom), ''), c.email) AS company_name,
            c.prenom AS contact_firstname,
            c.nom AS contact_lastname,
            c.email,
            c.telephone AS phone,
            a.status
        FROM clients c
        INNER JOIN client_portal_accounts a ON a.client_id = c.id
        WHERE c.id = :id AND a.status = 'active' AND c.actif = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    return $client ?: null;
}

function portalRequireClient(PDO $db) {
    $client = portalCurrentClient($db);
    if (!$client) {
        portalRespond(['success' => false, 'authenticated' => false, 'message' => 'Non connecté'], 401);
    }
    return $client;
}

function portalMoney($amount, $currency = 'EUR') {
    return number_format((float)$amount, 2, '.', '') . ' ' . $currency;
}

function portalStatusLabel($status) {
    $labels = [
        'active' => 'Actif',
        'annulee' => 'Annulee',
        'brouillon' => 'Brouillon',
        'envoye' => 'Envoye',
        'envoyee' => 'Envoyee',
        'emise' => 'Emise',
        'payee' => 'Payee',
        'partielle' => 'Partielle',
        'impayee' => 'Impayee',
        'accepte' => 'Accepte',
        'refuse' => 'Refuse',
        'expire' => 'Expire',
        'paused' => 'En pause',
        'cancelled' => 'Annulé',
        'draft' => 'Brouillon',
        'sent' => 'Envoyée',
        'due' => 'À payer',
        'paid' => 'Payée',
        'pending' => 'À traiter',
        'contacted' => 'Contacté',
        'quote_sent' => 'Devis envoyé',
        'in_progress' => 'En cours',
        'review' => 'Recette',
        'completed' => 'Terminé',
        'open' => 'Ouvert',
        'waiting' => 'En attente',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
    ];
    return $labels[$status] ?? ucfirst((string)$status);
}

function portalStatusTone($status) {
    if (in_array($status, ['active', 'paid', 'payee', 'accepte', 'completed', 'resolved', 'closed'], true)) {
        return 'ok';
    }
    if (in_array($status, ['brouillon', 'sent', 'envoye', 'envoyee', 'emise', 'due', 'impayee', 'partielle', 'waiting', 'quote_sent', 'review', 'pending'], true)) {
        return 'wait';
    }
    return 'neutral';
}

function portalFetchSubscription(PDO $db, $clientId) {
    if (!portalTableExists($db, 'support_subscriptions')) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT *
        FROM support_subscriptions
        WHERE client_id = :client_id
        ORDER BY FIELD(status, 'active', 'paused', 'cancelled'), created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':client_id' => $clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $options = [];
    if (!empty($row['options_json'])) {
        $decoded = json_decode($row['options_json'], true);
        $options = is_array($decoded) ? $decoded : [];
    }

    $included = (float)$row['included_hours'];
    $used = (float)$row['used_hours'];
    return [
        'id' => (int)$row['id'],
        'plan_name' => $row['plan_name'],
        'status' => $row['status'],
        'status_label' => portalStatusLabel($row['status']),
        'monthly_price' => (float)$row['monthly_price'],
        'currency' => $row['currency'],
        'included_hours' => $included,
        'used_hours' => $used,
        'remaining_hours' => max(0, $included - $used),
        'usage_percent' => $included > 0 ? min(100, round(($used / $included) * 100)) : 0,
        'response_sla' => $row['response_sla'],
        'renewal_date' => $row['renewal_date'],
        'overage_rate' => $row['overage_rate'] !== null ? (float)$row['overage_rate'] : null,
        'options' => $options,
    ];
}

function portalFetchSupportUsage(PDO $db, $clientId) {
    if (!portalTableExists($db, 'support_usage')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT work_date, title, duration_minutes, status
        FROM support_usage
        WHERE client_id = :client_id
        ORDER BY work_date DESC, id DESC
        LIMIT 8
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'date' => $row['work_date'],
            'title' => $row['title'],
            'duration' => round(((int)$row['duration_minutes']) / 60, 2),
            'status' => $row['status'],
            'status_label' => portalStatusLabel($row['status']),
            'tone' => portalStatusTone($row['status']),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchInvoices(PDO $db, $clientId) {
    if (!portalTableExists($db, 'factures')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT id, numero, date_facture, date_echeance, statut, montant_ttc
        FROM factures
        WHERE client_id = :client_id
        ORDER BY date_facture DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'number' => $row['numero'],
            'title' => 'Facture ' . $row['numero'],
            'date' => $row['date_facture'],
            'due_date' => $row['date_echeance'],
            'amount' => (float)$row['montant_ttc'],
            'currency' => 'EUR',
            'status' => $row['statut'],
            'status_label' => portalStatusLabel($row['statut']),
            'tone' => portalStatusTone($row['statut']),
            'pdf_url' => null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchPayments(PDO $db, $clientId) {
    if (!portalHasTables($db, ['paiements', 'factures'])) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT p.*, f.numero AS invoice_number
        FROM paiements p
        INNER JOIN factures f ON f.id = p.facture_id
        WHERE f.client_id = :client_id
        ORDER BY p.date_paiement DESC, p.id DESC
        LIMIT 12
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'invoice_number' => $row['invoice_number'],
            'provider' => 'ERP',
            'method_label' => $row['mode_paiement'] ?: 'Paiement',
            'date' => $row['date_paiement'],
            'amount' => (float)$row['montant'],
            'currency' => 'EUR',
            'status' => 'payee',
            'status_label' => portalStatusLabel('payee'),
            'tone' => portalStatusTone('payee'),
            'reference' => $row['reference'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchPaymentMethods(PDO $db, $clientId) {
    if (!portalTableExists($db, 'payment_methods')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT provider, brand, label, expires_at, is_default, status
        FROM payment_methods
        WHERE client_id = :client_id
        ORDER BY is_default DESC, created_at DESC
        LIMIT 6
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'provider' => $row['provider'],
            'brand' => $row['brand'],
            'label' => $row['label'],
            'expires_at' => $row['expires_at'],
            'is_default' => (bool)$row['is_default'],
            'status' => $row['status'],
            'status_label' => portalStatusLabel($row['status']),
            'tone' => portalStatusTone($row['status']),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchQuotes(PDO $db, $clientId) {
    if (!portalTableExists($db, 'devis')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT id, numero, date_devis, statut, montant_ttc, notes
        FROM devis
        WHERE client_id = :client_id
        ORDER BY date_devis DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'number' => $row['numero'],
            'title' => $row['notes'] ?: ('Devis ' . $row['numero']),
            'date' => $row['date_devis'],
            'amount' => (float)$row['montant_ttc'],
            'currency' => 'EUR',
            'status' => $row['statut'],
            'status_label' => portalStatusLabel($row['statut']),
            'tone' => portalStatusTone($row['statut']),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchProjects(PDO $db, $clientId) {
    if (!portalHasTables($db, ['client_projects', 'project_milestones'])) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT *
        FROM client_projects
        WHERE client_id = :client_id
        ORDER BY FIELD(status, 'in_progress', 'review', 'planned', 'paused', 'completed'), updated_at DESC
        LIMIT 8
    ");
    $stmt->execute([':client_id' => $clientId]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as &$project) {
        $milestones = $db->prepare("
            SELECT title, description, status, due_date
            FROM project_milestones
            WHERE project_id = :project_id
            ORDER BY sort_order ASC, id ASC
        ");
        $milestones->execute([':project_id' => $project['id']]);
        $project['milestones'] = $milestones->fetchAll(PDO::FETCH_ASSOC);
        $project['status_label'] = portalStatusLabel($project['status']);
        $project['tone'] = portalStatusTone($project['status']);
    }
    unset($project);
    return $projects;
}

function portalFetchTickets(PDO $db, $clientEmail) {
    if (!portalTableExists($db, 'tickets')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT ticket_number, subject, status, priority, updated_at, created_at
        FROM tickets
        WHERE customer_email = :email
        ORDER BY updated_at DESC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':email' => $clientEmail]);
    return array_map(function ($row) {
        return [
            'number' => $row['ticket_number'],
            'subject' => $row['subject'],
            'status' => $row['status'],
            'status_label' => portalStatusLabel($row['status']),
            'tone' => portalStatusTone($row['status']),
            'priority' => $row['priority'],
            'updated_at' => $row['updated_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchDocuments(PDO $db, $clientId) {
    if (!portalTableExists($db, 'client_documents')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT category, title, file_url, amount_label, created_at
        FROM client_documents
        WHERE client_id = :client_id
        ORDER BY created_at DESC, id DESC
        LIMIT 24
    ");
    $stmt->execute([':client_id' => $clientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function portalBuildDashboard(PDO $db, array $client) {
    $subscription = portalFetchSubscription($db, (int)$client['id']);
    $invoices = portalFetchInvoices($db, (int)$client['id']);
    $payments = portalFetchPayments($db, (int)$client['id']);
    $paymentMethods = portalFetchPaymentMethods($db, (int)$client['id']);
    $quotes = portalFetchQuotes($db, (int)$client['id']);
    $projects = portalFetchProjects($db, (int)$client['id']);
    $tickets = portalFetchTickets($db, $client['email']);
    $documents = portalFetchDocuments($db, (int)$client['id']);
    $supportUsage = portalFetchSupportUsage($db, (int)$client['id']);

    $dueInvoices = array_values(array_filter($invoices, function ($invoice) {
        return in_array($invoice['status'], ['sent', 'envoyee', 'emise', 'due', 'impayee', 'partielle'], true);
    }));
    $dueAmount = array_reduce($dueInvoices, function ($sum, $invoice) {
        return $sum + (float)$invoice['amount'];
    }, 0.0);
    $activeProject = $projects[0] ?? null;
    $openTickets = array_values(array_filter($tickets, function ($ticket) {
        return !in_array($ticket['status'], ['resolved', 'closed'], true);
    }));
    $quotesToSign = array_values(array_filter($quotes, function ($quote) {
        return in_array($quote['status'], ['pending', 'quote_sent', 'brouillon', 'envoye'], true);
    }));

    return [
        'success' => true,
        'authenticated' => true,
        'client' => $client,
        'summary' => [
            'due_amount' => $dueAmount,
            'due_amount_label' => portalMoney($dueAmount, 'EUR'),
            'due_date' => $dueInvoices[0]['due_date'] ?? null,
            'support_plan' => $subscription['plan_name'] ?? 'Aucun',
            'support_remaining_hours' => $subscription['remaining_hours'] ?? 0,
            'active_project_progress' => $activeProject ? (int)$activeProject['progress'] : 0,
            'active_project_name' => $activeProject['name'] ?? null,
            'open_tickets' => count($openTickets),
            'quotes_to_sign' => count($quotesToSign),
        ],
        'subscription' => $subscription,
        'support_usage' => $supportUsage,
        'invoices' => $invoices,
        'payments' => $payments,
        'payment_methods' => $paymentMethods,
        'quotes' => $quotes,
        'projects' => $projects,
        'tickets' => $tickets,
        'documents' => $documents,
    ];
}

$action = $_GET['action'] ?? '';
$input = portalInput();
if (!$action) {
    $action = $input['action'] ?? 'me';
}

try {
    $db = getDB();

    if ($action === 'login') {
        if (!portalHasTables($db, ['clients', 'client_portal_accounts'])) {
            portalRespond([
                'success' => false,
                'message' => 'Tables client manquantes. Importez database/client_portal.sql.',
            ], 503);
        }

        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            portalRespond(['success' => false, 'message' => 'Identifiants invalides'], 422);
        }

        $stmt = $db->prepare("
            SELECT
                a.client_id,
                a.password_hash,
                a.status,
                c.id,
                COALESCE(NULLIF(c.raison_sociale, ''), NULLIF(CONCAT_WS(' ', c.prenom, c.nom), ''), c.email) AS company_name,
                c.prenom AS contact_firstname,
                c.nom AS contact_lastname,
                c.email,
                c.telephone AS phone
            FROM client_portal_accounts a
            INNER JOIN clients c ON c.id = a.client_id
            WHERE a.email = :email AND a.status = 'active' AND c.actif = 1
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || !password_verify($password, $account['password_hash'])) {
            portalRespond(['success' => false, 'message' => 'Identifiants incorrects'], 401);
        }

        $_SESSION['client_id'] = (int)$account['client_id'];
        unset($account['password_hash']);
        portalRespond(portalBuildDashboard($db, $account));
    }

    if ($action === 'logout') {
        unset($_SESSION['client_id']);
        portalRespond(['success' => true, 'authenticated' => false]);
    }

    $client = portalRequireClient($db);
    portalRespond(portalBuildDashboard($db, $client));
} catch (Throwable $e) {
    error_log('[client-portal.php] ' . $e->getMessage());
    portalRespond(['success' => false, 'message' => 'Erreur portail client'], 500);
}
