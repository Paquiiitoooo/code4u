# Déploiement GitHub Actions → VPS

Le workflow `CI and deploy` ([.github/workflows/deploy.yml](../.github/workflows/deploy.yml)) s'exécute à chaque push sur `main` :

1. **Validate** — lint de tous les fichiers `.php` (`php -l`).
2. **Deploy** — synchronisation `rsync` vers le VPS, puis post-déploiement
   (dossier `logs/`, propriétaire + permissions Plesk).

## Secrets à créer dans GitHub

`Repository → Settings → Secrets and variables → Actions → New repository secret`

| Secret | Exemple | Description |
|---|---|---|
| `VPS_HOST` | `82.165.120.46` | IP ou nom d'hôte du VPS |
| `VPS_USER` | `root` | Utilisateur SSH (droit d'écriture sur la cible) |
| `VPS_PORT` | `22` | Port SSH (optionnel, `22` par défaut) |
| `VPS_TARGET` | `/var/www/vhosts/code4u.fr/httpdocs` | Racine du site sur le VPS |
| `VPS_SSH_PRIVATE_KEY` | *(clé privée)* | Clé SSH autorisée à écrire dans `VPS_TARGET` |

## Base de données : `admin/config/local.php` (sur le VPS, hors dépôt)

Les identifiants MySQL **ne sont pas** dans le dépôt ni dans les secrets GitHub.
Ils vivent uniquement dans `admin/config/local.php` **sur le VPS** :

```php
<?php
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'erp_code4u',
    'db_user' => 'erp_code4u',
    'db_pass' => '********',
];
```

Ce fichier est exclu de `rsync` (jamais écrasé ni supprimé par un déploiement).
Il n'est à créer qu'**une seule fois** sur le VPS.

## Données protégées (jamais écrasées / supprimées par le déploiement)

Le `rsync --delete` exclut : `.git/`, `.github/`, `.env*`, `admin/config/local.php`,
`admin/uploads/`, `logs/`, `contact_logs.txt`, `node_modules/`, `vendor/`,
`.publish-temp-*/`.

## Première mise en route

1. Créer les 5 secrets `VPS_*` ci-dessus.
2. S'assurer que `admin/config/local.php` existe sur le VPS (identifiants `erp_code4u`).
3. Vérifier que la base ERP `erp_code4u` contient la table `client_portal_accounts`
   (sinon importer [database/portal_setup_erp_code4u.sql](../database/portal_setup_erp_code4u.sql)).
4. Pousser sur `main` (ou *Actions → Run workflow*).
5. Tester `/espace-client.html` (compte démo : `demo@code4u.fr` / `code4u2026`).
