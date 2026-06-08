# À quoi on joue

Plateforme de découverte et recommandation de jeux de société, connectée à BoardGameGeek.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | Symfony 8 — API JSON (PHP 8.4) |
| Frontend | React 18 + Vite 6 + TailwindCSS v4 |
| Base de données | PostgreSQL 16 |
| Reverse proxy | Caddy 2 (HTTPS automatique en production) |
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

---

## Tâches CRON

Le conteneur `cron` exécute quatre tâches automatiques chaque nuit.  
Les logs sont accessibles dans les fichiers `/tmp/bgg-*.log` du conteneur.

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
Importe le catalogue complet depuis un fichier ZIP déposé dans `backend/var/imports/bgg/`.  
Ne fait rien si aucun fichier n'est présent.

### Consulter les logs cron

```bash
# Logs du resync incrémental
docker compose exec cron tail -50 /tmp/bgg-resync.log

# Logs des nouveautés
docker compose exec cron tail -50 /tmp/bgg-new.log

# Logs du premier enrichissement
docker compose exec cron tail -50 /tmp/bgg-enrich.log

# Logs de l'import CSV
docker compose exec cron tail -50 /tmp/bgg-import-csv.log
```

---

## Mettre à jour le catalogue depuis BGG (procédure ZIP)

BGG publie périodiquement une archive complète du catalogue au format CSV.  
Voici la procédure complète pour intégrer une mise à jour.

### 1. Déposer le fichier ZIP

Copier le fichier ZIP téléchargé depuis BGG dans le dossier d'imports :

```bash
cp ~/Téléchargements/bgg_export_*.zip backend/var/imports/bgg/
# ou directement dans le conteneur :
docker compose cp ~/Téléchargements/bgg_export_*.zip backend:/var/www/html/var/imports/bgg/
```

> Le ZIP doit contenir le fichier `boardgames_ranks.csv`. Les anciennes archives sont déplacées automatiquement dans `var/imports/bgg/processed/` après traitement.

### 2. Lancer l'import CSV (jeux de base + extensions)

```bash
# Importer TOUS les jeux (jeux de base et extensions)
docker compose exec backend php bin/console app:bgg:import-csv --include-expansions

# Importer uniquement les jeux de base (sans extensions)
docker compose exec backend php bin/console app:bgg:import-csv
```

L'import crée des entrées légères (`source=bgg_csv`) : nom, rang BGG, note, flag extension.  
Les données complètes (mécaniques, description, image…) sont récupérées à l'étape suivante.

### 3. Enrichir les nouveaux jeux via l'API BGG

```bash
# Enrichir les jeux sans données complètes (nouveaux imports CSV)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=pending --limit=0
```

> ⚠️ BGG limite les requêtes à ~2/sec. Avec plusieurs milliers de jeux, cette étape peut prendre **plusieurs heures**. Il est préférable de la lancer en arrière-plan ou de laisser le cron nocturne s'en charger progressivement (300 jeux/nuit).

```bash
# Lancer en arrière-plan
docker compose exec -d backend php bin/console app:bgg:enrich-pending --mode=pending --limit=0

# Surveiller la progression
docker compose exec cron tail -f /tmp/bgg-enrich.log
```

### 4. Relier les extensions à leurs jeux de base

```bash
# Lier les extensions qui n'ont pas encore de jeu de base renseigné
docker compose exec backend php bin/console app:bgg:relink-expansions

# Forcer le relinkage de TOUTES les extensions (même celles déjà liées)
docker compose exec backend php bin/console app:bgg:relink-expansions --all
```

### 5. Vérification

```bash
# Nombre de jeux en base
docker compose exec database psql -U app -d app -c "
  SELECT
    CASE WHEN is_expansion THEN 'Extensions' ELSE 'Jeux de base' END AS type,
    COUNT(*) AS total,
    COUNT(*) FILTER (WHERE source = 'bgg_csv')  AS stubs_csv,
    COUNT(*) FILTER (WHERE source = 'bgg_api')  AS enrichis_api
  FROM game
  GROUP BY is_expansion;"

# Extensions sans lien vers leur jeu de base
docker compose exec database psql -U app -d app -c "
  SELECT COUNT(*) FROM game
  WHERE is_expansion = true AND implements_bgg_ids::text = '[]';"
```

---

## Commandes CLI

Toutes les commandes s'exécutent dans le conteneur `backend` :

```bash
docker compose exec backend php bin/console <commande> [options]
```

---

### `app:bgg:import-csv` — Import catalogue CSV

Importe le catalogue complet BGG depuis un ZIP déposé dans `backend/var/imports/bgg/`.  
Le ZIP doit contenir le fichier `boardgames_ranks.csv` exporté depuis BGG.

