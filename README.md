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
- Vue en deux onglets :
  - **⭐ Déjà joués** — jeux avec une note BGG, triés par note décroissante
  - **🎁 Pas encore joués** — jeux sans note, triés alphabétiquement
- Infinite scroll sur chaque onglet

### Recommandations personnalisées
- **Ma collection** — recommande parmi les jeux possédés, scorés par note personnelle + fraîcheur + qualité BGG
- **À découvrir** — suggère des jeux hors collection avec un seuil de qualité BGG ≥ 6.5
- Filtres : nombre de joueurs, durée maximale, style de jeu (14 familles)
- Les extensions ne sont jamais suggérées dans les recommandations

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
- **Section Extensions** : liste cliquable des extensions connues avec indicateur de possession (✓ possédée / non possédée), filtrée sur les extensions officielles (≥ 50 votants BGG)

### Recherche
- Recherche textuelle dans le catalogue (jusqu'à 200 résultats)
- Inclut les extensions et packs promo
- Enrichissement automatique depuis BGG pour les jeux absents de la base

### Administration
- Gestion des mappings mécaniques BGG ↔ familles Engelstein (4 onglets : moteurs / support / extras / non mappées)
- Recalcul en masse des familles pour tous les jeux en base
- Visualisation des mécaniques BGG non encore mappées, triées par fréquence

---

## Tâches CRON

Le conteneur `cron` exécute quatre tâches automatiques chaque nuit.  
Les logs sont accessibles dans les fichiers `/tmp/bgg-*.log` du conteneur.

```
┌────── heure
│ ┌──── minute
│ │
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

## Commandes CLI

Toutes les commandes s'exécutent dans le conteneur `backend` :

```bash
docker compose exec backend php bin/console <commande> [options]
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

**Exemples**

```bash
# Resync des 300 jeux les plus anciens (équivalent tâche nuit)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=resync --limit=300

# Resync de tous les jeux (cycle complet — plusieurs heures, à éviter en production)
# BGG rate-limite à ~2 req/sec → préférer la tâche cron quotidienne (300/nuit)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=resync --limit=0 --sleep=2000

# Premier enrichissement des 50 jeux CSV les plus populaires
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=pending --limit=50

# Resync rapide sans pause (usage dev/test uniquement)
docker compose exec backend php bin/console app:bgg:enrich-pending --mode=resync --limit=20 --sleep=0
```

---

### `app:bgg:sync-new` — Nouveautés BGG

Importe les jeux absents de la base détectés via :
- La **BGG Hot List** (50 jeux tendance du moment)
- Une recherche sur les jeux publiés **cette année**

Ne met pas à jour les jeux déjà présents (c'est le rôle d'`enrich-pending`).

```bash
# Lancer manuellement (identique à la tâche de 3h)
docker compose exec backend php bin/console app:bgg:sync-new
```

---

### `app:bgg:import-csv` — Import catalogue CSV

Importe le catalogue complet BGG depuis un ZIP déposé dans `backend/var/imports/bgg/`.  
Le ZIP doit contenir le fichier `boardgames_ranks.csv` exporté depuis BGG.

```bash
# Lancer manuellement
docker compose exec backend php bin/console app:bgg:import-csv

# Avec un fichier spécifique
docker compose exec backend php bin/console app:bgg:import-csv --file=var/imports/bgg/mon-export.zip
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

### 4. Peupler la base (optionnel)

```bash
# Jeux populaires pour démarrer
docker compose exec backend php bin/console app:games:seed

# Ou importer depuis un CSV BGG
docker compose exec backend php bin/console app:bgg:import-csv
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
│   │   ├── Command/                # Commandes CLI (cron + admin)
│   │   ├── Controller/             # Endpoints API
│   │   ├── Entity/                 # Entités Doctrine (Game, User, …)
│   │   ├── Repository/             # Requêtes DB
│   │   └── Service/                # Logique métier (BGG, recommandations, …)
│   └── migrations/                 # Migrations Doctrine
│
└── frontend/                       # React 18 + Vite + Tailwind v4
    └── src/
        ├── pages/                  # Pages (Library, Recommendations, GameDetail, …)
        ├── components/             # Composants réutilisables
        ├── utils/engelstein.js     # Familles de mécaniques + couleurs
        └── api.js                  # Appels API centralisés
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
