-- Ancienne extension ERP/portail restaurée comme fichier de travail.
-- Ne pas importer sur le site vitrine restauré sauf reprise du chantier ERP.

CREATE TABLE IF NOT EXISTS quote_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL UNIQUE,
  status VARCHAR(50) NOT NULL DEFAULT 'submitted',
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  estimated_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  configuration_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
