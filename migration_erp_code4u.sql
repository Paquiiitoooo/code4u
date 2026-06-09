-- ============================================================
--  Migration ERP Code4U  —  CREATE TABLE IF NOT EXISTS
--  Sécurisé : ne touche pas les tables existantes
--  Ordre : respecte les FK (parents avant enfants)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Utilisateurs ERP ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id                  INT NOT NULL AUTO_INCREMENT,
  email               VARCHAR(255) NOT NULL,
  password            VARCHAR(255) NOT NULL,
  first_name          VARCHAR(100) NOT NULL,
  last_name           VARCHAR(100) NOT NULL,
  telephone           VARCHAR(20) NULL,
  photo_url           TEXT NULL,
  role                ENUM('admin','user') NOT NULL DEFAULT 'user',
  two_factor_enabled  TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_secret   VARCHAR(255) NULL,
  created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_codes (
  id         INT NOT NULL AUTO_INCREMENT,
  user_id    INT NOT NULL,
  code       VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_tfc_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Clients ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
  id                      INT NOT NULL AUTO_INCREMENT,
  code                    VARCHAR(50) NOT NULL,
  type                    ENUM('particulier','entreprise') NOT NULL DEFAULT 'entreprise',
  nom                     VARCHAR(255) NOT NULL,
  prenom                  VARCHAR(100) NULL,
  raison_sociale          VARCHAR(255) NULL,
  siret                   VARCHAR(20) NULL,
  email                   VARCHAR(255) NULL,
  telephone               VARCHAR(20) NULL,
  adresse                 TEXT NULL,
  code_postal             VARCHAR(10) NULL,
  ville                   VARCHAR(100) NULL,
  pays                    VARCHAR(100) NOT NULL DEFAULT 'France',
  tva_intracommunautaire  VARCHAR(20) NULL,
  notes                   TEXT NULL,
  actif                   TINYINT(1) NOT NULL DEFAULT 1,
  created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_interactions (
  id           INT NOT NULL AUTO_INCREMENT,
  client_id    INT NOT NULL,
  type         ENUM('note','appel','email','rdv','tache') NOT NULL DEFAULT 'note',
  direction    ENUM('entrant','sortant','interne') NOT NULL DEFAULT 'interne',
  subject      VARCHAR(255) NOT NULL,
  content      TEXT NULL,
  status       ENUM('a_faire','en_cours','termine','annule') NOT NULL DEFAULT 'termine',
  due_at       DATETIME NULL,
  completed_at DATETIME NULL,
  created_by   VARCHAR(120) NULL,
  created_at   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_ci_client (client_id),
  CONSTRAINT fk_ci_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_projects (
  id            INT NOT NULL AUTO_INCREMENT,
  client_id     INT NOT NULL,
  name          VARCHAR(255) NOT NULL,
  status        ENUM('planned','in_progress','review','completed','paused') NOT NULL DEFAULT 'planned',
  progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  current_phase VARCHAR(120) NULL,
  start_date    DATE NULL,
  due_date      DATE NULL,
  created_at    DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at    DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_cp_client (client_id),
  CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_milestones (
  id           INT NOT NULL AUTO_INCREMENT,
  project_id   INT NOT NULL,
  title        VARCHAR(255) NOT NULL,
  description  VARCHAR(255) NULL,
  status       ENUM('todo','current','done') NOT NULL DEFAULT 'todo',
  sort_order   INT NOT NULL DEFAULT 0,
  due_date     DATE NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pm_project (project_id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES client_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_subscriptions (
  id                     INT NOT NULL AUTO_INCREMENT,
  client_id              INT NOT NULL,
  plan_name              VARCHAR(120) NOT NULL,
  status                 ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
  monthly_price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency               VARCHAR(3) NOT NULL DEFAULT 'EUR',
  included_hours         DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  used_hours             DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  response_sla           VARCHAR(120) NULL,
  renewal_date           DATE NULL,
  overage_rate           DECIMAL(10,2) NULL,
  options_json           JSON NULL,
  payment_token          VARCHAR(64) NULL,
  stripe_subscription_id VARCHAR(64) NULL,
  created_at             DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at             DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_ss_client (client_id),
  CONSTRAINT fk_ss_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_usage (
  id               INT NOT NULL AUTO_INCREMENT,
  client_id        INT NOT NULL,
  subscription_id  INT NULL,
  work_date        DATE NOT NULL,
  title            VARCHAR(255) NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 0,
  status           ENUM('planned','done','billed') NOT NULL DEFAULT 'done',
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_su_client (client_id),
  CONSTRAINT fk_su_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
  id         INT NOT NULL AUTO_INCREMENT,
  client_id  INT NOT NULL,
  provider   VARCHAR(40) NOT NULL DEFAULT 'manuel',
  brand      VARCHAR(40) NOT NULL DEFAULT 'Carte',
  label      VARCHAR(120) NOT NULL,
  expires_at VARCHAR(10) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_pm_client (client_id),
  CONSTRAINT fk_pm_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Devis ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS devis (
  id                      INT NOT NULL AUTO_INCREMENT,
  numero                  VARCHAR(50) NOT NULL,
  client_id               INT NOT NULL,
  date_devis              DATE NOT NULL,
  date_validite           DATE NULL,
  statut                  ENUM('brouillon','envoye','accepte','refuse','expire') NOT NULL DEFAULT 'brouillon',
  montant_ht              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  montant_tva             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  montant_ttc             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remise                  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  notes                   TEXT NULL,
  conditions              TEXT NULL,
  signature_token         VARCHAR(64) NULL,
  signature_token_expires_at DATETIME NULL,
  signature_date          DATETIME NULL,
  signature_ip            VARCHAR(45) NULL,
  signature_telephone     VARCHAR(30) NULL,
  signature_signataire    VARCHAR(100) NULL,
  signature_data          LONGTEXT NULL,
  signature_consentement  TINYINT(1) NOT NULL DEFAULT 0,
  signature_document_hash VARCHAR(64) NULL,
  signature_user_agent    VARCHAR(500) NULL,
  created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_devis_numero (numero),
  UNIQUE KEY uq_devis_sig_token (signature_token),
  KEY idx_devis_client (client_id),
  CONSTRAINT fk_devis_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devis_lignes (
  id          INT NOT NULL AUTO_INCREMENT,
  devis_id    INT NOT NULL,
  description TEXT NOT NULL,
  quantite    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  prix_ht     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  taux_tva    DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  montant_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sort_order  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_dl_devis (devis_id),
  CONSTRAINT fk_dl_devis FOREIGN KEY (devis_id) REFERENCES devis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Factures ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS factures (
  id               INT NOT NULL AUTO_INCREMENT,
  numero           VARCHAR(50) NOT NULL,
  client_id        INT NOT NULL,
  devis_id         INT NULL,
  date_facture     DATE NOT NULL,
  date_echeance    DATE NULL,
  statut           ENUM('brouillon','emise','envoyee','payee','partielle','impayee','annulee') NOT NULL DEFAULT 'brouillon',
  montant_ht       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  montant_tva      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  montant_ttc      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  montant_paye     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remise           DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  notes            TEXT NULL,
  conditions       TEXT NULL,
  nombre_relances  INT NOT NULL DEFAULT 0,
  relance_envoyee_at DATETIME NULL,
  payment_token    VARCHAR(64) NULL,
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_factures_numero (numero),
  KEY idx_factures_client (client_id),
  KEY idx_factures_devis (devis_id),
  CONSTRAINT fk_factures_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT,
  CONSTRAINT fk_factures_devis  FOREIGN KEY (devis_id)  REFERENCES devis (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facture_lignes (
  id          INT NOT NULL AUTO_INCREMENT,
  facture_id  INT NOT NULL,
  description TEXT NOT NULL,
  quantite    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  prix_ht     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  taux_tva    DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
  montant_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sort_order  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_fl_facture (facture_id),
  CONSTRAINT fk_fl_facture FOREIGN KEY (facture_id) REFERENCES factures (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paiements (
  id             INT NOT NULL AUTO_INCREMENT,
  facture_id     INT NOT NULL,
  montant        DECIMAL(10,2) NOT NULL,
  date_paiement  DATE NOT NULL,
  mode_paiement  VARCHAR(50) NOT NULL DEFAULT 'virement',
  reference      VARCHAR(120) NULL,
  notes          TEXT NULL,
  created_at     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_paiements_facture (facture_id),
  CONSTRAINT fk_paiements_facture FOREIGN KEY (facture_id) REFERENCES factures (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tickets support ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
  id              INT NOT NULL AUTO_INCREMENT,
  ticket_number   VARCHAR(20) NOT NULL,
  client_id       INT NULL,
  customer_name   VARCHAR(120) NULL,
  customer_email  VARCHAR(255) NULL,
  subject         VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  status          ENUM('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
  priority        ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  category        VARCHAR(80) NULL,
  assigned_to     INT NULL,
  resolved_at     DATETIME NULL,
  created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_number (ticket_number),
  KEY idx_tickets_client (client_id),
  KEY idx_tickets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
  id          INT NOT NULL AUTO_INCREMENT,
  ticket_id   INT NOT NULL,
  sender_type ENUM('customer','admin','system') NOT NULL DEFAULT 'admin',
  message     TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_tm_ticket (ticket_id),
  CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Produits ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produits (
  id          INT NOT NULL AUTO_INCREMENT,
  reference   VARCHAR(50) NULL,
  nom         VARCHAR(255) NOT NULL,
  description TEXT NULL,
  prix_ht     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  taux_tva    DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
  unite       VARCHAR(30) NULL,
  actif       TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Modèles mail ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modeles_mail (
  id         INT NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(120) NOT NULL,
  sujet      VARCHAR(255) NOT NULL,
  corps      TEXT NOT NULL,
  type       VARCHAR(60) NULL,
  actif      TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Prospects ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prospects (
  id         INT NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NULL,
  telephone  VARCHAR(20) NULL,
  societe    VARCHAR(255) NULL,
  source     VARCHAR(80) NULL,
  status     VARCHAR(40) NOT NULL DEFAULT 'nouveau',
  notes      TEXT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  FIN — Toutes les tables ci-dessus sont créées SI elles
--        n'existent pas encore. Les tables déjà présentes
--        (clients, factures, devis, tickets…) ne sont pas
--        modifiées.
-- ============================================================
