# À quoi on joue

Plateforme de découverte et de recommandation de jeux de société, alimentée par BoardGameGeek (BGG). Dépôt `mmagny89/aqoj`, API Symfony + front React en headless, même origine.
- Résumés chargés selon le dossier : `app/CLAUDE.md` (back), `frontend/CLAUDE.md` (front). Fonctionnalités et procédures d'exploitation (tâches planifiées, import CSV) : `README.md` ; infrastructure : `README.docker.md`.

## État
- **Local seulement** : ni préproduction ni production en service. Les fichiers `compose.staging.yml`, `compose.prod.yml` et `outils/` sont prêts ; il reste à préparer le serveur (Traefik, secrets) puis à poser les variables de dépôt `DEPLOIEMENT_PROD` et `DEPLOIEMENT_STAGING` (valeur `oui`) pour activer les déploiements du workflow.
- Symfony 8.1 (API en contrôleurs, Doctrine, PostgreSQL 18) sur FrankenPHP en mode worker ; React 18 + Vite + Tailwind v4 en headless, même origine.

## Infrastructure — règle qui fait autorité
- `.claude/rules/stack-conventions.md` prime pour Docker, `.env`, nommage, ports et déploiement. Ne pas la reformuler ici. `.claude/` est ignoré par git (copie de `../claude/subagent/`).
- Modifier le stack : agents `symfony-docker` (socle) et `stack-env-config` (overrides, `.dist`, CI). Régénérer : `.claude/scripts/setup-symfony.sh --shared-server --headless --same-origin` — à lancer dans un dossier vide nommé `aqoj` puis à fusionner à la main : le projet a ajouté un service `worker` (planificateur BGG), des variables `BGG_*_CRON`, `ALERT_EMAIL`, `JWT_SECRET`, `BGG_TOKEN` et un job `frontend` dans le workflow.
- Un défaut dans un fichier issu de `.claude/scripts` ou `.claude/resources` se corrige aussi à la source, dans le dépôt voisin `../claude` (`subagent/`).
- Aucun code applicatif à la racine : PHP dans `app/`, React dans `frontend/`.

## Commandes
Toujours par la clé de service (`php`, `node`, `worker`, `database`), jamais par le nom de conteneur (`aqoj-dev-php`).
- Démarrer : `docker compose --profile frontend up -d --wait` (`make up`) ; sans `--profile frontend` : API seule.
- Console : `docker compose exec php php bin/console <commande>` (`make console cmd="…"`).
- Qualité : `make qualite` = `make stan` (PHPStan niveau 6) + `make test` (PHPUnit en `APP_ENV=test` puis Vitest) + `make lint` (ESLint).
- Paquet PHP : `docker compose exec php composer require <paquet>` (pas de Flex : aucune recette ne s'applique, `config/bundles.php` et la configuration s'écrivent à la main) ; paquet JS : `docker compose --profile frontend exec node npm install <paquet>`.
- Migration : `doctrine:migrations:migrate` ; les migrations sont du SQL écrit à la main (`IF NOT EXISTS`) et `doctrine:schema:validate` signale un écart ancien (colonne `user_game.bgg_user_rating` hors entité, identifiants, index) : ne pas « corriger » en lançant `schema:update`, qui supprimerait des colonnes en service.
- Base de test : `docker compose exec -e APP_ENV=test php sh -c 'php bin/console doctrine:database:create --if-not-exists && php bin/console doctrine:migrations:migrate -n'`.
- BGG : `app:bgg:enrich-pending --mode=pending|resync --limit=N`, `app:bgg:sync-new`, `app:import:catalogue <fichier> --source=bgg` (import CSV ; `app:bgg:import-csv` en est le dépôt ZIP planifié) ; planifiés par le `worker` (`debug:scheduler`, `docker compose logs -f worker`, et `docker compose restart worker` après toute modification de `MainSchedule` ou d'une commande planifiée).
- Contrôles du stack : `.claude/scripts/check-stack.sh` (statique), `.claude/scripts/check-gouvernance.sh`, `.claude/scripts/verify-stack.sh symfony` (réel).
- Build de prod local : `COMPOSE_FILE=compose.yml TARGET=prod docker compose build php` (jamais via `compose.dev.yml`).
- Jamais `docker compose down --volumes` sur un environnement portant des données.

URLs de dev : front `http://localhost:5173` (Vite, proxifie `/api`), API `https://localhost/api` (certificat local), e-mails Mailpit `http://localhost:8025`.

## Conventions
- PHP/Symfony : skills `symfony-coding-standards`, `phpstan-analysis`, `phpunit-testing-standards` ; React : `react-jsx-coding-standards`, `react-testing-standards`, `tailwind-css-standards` ; accessibilité `web-accessibility-a11y` ; sécurité `web-security-checklist` ; CI `ci-pipeline-standards` (`.github/workflows/qualite.yml`) ; schéma `database-standards`.
- Commits : `git-commit-conventions` — Conventional Commits en français, aucune mention d'IA (ni co-auteur, ni pied de page généré), y compris dans les PR.
- Toute nouvelle règle métier : son résumé dans le `CLAUDE.md` du dossier concerné.

## Branches
- `develop` reçoit le travail ; `main` ne reçoit que du validé. Une branche par tâche : `feat/*`, `fix/*`, `docs/*`, `refactor/*`, `chore/*`.
- Ne jamais pousser `develop`, `main` ni une branche : donner la commande, l'utilisatrice pousse.

## Invariants
- **Aucun appel à BGG pendant la requête d'un joueur**, sauf trois exceptions bornées : l'import de sa collection, le premier enrichissement d'une fiche encore vide (un essai par jour et par jeu), le repli de la recherche textuelle (mis en cache 24 h). Tout le reste passe par le planificateur.
- Seul `App\Service\BggApiService` appelle BGG (cadence 5 s en CLI, 20 identifiants au plus par appel) ; les tâches BGG prennent `BggJobLock`.
- Images des jeux servies par le CDN de BGG, jamais copiées ; mention « Powered by BGG » avec lien retour obligatoire (pied de page).
- Mode worker FrankenPHP : aucune propriété de service ne porte l'état d'une requête (hors le cache de lecture de `MechanicFamilyResolver`, invalidé par l'administration, et la cadence de `BggApiService`).

## Pièges d'environnement
- Caddy (dans l'image PHP) ne route vers PHP que `/api`, `/_profiler`, `/_wdt`, `/bundles/` : une route Symfony hors de ces préfixes s'ajoute dans `docker/php/Caddyfile` **et** `frontend/vite.config.js`.
- Après modification de `docker/php/**` : `docker compose up -d --build --wait`.
- Un changement de version majeure de PostgreSQL ne relit pas l'ancien volume : sauvegarder (`pg_dump -Fc`), recréer le volume, restaurer.
- Un volume Caddy créé par une autre image (propriétaire root) fait échouer l'émission du certificat local (« permission denied » sur `/data/caddy`) : supprimer les volumes `*-caddy-data` et `*-caddy-config`.
- Dev : `MAILER_DSN` pointe sur Mailpit ; vérifier là qu'un e-mail arrive.
