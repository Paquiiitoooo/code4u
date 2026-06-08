-- Code4U client portal extension for the existing ERP database.
-- Import this into the same database as the ERP tables: clients, devis, factures, paiements.
-- It does not duplicate ERP clients, quotes or invoices. It only adds portal auth and support/project metadata.

CREATE TABLE IF NOT EXISTS client_portal_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_client_portal_accounts_client (client_id),
  CONSTRAINT fk_client_portal_accounts_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS support_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  plan_name VARCHAR(120) NOT NULL,
  status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
  monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  included_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  used_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  response_sla VARCHAR(120) DEFAULT NULL,
  renewal_date DATE DEFAULT NULL,
  overage_rate DECIMAL(10,2) DEFAULT NULL,
  options_json JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_support_subscriptions_client (client_id),
  CONSTRAINT fk_support_subscriptions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS support_usage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  subscription_id INT DEFAULT NULL,
  work_date DATE NOT NULL,
  title VARCHAR(255) NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 0,
  status ENUM('planned','done','billed') NOT NULL DEFAULT 'done',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_support_usage_client_date (client_id, work_date),
  CONSTRAINT fk_support_usage_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_usage_subscription FOREIGN KEY (subscription_id) REFERENCES support_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  provider VARCHAR(60) DEFAULT NULL,
  brand VARCHAR(60) DEFAULT NULL,
  label VARCHAR(120) NOT NULL,
  expires_at VARCHAR(20) DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','expired','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_payment_methods_client (client_id),
  CONSTRAINT fk_payment_methods_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS client_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  status ENUM('planned','in_progress','review','completed','paused') NOT NULL DEFAULT 'planned',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  current_phase VARCHAR(120) DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_client_projects_client (client_id),
  CONSTRAINT fk_client_projects_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS project_milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  status ENUM('todo','current','done') NOT NULL DEFAULT 'todo',
  sort_order INT NOT NULL DEFAULT 0,
  due_date DATE DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  KEY idx_project_milestones_project (project_id, sort_order),
  CONSTRAINT fk_project_milestones_project FOREIGN KEY (project_id) REFERENCES client_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS client_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  project_id INT DEFAULT NULL,
  facture_id INT DEFAULT NULL,
  devis_id INT DEFAULT NULL,
  category ENUM('invoice','quote','contract','asset','report','other') NOT NULL DEFAULT 'other',
  title VARCHAR(255) NOT NULL,
  file_url VARCHAR(500) DEFAULT NULL,
  amount_label VARCHAR(80) DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_client_documents_client (client_id, created_at),
  CONSTRAINT fk_client_documents_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_client_documents_project FOREIGN KEY (project_id) REFERENCES client_projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_client_documents_facture FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE SET NULL,
  CONSTRAINT fk_client_documents_devis FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

SET @demo_client_id := (SELECT id FROM clients WHERE email = 'info@visioframe.com' LIMIT 1);

INSERT INTO client_portal_accounts (client_id, email, password_hash, status)
SELECT @demo_client_id, 'demo@code4u.fr', '$2y$12$W1ZWjivij5y/rYmJcSC0N.jInj/0m0QgtFCiiSVBV4mdMiVI4OUFS', 'active'
WHERE @demo_client_id IS NOT NULL
ON DUPLICATE KEY UPDATE client_id = VALUES(client_id), password_hash = VALUES(password_hash), status = 'active';

INSERT INTO support_subscriptions (client_id, plan_name, status, monthly_price, currency, included_hours, used_hours, response_sla, renewal_date, overage_rate, options_json)
SELECT @demo_client_id, 'Support Premium', 'active', 149.00, 'EUR', 20.00, 12.50, 'Réponse sous 8 h ouvrées', '2026-07-01', 65.00,
       JSON_ARRAY('Sauvegarde hebdomadaire supervisée', 'Correctifs rapides et mises à jour mineures', '1 évolution légère incluse')
WHERE @demo_client_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM support_subscriptions WHERE client_id = @demo_client_id AND plan_name = 'Support Premium');

INSERT INTO payment_methods (client_id, provider, brand, label, expires_at, is_default, status)
SELECT @demo_client_id, 'stripe', 'Visa', 'Visa •••• 4242', '08/2028', 1, 'active'
WHERE @demo_client_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM payment_methods WHERE client_id = @demo_client_id AND label = 'Visa •••• 4242');
