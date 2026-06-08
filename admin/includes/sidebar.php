<?php
$currentPage = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-header-content">
            <img src="<?php echo asset_path('assets/images/Logo_Code4U.png'); ?>" alt="Code4U" class="sidebar-logo">
            <h2>Admin Panel</h2>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Fermer le menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="chat.php" class="nav-item <?php echo $currentPage === 'chat.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i>
            <span>Chat Support</span>
            <?php
            try {
                $db = getDB();
                ensureIsReadColumn($db);
                $hasIsRead = hasIsReadColumn($db);
                
                if ($hasIsRead) {
                    $unreadCount = $db->query("SELECT COUNT(DISTINCT t.id) as count
                                               FROM tickets t
                                               INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                                               WHERE (t.source = 'chatbot' OR t.subject LIKE '%Chat en direct%' OR t.subject LIKE '%chat direct%')
                                               AND t.status != 'closed'
                                               AND tm.sender_type = 'customer'
                                               AND (tm.is_read = 0 OR tm.is_read IS NULL)")->fetch()['count'];
                } else {
                    // If column doesn't exist, count all customer messages
                    $unreadCount = $db->query("SELECT COUNT(DISTINCT t.id) as count
                                               FROM tickets t
                                               INNER JOIN ticket_messages tm ON tm.ticket_id = t.id
                                               WHERE (t.source = 'chatbot' OR t.subject LIKE '%Chat en direct%' OR t.subject LIKE '%chat direct%')
                                               AND t.status != 'closed'
                                               AND tm.sender_type = 'customer'")->fetch()['count'];
                }
                
                if ($unreadCount > 0):
                ?>
                <span class="nav-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                <?php endif;
            } catch (Exception $e) {
                // Silently fail
            }
            ?>
        </a>
        
        <a href="tickets.php" class="nav-item <?php echo $currentPage === 'tickets.php' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i>
            <span>Tickets</span>
            <?php
            try {
                $db = getDB();
                $urgentCount = $db->query("SELECT COUNT(*) FROM tickets WHERE priority = 'urgent' AND status != 'closed'")->fetchColumn();
                if ($urgentCount > 0):
                ?>
                <span class="nav-badge"><?php echo $urgentCount; ?></span>
                <?php endif;
            } catch (Exception $e) {
                // Silently fail if database query fails
            }
            ?>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username']); ?></strong>
                <span><?php echo htmlspecialchars($_SESSION['admin_role']); ?></span>
            </div>
        </div>
        <a href="api/auth.php?action=logout" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            Déconnexion
        </a>
    </div>
</aside>