**Options**

| Option | Description |
|---|---|
| `--include-expansions` | Importer aussi les extensions (par défaut : ignorées) |
| `--dry-run` | Simuler sans écrire en base |

```bash
# Import jeux de base uniquement
docker compose exec backend php bin/console app:bgg:import-csv

# Import jeux de base + extensions
docker compose exec backend php bin/console app:bgg:import-csv --include-expansions

# Vérifier le fichier sans importer
docker compose exec backend php bin/console app:bgg:import-csv --dry-run
```

---

### `app:bgg:enrich-pending` — Enrichissement / resync BGG

Enrichit ou resynchronise les jeux depuis l'API BGG, par lots de 20.  
En cas d'erreur sur un lot, il est ignoré et la commande continue.

**Options**

| Option | Description | Défaut |
|---|---|---|
| `--mode` | `pending` (CSV → API) ou `resync` (refresh des jeux existants) | `pending` |
| `--limit` | Nombre maximum de jeux à traiter (0 = tous) | `300` |
| `--sleep` | Pause en millisecondes entre chaque lot de 20 (min. recommandé : 2000) | `2000` |

```bash
# Enrichir les 50 jeux CSV les plus populaires
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=pending --limit=50

# Enrichir tout (peut prendre des heures avec un grand catalogue)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=pending --limit=0

# Resync des 300 jeux les plus anciens (équivalent tâche nuit)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=resync --limit=300

# Resync de tous les jeux enrichis
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=resync --limit=0
```

---

### `app:bgg:relink-expansions` — Liaison extensions ↔ jeux de base

Re-fetche les extensions depuis BGG pour renseigner leur champ `implements_bgg_ids` (lien vers le jeu parent).  
À relancer après un import CSV d'extensions ou si des fiches d'extension n'affichent pas leur jeu de base.

**Options**

| Option | Description |
|---|---|
| `--all` | Re-fetche toutes les extensions, même celles déjà liées |
| `--limit` | Nombre max d'extensions à traiter | `500` |

```bash
# Relier uniquement les extensions sans lien (cas nominal après import)
docker compose exec backend php bin/console app:bgg:relink-expansions

# Forcer le relinkage de toutes les extensions
docker compose exec backend php bin/console app:bgg:relink-expansions --all

# Traiter seulement les 100 premières (débogage)
docker compose exec backend php bin/console app:bgg:relink-expansions --limit=100
```

---

### `app:bgg:sync-new` — Nouveautés BGG

Importe les jeux absents de la base détectés via :
- La **BGG Hot List** (50 jeux tendance du moment)
- Une recherche sur les jeux publiés **cette année**

Ne met pas à jour les jeux déjà présents (c'est le rôle d'`enrich-pending`).

```bash
docker compose exec backend php bin/console app:bgg:sync-new
```

---

### `app:recompute-mechanic-families` — Recalcul des familles

Recalcule les colonnes `mechanic_families` et `detected_engines` pour **tous les jeux** en base, à partir des mappings actifs en DB.  
À relancer après avoir modifié des mappings dans l'interface d'administration.

```bash
docker compose exec backend php bin/console app:recompute-mechanic-families
```

---

### `app:games:seed` — Jeux de départ

Importe une sélection de jeux populaires pour peupler rapidement une base vide.

```bash
docker compose exec backend php bin/console app:games:seed
```

---

### Migrations Doctrine

```bash
# Voir le statut des migrations
docker compose exec backend php bin/console doctrine:migrations:status

# Appliquer toutes les migrations en attente
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# Générer une nouvelle migration après modification d'une entité
docker compose exec backend php bin/console doctrine:migrations:diff
```

---

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) >= 4.x
- `make` (optionnel — pour les raccourcis)

---

## Installation

### 1. Cloner le dépôt

```bash
git clone <url-du-repo> aqoj
cd aqoj
```

### 2. Configurer les variables d'environnement

**Infrastructure Docker** (à la racine) :

```bash
cp .env .env.local
```

Éditer `.env.local` :

```dotenv
COMPOSE_PROJECT_NAME=aqoj

POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=app          # choisir un mot de passe fort en prod

SERVER_NAME=http://:80         # dev — remplacer par le domaine en prod
CADDY_EMAIL=                   # email pour Let's Encrypt (prod uniquement)
```

**Application Symfony** (dans `backend/`) :

```bash
cp backend/.env backend/.env.local
```

Éditer `backend/.env.local` :

