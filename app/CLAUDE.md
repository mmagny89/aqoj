# Règles du back — À quoi on joue

Symfony 8.1, PHP 8.5 (FrankenPHP en mode worker), Doctrine, PostgreSQL 18. Pas de Flex : configuration manuelle (`config/bundles.php`, `config/services.yaml`), pas de Twig (API JSON seulement).

## BGG
- `BggApiService` est le seul client : `request()` règle la cadence (5 s en CLI, 1 s en web), les nouvelles tentatives (202, 429, 5xx, réseau ; une seule tentative en web) et lève `BggApiException` (code = statut HTTP).
- Un appel `thing` porte 20 identifiants au plus ; `versions=1` donne le titre français : édition **monolingue** française la plus récente (`canonicalname`), jamais les noms alternatifs.
- `Game.name` = titre affiché (français si connu), `nameOriginal` = titre BGG. La recherche porte sur les deux, sans accents ni casse (`unaccent`).
- `game.last_fetch_attempt_at` date chaque appel, réussi ou non : un jeu absent de la réponse n'est redemandé qu'après 7 jours. `last_synced_at` ne date que les synchros réussies ; l'import CSV le pose aussi, donc ne pas s'en servir pour détecter « jamais enrichi » (utiliser `source = 'bgg_csv'`).
- File `pending` : jeux possédés d'abord, puis rang BGG. `resync` : périmés seulement (30 jours si classés, 90 sinon).
- Les tâches BGG (`enrich-pending`, `sync-new`) sont planifiées par `App\Scheduler\MainSchedule` (service `worker`, horaires dans `BGG_*_CRON`) ; elles prennent `BggJobLock` (verrou consultatif PostgreSQL) ; un second passage est sauté. Les lots sont enregistrés un à un : une tâche interrompue reprend. `BggApiException` pendant un lot arrête la tâche (code non nul).
- Import du catalogue (même architecture que BG-TCG) : une source = une classe `App\Catalogue\Import\SourceImporterInterface` (`#[AsTaggedItem(index: 'bgg')]`) qui lit un fichier et rend des `GameData`, sans rien écrire ; `CatalogueImporter` rapproche par lots (SQL `ON CONFLICT`, une fiche enrichie par l'API garde sa note) et rend un `ImportReport`. Commande `app:import:catalogue <fichier> --source=bgg [--include-expansions] [--dry-run]` ; `app:bgg:import-csv` n'est que le dépôt ZIP (`var/imports/bgg/`) appelé par le planificateur. Nouvelle source : une classe taguée, aucune autre modification.
- Journal des tâches : `JobRunSubscriber` écrit une ligne `job_run` (SQL direct, `JobRunRecorder`) pour chaque exécution des commandes listées dans `TRACKED`, lancée à la main ou par le worker ; une commande ajoute son bilan par `JobRunRecorder::note([...])` (clé `skipped` = passage sauté, statut `skipped`). Une tâche « en cours » depuis plus de 12 h s'affiche « interrompue » (worker coupé). Lu par `/api/admin/jobs` et `/api/admin/games*` (explorateur ; ses filtres reprennent les règles de `GameRepository::findStaleForResync` et `findPendingEnrichmentIds`), affichés par le tableau de bord `/admin`. Toute nouvelle tâche BGG s'ajoute à `TRACKED`.
- Dans un `Command`, ne pas nommer une méthode `run()` (déjà définie, publique, dans `Command`).

## API et accès
- Authentification par JWT HS256 (24 h), portée par le pare-feu `api` (`security.yaml`, `App\Security\JwtAuthenticator`). Un jeton absent, expiré ou illisible laisse la requête **anonyme** : les routes publiques (catalogue, recherche) répondent toujours.
- `access_control` est le garde-fou central : `^/api/admin` exige `ROLE_ADMIN`, et `^/api/(auth/me|bgg|sessions|user)` exige `ROLE_USER` (401 anonyme, 403 sinon, en JSON). Toute nouvelle route privée entre dans ce fichier — une route sous `/api/admin` est protégée d'office.
- Les contrôleurs gardent leurs propres contrôles (`AuthController::extractUser()`, `isAdmin()`) en seconde ligne et pour les routes à identité facultative (`/api/games*`, qui marquent les jeux possédés).
- `tests/Functional/AccessControlTest.php` liste les routes admin : y ajouter toute nouvelle route admin.
- Limitation : `login` (5 essais par quart d'heure pour une même paire adresse + e-mail), `login_ip` (20 par adresse), `register` (5 par heure et par adresse), réponse 429 avec `Retry-After`. L'adresse vient de `X-Forwarded-For` (`trusted_proxies` = `SYMFONY_TRUSTED_PROXIES`, vide en dev, `private_ranges` derrière Traefik). Stockage dans `cache.rate_limiter` (fichiers) : à déplacer sur un stockage partagé si le back passe à plusieurs instances.
- Catalogue public en lecture seule pour BG-TCG : API Platform 5 sur `Game` (`GET /api/catalogue/games`, `/api/catalogue/games/{bggId}`), seuls les champs du groupe `catalogue:read` sortent. Pagination 100 (500 au plus, `itemsPerPage`, `page`), filtres `name`, `bggType`, `isExpansion`, `yearPublished|complexity|playingTime|minPlayers|maxPlayers|bggRank|usersRated|lastSyncedAt[gte|lte|gt|lt]`, tri `order[<champ>]`. Sert la base telle quelle, jamais d'appel à BGG. Description : `/api/docs.jsonopenapi` ; interface Swagger UI sur `/api/docs` en dev seulement (Twig en require-dev, fichiers publiés par `assets:install`, rejoué par les scripts composer et ignoré par git : `public/bundles/`). Un champ ajouté au groupe est publié : relire `web-security-checklist` avant.
- Le front est servi sur la même origine : pas de CORS.

## Tests et analyse
- PHPUnit 13, `APP_ENV=test` forcé par `phpunit.xml.dist`, base `app_test` (suffixe automatique) ; DAMA annule chaque test dans sa transaction.
- En test, `BggApiService` reçoit un `MockHttpClient` vide (`config/services.yaml`) : aucun appel réel à BGG ne part jamais d'un test.
- PHPStan niveau 6 (`phpstan.neon`) ; `missingType.iterableValue` et `missingType.generics` sont ignorés (dette connue), tout le reste doit passer.
- Après modification de la configuration : `php bin/console cache:warmup` en `dev`, car PHPStan lit le conteneur compilé.
- Alertes : une erreur sur les canaux `jobs` et `messenger` part par e-mail (`ALERT_EMAIL`, dédupliquée une heure) en staging et en production ; `tests/Functional/JobAlertTest.php` le vérifie. Les tâches planifiées sont des `RunCommandMessage` avec `throwOnFailure` : une commande en échec arrive jusque-là.
- Un paquet ajouté pour le planificateur manque vite : `dragonmantank/cron-expression` est requis par les expressions cron de `MainSchedule`.
