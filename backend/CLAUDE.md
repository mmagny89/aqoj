# Règles du back — À quoi on joue

Symfony 8.1, PHP 8.4, Doctrine, PostgreSQL. Pas de Flex : configuration manuelle (`config/bundles.php`, `config/services.yaml`), pas de Twig (API JSON seulement).

## BGG
- `BggApiService` est le seul client : `request()` règle la cadence (5 s en CLI, 1 s en web), les nouvelles tentatives (202, 429, 5xx, réseau ; une seule tentative en web) et lève `BggApiException` (code = statut HTTP).
- Un appel `thing` porte 20 identifiants au plus ; `versions=1` donne le titre français : édition **monolingue** française la plus récente (`canonicalname`), jamais les noms alternatifs.
- `Game.name` = titre affiché (français si connu), `nameOriginal` = titre BGG. La recherche porte sur les deux, sans accents ni casse (`unaccent`).
- `game.last_fetch_attempt_at` date chaque appel, réussi ou non : un jeu absent de la réponse n'est redemandé qu'après 7 jours. `last_synced_at` ne date que les synchros réussies ; l'import CSV le pose aussi, donc ne pas s'en servir pour détecter « jamais enrichi » (utiliser `source = 'bgg_csv'`).
- File `pending` : jeux possédés d'abord, puis rang BGG. `resync` : périmés seulement (30 jours si classés, 90 sinon).
- Les tâches BGG (`enrich-pending`, `sync-new`) prennent `BggJobLock` (verrou consultatif PostgreSQL) ; un second passage est sauté. Les lots sont enregistrés un à un : une tâche interrompue reprend. `BggApiException` pendant un lot arrête la tâche (code non nul).
- Dans un `Command`, ne pas nommer une méthode `run()` (déjà définie, publique, dans `Command`).

## API et accès
- Authentification par JWT HS256 (24 h) maison : `AuthController::extractUser()` ; chaque route protégée doit l'appeler. Les routes admin répondent 403 sans administrateur — `tests/Functional/AccessControlTest.php` les liste, y ajouter toute nouvelle route admin.
- Le front est servi sur la même origine ; CORS limité à `CORS_ALLOW_ORIGIN`.

## Tests et analyse
- PHPUnit 13, `APP_ENV=test` forcé par `phpunit.xml.dist`, base `app_test` (suffixe automatique) ; DAMA annule chaque test dans sa transaction.
- En test, `BggApiService` reçoit un `MockHttpClient` vide (`config/services.yaml`) : aucun appel réel à BGG ne part jamais d'un test.
- PHPStan niveau 6 (`phpstan.neon`) ; `missingType.iterableValue` et `missingType.generics` sont ignorés (dette connue), tout le reste doit passer.
- Après modification de la configuration : `php bin/console cache:warmup` en `dev`, car PHPStan lit le conteneur compilé.
