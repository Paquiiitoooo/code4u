<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
?>
<div class="admin-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-logo">
            <img src="<?php echo asset_path('assets/images/Logo_Code4U.png'); ?>" alt="Code4U" class="header-logo-img">
        </div>
    </div>
    
    <div class="header-right">
        <!-- Theme Toggle -->
        <button class="header-action-btn" id="adminThemeToggle" aria-label="Toggle theme" title="Changer le thème">
            <i class="fas fa-moon"></i>
        </button>
        
        <!-- Notifications -->
        <div class="header-notifications">
            <button class="header-action-btn notification-btn" id="notificationBtn" aria-label="Notifications" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" style="display: none;">0</span>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-dropdown-header">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                    <button class="notification-close-btn" id="closeNotificationDropdown">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notification-dropdown-body" id="notificationList">
                    <div class="notification-loading">
                        <i class="fas fa-spinner fa-spin"></i> Chargement...
                    </div>
                </div>
                <div class="notification-dropdown-footer">
                    <a href="chat.php" class="notification-view-all">
                        <i class="fas fa-comments"></i> Voir toutes les conversations
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
