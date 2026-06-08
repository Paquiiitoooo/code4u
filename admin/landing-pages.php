<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Pages - Code4U Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="admin-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-file-alt"></i> Landing Pages</h1>
                <button class="btn btn-primary" id="newLandingPageBtn">
                    <i class="fas fa-plus"></i> Nouvelle Landing Page
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Statut:</label>
                    <select id="filterStatus" class="filter-select">
                        <option value="">Tous</option>
                        <option value="draft">Brouillon</option>
                        <option value="published">Publié</option>
                        <option value="archived">Archivé</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <input type="text" id="searchInput" class="search-input" placeholder="Rechercher...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <!-- Landing Pages Grid -->
            <div class="landing-pages-grid" id="landingPagesGrid">
                <div class="loading">Chargement...</div>
            </div>
            
            <!-- Pagination -->
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
    
    <!-- Landing Page Modal -->
    <div class="modal" id="landingPageModal">
        <div class="modal-content modal-extra-large">
            <div class="modal-header">
                <h2 id="modalTitle">Éditeur de Landing Page</h2>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="landingPageModalBody">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    
    <script src="<?php echo asset_path('admin/assets/js/admin.js'); ?>"></script>
    <script src="<?php echo asset_path('admin/assets/js/landing-pages.js'); ?>"></script>
</body>
</html>

