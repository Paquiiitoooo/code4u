<?php
/**
 * Helper Functions for Code4U Admin Panel
 */

/**
 * Get asset path with BASE_PATH support
 */
function asset_path($path) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    $cleanPath = ltrim($path, '/');
    // If BASE_PATH is empty, return path starting with /
    // If BASE_PATH is set, return BASE_PATH/path
    return $basePath ? $basePath . '/' . $cleanPath : '/' . $cleanPath;
}

/**
 * Log activity
 */
function logActivity($db, $action, $entityType = null, $entityId = null, $description = null) {
    try {
        $adminId = getCurrentAdminId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent)
                VALUES (:admin_id, :action, :entity_type, :entity_id, :description, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'admin_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    } catch (PDOException $e) {
        // Log l'erreur mais ne bloque pas l'application
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Update statistics
 */
function updateStatistic($db, $metricType, $value = 1, $metadata = null) {
    $date = date('Y-m-d');
    
    $sql = "INSERT INTO statistics (date, metric_type, metric_value, metadata)
            VALUES (:date, :metric_type, :metric_value, :metadata)
            ON DUPLICATE KEY UPDATE 
                metric_value = metric_value + :metric_value_update,
                metadata = :metadata_update";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'date' => $date,
        'metric_type' => $metricType,
        'metric_value' => $value,
        'metadata' => $metadata ? json_encode($metadata) : null,
        'metric_value_update' => $value,
        'metadata_update' => $metadata ? json_encode($metadata) : null
    ]);
}

/**
 * Get statistics for dashboard
 */
function getDashboardStats($db) {
    $stats = [
        'tickets' => ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'urgent' => 0],
        'recent_tickets' => 0,
        'recent_activity' => 0,
        'assigned_tickets' => 0
    ];
    
    try {
        // Tickets stats
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
                FROM tickets";
        $stmt = $db->query($sql);
        $result = $stmt->fetch();
        if ($result) {
            $stats['tickets'] = $result;
        }
    } catch (PDOException $e) {
        // Table might not exist yet
        error_log("Error getting tickets stats: " . $e->getMessage());
    }
    
    try {
        // Assigned tickets count
        $sql = "SELECT COUNT(*) as count FROM tickets WHERE assigned_to IS NOT NULL AND status != 'closed'";
        $stmt = $db->query($sql);
        $result = $stmt->fetch();
        if ($result) {
            $stats['assigned_tickets'] = $result['count'] ?? 0;
        }
    } catch (PDOException $e) {
        error_log("Error getting assigned tickets: " . $e->getMessage());
    }
    
    try {
        // Recent tickets (last 7 days)
        $sql = "SELECT COUNT(*) as count FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $db->query($sql);
        $result = $stmt->fetch();
        if ($result) {
            $stats['recent_tickets'] = $result['count'] ?? 0;
        }
    } catch (PDOException $e) {
        // Table might not exist yet
        error_log("Error getting recent tickets: " . $e->getMessage());
    }
    
    try {
        // Recent activity
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $db->query($sql);
        $result = $stmt->fetch();
        if ($result) {
            $stats['recent_activity'] = $result['count'] ?? 0;
        }
    } catch (PDOException $e) {
        // Table might not exist yet
        error_log("Error getting recent activity: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '-';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'open' => 'badge-open',
        'in_progress' => 'badge-progress',
        'waiting' => 'badge-waiting',
        'resolved' => 'badge-resolved',
        'closed' => 'badge-closed',
        'draft' => 'badge-draft',
        'published' => 'badge-published',
        'archived' => 'badge-archived'
    ];
    return $classes[$status] ?? 'badge-default';
}

/**
 * Get priority badge class
 */
function getPriorityBadgeClass($priority) {
    $classes = [
        'low' => 'badge-low',
        'medium' => 'badge-medium',
        'high' => 'badge-high',
        'urgent' => 'badge-urgent'
    ];
    return $classes[$priority] ?? 'badge-medium';
}

/**
 * Generate secure access code for tickets
 * Format: 6 alphanumeric characters (uppercase) + 4 digits = 10 characters total
 */
