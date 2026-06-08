-- =====================================================================
--  Code4U — Activation du portail client sur la base ERP "erp_code4u"
-- =====================================================================
--  Objectif : rendre l'espace client (espace-client.html + admin/api/
--  client-portal.php) fonctionnel SANS modifier ni supprimer tes données.
--
--  100% ADDITIF / NON DESTRUCTIF :
--    - la table est créée en "CREATE TABLE IF NOT EXISTS"
--    - aucune table existante n'est modifiée ou supprimée
--    - le compte démo est inséré en "ON DUPLICATE KEY UPDATE"
--
--  Le tableau de bord lit déjà tes VRAIES données depuis l'ERP
--  (clients, devis, factures, paiements). Les seules infos qui
--  manquaient pour l'espace client : une table de COMPTES DE CONNEXION.
--
--  Import :
--    Plesk -> Bases de données -> phpMyAdmin -> base "erp_code4u"
--    -> onglet "Importer" -> choisir ce fichier -> Exécuter.
--  (ou : mysql -u erp_code4u -p erp_code4u < portal_setup_erp_code4u.sql)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Comptes de connexion au portail (auth de l'espace client)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Compte de DÉMO pour tester tout de suite
--    Login : demo@code4u.fr   /   Mot de passe : code4u2026
--    Rattaché au client "Visioframe" (info@visioframe.com, id=1).
-- ---------------------------------------------------------------------
SET @demo_client_id := (SELECT id FROM clients WHERE email = 'info@visioframe.com' LIMIT 1);

INSERT INTO client_portal_accounts (client_id, email, password_hash, status)
SELECT @demo_client_id, 'demo@code4u.fr', '$2y$12$W1ZWjivij5y/rYmJcSC0N.jInj/0m0QgtFCiiSVBV4mdMiVI4OUFS', 'active'
WHERE @demo_client_id IS NOT NULL
ON DUPLICATE KEY UPDATE client_id = VALUES(client_id), password_hash = VALUES(password_hash), status = 'active';

-- ---------------------------------------------------------------------
-- (OPTIONNEL) Donner un accès à tes VRAIS clients.
--    Génère un hash bcrypt :
--      php -r "echo password_hash('TON_MDP', PASSWORD_BCRYPT);"
--    puis décommente / adapte (un bloc par client) :
-- ---------------------------------------------------------------------
-- INSERT INTO client_portal_accounts (client_id, email, password_hash, status)
-- SELECT id, email, '$2y$12$REMPLACE_PAR_TON_HASH_BCRYPT', 'active'
-- FROM clients WHERE email = 'info@visioframe.com' AND actif = 1
-- ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = 'active';

-- ---------------------------------------------------------------------
-- Vérification (à exécuter après l'import) :
-- ---------------------------------------------------------------------
SELECT a.email AS login, a.status, c.id AS client_id,
       COALESCE(c.raison_sociale, CONCAT_WS(' ', c.prenom, c.nom)) AS client
FROM client_portal_accounts a
JOIN clients c ON c.id = a.client_id;
-- Doit renvoyer au moins : demo@code4u.fr | active | 1 | Visioframe
