// ========================================
// Code4U Admin Panel - Main JavaScript
// ========================================

// Global notification checker
let globalNotificationInterval = null;

function initGlobalNotifications() {
    // Check notifications every 10 seconds
    globalNotificationInterval = setInterval(async () => {
        try {
            const data = await adminUtils.apiRequest('api/notifications.php');
            if (data.success) {
                updateGlobalNotificationBadge(data.data.unread_chats);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }, 10000);
    
    // Initial check
    setTimeout(() => {
        adminUtils.apiRequest('api/notifications.php').then(data => {
            if (data.success) {
                updateGlobalNotificationBadge(data.data.unread_chats);
            }
        }).catch(() => {});
    }, 1000);
}

function updateGlobalNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initGlobalNotifications();
    // Theme management
    initTheme();
    
    // Sidebar toggle
    initSidebar();
    
    // Modal management
    initModals();
    
    // User menu dropdown
    initUserMenu();
    
    // Notifications dropdown
    initNotifications();
    
    // Close dropdowns on outside click
    initDropdownClose();
    
    console.log('✅ Admin panel initialized');
});

// User Menu Dropdown
function initUserMenu() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.querySelector('.header-user-menu');
    
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });
    }
}

// Notifications Dropdown
function initNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const closeBtn = document.getElementById('closeNotificationDropdown');
    const headerNotifications = document.querySelector('.header-notifications');
    
    if (notificationBtn && notificationDropdown && headerNotifications) {
        // Toggle dropdown
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            headerNotifications.classList.toggle('active');
            
            // Load notifications when opening
            if (headerNotifications.classList.contains('active')) {
                loadNotifications();
            }
        });
        
        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                headerNotifications.classList.remove('active');
            });
        }
    }
}

async function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    notificationList.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
    
    try {
        const data = await adminUtils.apiRequest('api/notifications.php');
        if (data.success) {
            displayNotifications(data.data);
        } else {
            notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i> Erreur lors du chargement</div>';
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i> Erreur lors du chargement</div>';
    }
}

function displayNotifications(data) {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    const { unread_chats, new_tickets, recent_messages } = data;
    
    if (recent_messages.length === 0 && new_tickets === 0) {
        notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i> Aucune notification</div>';
        return;
    }
    
    let html = '';
    
    // Recent messages
    recent_messages.forEach(msg => {
        const timeAgo = getTimeAgo(msg.created_at);
        html += `
            <div class="notification-item" onclick="window.location.href='chat.php?ticket=${msg.ticket_id}'">
                <div class="notification-item-icon">
                    <i class="fas fa-comment"></i>
                </div>
                <div class="notification-item-content">
                    <div class="notification-item-title">${escapeHtml(msg.customer_name)}</div>
                    <div class="notification-item-message">${escapeHtml(msg.message)}</div>
                    <div class="notification-item-time">${timeAgo}</div>
                </div>
            </div>
        `;
    });
    
    // New tickets notification
    if (new_tickets > 0) {
        html += `
            <div class="notification-item" onclick="window.location.href='tickets.php'">
                <div class="notification-item-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="notification-item-content">
                    <div class="notification-item-title">Nouveaux tickets</div>
                    <div class="notification-item-message">${new_tickets} nouveau${new_tickets > 1 ? 'x' : ''} ticket${new_tickets > 1 ? 's' : ''} dans les dernières 24h</div>
                </div>
            </div>
        `;
    }
    
    notificationList.innerHTML = html;
}

function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    if (diffHours < 24) return `Il y a ${diffHours}h`;
    if (diffDays < 7) return `Il y a ${diffDays}j`;
    return date.toLocaleDateString('fr-FR');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close dropdowns when clicking outside
function initDropdownClose() {
    document.addEventListener('click', function(e) {
        const userMenu = document.querySelector('.header-user-menu');
        const headerNotifications = document.querySelector('.header-notifications');
        
        if (userMenu && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
        
        if (headerNotifications && !headerNotifications.contains(e.target)) {
            headerNotifications.classList.remove('active');
        }
    });
}

// Update current page in breadcrumb
function updateCurrentPage(pageName) {
    const currentPageEl = document.getElementById('currentPage');
    if (currentPageEl) {
        currentPageEl.textContent = pageName;
    }
}

// Auto-update page name from title
document.addEventListener('DOMContentLoaded', function() {
    const pageTitle = document.title;
    const pageName = pageTitle.replace(' - Code4U Admin', '').trim();
    updateCurrentPage(pageName);
});

// Theme Management
function initTheme() {
    const themeToggle = document.getElementById('adminThemeToggle');
    const html = document.documentElement;
    
    // Load saved theme
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('admin_theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }
}

function updateThemeIcon(theme) {
    const themeToggle = document.getElementById('adminThemeToggle');
    if (themeToggle) {
        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
}

// Sidebar Toggle
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.querySelector('.admin-sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
    }
    
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    // Close sidebar on mobile when clicking outside
    if (sidebar) {
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                const target = e.target;
                const isClickInside = sidebar.contains(target);
                const isToggleBtn = sidebarToggle && sidebarToggle.contains(target);
                const isCloseBtn = sidebarCloseBtn && sidebarCloseBtn.contains(target);
                
                if (!isClickInside && !isToggleBtn && !isCloseBtn) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
}

// Modal Management
function initModals() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                closeModal(modal);
            });
        }
        
        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal(modal);
            }
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (typeof modal === 'string') {
        modal = document.getElementById(modal);
    }
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// API Helper Functions
async function apiRequest(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Erreur API');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Format Date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Format Relative Time
function formatRelativeTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'À l\'instant';
    if (minutes < 60) return `Il y a ${minutes} min`;
    if (hours < 24) return `Il y a ${hours}h`;
    if (days < 7) return `Il y a ${days}j`;
    return formatDate(dateString);
}

// Show Notification
function showNotification(message, type = 'info') {
    // Create notification container if it doesn't exist
    let container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${icons[type] || icons.info}"></i>
        </div>
        <div class="notification-content">${message}</div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// Confirm Dialog - Modal propre
function confirmDialog(message, title = 'Confirmation') {
    return new Promise((resolve) => {
        // Créer le modal
        const modal = document.createElement('div');
        modal.className = 'modal confirm-modal';
        modal.id = 'confirmModal';
        modal.innerHTML = `
            <div class="modal-content confirm-modal-content">
                <div class="confirm-modal-header">
                    <div class="confirm-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>${title}</h3>
                </div>
                <div class="confirm-modal-body">
                    <div>${message}</div>
                </div>
                <div class="confirm-modal-footer">
                    <button class="btn btn-secondary" data-action="cancel">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button class="btn btn-danger" data-action="confirm">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Ouvrir le modal
        setTimeout(() => {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }, 10);
        
        // Gérer les clics
        const handleClick = function(e) {
            const action = e.target.closest('[data-action]')?.dataset.action;
            
            if (action === 'confirm') {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                }, 300);
                resolve(true);
            } else if (action === 'cancel' || e.target === modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                }, 300);
                resolve(false);
            }
        };
        
        modal.addEventListener('click', handleClick);
        
        // Fermer avec Escape
        const handleEscape = function(e) {
            if (e.key === 'Escape') {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                    document.removeEventListener('keydown', handleEscape);
                }, 300);
                resolve(false);
            }
        };
        
        document.addEventListener('keydown', handleEscape);
    });
}

// Export functions
window.adminUtils = {
    openModal,
    closeModal,
    apiRequest,
    formatDate,
    formatRelativeTime,
    showNotification,
    confirmDialog
};