function generateAccessCode($db) {
    // Always generate a code, even if column doesn't exist
    // The code will be stored in the email and can be used later
    
    do {
        // Generate 6 random uppercase letters/numbers
        $part1 = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing chars (0, O, I, 1)
        for ($i = 0; $i < 6; $i++) {
            $part1 .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Generate 4 random digits
        $part2 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        
        $accessCode = $part1 . $part2;
        
        // Check if code already exists (only if column exists)
        try {
            $checkSql = "SHOW COLUMNS FROM tickets LIKE 'access_code'";
            $checkStmt = $db->query($checkSql);
            $columnExists = $checkStmt->fetch() !== false;
            
            if ($columnExists) {
                $sql = "SELECT id FROM tickets WHERE access_code = :code LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute(['code' => $accessCode]);
                $exists = $stmt->fetch();
            } else {
                // Column doesn't exist, but we still generate a unique code
                // Check in a temporary table or just return (code is random enough)
                $exists = false;
            }
        } catch (PDOException $e) {
            // If error checking, assume code is unique (very low collision probability)
            $exists = false;
        }
    } while ($exists);
    
    return $accessCode;
}

/**
 * Generate slug from string
 */
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate HTML email template for ticket access code
 */
function generateTicketEmailHTML($customerName, $ticketNumber, $accessCode, $siteUrl = 'https://code4u.fr') {
    // Utiliser contact@code4u.fr pour l'envoi
    $logoUrl = $siteUrl . '/assets/images/Logo_Code4U.png';
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre code d\'accès - Ticket ' . htmlspecialchars($ticketNumber) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #2d2d2d; text-align: center;">
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="Code4U" style="max-width: 200px; height: auto;" />
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 20px 0; font-size: 24px; color: #2d2d2d; font-weight: bold;">Bonjour ' . htmlspecialchars($customerName) . ',</h1>
                            
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #4a4a4a;">
                                Votre ticket de support a été créé avec succès via notre chatbot.
                            </p>
                            
                            <div style="background-color: #f9f9f9; border: 2px solid #2d2d2d; padding: 20px; margin: 30px 0; text-align: center;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; color: #4a4a4a; font-weight: bold;">Numéro de ticket</p>
                                <p style="margin: 0; font-size: 28px; color: #2d2d2d; font-weight: bold; letter-spacing: 2px;">' . htmlspecialchars($ticketNumber) . '</p>
                            </div>
                            
                            <div style="background-color: #fff3cd; border: 3px solid #ffc107; padding: 25px; margin: 30px 0; text-align: center; border-radius: 0;">
                                <p style="margin: 0 0 15px 0; font-size: 14px; color: #856404; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">🔐 Code d\'accès secret</p>
                                <p style="margin: 0; font-size: 32px; color: #2d2d2d; font-weight: bold; letter-spacing: 4px; font-family: \'Courier New\', monospace;">' . htmlspecialchars($accessCode) . '</p>
                                <p style="margin: 15px 0 0 0; font-size: 12px; color: #856404;">
                                    ⚠️ IMPORTANT : Conservez ce code secret. Il vous permettra d\'accéder à votre ticket de manière sécurisée.
                                </p>
                            </div>
                            
                            <p style="margin: 20px 0; font-size: 16px; line-height: 1.6; color: #4a4a4a;">
                                Pour consulter votre ticket et communiquer avec notre équipe, rendez-vous sur notre site et utilisez ce code d\'accès.
                            </p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . htmlspecialchars($siteUrl) . '" style="display: inline-block; padding: 15px 30px; background-color: #2d2d2d; color: #ffffff; text-decoration: none; font-weight: bold; border-radius: 0; font-size: 16px;">Accéder au site</a>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9f9f9; border-top: 1px solid #e0e0e0; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #4a4a4a;">
                                <strong>Code4U</strong><br>
                                Support client
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                Cet email a été envoyé automatiquement. Merci de ne pas y répondre directement.<br>
                                Pour toute question, utilisez le code d\'accès ci-dessus pour accéder à votre ticket.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return $html;
}

/**
 * Send email notification
 * En mode développement local, les emails sont loggés au lieu d'être envoyés
 */
function sendEmailNotification($to, $subject, $body, $isHTML = false) {
    // Vérifier si le mode développement email est activé
    if (defined('EMAIL_DEV_MODE') && EMAIL_DEV_MODE) {
        // Mode développement: logger l'email au lieu de l'envoyer
        $logDir = dirname(EMAIL_LOG_FILE);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logContent = str_repeat('=', 80) . "\n";
        $logContent .= "DATE: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "TO: " . $to . "\n";
        $logContent .= "SUBJECT: " . $subject . "\n";
        $logContent .= "FROM: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\n";
        $logContent .= str_repeat('-', 80) . "\n";
        $logContent .= $body . "\n";
        $logContent .= str_repeat('=', 80) . "\n\n";
        
        @file_put_contents(EMAIL_LOG_FILE, $logContent, FILE_APPEND | LOCK_EX);
        
        // Retourner true pour simuler un envoi réussi
        return true;
    }
    
    // Mode production: envoyer l'email normalement avec headers anti-spam
    $fromEmail = 'noreply@code4u.fr';
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Code4U Support';
    
    // Headers pour forcer l'affichage de contact@code4u.fr
    $headers = [];
    $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
    $headers[] = "Sender: " . $fromEmail;
    $headers[] = "Reply-To: contact@code4u.fr";
    $headers[] = "Return-Path: " . $fromEmail;
    $headers[] = "X-Mailer: Code4U Ticket System";
    $headers[] = "X-Priority: 3";
    $headers[] = "X-MSMail-Priority: Normal";
    $headers[] = "List-Unsubscribe: <" . (defined('SITE_URL') ? SITE_URL : 'https://code4u.fr') . ">";
    $headers[] = "Precedence: bulk";
    $headers[] = "X-Envelope-From: " . $fromEmail;
    
    if ($isHTML) {
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
    } else {
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
    }
    
    // Use additional_parameters to set envelope sender (force l'enveloppe sender)
    // Important: le paramètre -f force l'enveloppe sender, ce qui change l'adresse d'affichage
    $additionalParams = "-f" . $fromEmail;
    
    // Essayer d'envoyer avec le paramètre -f
    $result = @mail($to, $subject, $body, implode("\r\n", $headers), $additionalParams);
    
    // Si ça ne fonctionne pas, essayer avec sendmail_path ou utiliser ini_set
    if (!$result) {
        // Essayer avec une autre méthode si disponible
        $oldFrom = ini_get('sendmail_from');
        ini_set('sendmail_from', $fromEmail);
        $result = mail($to, $subject, $body, implode("\r\n", $headers), $additionalParams);
        if ($oldFrom !== false) {
            ini_set('sendmail_from', $oldFrom);
        }
    }
    
    return $result;
}

/**
 * Get current admin ID from session
 */
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $loginUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/admin/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Ensure access_code column exists in tickets table
 * This function checks and creates the column if it doesn't exist
 */
function ensureAccessCodeColumn($db) {
    static $checked = false;
    if ($checked) {
        return true; // Already checked in this request
    }
    
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `tickets` LIKE 'access_code'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `tickets` ADD COLUMN `access_code` VARCHAR(20) DEFAULT NULL AFTER `ticket_number`");
            $db->exec("ALTER TABLE `tickets` ADD UNIQUE KEY `idx_access_code` (`access_code`)");
            error_log("Added 'access_code' column to 'tickets' table.");
        }
    } catch (PDOException $e) {
        error_log("Error checking/adding 'access_code' column: " . $e->getMessage());
    }
    $checked = true;
    return true;
}

