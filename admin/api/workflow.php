<?php
/**
 * Code4U V2 — Email Workflow Engine
 *
 * Usage:
 *   require_once '/path/to/admin/api/workflow.php';
 *   triggerWorkflow($pdo, $orderId, 'new_order_client', $clientEmail, $orderData);
 *   triggerWorkflow($pdo, $orderId, 'new_order_admin',  $adminEmail,  $orderData);
 *
 * Supported workflow types:
 *   new_order_client  — confirmation email to the client
 *   new_order_admin   — notification email to the admin
 */

if (!function_exists('triggerWorkflow')) {

// ── Main dispatcher ───────────────────────────────────────────

function triggerWorkflow($pdo, $orderId, $workflowType, $recipientEmail, $data = []) {

    // Convenience variables pulled from $data
    $ref       = $data['reference']        ?? 'CODE4U-XXXX';
    $firstname = $data['client_firstname'] ?? 'Client';
    $lastname  = $data['client_lastname']  ?? '';
    $service   = $data['service_name']     ?? 'Service';
    $total     = number_format((float)($data['total_price'] ?? 0), 0, ',', ' ') . ' €';
    $email     = $data['client_email']     ?? '';
    $phone     = $data['client_phone']     ?? 'Non renseigné';
    $company   = $data['client_company']   ?? '';
    $desc      = $data['description']      ?? '';

    $subject = '';
    $html    = '';

    switch ($workflowType) {

        // ── Client confirmation ───────────────────────────────────
        case 'new_order_client':
            $subject = "Votre commande Code4U est enregistree - Ref. $ref";

            $steps = [
                ['1', 'Votre commande est enregistrée dans notre système'],
                ['2', 'Vous recevez cet email de confirmation'],
                ['3', 'Nous vous contactons sous 24h pour valider votre projet'],
                ['4', 'Vous recevez votre devis officiel personnalisé'],
                ['5', 'Nous démarrons le développement après votre validation'],
            ];
            $stepsHtml = '';
            foreach ($steps as $s) {
                $stepsHtml .= '<div style="display:flex;gap:12px;margin-bottom:10px;align-items:flex-start">'
                    . '<span style="background:#4361ee;color:white;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;margin-top:2px">' . $s[0] . '</span>'
                    . '<span style="font-size:14px;color:#475569;line-height:1.5">' . $s[1] . '</span>'
                    . '</div>';
            }

            $content = '
                <p style="font-size:16px;margin:0 0 16px">Bonjour <strong>' . $firstname . '</strong>,</p>
                <p style="color:#64748b;line-height:1.7;margin:0 0 24px">
                    Votre commande a bien été enregistrée. Nous vous contacterons
                    <strong style="color:#4361ee">sous 24h</strong> pour valider votre projet et vous envoyer votre devis officiel.
                </p>

                <div style="background:#f1f5f9;border-radius:12px;padding:20px;margin:0 0 24px">
                    <h3 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8">Récapitulatif</h3>
                    <table style="width:100%;border-collapse:collapse">
                        <tr><td style="padding:6px 0;color:#64748b;font-size:14px">Service</td><td style="padding:6px 0;font-weight:600;text-align:right;font-size:14px">' . $service . '</td></tr>
                        <tr><td style="padding:6px 0;color:#64748b;font-size:14px">Référence</td><td style="padding:6px 0;font-weight:600;text-align:right;font-size:14px;color:#4361ee">' . $ref . '</td></tr>
                        <tr style="border-top:2px solid #e2e8f0"><td style="padding:10px 0 0;font-weight:700;font-size:16px">Total estimé</td><td style="padding:10px 0 0;font-weight:800;font-size:20px;text-align:right;color:#4361ee">' . $total . '</td></tr>
                    </table>
                </div>

                <div style="background:linear-gradient(135deg,rgba(67,97,238,0.06),rgba(114,9,183,0.04));border:1px solid rgba(67,97,238,0.15);border-radius:12px;padding:20px;margin:0 0 24px">
                    <h3 style="margin:0 0 12px;font-size:14px;color:#4361ee">Que se passe-t-il maintenant ?</h3>
                    ' . $stepsHtml . '
                </div>

                <p style="color:#64748b;font-size:14px">Une question ? Répondez à cet email ou appelez-nous au <a href="tel:+33652372636" style="color:#4361ee">+33 6 52 37 26 36</a></p>
            ';

            $html = buildEmailLayout('Commande enregistrée !', $content);
            break;

        // ── Admin notification ────────────────────────────────────
        case 'new_order_admin':
            $subject = "Nouvelle commande #$ref - $service - $total";

            // Build options list
            $optionsList = '';
            if (!empty($data['options'])) {
                $opts = is_string($data['options']) ? json_decode($data['options'], true) : $data['options'];
                if (is_array($opts)) {
                    foreach ($opts as $opt) {
                        $optName  = htmlspecialchars($opt['name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $optPrice = number_format((float)($opt['price'] ?? 0), 0, ',', ' ');
                        $optionsList .= "<li style='padding:4px 0;font-size:14px;color:#475569'>{$optName} — +{$optPrice} €</li>";
                    }
                }
            }

            $fullName    = trim($firstname . ' ' . $lastname);
            $adminSiteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://code4u.fr';

            $content = '
                <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:12px;padding:16px;margin:0 0 24px">
                    <p style="margin:0;font-weight:700;color:#92400e">Action requise : contactez le client sous 24h</p>
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <tr style="background:#f8fafc"><td style="padding:10px 12px;font-weight:600;width:40%">Référence</td><td style="padding:10px 12px;color:#4361ee;font-weight:700">' . $ref . '</td></tr>
                    <tr><td style="padding:10px 12px;font-weight:600">Service</td><td style="padding:10px 12px">' . $service . '</td></tr>
                    <tr style="background:#f8fafc"><td style="padding:10px 12px;font-weight:600">Total estimé</td><td style="padding:10px 12px;font-weight:700;color:#4361ee">' . $total . '</td></tr>
                    <tr><td style="padding:10px 12px;font-weight:600">Client</td><td style="padding:10px 12px">' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr style="background:#f8fafc"><td style="padding:10px 12px;font-weight:600">Email</td><td style="padding:10px 12px"><a href="mailto:' . $email . '" style="color:#4361ee">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a></td></tr>
                    <tr><td style="padding:10px 12px;font-weight:600">Téléphone</td><td style="padding:10px 12px"><a href="tel:' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '" style="color:#4361ee">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a></td></tr>
                    <tr style="background:#f8fafc"><td style="padding:10px 12px;font-weight:600">Entreprise</td><td style="padding:10px 12px">' . ($company ? htmlspecialchars($company, ENT_QUOTES, 'UTF-8') : '—') . '</td></tr>
                </table>
                ' . ($optionsList ? "<h4 style='margin:20px 0 8px;font-size:14px;color:#1e293b'>Options sélectionnées :</h4><ul style='margin:0;padding-left:20px'>$optionsList</ul>" : '') . '
                ' . ($desc ? "<h4 style='margin:20px 0 8px;font-size:14px;color:#1e293b'>Description projet :</h4><p style='background:#f8fafc;padding:12px;border-radius:8px;font-size:14px;color:#475569;line-height:1.6'>" . nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) . '</p>' : '') . '
                <div style="margin-top:28px;text-align:center">
                    <a href="' . $adminSiteUrl . '/admin/orders.php" style="background:linear-gradient(135deg,#4361ee,#7209b7);color:white;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:600;display:inline-block;font-size:15px">Voir dans l\'admin</a>
                </div>
            ';

            $html = buildEmailLayout('Nouvelle commande reçue', $content);
            break;

        default:
            // Unknown workflow — skip silently, log as skipped
            try {
                $pdo->prepare("INSERT INTO workflow_log (order_id, workflow_type, recipient, subject, status, error_message) VALUES (?,?,?,?,?,?)")
                    ->execute([$orderId, $workflowType, $recipientEmail, '', 'skipped', "Workflow type '$workflowType' non défini"]);
            } catch (Exception $e) {
                error_log('[workflow] log error: ' . $e->getMessage());
            }
            return false;
    }

    // ── Send and log ──────────────────────────────────────────
    $sent        = sendEmail($recipientEmail, $subject, $html);
    $logStatus   = $sent ? 'sent' : 'failed';
    $logError    = $sent ? null   : 'mail() a retourné false';

    try {
        $pdo->prepare("
            INSERT INTO workflow_log (order_id, workflow_type, recipient, subject, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$orderId, $workflowType, $recipientEmail, $subject, $logStatus, $logError]);
    } catch (Exception $e) {
        error_log('[workflow] log insert error: ' . $e->getMessage());
    }

    return $sent;
}

// ── sendEmail ─────────────────────────────────────────────────

function sendEmail($to, $subject, $html) {
    $from     = 'Code4U <noreply@code4u.fr>';
    $boundary = 'CODE4U_' . md5(microtime(true) . $to);

    // Auto-generate plain-text version
    $plain = strip_tags(str_replace(
        ['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>', '</h4>', '</li>'],
        "\n",
        $html
    ));
    $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
    $plain = preg_replace('/[ \t]+/', ' ', $plain);
    $plain = preg_replace('/\n{3,}/', "\n\n", trim($plain));

    $headers  = "From: $from\r\n";
    $headers .= "Reply-To: contact@code4u.fr\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "X-Mailer: Code4U-Workflow/2.0\r\n";
    $headers .= "X-Priority: 3\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $plain . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--$boundary--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($to, $encodedSubject, $body, $headers);
}

// ── buildEmailLayout ──────────────────────────────────────────

function buildEmailLayout($title, $content) {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . $safeTitle . '</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Inter,Arial,sans-serif;-webkit-font-smoothing:antialiased">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f1f5f9;padding:40px 20px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%">

    <!-- HEADER -->
    <tr>
        <td style="background:linear-gradient(135deg,#4361ee 0%,#7209b7 100%);padding:32px 40px;border-radius:16px 16px 0 0;text-align:center">
            <div style="font-size:30px;font-weight:900;color:white;letter-spacing:-0.03em;line-height:1">
                Code<span style="color:rgba(255,255,255,0.65)">4</span>U
            </div>
            <p style="color:rgba(255,255,255,0.82);margin:10px 0 0;font-size:14px;letter-spacing:0.01em">' . $safeTitle . '</p>
        </td>
    </tr>

    <!-- BODY -->
    <tr>
        <td style="background:#ffffff;padding:36px 40px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0">
            ' . $content . '
        </td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td style="background:#1e293b;padding:24px 40px;border-radius:0 0 16px 16px;text-align:center">
            <p style="color:#64748b;font-size:12px;margin:0 0 6px">
                Code4U — 114 Avenue de Thionville, 57050 Metz, France
            </p>
            <p style="color:#64748b;font-size:12px;margin:0 0 10px">
                <a href="tel:+33652372636" style="color:#4361ee;text-decoration:none">+33 6 52 37 26 36</a>
                &nbsp;·&nbsp;
                <a href="mailto:contact@code4u.fr" style="color:#4361ee;text-decoration:none">contact@code4u.fr</a>
                &nbsp;·&nbsp;
                <a href="https://code4u.fr" style="color:#4361ee;text-decoration:none">code4u.fr</a>
            </p>
            <p style="color:#334155;font-size:11px;margin:0">&copy; 2025 Code4U — Tous droits réservés</p>
        </td>
    </tr>

</table>
</td></tr>
</table>

</body>
</html>';
}

} // end if !function_exists('triggerWorkflow')
