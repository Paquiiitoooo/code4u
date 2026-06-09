<?php
/**
 * CLI only.
 * Cree/reinitialise les acces espace client et envoie l'email d'invitation.
 *
 * Usage:
 *   php admin/tools/provision-client-portal.php
 *   php admin/tools/provision-client-portal.php "Visioframe" "Pacome VANTINI"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$adminDir = dirname(__DIR__);
require_once $adminDir . '/config/config.php';

function cliLine($text = '') {
    fwrite(STDOUT, $text . PHP_EOL);
}

function portalRandomPassword($length = 14) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%+='; 
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function portalEnsureAccountsTable(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS client_portal_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active','disabled') NOT NULL DEFAULT 'active',
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            KEY idx_client_portal_accounts_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function portalClientLabel(array $client) {
    $name = trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? ''));
    return $client['raison_sociale'] ?: ($name ?: $client['email']);
}

function portalFindClient(PDO $db, $needle) {
    $needle = trim((string)$needle);
    $like = '%' . $needle . '%';
    $stmt = $db->prepare("
        SELECT id, raison_sociale, prenom, nom, email
        FROM clients
        WHERE actif = 1
          AND email IS NOT NULL
          AND email <> ''
          AND (
            raison_sociale LIKE :like
            OR CONCAT_WS(' ', prenom, nom) LIKE :like
            OR nom LIKE :like
            OR prenom LIKE :like
            OR email LIKE :like
          )
        ORDER BY
          CASE
            WHEN raison_sociale = :needle THEN 0
            WHEN CONCAT_WS(' ', prenom, nom) = :needle THEN 0
            ELSE 1
          END,
          id DESC
        LIMIT 1
    ");
    $stmt->execute([':like' => $like, ':needle' => $needle]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    return $client ?: null;
}

function portalSendAccessMail(array $client, $password) {
    $to = $client['email'];
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'contact@code4u.fr';
    $label = htmlspecialchars(portalClientLabel($client), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
    $passwordHtml = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $loginUrl = 'https://code4u.fr/espace-client.html';
    $html = '<!doctype html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#15151d">'
        . '<div style="max-width:600px;margin:0 auto;padding:24px">'
        . '<div style="background:#14131a;color:#fff;padding:18px 22px;border-radius:10px 10px 0 0;font-size:20px;font-weight:800">Code4U</div>'
        . '<div style="background:#fff;border:1px solid #e1e4ea;border-top:0;border-radius:0 0 10px 10px;padding:24px">'
        . '<h1 style="margin:0 0 14px;font-size:20px">Votre espace client est pret</h1>'
        . '<p>Bonjour ' . $label . ',</p>'
        . '<p>Votre espace client Code4U est disponible. Vous pouvez y consulter vos devis, factures, paiements, projets, tickets et votre abonnement support.</p>'
        . '<div style="background:#f6f8fb;border:1px solid #dde3ee;border-radius:8px;padding:16px;margin:18px 0">'
        . '<p style="margin:0 0 8px"><strong>Lien :</strong> <a href="' . $loginUrl . '">' . $loginUrl . '</a></p>'
        . '<p style="margin:0 0 8px"><strong>Email :</strong> ' . $email . '</p>'
        . '<p style="margin:0"><strong>Mot de passe temporaire :</strong> <code style="font-size:15px">' . $passwordHtml . '</code></p>'
        . '</div>'
        . '<p>Pour votre securite, changez ce mot de passe apres votre premiere connexion dans l’onglet <strong>Mon compte</strong>.</p>'
        . '<p style="margin-top:22px;color:#606775;font-size:13px">Si vous n’avez pas demande cet acces, repondez simplement a cet email.</p>'
        . '</div></div></body></html>';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Code4U <' . $from . '>',
        'Reply-To: ' . $from,
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode('Votre acces espace client Code4U') . '?=', $html, implode("\r\n", $headers));
}

$targets = array_slice($argv, 1);
if (!$targets) {
    $targets = ['Visioframe', 'Pacome VANTINI'];
}

$db = getDB();
portalEnsureAccountsTable($db);

foreach ($targets as $target) {
    $client = portalFindClient($db, $target);
    if (!$client) {
        cliLine('[SKIP] Client introuvable: ' . $target);
        continue;
    }
    $password = portalRandomPassword();
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT INTO client_portal_accounts (client_id, email, password_hash, status)
        VALUES (:client_id, :email, :password_hash, 'active')
        ON DUPLICATE KEY UPDATE
            client_id = VALUES(client_id),
            password_hash = VALUES(password_hash),
            status = 'active'
    ");
    $stmt->execute([
        ':client_id' => (int)$client['id'],
        ':email' => strtolower(trim($client['email'])),
        ':password_hash' => $hash,
    ]);
    $sent = portalSendAccessMail($client, $password);
    cliLine(($sent ? '[OK]' : '[MAIL FAIL]') . ' ' . portalClientLabel($client) . ' <' . $client['email'] . '>');
}
