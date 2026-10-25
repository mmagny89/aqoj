# À quoi on joue

[![Qualité et déploiement](https://github.com/mmagny89/aqoj/actions/workflows/qualite.yml/badge.svg?branch=develop)](https://github.com/mmagny89/aqoj/actions/workflows/qualite.yml)
[![Version](https://img.shields.io/github/v/tag/mmagny89/aqoj?label=version&sort=semver)](CHANGELOG.md)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)
![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
[![Licence propriétaire](https://img.shields.io/badge/licence-propri%C3%A9taire-lightgrey)](LICENSE)
[![Conventional Commits](https://img.shields.io/badge/Conventional_Commits-1.0.0-fe5196?logo=conventionalcommits&logoColor=white)](https://www.conventionalcommits.org/fr/v1.0.0/)

Plateforme de découverte et recommandation de jeux de société, connectée à BoardGameGeek.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | Symfony 8.1 — API JSON (PHP 8.5, FrankenPHP) |
| Frontend | React 18 + Vite 6 + TailwindCSS v4 |
| Base de données | PostgreSQL 18 |
| Serveur web | FrankenPHP (Caddy intégré), Traefik en frontal en staging et production |
| Conteneurisation | Docker Compose |

---

## Fonctionnalités

### Ludothèque personnelle
- Import de la collection depuis BoardGameGeek (username BGG)
- Synchronisation des notes personnelles BGG (1–10) sur chaque jeu
- Vue en deux onglets principaux :
  - **⭐ Déjà joués** — jeux avec une note BGG, triés par note décroissante
  - **🎁 Pas encore joués** — jeux sans note, triés alphabétiquement
- Chaque onglet dispose de deux sous-onglets **🎲 Jeux de base** et **➕ Extensions**
- Infinite scroll sur chaque onglet
- **Préférences mécaniques** calculées automatiquement depuis la collection :
  - Moteurs Engelstein dominants (ex. Construction de deck, Placement d'ouvriers…)
  - Mécaniques de support les plus jouées
  - Catégories BGG favorites
  - Recalcul automatique si les préférences sont absentes

### Recommandations personnalisées
- **Ma collection** — recommande parmi les jeux possédés, scorés par note personnelle + fraîcheur + qualité BGG
- **À découvrir** — suggère des jeux hors collection avec un seuil de qualité BGG ≥ 6.5
- Jusqu'à **30 résultats** par onglet, séparés en sous-onglets Jeux de base / Extensions
- Filtres disponibles dans la barre latérale :
  - Nombre de joueurs
  - Durée maximale (15 min → 4h)
  - Style / Moteur de jeu (modèle Engelstein — 6 moteurs centraux)
  - Mécaniques de support (8 familles)
  - Caractéristiques (Solo, Temps réel, Dextérité, Legacy)
  - **Thèmes & univers** — catégories mappées dans l'interface admin
  - **Types de jeu** — catégories mappées dans l'interface admin
- Les filtres thématiques sont chargés dynamiquement depuis les mappings admin (plus de liste figée dans le code)
- Suggestions de filtres basées sur les préférences mécaniques de l'utilisateur

### Moteur de mécaniques (modèle Engelstein)
Chaque jeu est analysé selon 18 familles de mécaniques :

| Catégorie | Familles |
|---|---|
| **6 moteurs centraux** | Placement d'ouvriers, Construction de deck, Construction de moteur, Contrôle de zone, Gestion de main, Enchères |
| **8 familles de support** | Jeu de cartes, Placement spatial, Mouvement, Gestion de ressources, Résolution, Hasard, Coopération, Marquage de points |
| **4 caractéristiques** | Solo / Solitaire, Temps réel, Adresse / Dextérité, Legacy / Campagne |

Le mapping entre mécaniques BGG et familles Engelstein est entièrement configurable via l'interface d'administration.

### Fiches de jeux
- Moteurs principaux et mécaniques de support avec les équivalents BGG
- Caractéristiques transversales (solo, temps réel, dextérité, legacy)
- Description, complexité (1–5), joueurs, durée, note communauté + note personnelle
- Lien direct vers la fiche BoardGameGeek (URL adaptée : `/boardgame/` ou `/boardgameexpansion/`)
- **Section Extensions** : liste des extensions connues avec indicateur de possession
- **Jeux similaires** : liste avec score de similarité (mécaniques 40%, catégories 20%, joueurs 15%, complexité 15%, âge 10%), bouton "Voir 5 de plus" jusqu'à épuisement

#### Fiches d'extensions
Quand le jeu consulté est une extension :
- Section **Jeu de base** : fiche du jeu parent avec indicateur de possession
- Section **Autres extensions** : extensions sœurs du même jeu de base

### Thématiques admin
L'interface d'administration permet de mapper des catégories BGG brutes (en anglais) vers des thématiques françaises avec emoji, regroupées en deux sections :
- **Thèmes & univers** : Fantastique, Science-Fiction, Horreur, Historique…
- **Types de jeu** : Stratégie, Party Game, Coopératif…

Ces mappings alimentent directement les filtres de la page Recommandations.

### Page d'aide (`/aide`)
Explications sur le fonctionnement du site, du modèle de recommandation, du modèle Engelstein, du score de similarité et du calcul de préférences mécaniques.

### Recherche
- Recherche textuelle dans le catalogue (jusqu'à 200 résultats)
- Inclut les extensions et packs promo
- Enrichissement automatique depuis BGG pour les jeux absents de la base

### Administration
- Gestion des mappings mécaniques BGG ↔ familles Engelstein (4 onglets : moteurs / support / extras / non mappées)
- Gestion des thématiques BGG → labels français + emoji (groupes : thèmes / types de jeu)
- Recalcul en masse des familles pour tous les jeux en base
- Visualisation des mécaniques BGG non encore mappées, triées par fréquence
- Mot de passe : l'œil affiche ou masque la saisie ; « Mot de passe oublié ? » envoie par e-mail un lien valable une heure et à usage unique (l'empreinte seule est stockée, la réponse est la même que le compte existe ou non) ; « Mon compte » change le mot de passe (exige l'actuel, déconnecte les autres appareils, avertit par e-mail) et enregistre le **numéro d'adhérent** (EDJ-0123, saisi par la personne, non vérifié ; visible et cherchable par l'administration).
- Rôles : un compte est **Membre** ou **VIP** (le statut administrateur est à part). L'inscription se fait par **code d'invitation** en préproduction et production : créé dans l'onglet Membres de `/admin` ou par `app:invitation:create "<libellé>" --role=member|vip --uses=N --until=AAAA-MM-JJ`, il accorde son rôle, sert N fois, expire et se révoque. `app:user:role <e-mail> member|vip` change un rôle, `app:user:admin <e-mail> [--revoke]` donne ou retire l'administration (jamais depuis l'interface). Le catalogue, la recherche et l'API `/api/catalogue` restent publics.
- Tableau de bord (`/admin`, six onglets : Catalogue, Tâches, Explorer, Membres, Communauté, Mappings ; l'onglet courant est dans l'URL, `?onglet=`) : dernière exécution et historique des tâches d'import et de synchronisation (bilan chiffré, durée, erreur), puis explorateur du catalogue filtrable (à enrichir, jamais demandés à BGG, sans réponse de BGG, à rafraîchir, sans image, extensions sans jeu de base…) avec fiche de diagnostic ; lecture seule

---

## Tâches planifiées

Le service `worker` (planificateur Symfony, `app/src/Scheduler/MainSchedule.php`) exécute quatre tâches chaque nuit, heure de Paris.
Les horaires se changent par les variables `BGG_*_CRON` (voir « Variables d'environnement ») : la préproduction décale les siens,
car elle partage le jeton BGG avec la production.
Aucun appel BGG n'est fait pendant la requête d'un joueur, hormis trois exceptions bornées : l'import de sa collection, le premier enrichissement d'une fiche encore vide (un essai par jour et par jeu) et le repli de la recherche textuelle (mis en cache 24 h).
Une tâche qui échoue écrit sur le canal `messenger` ; en préproduction et en production, une erreur envoie un e-mail à `ALERT_EMAIL` (au plus un par heure et par erreur).

```
1h00  app:bgg:enrich-pending --mode=resync  →  Resync incrémental (300 jeux/nuit)
2h00  app:bgg:enrich-pending --mode=pending →  Premiers enrichissements CSV
3h00  app:bgg:sync-new                      →  Nouveautés BGG (Hot List + année)
4h00  app:bgg:import-csv                    →  Import ZIP BGG si disponible
```

### Détail de chaque tâche

#### 1h00 — Resync incrémental
Rafraîchit les données BGG des jeux déjà enrichis (description, note, rang, extensions…).  
Priorité aux jeux les plus anciennement synchronisés (`last_synced_at ASC`).  
Avec ~9 000 jeux et 300/nuit → **cycle complet en ~30 jours**.

#### 2h00 — Premier enrichissement CSV
Traite les jeux importés via CSV BGG (`source=bgg_csv`) qui n'ont jamais été appelés via l'API BGG.  
Priorité par rang BGG croissant (jeux populaires d'abord).

#### 3h00 — Nouveautés BGG
1. Récupère la **BGG Hot List** (top 50 jeux du moment)
2. Recherche les jeux publiés cette **année** sur BGG
3. Importe en base uniquement les jeux absents

#### 4h00 — Import CSV BGG
Importe le catalogue complet depuis un fichier ZIP déposé dans `app/var/imports/bgg/`.  
Ne fait rien si aucun fichier n'est présent.

### Consulter les tâches

```bash
# Prochains passages
docker compose exec php php bin/console debug:scheduler

# Journal du planificateur (les commandes écrivent ici leur progression)
docker compose logs -f worker
```

Après toute modification de `MainSchedule` ou d'une commande planifiée : `docker compose restart worker`.

---

## Installation

### 1. Cloner le dépôt

```bash
git clone <url-du-repo> aqoj
cd aqoj
```

### 2. Configurer les variables d'environnement

**Infrastructure Docker** : le `.env` de la racine est commité et suffit en développement (ports, versions d'images, mot de passe de développement de PostgreSQL). Rien à copier.

**Application Symfony** (dans `app/`) :

```bash
cp app/.env app/.env.local
```

Éditer `app/.env.local` (non commité, monté dans le conteneur) :

```dotenv
APP_SECRET=changeme            # générer avec : docker compose exec php php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=changeme            # idem
BGG_TOKEN=                     # jeton d'application BGG
```

> `DATABASE_URL`, `MAILER_DSN` et les autres variables d'infrastructure sont injectés par Docker Compose — ne pas les définir dans `app/.env.local`.

### 3. Lancer la stack

```bash
docker compose --profile frontend up -d --build --wait
```

(ou `make up`). Les dépendances PHP (`vendor/`) et Node.js (`node_modules/`) s'installent automatiquement au premier lancement.
Le profil `frontend` démarre le serveur de développement du front ; sans lui, seule l'API tourne.

### 4. Peupler la base

```bash
# Option A — quelques jeux populaires pour démarrer vite
docker compose exec php php bin/console app:games:seed

# Option B — catalogue complet depuis un ZIP BGG (voir section "Mettre à jour le catalogue")
docker compose exec php php bin/console app:bgg:import-csv --include-expansions
# ou, depuis un CSV déjà extrait : app:import:catalogue <fichier> --source=bgg --include-expansions --no-debug
docker compose exec php php bin/console app:bgg:enrich-pending --mode=pending --limit=200
docker compose exec php php bin/console app:bgg:relink-expansions
```

---

## URLs

| Service | URL |
|---|---|
| Frontend (Vite) | http://localhost:5173 |
| API Symfony | https://localhost/api/health (certificat local : accepter l'avertissement) |
| Catalogue des jeux (lecture seule, pour BG-TCG) | https://localhost/api/catalogue/games?itemsPerPage=10 ; interface Swagger UI : https://localhost/api/docs (dev) |
| E-mails de développement (Mailpit) | http://localhost:8025 |

---

## Développement

### Live reload

| Modification | Comportement |
|---|---|
| Fichier React / CSS | Hot Module Replacement instantané (Vite HMR) |
| Fichier PHP / Symfony | Rechargement automatique du worker FrankenPHP ; `docker compose restart worker` pour le planificateur |
| `composer.json` ou `package.json` | Rebuild requis (`make build`) |
| `Dockerfile` | Rebuild requis |

### Raccourcis Make

```bash
make up              # Démarrer en arrière-plan
make down            # Arrêter
make build           # Rebuild complet
make logs            # Logs en temps réel
make shell-php       # Shell PHP
make shell-node      # Shell Node
make qualite         # PHPStan, PHPUnit, ESLint, Vitest
make console cmd="cache:clear"   # Commande Symfony
```

---

## Structure du projet

```
aqoj/
├── compose.yml                     # Socle commun (php, worker, database)
├── compose.{dev,staging,prod}.yml  # Un override par environnement
├── .env                            # Variables Compose (commité, sans secret)
├── .env.{staging,prod}.local.dist  # Modèles de secrets de déploiement
├── Makefile
├── outils/                         # Outils d'exploitation (déploiement, secrets)
│
├── docker/
│   ├── php/                        # Dockerfile FrankenPHP (stages dev, prod, staging), Caddyfile
│   └── node/Dockerfile             # Serveur de développement du front
│
├── app/                            # Symfony 8.1 — API JSON
│   ├── src/
│   │   ├── Command/                # Commandes CLI
│   │   │   ├── ImportCatalogueCommand.php  # app:import:catalogue <fichier> --source=bgg
│   │   │   ├── ImportBggCsvCommand.php     # ZIP déposé → importeur (tâche planifiée)
│   │   │   ├── EnrichGamesPendingCommand.php
│   │   │   ├── SyncBggNewReleasesCommand.php
│   │   │   ├── RelinkExpansionsCommand.php
│   │   │   ├── RecomputeMechanicFamiliesCommand.php
│   │   │   └── SeedGamesCommand.php
│   │   ├── Controller/             # Endpoints API
│   │   │   ├── AuthController.php
│   │   │   ├── GameController.php
│   │   │   ├── UserController.php
│   │   │   └── AdminController.php
│   │   ├── Entity/                 # Entités Doctrine
│   │   │   ├── Game.php
│   │   │   ├── User.php
│   │   │   ├── UserGame.php
│   │   │   ├── UserPreference.php
│   │   │   └── ThemeMapping.php
│   │   ├── Repository/             # Requêtes DB
│   │   ├── Scheduler/              # Planification des tâches BGG (worker)
│   │   ├── Catalogue/              # Import : sources (Import/), rapprochement (CatalogueImporter)
│   │   ├── Security/               # Authentification JWT du pare-feu
│   │   └── Service/                # Logique métier
│   │       ├── BggApiService.php         # Appels API BGG
│   │       ├── GameEnrichmentService.php # Hydratation des entités
│   │       ├── MechanicFamilyResolver.php
│   │       ├── RecommendationService.php
│   │       └── UserPreferenceService.php
│   └── migrations/                 # Migrations Doctrine
│
└── frontend/                       # React 18 + Vite + Tailwind v4 (build statique servi par FrankenPHP)
    └── src/
        ├── pages/
        │   ├── HomePage.jsx
        │   ├── LibraryPage.jsx        # Ludothèque (sous-onglets base/ext)
        │   ├── RecommendationPage.jsx # Recommandations (filtres dynamiques)
        │   ├── GameDetailPage.jsx     # Fiche jeu + extensions + similarité
        │   ├── SearchPage.jsx
        │   ├── HelpPage.jsx           # /aide — explication du moteur
        │   ├── AdminPage.jsx
        │   └── AdminThemesPage.jsx
        ├── components/
        │   └── GameCard.jsx           # Carte jeu (badge extension, % similarité)
        ├── utils/
        │   ├── engelstein.js          # Familles de mécaniques + couleurs
        │   └── categories.js          # Catégories BGG (recherche)
        └── api.js                     # Appels API centralisés
```

---

## Variables d'environnement

### `.env` (racine) — Docker Compose

Commité, sans secret. En préproduction et production, il est **remplacé** (pas complété) par `--env-file .env.staging.local` ou `.env.prod.local`, créés à partir des `.dist`.

| Variable | Description | Dev | Prod |
|---|---|---|---|
| `ENV` | Suffixe des noms de projet, conteneurs, réseau et volumes | `dev` | `prod` |
| `POSTGRES_*` | Base PostgreSQL | valeurs de dev du `.env` | mot de passe fort |
| `SERVER_NAME` | Domaine routé par Traefik | — | `mondomaine.fr` |
| `SYMFONY_TRUSTED_PROXIES` | Proxies de confiance (adresse du client) | *(vide)* | `private_ranges` |
| `MAILER_DSN`, `MAILER_FROM` | Transport et expéditeur des e-mails | Mailpit | transport réel |
| `ALERT_EMAIL` | Destinataire des alertes d'échec de tâche | `alerte@aqoj.test` | adresse réelle |
| `PUBLIC_URL` | Adresse publique du site, pour les liens des e-mails (jamais déduite de l'en-tête Host) | `http://localhost:5173` | `https://<domaine>` |
| `REGISTRATION_REQUIRES_CODE` | `1` : l'inscription exige un code d'invitation ; `0` : elle reste libre. Vide refusé | `0` | `1` |
| `BGG_*_CRON` | Horaires des quatre tâches BGG (heure de Paris) | 1 h, 2 h, 3 h, 4 h | décalés en préproduction |
| `APP_SECRET`, `JWT_SECRET`, `BGG_TOKEN` | Secrets applicatifs | `app/.env.local` | `.env.prod.local` |

### `app/.env.local` — développement uniquement

| Variable | Description |
|---|---|
| `APP_SECRET` | Clé secrète Symfony |
| `JWT_SECRET` | Clé de signature des jetons de session |
| `BGG_TOKEN` | Jeton d'application BoardGameGeek |

---

## Production

Staging et production s'exécutent derrière Traefik (réseau externe `traefik`), sur un serveur préparé selon `README.docker.md`.
L'image de production embarque le build statique du front (`frontend/dist` → `/srv/frontend`) et n'a ni Node ni dépendances de développement ; le worker partage cette image.

```bash
# Construire l'image de production en local pour la vérifier
COMPOSE_FILE=compose.yml TARGET=prod docker compose build php
```

Déploiement : `README.docker.md` (infrastructure) et `outils/` (déploiement, secrets). Les jobs de déploiement du workflow `.github/workflows/qualite.yml` ne s'activent qu'une fois posées les variables de dépôt `DEPLOIEMENT_PROD` (pour `main`) et `DEPLOIEMENT_STAGING` (pour `develop`), à `oui`. Le détail de ce qui se configure dans GitHub : `GITHUB.md`.

---

## Projet

[`CHANGELOG.md`](CHANGELOG.md) · [`CONTRIBUTING.md`](CONTRIBUTING.md) · [`SECURITY.md`](SECURITY.md) · [`LICENSE`](LICENSE)

Qualité : `make qualite` (PHPStan, PHPUnit, ESLint, Vitest) ; la même chaîne tourne dans GitHub Actions.

Les données de jeux viennent de [BoardGameGeek](https://boardgamegeek.com).

Pages légales : l'éditeur, le contact et l'hébergeur s'affichent depuis `frontend/src/legal.js`, lu dans les variables de build
`VITE_PUBLISHER_NAME`, `VITE_CONTACT_EMAIL`, `VITE_HOST_NAME` et `VITE_HOST_ADDRESS`. Tant qu'elles manquent, les pages affichent
« [à renseigner avant la mise en ligne] » : à renseigner avant toute mise en ligne.
