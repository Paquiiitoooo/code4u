# Refonte Code4U — Itération 1 : Design system + refonte visuelle

> Itération réalisée : **style premium éditorial (esprit codsense.fr, sans copie)** + nettoyage anti‑IA.
> Les phases ERP / signature / espace client / Kanban / CI‑CD sont **planifiées mais non codées** (voir bas de page).

---

## 1. Ce qui a changé

### Nouveau design system
- **`assets/css/style.css`** — bloc de tokens `:root` + `[data-theme="dark"]` **réécrit** (mêmes noms de
  variables, nouvelles valeurs). Plus de cyan/orange/dégradés « IA ». Palette : papier ivoire `#fcfbf9`,
  encre `#14131a`, **un seul accent cobalt `#2e40e5`**, ombres douces.
- **`assets/css/platform.css`** *(nouveau)* — couche chargée après `style.css` : polices, base typographique,
  boutons, navigation, sections, cartes services, **grille Réalisations**, tarifs, FAQ, formulaires, footer,
  focus visible (AA), `prefers-reduced-motion`. Neutralise les blobs/particules du hero et le bandeau de
  faux avis.
- **Typo** : `Bricolage Grotesque` (titres) + `Inter` (texte), via Google Fonts. *(à confirmer, cf. §3)*

### Pages
- **`index.html`** — hero réécrit, **fausses statistiques supprimées** (« 50+ projets », « 100 % clients
  satisfaits ») → 4 cartes de confiance honnêtes, services alignés sur l'offre réelle (dont *Connexion ERP &
  facturation*), **nouvelle section Réalisations** factuelle (Chrono Pizza, Kangal Kebab, Oui Pneu, Mon
  Estimation Travaux, Visioframe), **faux témoignages défilants supprimés** → mention « estimation indicative »
  + CTA simulateur, coordonnées complètes + mention TVA art. 293 B.
- **`templates/header.html`** — **toutes les images Unsplash retirées** (menus texte épurés), libellés alignés
  sur les services, CTA « Devis gratuit » → simulateur. Tous les hooks JS conservés.
- **`templates/footer.html`** — tagline concrète, liens services réels, année 2026, mention légale/TVA.
- **`commander.html` + `assets/css/commander.css`** — simulateur **repassé du thème sombre violet au thème
  clair cobalt** (cohérent avec le site), police d'affichage, contrastes muets corrigés (AA). **Le wizard
  fonctionne** (sélection, options, étapes, devis) — `order-wizard.js` intact.
- **`web.html` / `logiciel.html`** — héritent du design system ; « 50+ projets » remplacé par une mention
  honnête. `web.css` ne contenait aucune couleur en dur (il suit déjà les tokens).
- **Pages légales** (`cgv`, `mentions-legales`, `politique-confidentialite`) + `espace-membre.html` —
  `platform.css` ajouté, héritent des tokens.

> **Aucun fichier de l'ERP (`erpcode4u`) n'a été touché.** Aucune écriture en base ERP dans cette itération.

---

## 2. Audit anti‑IA (§7 du brief)

| # | Point | État |
|---|-------|------|
| 1 | Template généré ? | **Non** — palette/typo distinctives, plus d'images stock, plus de dégradés arc‑en‑ciel. |
| 2 | Textes « ChatGPT » ? | Réécrits concrets — **à relire par Pacôme** (cf. §3). |
| 3 | Sections utiles à la vente ? | Services chiffrés, réalisations cliquables en prod, tarifs + simulateur, contact. |
| 4 | Confiance achat à plusieurs k€ ? | Réalisations réelles vérifiables, interlocuteur unique, statut légal, prix sans TVA. *(à renforcer : étude de cas Visioframe).* |
| 5 | Parcours d'achat clair ? | « Estimer mon projet » → simulateur → devis. Hero + nav + tarifs convergent. |
| 6 | Crédible sans surpromettre ? | **Faux avis & chiffres inventés supprimés** ; « estimation indicative ». |
| 7 | Espace client utilisable ? | **`espace-client.html` créé** (shell front‑end mobile‑first : connexion + aperçu devis/factures/projets). Auth + lecture ERP = phase 4. Ancienne page « suivi de ticket » retirée de la navigation. |
| 8 | Cohérent mobile/tablette/desktop ? | Tokens responsive ; vérifié desktop + mobile. *(captures multi‑tailles limitées par l'outil de preview — à revoir sur device réel).* |

---

## 3. ⚠️ À VALIDER PAR PACÔME

1. **Descriptions des réalisations** (section *Réalisations* de `index.html`) — rédigées factuelles, **sans
   chiffres**. À relire/ajuster (surtout Visioframe).
2. **Visuel Visioframe manquant** dans `assets/images/` → la carte affiche « Visuel à ajouter ».
   Fournir une image (idéalement `assets/images/visioframe.png`) ou confirmer l'URL publique.
3. **Tarifs** — conservés tels quels (Vitrine 599 € · E‑commerce 1490 € · App web 1199 € · Automatisation
   499 €). À confirmer. La page tarifs affiche aussi « + 99 € hébergement / an » et « Site avec BDD 1 199 € ».
4. **Promesse « Devis gratuit sous 24 h »** (hero web/logiciel, méta) — confirmer que c'est tenable.
5. **Choix typographique** *Bricolage Grotesque + Inter* — valider ou proposer une autre paire.
6. **Coordonnées / mentions** — adresse 114 av. de Thionville 57050 Metz, +33 6 52 37 26 36,
   contact@code4u.fr, « TVA non applicable, art. 293 B du CGI » : OK ?
7. **Mode sombre** conservé et fonctionnel, mais le **clair est l'expérience vitrine soignée** (priorité brief).

---

## 4. Prévisualiser en local
```bash
cd Code4U
php -S 127.0.0.1:8000          # puis ouvrir http://127.0.0.1:8000/index.html
```
Pages à vérifier : `index.html`, `web.html`, `logiciel.html`, `commander.html` (simulateur complet),
+ thème clair/sombre, menu mobile (≤ 768 px), FAQ, bannière cookies.

---

## 5. Phases suivantes (planifiées, non codées) — rappel
- **Phase 2** — Simulateur → ERP : module audité *INSERT‑only* dans `erp_code4u` (client `CLIxxx` + devis
  `DEV‑xxxxxx` `brouillon`, TVA 0), développé/testé sur un **import local du dump**, transport prod en config.
  Migration des **tables site préfixées** (`site_users`, `quote_requests`, `quote_signatures`,
  `stripe_payments`, `integration_outbox`).
- **Phase 3** — Page signature `/sign/devis/{token}` (proxy serveur des endpoints publics ERP).
- **Phase 4** — Espace client : brancher `espace-client.html` sur l'auth `site_users` (mot de passe haché)
  + dashboard lecture ERP (devis/factures/paiements). `espace-membre.html` (ancien suivi de ticket) n'est plus
  lié — à supprimer ou rediriger.
- **Phase 5** — Board Kanban projet.
- **Phase 6** — CI/CD Plesk (`.github/workflows/deploy.yml`, rsync SSH) + README + **sortie des secrets**.

### 🔒 Sécurité à traiter avant tout push public
`admin/config/database.php` contient encore des **identifiants de prod en clair** (IONOS) et un mot de passe
local. À externaliser hors Git (`admin/config/local.php` ignoré) **et à faire tourner le mot de passe IONOS
exposé** avant publication sur `github.com/Paquiiitoooo/code4u`.
