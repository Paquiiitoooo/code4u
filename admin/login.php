<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Code4U</title>
    <link rel="stylesheet" href="<?php echo asset_path('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_path('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-login">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo asset_path('assets/images/Logo_Code4U.png'); ?>" alt="Code4U" class="login-logo">
                <h1>Panel Administrateur</h1>
                <p>Connectez-vous pour accéder au tableau de bord</p>
            </div>
            
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Nom d'utilisateur ou Email
                    </label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Mot de passe
                    </label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Se souvenir de moi</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
                
                <div id="loginMessage" class="form-message"></div>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2025 Code4U - Tous droits réservés</p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('loginMessage');
            messageDiv.textContent = 'Connexion en cours...';
            messageDiv.className = 'form-message';
            
            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.textContent = 'Connexion réussie, redirection...';
                    messageDiv.className = 'form-message success';
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    messageDiv.textContent = data.message || 'Erreur de connexion';
                    messageDiv.className = 'form-message error';
                }
            } catch (error) {
                messageDiv.textContent = 'Erreur de connexion au serveur';
                messageDiv.className = 'form-message error';
            }
        });
        
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    </script>
</body>
</html>