/**
 * Ensure is_read column exists in ticket_messages table
 * This function checks and creates the column if it doesn't exist
 */
function ensureIsReadColumn($db) {
    static $checked = false;
    if ($checked) {
        return true; // Already checked in this request
    }
    
    try {
        // Check if column exists
        $sql = "SHOW COLUMNS FROM ticket_messages LIKE 'is_read'";
        $stmt = $db->query($sql);
        $column = $stmt->fetch();
        
        if (!$column) {
            // Column doesn't exist, create it
            $alterSql = "ALTER TABLE `ticket_messages` 
                        ADD COLUMN `is_read` TINYINT(1) DEFAULT 0 AFTER `is_internal`,
                        ADD INDEX `idx_is_read` (`is_read`)";
            $db->exec($alterSql);
            error_log("Created is_read column in ticket_messages table");
        }
        
        $checked = true;
        return true;
    } catch (PDOException $e) {
        error_log("Error checking/creating is_read column: " . $e->getMessage());
        return false; // Column might not exist, but we'll handle it gracefully
    }
}

/**
 * Check if is_read column exists (without creating it)
 */
function hasIsReadColumn($db) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }
    
    try {
        $sql = "SHOW COLUMNS FROM ticket_messages LIKE 'is_read'";
        $stmt = $db->query($sql);
        $hasColumn = $stmt->fetch() !== false;
        return $hasColumn;
    } catch (PDOException $e) {
        error_log("Error checking is_read column: " . $e->getMessage());
        $hasColumn = false;
        return false;
    }
}
