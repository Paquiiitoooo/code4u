<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();
$db = getDB();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - Code4U Admin</title>
    <link rel="stylesheet" href="<?php echo asset_path('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/tickets.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body" data-theme="light">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="admin-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-ticket-alt"></i> Tickets</h1>
                <p>Gestion des tickets de support client</p>
            </div>
            
            <div class="tickets-wrapper">
                <!-- Filters -->
                <div class="filters-bar">
                    <div class="filter-group filter-group-tabs">
                        <label><i class="fas fa-list"></i> Vue:</label>
                        <div class="tickets-tabs">
                            <button class="tab-btn active" data-tab="active" id="tabActive">
                                <i class="fas fa-inbox"></i> Tickets actifs
                            </button>
                            <button class="tab-btn" data-tab="archived" id="tabArchived">
                                <i class="fas fa-archive"></i> Archives
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Statut:</label>
                        <select id="filterStatus" class="filter-select">
                            <option value="">Tous</option>
                            <option value="open">Ouvert</option>
                            <option value="in_progress">En cours</option>
                            <option value="waiting">En attente</option>
                            <option value="resolved">Résolu</option>
                            <option value="closed">Fermé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-flag"></i> Priorité:</label>
                        <select id="filterPriority" class="filter-select">
                            <option value="">Toutes</option>
                            <option value="low">Basse</option>
                            <option value="medium">Moyenne</option>
                            <option value="high">Haute</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-group-search">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="search-input" placeholder="Rechercher un ticket...">
                        </div>
                    </div>
                    
                    <div class="filter-group filter-group-actions">
                        <button class="btn btn-primary" id="newTicketBtn">
                            <i class="fas fa-plus"></i> Nouveau Ticket
                        </button>
                    </div>
                </div>
                
                <!-- Tickets Table Container with Internal Scroll -->
                <div class="table-container">
                    <table class="data-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Numéro</th>
                                <th><i class="fas fa-comment"></i> Sujet</th>
                                <th><i class="fas fa-user"></i> Client</th>
                                <th><i class="fas fa-info-circle"></i> Statut</th>
                                <th><i class="fas fa-flag"></i> Priorité</th>
                                <th><i class="fas fa-user-tie"></i> Assigné</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ticketsTableBody">
                            <tr>
                                <td colspan="8" class="loading">Chargement...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>
    
    <!-- Ticket Modal -->
    <div class="modal" id="ticketModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modalTitle">Détails du Ticket</h2>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body ticket-modal-content" id="ticketModalBody">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    
    <script src="<?php echo asset_path('admin/assets/js/admin.js'); ?>"></script>
    <script src="<?php echo asset_path('admin/assets/js/tickets.js'); ?>"></script>
</body>
</html>
