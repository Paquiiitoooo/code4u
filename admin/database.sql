-- ========================================
-- Code4U Admin Panel - Database Schema
-- ========================================

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL UNIQUE,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tickets de support
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL UNIQUE,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `status` enum('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `category` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `source` enum('chatbot','form','email','phone','admin') DEFAULT 'form',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_number` (`ticket_number`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`assigned_to`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des messages/réponses des tickets
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `sender_type` enum('customer','admin','system') DEFAULT 'customer',
  `sender_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `attachments` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des landing pages
CREATE TABLE IF NOT EXISTS `landing_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL UNIQUE,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `template` varchar(100) DEFAULT 'default',
  `custom_css` text DEFAULT NULL,
  `custom_js` text DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des statistiques
CREATE TABLE IF NOT EXISTS `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `metric_type` varchar(50) NOT NULL,
  `metric_value` int(11) DEFAULT 0,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_metric` (`date`, `metric_type`),
  KEY `idx_date` (`date`),
  KEY `idx_metric_type` (`metric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des événements/activités
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des sessions admin
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL UNIQUE,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`),
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion d'un admin par défaut (mot de passe: admin123 - À CHANGER EN PRODUCTION!)
-- Hash généré avec password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`) VALUES
('admin', 'admin@code4u.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur Principal', 'super_admin')
ON DUPLICATE KEY UPDATE `username`=`username`;

