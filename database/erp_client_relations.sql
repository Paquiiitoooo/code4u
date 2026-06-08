-- ERP Code4U - relation client / CRM léger.
-- Importer dans la même base que les tables ERP existantes.

CREATE TABLE IF NOT EXISTS client_interactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  type ENUM('note','appel','email','rdv','tache') NOT NULL DEFAULT 'note',
  direction ENUM('entrant','sortant','interne') NOT NULL DEFAULT 'interne',
  subject VARCHAR(255) NOT NULL,
  content TEXT DEFAULT NULL,
  status ENUM('a_faire','en_cours','termine','annule') NOT NULL DEFAULT 'termine',
  due_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_by VARCHAR(120) DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_client_interactions_client_created (client_id, created_at),
  KEY idx_client_interactions_status_due (status, due_at),
  CONSTRAINT fk_client_interactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
