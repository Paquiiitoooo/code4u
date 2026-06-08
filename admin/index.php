<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

try {
    $db = getDB();
    $stats = getDashboardStats($db);
} catch (Exception $e) {
    error_log("Error in admin/index.php: " . $e->getMessage());
    die("Erreur lors du chargement des données. Veuillez vérifier la configuration de la base de données.");
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Code4U Admin</title>
    <link rel="stylesheet" href="<?php echo asset_path('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body" data-theme="light">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="admin-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Tableau de bord</h1>
                <p>Vue d'ensemble de votre système</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['tickets']['total'] ?? 0; ?></h3>
                        <p>Total Tickets</p>
                        <span class="stat-badge badge-open"><?php echo $stats['tickets']['open'] ?? 0; ?> ouverts</span>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['tickets']['urgent'] ?? 0; ?></h3>
                        <p>Tickets Urgents</p>
                        <span class="stat-badge badge-urgent">À traiter</span>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['assigned_tickets'] ?? 0; ?></h3>
                        <p>Tickets Assignés</p>
                        <span class="stat-badge badge-published">En cours</span>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['recent_activity'] ?? 0; ?></h3>
                        <p>Activité (24h)</p>
                        <span class="stat-badge">Récentes</span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Tickets -->
            <div class="content-row">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Tickets récents</h3>
                        <a href="tickets.php" class="btn-link">Voir tout <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <div id="recentTickets" class="tickets-list">
                            <div class="loading">Chargement...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo asset_path('admin/assets/js/admin.js'); ?>"></script>
    <script>
        // Load dashboard data
        loadDashboardData();
        
        async function loadDashboardData() {
            try {
                // Load recent tickets
                const ticketsRes = await fetch('api/tickets.php?action=list&limit=5');
                const ticketsData = await ticketsRes.json();
                if (ticketsData.success) {
                    displayRecentTickets(ticketsData.data);
                }
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
            }
        }
        
        function displayRecentTickets(tickets) {
            const container = document.getElementById('recentTickets');
            if (tickets.length === 0) {
                container.innerHTML = '<p class="empty">Aucun ticket récent</p>';
                return;
            }
            
            container.innerHTML = tickets.map(ticket => `
                <div class="ticket-item">
                    <div class="ticket-header">
                        <span class="ticket-number">${ticket.ticket_number}</span>
                        <span class="badge ${getStatusBadgeClass(ticket.status)}">${ticket.status}</span>
                    </div>
                    <h4>${ticket.subject}</h4>
                    <p class="ticket-meta">
                        <i class="fas fa-user"></i> ${ticket.customer_name} • 
                        <i class="fas fa-clock"></i> ${formatDate(ticket.created_at)}
                    </p>
                </div>
            `).join('');
        }
        
        function getStatusBadgeClass(status) {
            const classes = {
                'open': 'badge-open',
                'in_progress': 'badge-progress',
                'resolved': 'badge-resolved',
                'closed': 'badge-closed'
            };
            return classes[status] || 'badge-default';
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', { 
                day: '2-digit', 
                month: 'short', 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    </script>
</body>
</html>