```dotenv
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=changeme            # générer avec : docker compose exec backend php -r "echo bin2hex(random_bytes(32));"
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

> `DATABASE_URL` est injecté automatiquement par Docker Compose — ne pas le définir dans `backend/.env.local`.

### 3. Lancer la stack

```bash
docker compose up --build
```

Les dépendances PHP (`vendor/`) et Node.js (`node_modules/`) s'installent automatiquement au premier lancement.

### 4. Peupler la base

```bash
# Option A — quelques jeux populaires pour démarrer vite
docker compose exec backend php bin/console app:games:seed

# Option B — catalogue complet depuis un ZIP BGG (voir section "Mettre à jour le catalogue")
docker compose exec backend php bin/console app:bgg:import-csv --include-expansions
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=pending --limit=200
docker compose exec backend php bin/console app:bgg:relink-expansions
```

---

## URLs

| Service | URL |
|---|---|
| Frontend | http://localhost |
| API Symfony | http://localhost/api/health |

---

## Développement

### Live reload

| Modification | Comportement |
|---|---|
| Fichier React / CSS | Hot Module Replacement instantané (Vite HMR) |
| Fichier PHP / Symfony | Pris en compte à la prochaine requête, sans redémarrage |
| `composer.json` ou `package.json` | Rebuild requis (`docker compose up --build`) |
| `Dockerfile` | Rebuild requis |

### Raccourcis Make

```bash
make up              # Démarrer en arrière-plan
make down            # Arrêter
make build           # Rebuild complet
make logs            # Logs en temps réel
make shell-backend   # Shell PHP
make shell-frontend  # Shell Node
make console cmd="cache:clear"   # Commande Symfony
```

---

## Structure du projet

```
aqoj/
├── compose.yml                     # Orchestration Docker
├── .env / .env.local               # Config Docker
├── Makefile
│
├── docker/
│   ├── php/Dockerfile              # PHP 8.4 + extensions
│   ├── node/Dockerfile             # Node 20
│   ├── caddy/Caddyfile             # Proxy /api → Symfony, / → Vite
│   └── cron/crontab                # Tâches planifiées
│
├── backend/                        # Symfony 8 — API JSON
│   ├── src/
│   │   ├── Command/                # Commandes CLI
│   │   │   ├── ImportBggCsvCommand.php
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
│   │   └── Service/                # Logique métier
│   │       ├── BggApiService.php         # Appels API BGG
│   │       ├── BggCsvImportService.php   # Import CSV
│   │       ├── GameEnrichmentService.php # Hydratation des entités
│   │       ├── MechanicFamilyResolver.php
│   │       ├── RecommendationService.php
│   │       └── UserPreferenceService.php
│   └── migrations/                 # Migrations Doctrine
│
└── frontend/                       # React 18 + Vite + Tailwind v4
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

### `.env.local` (racine) — Infrastructure Docker

| Variable | Description | Dev | Prod |
|---|---|---|---|
| `COMPOSE_PROJECT_NAME` | Préfixe des conteneurs Docker | `aqoj` | `aqoj` |
| `POSTGRES_DB` | Nom de la base | `app` | `aqoj_prod` |
| `POSTGRES_USER` | Utilisateur PostgreSQL | `app` | `aqoj` |
| `POSTGRES_PASSWORD` | Mot de passe PostgreSQL | `app` | `s3cr3t_fort` |
| `SERVER_NAME` | Adresse Caddy | `http://:80` | `mondomaine.fr` |
| `CADDY_EMAIL` | Email alertes Let's Encrypt | *(vide)* | `admin@mondomaine.fr` |

### `backend/.env.local` — Application Symfony

| Variable | Description | Dev | Prod |
|---|---|---|---|
| `APP_ENV` | Environnement | `dev` | `prod` |
| `APP_DEBUG` | Mode debug | `true` | `false` |
| `APP_SECRET` | Clé secrète | *(à générer)* | *(à générer)* |
| `CORS_ALLOW_ORIGIN` | Origines CORS autorisées (regex) | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | `^https://mondomaine\.fr$` |
| `BGG_TOKEN` | Token API BoardGameGeek (optionnel) | *(vide)* | *(token BGG)* |

---

## Production

```dotenv
# .env.local (racine)
SERVER_NAME=mondomaine.fr
CADDY_EMAIL=admin@mondomaine.fr
POSTGRES_PASSWORD=motdepasse_fort

# backend/.env.local
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=<secret_généré>
CORS_ALLOW_ORIGIN='^https://mondomaine\.fr$'
```

```bash
docker compose up --build -d
```

Caddy gère automatiquement le certificat TLS (Let's Encrypt), la redirection HTTP → HTTPS et son renouvellement.

> **Prérequis prod** : ports 80 et 443 ouverts, DNS du domaine pointant vers l'IP du serveur avant le premier démarrage.
