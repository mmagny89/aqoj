# À quoi on joue

Plateforme de découverte et de recommandation de jeux de société, alimentée par BoardGameGeek (BGG). Dépôt `mmagny89/aqoj`, API Symfony + front React en headless, même origine.
- Résumés chargés selon le dossier : `backend/CLAUDE.md` (back), `frontend/CLAUDE.md` (front). Fonctionnalités et procédures d'exploitation (cron, import CSV) : `README.md`.

## État
- **Local seulement** : ni préproduction ni production en service. Pas de Dockerfile de production, de `compose.staging.yml` ni de `compose.prod.yml` pour l'instant.
- Symfony 8.1 (API en contrôleurs, Doctrine, PostgreSQL 16, PHP 8.4), React 18 + Vite + Tailwind v4, Caddy en frontal, service `cron` (crond) pour BGG.

## Infrastructure
- `.claude/rules/stack-conventions.md` prime pour Docker, `.env`, nommage, ports et déploiement ; ne pas la reformuler ici. `.claude/` est ignoré par git (copie de `../claude/subagent/`).
- Écart assumé avec ces conventions : clés de service `backend`, `frontend`, `cron`, `caddy`, `database` (pas `php`, `node`, `worker`) et PHP dans `backend/` (pas `app/`).
- Nommage : projet `aqoj-${ENV}` (`ENV=dev` dans `.env`), conteneurs `aqoj-dev-<service>`, volumes `aqoj-dev-database-data`, etc.
- Un défaut dans un fichier issu de `.claude/scripts` ou `.claude/resources` se corrige aussi à la source, dans le dépôt voisin `../claude` (`subagent/`).

## Commandes
Toujours par la clé de service, jamais par le nom de conteneur.
- Démarrer : `docker compose up -d --wait` ; URL de dev `http://localhost` (HTTP seulement : en `https://` le navigateur obtient `ERR_CONNECTION_RESET`).
- Console : `docker compose exec backend php bin/console <commande>`
- Qualité : `make qualite` (PHPStan niveau 6, PHPUnit avec `APP_ENV=test`, ESLint, Vitest) ; séparément `make stan`, `make test`, `make lint`.
- Paquet PHP : `docker compose exec backend composer require <paquet>` ; paquet JS : `docker compose exec frontend npm install <paquet>`
- Migration : `doctrine:migrations:migrate` ; les migrations existantes sont du SQL écrit à la main (`IF NOT EXISTS`), `doctrine:schema:validate` signale un écart ancien, hors des colonnes récentes.
- Base de test : `APP_ENV=test php bin/console doctrine:database:create --if-not-exists` puis `doctrine:migrations:migrate`.
- BGG : `app:bgg:enrich-pending --mode=pending|resync --limit=N`, `app:bgg:sync-new`, `app:bgg:import-csv` (planifiés dans `docker/cron/crontab`).
- `docker compose down` ne suffit pas après un changement de nom de projet : `docker compose -p <ancien nom> down`.
- Jamais `docker compose down --volumes` sur un environnement portant des données.

## Conventions
- PHP/Symfony : skills `symfony-coding-standards`, `phpstan-analysis`, `phpunit-testing-standards` ; React : `react-jsx-coding-standards`, `react-testing-standards`, `tailwind-css-standards` ; accessibilité `web-accessibility-a11y` ; sécurité `web-security-checklist` ; CI `ci-pipeline-standards` (`.github/workflows/qualite.yml`) ; schéma `database-standards`.
- Commits : `git-commit-conventions` — Conventional Commits en français, aucune mention d'IA (ni co-auteur, ni pied de page généré), y compris dans les PR.
- Toute nouvelle règle métier : son résumé dans le `CLAUDE.md` du dossier concerné.

## Branches
- `develop` reçoit le travail ; `main` ne reçoit que du validé. Une branche par tâche : `feat/*`, `fix/*`, `docs/*`, `refactor/*`, `chore/*`.
- Ne jamais pousser `develop`, `main` ni une branche : donner la commande, l'utilisatrice pousse.

## Invariants
- **Aucun appel à BGG pendant la requête d'un joueur**, sauf trois exceptions bornées : l'import de sa collection, le premier enrichissement d'une fiche encore vide (un essai par jour et par jeu), le repli de la recherche textuelle (mis en cache 24 h). Tout le reste passe par le cron.
- Seul `App\Service\BggApiService` appelle BGG (cadence 5 s en CLI, 20 identifiants au plus par appel) ; les tâches BGG prennent `BggJobLock`.
- Images des jeux servies par le CDN de BGG, jamais copiées ; logo « Powered by BGG » avec lien retour obligatoire.

## Pièges d'environnement
- Caddy ne route que `/api/*` vers le back, le reste vers Vite : une route Symfony hors de `/api` ne répond pas.
- Après changement de `docker/php/**` : `docker compose up -d --build --wait`.
- Changement de nom de réseau ou de projet : recréer `database` (`up -d --force-recreate database`) puis redémarrer `backend` et `cron`, sinon « could not translate host name "database" ».
