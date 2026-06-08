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

function portalColumnExists(PDO $db, $table, $column) {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
    ");
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function portalEnsureTicketAccessCode(PDO $db) {
    if (portalTableExists($db, 'tickets') && !portalColumnExists($db, 'tickets', 'access_code')) {
        $db->exec("ALTER TABLE tickets ADD COLUMN access_code VARCHAR(32) DEFAULT NULL AFTER ticket_number");
        $db->exec("ALTER TABLE tickets ADD UNIQUE KEY idx_access_code (access_code)");
    }
}

/**
 * Crée les tables tickets / ticket_messages si elles n'existent pas dans la
 * base ERP (le portail support en a besoin). Volontairement SANS clé étrangère
 * vers `admins` : cette table peut ne pas exister dans la base ERP. 100% additif.
 */
function portalEnsureTicketTables(PDO $db) {
    if (!portalTableExists($db, 'tickets')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_number VARCHAR(20) NOT NULL UNIQUE,
                access_code VARCHAR(32) DEFAULT NULL,
                subject VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50) DEFAULT NULL,
                status ENUM('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
                priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
                category VARCHAR(100) DEFAULT NULL,
                assigned_to INT DEFAULT NULL,
                source ENUM('chatbot','form','email','phone','admin') NOT NULL DEFAULT 'form',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at DATETIME DEFAULT NULL,
                UNIQUE KEY idx_access_code (access_code),
                KEY idx_ticket_number (ticket_number),
                KEY idx_customer_email (customer_email),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    if (!portalTableExists($db, 'ticket_messages')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS ticket_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                sender_type ENUM('customer','admin','system') NOT NULL DEFAULT 'customer',
                sender_id INT DEFAULT NULL,
                message TEXT NOT NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 0,
                attachments JSON DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ticket_id (ticket_id),
                CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    portalEnsureTicketAccessCode($db);
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
            c.adresse,
            c.code_postal,
            c.ville,
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

/**
 * Envoie un email de notification au client (charte Code4U).
 * Utilise mail() PHP (comme contact.php). En local, on logue seulement.
 */
function portalSendClientMail($client, $subject, $title, $bodyHtml) {
    $to = is_array($client) ? ($client['email'] ?? '') : (string)$client;
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'contact@code4u.fr';
    $html = '<!doctype html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;background:#f4f1eb;font-family:Inter,Arial,sans-serif;color:#14131a">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px">'
        . '<div style="background:#14131a;color:#fff;padding:16px 22px;border-radius:12px 12px 0 0;font-weight:800;font-size:18px">Code4U</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e7e2d8;border-top:0;border-radius:0 0 12px 12px">'
        . '<h1 style="margin:0 0 14px;font-size:18px;color:#2e40e5">' . $title . '</h1>'
        . $bodyHtml
        . '<p style="margin-top:24px;font-size:12px;color:#5c5a66">Email envoyé depuis votre espace client Code4U.</p>'
        . '</div></div></body></html>';

    if (defined('IS_LOCAL') && IS_LOCAL) {
        error_log('[portal-mail DEV] to=' . $to . ' | ' . $subject);
        return true;
    }
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Code4U <' . $from . '>',
        'Reply-To: ' . $from,
    ];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $html, implode("\r\n", $headers));
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
        'done' => 'Réalisé',
        'planned' => 'Planifié',
        'billed' => 'Facturé',
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

function portalFetchDocumentLines(PDO $db, $table, $foreignKey, $documentId) {
    if (!portalTableExists($db, $table)) {
        return [];
    }
    $stmt = $db->prepare("
        SELECT type_ligne, libelle, description, quantite, prix_unitaire_ht, tva
        FROM {$table}
        WHERE {$foreignKey} = :id
        ORDER BY ordre ASC, id ASC
    ");
    $stmt->execute([':id' => $documentId]);
    return array_map(function ($row) {
        $qte = (float)$row['quantite'];
        $pu = (float)$row['prix_unitaire_ht'];
        $tva = (float)$row['tva'];
        $ht = $qte * $pu;
        return [
            'type_ligne' => $row['type_ligne'],
            'libelle' => $row['libelle'],
            'description' => $row['description'],
            'quantite' => $qte,
            'prix_unitaire_ht' => $pu,
            'tva' => $tva,
            'montant_ht' => $ht,
            'montant_ttc' => $ht * (1 + $tva / 100),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function portalFetchInvoices(PDO $db, $clientId) {
    if (!portalTableExists($db, 'factures')) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT id, numero, date_facture, date_echeance, statut, montant_ht, montant_tva, montant_ttc, montant_paye, notes, conditions
        FROM factures
        WHERE client_id = :client_id
        ORDER BY date_facture DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) use ($db) {
        return [
            'id' => (int)$row['id'],
            'number' => $row['numero'],
            'title' => 'Facture ' . $row['numero'],
            'date' => $row['date_facture'],
            'due_date' => $row['date_echeance'],
            'amount_ht' => (float)$row['montant_ht'],
            'amount_tva' => (float)$row['montant_tva'],
            'amount' => (float)$row['montant_ttc'],
            'paid_amount' => (float)$row['montant_paye'],
            'remaining_amount' => max(0, (float)$row['montant_ttc'] - (float)$row['montant_paye']),
            'currency' => 'EUR',
            'status' => $row['statut'],
            'status_label' => portalStatusLabel($row['statut']),
            'tone' => portalStatusTone($row['statut']),
            'notes' => $row['notes'],
            'conditions' => $row['conditions'],
            'lines' => portalFetchDocumentLines($db, 'facture_lignes', 'facture_id', (int)$row['id']),
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
        SELECT id, provider, brand, label, expires_at, is_default, status
        FROM payment_methods
        WHERE client_id = :client_id
        ORDER BY is_default DESC, created_at DESC
        LIMIT 6
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
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
        SELECT id, numero, date_devis, statut, montant_ht, montant_tva, montant_ttc, notes, conditions, signature_token
        FROM devis
        WHERE client_id = :client_id
        ORDER BY date_devis DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([':client_id' => $clientId]);
    return array_map(function ($row) use ($db) {
        return [
            'id' => (int)$row['id'],
            'number' => $row['numero'],
            'title' => $row['notes'] ?: ('Devis ' . $row['numero']),
            'date' => $row['date_devis'],
            'amount_ht' => (float)$row['montant_ht'],
            'amount_tva' => (float)$row['montant_tva'],
            'amount' => (float)$row['montant_ttc'],
            'currency' => 'EUR',
            'status' => $row['statut'],
            'status_label' => portalStatusLabel($row['statut']),
            'tone' => portalStatusTone($row['statut']),
            'notes' => $row['notes'],
            'conditions' => $row['conditions'],
            'lines' => portalFetchDocumentLines($db, 'devis_lignes', 'devis_id', (int)$row['id']),
            'signature_url' => $row['signature_token'] ? ('sign/devis/' . rawurlencode($row['signature_token'])) : null,
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
    portalEnsureTicketAccessCode($db);

    $stmt = $db->prepare("
        SELECT id, ticket_number, access_code, subject, status, priority, updated_at, created_at
        FROM tickets
        WHERE customer_email = :email
        ORDER BY updated_at DESC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':email' => $clientEmail]);
    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'number' => $row['ticket_number'],
            'access_code' => $row['access_code'] ?? null,
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
        SELECT id, category, title, file_url, amount_label, created_at
        FROM client_documents
        WHERE client_id = :client_id
        ORDER BY created_at DESC, id DESC
        LIMIT 24
    ");
    $stmt->execute([':client_id' => $clientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function portalGenerateTicketNumber(PDO $db) {
    $prefix = 'SUP-';
    $stmt = $db->query("
        SELECT ticket_number
        FROM tickets
        WHERE ticket_number LIKE 'SUP-%'
        ORDER BY id DESC
        LIMIT 1
    ");
    $last = $stmt ? $stmt->fetchColumn() : null;
    $number = 1;
    if ($last && preg_match('/SUP-(\d+)/', $last, $matches)) {
        $number = ((int)$matches[1]) + 1;
    }
    return $prefix . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function portalGenerateAccessCode(PDO $db) {
    do {
        $code = strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE access_code = :code");
        $stmt->execute([':code' => $code]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $code;
}

function portalCreateTicket(PDO $db, array $client, array $input, $notify = true) {
    // Crée les tables tickets si besoin (base ERP sans module support) au lieu d'échouer.
    try {
        portalEnsureTicketTables($db);
    } catch (Throwable $e) {
        error_log('[client-portal.php] ensure ticket tables: ' . $e->getMessage());
    }
    if (!portalHasTables($db, ['tickets', 'ticket_messages'])) {
        portalRespond(['success' => false, 'message' => 'Le module support n’est pas disponible pour le moment.'], 503);
    }

    $subject = trim((string)($input['subject'] ?? ''));
    $description = trim((string)($input['description'] ?? $input['message'] ?? ''));
    if ($subject === '' || $description === '') {
        portalRespond(['success' => false, 'message' => 'Sujet et description requis.'], 422);
    }

    $priority = (string)($input['priority'] ?? 'medium');
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
        $priority = 'medium';
    }
    $category = strip_tags(trim((string)($input['category'] ?? 'support')));
    if ($category === '') {
        $category = 'support';
    }

    $ticketNumber = portalGenerateTicketNumber($db);
    $accessCode = portalGenerateAccessCode($db);
    $customerName = $client['company_name'] ?: trim(($client['contact_firstname'] ?? '') . ' ' . ($client['contact_lastname'] ?? ''));

    $stmt = $db->prepare("
        INSERT INTO tickets (
            ticket_number, access_code, subject, description, customer_name, customer_email,
            customer_phone, status, priority, category, source
        )
        VALUES (
            :ticket_number, :access_code, :subject, :description, :customer_name, :customer_email,
            :customer_phone, 'open', :priority, :category, 'form'
        )
    ");
    $stmt->execute([
        ':ticket_number' => $ticketNumber,
        ':access_code' => $accessCode,
        ':subject' => strip_tags($subject),
        ':description' => strip_tags($description),
        ':customer_name' => strip_tags($customerName ?: 'Client Code4U'),
        ':customer_email' => $client['email'],
        ':customer_phone' => $client['phone'] ?? null,
        ':priority' => $priority,
        ':category' => $category,
    ]);

    $ticketId = (int)$db->lastInsertId();
    $messageStmt = $db->prepare("
        INSERT INTO ticket_messages (ticket_id, sender_type, message, is_internal)
        VALUES (:ticket_id, 'customer', :message, 0)
    ");
    $messageStmt->execute([
        ':ticket_id' => $ticketId,
        ':message' => strip_tags($description),
    ]);

    if ($notify) {
        portalSendClientMail(
            $client,
            'Demande reçue — ' . $ticketNumber,
            'Votre demande a bien été reçue',
            '<p style="margin:0 0 12px">Bonjour,</p>'
            . '<p style="margin:0 0 12px">Nous avons bien reçu votre demande <strong>' . htmlspecialchars($ticketNumber) . '</strong> :</p>'
            . '<div style="background:#f4f1eb;border-radius:10px;padding:14px;margin:12px 0"><strong>' . htmlspecialchars($subject) . '</strong><br>' . nl2br(htmlspecialchars($description)) . '</div>'
            . '<p style="margin:0">Notre équipe vous répondra rapidement. Vous pouvez suivre son avancement depuis votre espace client.</p>'
        );
    }

    return [
        'success' => true,
        'ticket' => [
            'id' => $ticketId,
            'number' => $ticketNumber,
            'access_code' => $accessCode,
            'subject' => $subject,
            'status' => 'open',
            'status_label' => portalStatusLabel('open'),
            'tone' => portalStatusTone('open'),
            'priority' => $priority,
        ],
    ];
}

function portalFindInvoice(PDO $db, $clientId, $invoiceId) {
    $stmt = $db->prepare("
        SELECT *
        FROM factures
        WHERE id = :id AND client_id = :client_id
        LIMIT 1
    ");
    $stmt->execute([':id' => $invoiceId, ':client_id' => $clientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function portalPayInvoice(PDO $db, array $client, array $input) {
    if (!portalHasTables($db, ['factures', 'paiements'])) {
        portalRespond(['success' => false, 'message' => 'Tables de paiement manquantes.'], 503);
    }

    $invoiceId = (int)($input['invoice_id'] ?? 0);
    $invoice = portalFindInvoice($db, (int)$client['id'], $invoiceId);
    if (!$invoice) {
        portalRespond(['success' => false, 'message' => 'Facture introuvable.'], 404);
    }
    if (in_array($invoice['statut'], ['payee', 'annulee'], true)) {
        portalRespond(['success' => false, 'message' => 'Cette facture ne peut pas être payée.'], 422);
    }

    $amount = isset($input['amount']) ? (float)$input['amount'] : ((float)$invoice['montant_ttc'] - (float)$invoice['montant_paye']);
    $amount = max(0, min($amount, (float)$invoice['montant_ttc'] - (float)$invoice['montant_paye']));
    if ($amount <= 0) {
        portalRespond(['success' => false, 'message' => 'Montant de paiement invalide.'], 422);
    }

    $db->beginTransaction();
    try {
        $reference = 'PORTAIL-' . date('YmdHis') . '-' . $invoiceId;
        $payment = $db->prepare("
            INSERT INTO paiements (facture_id, montant, date_paiement, mode_paiement, reference, notes)
            VALUES (:facture_id, :montant, CURDATE(), :mode_paiement, :reference, :notes)
        ");
        $payment->execute([
            ':facture_id' => $invoiceId,
            ':montant' => $amount,
            ':mode_paiement' => strip_tags((string)($input['method'] ?? 'Portail client')),
            ':reference' => $reference,
            ':notes' => 'Paiement saisi depuis l’espace client.',
        ]);

        $newPaid = (float)$invoice['montant_paye'] + $amount;
        $newStatus = $newPaid + 0.01 >= (float)$invoice['montant_ttc'] ? 'payee' : 'partielle';
        $update = $db->prepare("
            UPDATE factures
            SET montant_paye = :montant_paye, statut = :statut
            WHERE id = :id AND client_id = :client_id
        ");
        $update->execute([
            ':montant_paye' => $newPaid,
            ':statut' => $newStatus,
            ':id' => $invoiceId,
            ':client_id' => (int)$client['id'],
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $fullyPaid = $newStatus === 'payee';
    portalSendClientMail(
        $client,
        'Paiement enregistré — Facture ' . $invoice['numero'],
        'Merci, votre paiement a bien été enregistré',
        '<p style="margin:0 0 12px">Bonjour,</p>'
        . '<p style="margin:0 0 12px">Nous confirmons la réception de votre paiement pour la facture <strong>' . htmlspecialchars($invoice['numero']) . '</strong>.</p>'
        . '<div style="background:#f4f1eb;border-radius:10px;padding:14px;margin:12px 0">'
        . 'Montant réglé : <strong>' . portalMoney($amount) . '</strong><br>'
        . 'Référence : ' . htmlspecialchars($reference) . '<br>'
        . 'Statut : <strong>' . ($fullyPaid ? 'Payée' : 'Partiellement réglée') . '</strong>'
        . '</div>'
        . ($fullyPaid ? '<p style="margin:0">Votre facture acquittée (avec mention « PAYÉE ») est disponible dans votre espace client.</p>' : '<p style="margin:0">Le solde restant est consultable dans votre espace client.</p>')
    );

    return ['success' => true, 'message' => 'Paiement enregistré.', 'reference' => $reference];
}

function portalUpdateSubscription(PDO $db, array $client, array $input, $status) {
    if (!portalTableExists($db, 'support_subscriptions')) {
        portalRespond(['success' => false, 'message' => 'Table abonnement manquante.'], 503);
    }
    if (!in_array($status, ['active', 'paused', 'cancelled'], true)) {
        portalRespond(['success' => false, 'message' => 'Statut abonnement invalide.'], 422);
    }
    $subscriptionId = (int)($input['subscription_id'] ?? 0);
    $stmt = $db->prepare("
        UPDATE support_subscriptions
        SET status = :status
        WHERE id = :id AND client_id = :client_id
    ");
    $stmt->execute([
        ':status' => $status,
        ':id' => $subscriptionId,
        ':client_id' => (int)$client['id'],
    ]);
    if ($stmt->rowCount() === 0) {
        portalRespond(['success' => false, 'message' => 'Abonnement introuvable.'], 404);
    }

    $label = $status === 'cancelled' ? 'Résiliation demandée' : ($status === 'paused' ? 'Mise en pause demandée' : 'Réactivation demandée');
    if (portalHasTables($db, ['tickets', 'ticket_messages'])) {
        // notify=false : on envoie un email dédié abonnement ci-dessous (pas le mail "demande reçue").
        portalCreateTicket($db, $client, [
            'subject' => $label,
            'description' => 'Action demandée depuis l’espace client pour l’abonnement support.',
            'priority' => 'medium',
            'category' => 'abonnement',
        ], false);
    }

    portalSendClientMail(
        $client,
        $label . ' — Abonnement support',
        $label,
        '<p style="margin:0 0 12px">Bonjour,</p>'
        . '<p style="margin:0 0 12px">Votre demande concernant votre abonnement support a bien été prise en compte : <strong>' . htmlspecialchars($label) . '</strong>.</p>'
        . '<p style="margin:0">Notre équipe revient vers vous si une action de votre part est nécessaire. Vous pouvez gérer votre abonnement à tout moment depuis votre espace client.</p>'
    );

    return ['success' => true, 'message' => $label . '.'];
}

function portalAddPaymentMethod(PDO $db, array $client, array $input) {
    if (!portalTableExists($db, 'payment_methods')) {
        portalRespond(['success' => false, 'message' => 'Table moyens de paiement manquante.'], 503);
    }
    $label = trim((string)($input['label'] ?? ''));
    if ($label === '') {
        portalRespond(['success' => false, 'message' => 'Nom du moyen de paiement requis.'], 422);
    }

    $makeDefault = !empty($input['is_default']);
    $db->beginTransaction();
    try {
        if ($makeDefault) {
            $reset = $db->prepare("UPDATE payment_methods SET is_default = 0 WHERE client_id = :client_id");
            $reset->execute([':client_id' => (int)$client['id']]);
        }
        $stmt = $db->prepare("
            INSERT INTO payment_methods (client_id, provider, brand, label, expires_at, is_default, status)
            VALUES (:client_id, :provider, :brand, :label, :expires_at, :is_default, 'active')
        ");
        $stmt->execute([
            ':client_id' => (int)$client['id'],
            ':provider' => strip_tags((string)($input['provider'] ?? 'manuel')),
            ':brand' => strip_tags((string)($input['brand'] ?? 'Carte')),
            ':label' => strip_tags($label),
            ':expires_at' => strip_tags((string)($input['expires_at'] ?? '')),
            ':is_default' => $makeDefault ? 1 : 0,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['success' => true, 'message' => 'Moyen de paiement ajouté.'];
}

function portalSetPaymentMethodStatus(PDO $db, array $client, array $input, $mode) {
    if (!portalTableExists($db, 'payment_methods')) {
        portalRespond(['success' => false, 'message' => 'Table moyens de paiement manquante.'], 503);
    }
    $methodId = (int)($input['payment_method_id'] ?? 0);
    if ($mode === 'default') {
        $db->beginTransaction();
        try {
            $reset = $db->prepare("UPDATE payment_methods SET is_default = 0 WHERE client_id = :client_id");
            $reset->execute([':client_id' => (int)$client['id']]);
            $stmt = $db->prepare("UPDATE payment_methods SET is_default = 1, status = 'active' WHERE id = :id AND client_id = :client_id");
            $stmt->execute([':id' => $methodId, ':client_id' => (int)$client['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return ['success' => true, 'message' => 'Moyen de paiement défini par défaut.'];
    }

    $stmt = $db->prepare("UPDATE payment_methods SET status = 'disabled', is_default = 0 WHERE id = :id AND client_id = :client_id");
    $stmt->execute([':id' => $methodId, ':client_id' => (int)$client['id']]);
    if ($stmt->rowCount() === 0) {
        portalRespond(['success' => false, 'message' => 'Moyen de paiement introuvable.'], 404);
    }
    return ['success' => true, 'message' => 'Moyen de paiement désactivé.'];
}

function portalAddDocument(PDO $db, array $client, array $input) {
    if (!portalTableExists($db, 'client_documents')) {
        portalRespond(['success' => false, 'message' => 'Table documents manquante.'], 503);
    }
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        portalRespond(['success' => false, 'message' => 'Titre du document requis.'], 422);
    }
    $category = (string)($input['category'] ?? 'other');
    if (!in_array($category, ['invoice', 'quote', 'contract', 'asset', 'report', 'other'], true)) {
        $category = 'other';
    }
    $stmt = $db->prepare("
        INSERT INTO client_documents (client_id, category, title, file_url, amount_label)
        VALUES (:client_id, :category, :title, :file_url, :amount_label)
    ");
    $stmt->execute([
        ':client_id' => (int)$client['id'],
        ':category' => $category,
        ':title' => strip_tags($title),
        ':file_url' => strip_tags((string)($input['file_url'] ?? '')),
        ':amount_label' => strip_tags((string)($input['amount_label'] ?? '')),
    ]);
    return ['success' => true, 'message' => 'Document ajouté.'];
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
        return $sum + (float)($invoice['remaining_amount'] ?? $invoice['amount']);
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
                c.telephone AS phone,
                c.adresse,
                c.code_postal,
                c.ville
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

    if ($action === 'create-ticket') {
        portalRespond(portalCreateTicket($db, $client, $input), 201);
    }

    if ($action === 'pay-invoice') {
        portalRespond(portalPayInvoice($db, $client, $input));
    }

    if ($action === 'pause-subscription') {
        portalRespond(portalUpdateSubscription($db, $client, $input, 'paused'));
    }

    if ($action === 'resume-subscription') {
        portalRespond(portalUpdateSubscription($db, $client, $input, 'active'));
    }

    if ($action === 'cancel-subscription') {
        portalRespond(portalUpdateSubscription($db, $client, $input, 'cancelled'));
    }

    if ($action === 'add-payment-method') {
        portalRespond(portalAddPaymentMethod($db, $client, $input), 201);
    }

    if ($action === 'set-default-payment-method') {
        portalRespond(portalSetPaymentMethodStatus($db, $client, $input, 'default'));
    }

    if ($action === 'disable-payment-method') {
        portalRespond(portalSetPaymentMethodStatus($db, $client, $input, 'disabled'));
    }

    if ($action === 'add-document') {
        portalRespond(portalAddDocument($db, $client, $input), 201);
    }

    if ($action === 'request-quote') {
        portalRespond(portalCreateTicket($db, $client, [
            'subject' => trim((string)($input['subject'] ?? 'Demande de devis')),
            'description' => trim((string)($input['description'] ?? 'Nouvelle demande de devis depuis l’espace client.')),
            'priority' => 'medium',
            'category' => 'devis',
        ]), 201);
    }

    portalRespond(portalBuildDashboard($db, $client));
} catch (Throwable $e) {
    error_log('[client-portal.php] ' . $e->getMessage());
    portalRespond(['success' => false, 'message' => 'Erreur portail client'], 500);
}
