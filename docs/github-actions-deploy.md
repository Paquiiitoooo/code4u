# Déploiement GitHub Actions → VPS

Le workflow `CI and deploy` ([.github/workflows/deploy.yml](../.github/workflows/deploy.yml)) s'exécute à chaque push sur `main` :

1. **Validate** — lint de tous les fichiers `.php` (`php -l`).
2. **Deploy** — synchronisation `rsync` vers le VPS, génération de `admin/config/local.php` depuis les secrets, puis post-déploiement (dossier `logs/`, permissions).

## Secrets à créer dans GitHub

`Repository → Settings → Secrets and variables → Actions → New repository secret`

### Connexion SSH au VPS
| Secret | Exemple | Description |
|---|---|---|
| `VPS_HOST` | `82.165.120.46` | IP ou nom d'hôte du VPS |
| `VPS_USER` | `code4u` | Utilisateur SSH (droit d'écriture sur la cible) |
| `VPS_PORT` | `22` | Port SSH (optionnel, `22` par défaut) |
| `VPS_TARGET` | `/var/www/vhosts/code4u.fr/httpdocs` | Racine du site sur le VPS |
| `VPS_SSH_PRIVATE_KEY` | *(clé privée)* | Clé SSH autorisée à écrire dans `VPS_TARGET` |

### Base de données (génère `admin/config/local.php` sur le VPS)
| Secret | Exemple | Description |
|---|---|---|
| `DB_HOST` | `localhost` | Hôte MySQL (local au VPS) |
| `DB_NAME` | `erp_code4u` | Nom de la base ERP |
| `DB_USER` | `erp_code4u` | Utilisateur MySQL |
| `DB_PASS` | *(mot de passe)* | Mot de passe MySQL |

> Si `DB_PASS` n'est pas défini, l'étape est ignorée et `admin/config/local.php`
> doit être créé/maintenu manuellement sur le VPS. Le mot de passe n'est **jamais**
> stocké dans le dépôt : il vit uniquement dans les secrets GitHub (ou dans le
> fichier `local.php` du VPS, qui n'est ni versionné ni écrasé par `rsync`).

## Données protégées (jamais écrasées / supprimées par le déploiement)

Le `rsync --delete` exclut : `.git/`, `.github/`, `.env*`, `admin/config/local.php`,
`admin/uploads/`, `logs/`, `contact_logs.txt`, `node_modules/`, `vendor/`,
`.publish-temp-*/`.

## Première mise en route

1. Créer les secrets ci-dessus.
2. S'assurer que la base ERP `erp_code4u` existe sur le VPS, puis importer
   [database/portal_setup_erp_code4u.sql](../database/portal_setup_erp_code4u.sql)
   (active l'espace client sans toucher aux données).
3. Pousser sur `main` (ou lancer le workflow manuellement via *Run workflow*).
4. Tester `/espace-client.html` (compte démo : `demo@code4u.fr` / `code4u2026`).
