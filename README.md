# A quoi on joue

Plateforme de découverte et recommandation de jeux de société.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | Symfony 8 — API JSON (PHP 8.4) |
| Frontend | React 18 + Vite 6 + TailwindCSS v4 |
| Base de données | PostgreSQL 16 |
| Reverse proxy | Caddy 2 (HTTPS automatique en production) |
| Conteneurisation | Docker Compose |

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

Les `.env` contiennent les clés (commités, valeurs vides).  
Les `.env.local` contiennent les valeurs réelles (non commités).

**Infrastructure Docker** (à la racine) :

```bash
cp .env .env.local
```

Puis éditer `.env.local` :

```dotenv
COMPOSE_PROJECT_NAME=aqoj

POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=app          # choisir un mot de passe en prod

SERVER_NAME=http://:80         # dev — remplacer par le domaine en prod
CADDY_EMAIL=                   # email pour Let's Encrypt (prod uniquement)
```

**Application Symfony** (dans `backend/`) :

```bash
cp backend/.env backend/.env.local
```

Puis éditer `backend/.env.local` :

```dotenv
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=changeme            # générer un vrai secret (voir ci-dessous)
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

> `DATABASE_URL` est injecté automatiquement par Docker Compose — ne pas le définir dans `backend/.env.local`.

### 3. Lancer la stack Docker

Premier démarrage (build des images + installation des dépendances) :

```bash
docker compose up --build
```

Les dépendances PHP (`vendor/`) et Node.js (`node_modules/`) sont installées automatiquement au premier lancement.

---

## URLs

| Service | URL |
|---|---|
| Frontend | http://localhost |
| API Symfony | http://localhost/api/health |

> En production, remplacer `SERVER_NAME` par le domaine réel — Caddy gère le HTTPS et la redirection HTTP → HTTPS automatiquement.

---

## Développement

### Live reload

| Modification | Comportement |
|---|---|
| Fichier React / CSS | Hot Module Replacement instantané (Vite HMR) |
| Fichier PHP / Symfony | Pris en compte à la prochaine requête, sans redémarrage |
| `composer.json` ou `package.json` | Rebuild requis (`docker compose up --build`) |
| `Dockerfile` | Rebuild requis |

### Commandes utiles

```bash
# Démarrer en arrière-plan
make up
# ou
docker compose up -d

# Arrêter
make down

# Rebuild complet (après changement de dépendances)
make build

# Suivre les logs en temps réel
make logs

# Shell dans le conteneur backend (PHP)
make shell-backend

# Shell dans le conteneur frontend (Node)
make shell-frontend

# Commande Symfony (ex: make console cmd="cache:clear")
make console cmd="<commande>"

# Commande Symfony directe
docker compose exec backend php bin/console <commande>
```

### Générer un APP_SECRET

```bash
docker compose exec backend php -r "echo bin2hex(random_bytes(32));"
```

---

## Structure du projet

```
aqoj/
├── compose.yml                  # Orchestration Docker
├── .env                         # Clés Docker (commité, valeurs vides)
├── .env.local                   # Valeurs Docker (non commité)
├── Makefile                     # Raccourcis
│
├── docker/
│   ├── php/
│   │   ├── Dockerfile           # PHP 8.4 CLI + pdo_pgsql + Composer
│   │   └── entrypoint.sh        # Installe vendor/ si absent, lance php -S
│   ├── node/
│   │   ├── Dockerfile           # Node 20 Alpine
│   │   └── entrypoint.sh        # Installe node_modules/ si absent, lance Vite
│   └── caddy/
│       └── Caddyfile            # Proxy /api → Symfony, / → Vite
│
├── backend/                     # Symfony 8 — API uniquement
│   ├── .env                     # Clés Symfony (commité, valeurs vides)
│   ├── .env.local               # Valeurs Symfony (non commité)
│   ├── composer.json
│   ├── public/index.php         # Point d'entrée
│   ├── src/
│   │   ├── Kernel.php
│   │   └── Controller/
│   │       └── HealthController.php
│   └── config/
│       ├── bundles.php
│       ├── routes.yaml
│       ├── services.yaml
│       └── packages/
│           ├── framework.yaml
│           └── cors.yaml
│
└── frontend/                    # React 18 + Vite + Tailwind v4
    ├── package.json
    ├── vite.config.js
    ├── index.html
    └── src/
        ├── main.jsx
        ├── App.jsx
        └── index.css
```

---

## Variables d'environnement

### `.env.local` (racine) — Infrastructure Docker

| Variable | Description | Exemple dev | Exemple prod |
|---|---|---|---|
| `COMPOSE_PROJECT_NAME` | Préfixe des conteneurs Docker | `aqoj` | `aqoj` |
| `POSTGRES_DB` | Nom de la base de données | `app` | `aqoj_prod` |
| `POSTGRES_USER` | Utilisateur PostgreSQL | `app` | `aqoj` |
| `POSTGRES_PASSWORD` | Mot de passe PostgreSQL | `app` | `s3cr3t_fort` |
| `SERVER_NAME` | Adresse du site Caddy | `http://:80` | `mondomaine.fr` |
| `CADDY_EMAIL` | Email alertes Let's Encrypt | *(vide)* | `admin@mondomaine.fr` |

### `backend/.env.local` — Application Symfony

| Variable | Description | Dev | Prod |
|---|---|---|---|
| `APP_ENV` | Environnement Symfony | `dev` | `prod` |
| `APP_DEBUG` | Mode debug | `true` | `false` |
| `APP_SECRET` | Clé secrète Symfony | *(à générer)* | *(à générer)* |
| `CORS_ALLOW_ORIGIN` | Origines CORS autorisées (regex) | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | `^https://mondomaine\.fr$` |

> `DATABASE_URL` est construit automatiquement par Docker Compose depuis les `POSTGRES_*` — ne pas le définir dans `backend/.env.local`.

---

## Production

Pour déployer, deux changements suffisent dans `.env.local` :

```dotenv
SERVER_NAME=mondomaine.fr          # Caddy obtient le cert Let's Encrypt auto
CADDY_EMAIL=admin@mondomaine.fr    # Pour les alertes d'expiration cert
POSTGRES_PASSWORD=motdepasse_fort
```

Et dans `backend/.env.local` :

```dotenv
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=<secret_généré>
CORS_ALLOW_ORIGIN='^https://mondomaine\.fr$'
```

Puis :

```bash
docker compose up --build -d
```

Caddy gère automatiquement :
- Obtention du certificat TLS via Let's Encrypt
- Redirection HTTP → HTTPS
- Renouvellement du certificat

> **Prérequis prod** : les ports 80 et 443 doivent être accessibles depuis internet et le DNS du domaine doit pointer vers l'IP du serveur avant le premier `docker compose up`.
