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
    <title>Chat Support - Code4U Admin</title>
    <link rel="stylesheet" href="<?php echo asset_path('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/chat.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body" data-theme="light">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="admin-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-comments"></i> Chat Support</h1>
                <p>Conversations en direct avec les clients via le chatbot</p>
            </div>
            
            <div class="chat-container">
                <div class="chat-list">
                    <div class="chat-list-header">
                        <i class="fas fa-comments"></i> Conversations
                    </div>
                    <div class="chat-list-content" id="chatList">
                        <div class="empty-chat">Chargement des conversations...</div>
                    </div>
                </div>
                
                <div class="chat-window" id="chatWindow">
                    <div class="chat-window-header">
                        <div class="chat-window-header-content">
                            <h3 id="chatCustomerName">Sélectionnez une conversation</h3>
                            <small id="chatCustomerInfo"></small>
                        </div>
                        <button class="chat-close-btn" id="chatCloseBtn" style="display: none;" onclick="closeCurrentChat()">
                            <i class="fas fa-times"></i> Fermer
                        </button>
                    </div>
                    <div class="chat-window-body" id="chatMessages">
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <p>Sélectionnez une conversation pour commencer</p>
                        </div>
                    </div>
                    <div class="chat-input-area" id="chatInputArea" style="display: none;">
                        <input type="text" class="chat-input" id="chatInput" placeholder="Tapez votre message...">
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo asset_path('admin/assets/js/admin.js'); ?>"></script>
    <script src="<?php echo asset_path('admin/assets/js/chat.js'); ?>"></script>
</body>
</html>

