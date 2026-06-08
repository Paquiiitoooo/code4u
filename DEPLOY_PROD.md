# Deploiement production

1. Importer ou conserver la base ERP principale (`erp_code4u`) avec les tables `clients`, `devis`, `devis_lignes`, `factures` et `paiements`.
2. Importer `database/client_portal.sql` dans cette meme base pour ajouter les comptes portail, abonnements support, projets, moyens de paiement et documents.
3. Configurer le site public pour utiliser les memes identifiants MySQL que l'ERP via `admin/config/local.php` sur le VPS.
4. Envoyer les fichiers du site sur l'hebergement.
5. Tester `/espace-client.html`, le formulaire public de devis, puis verifier dans l'ERP que le client, le devis et les lignes sont crees.
