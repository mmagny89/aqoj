.PHONY: up down build logs restart shell-php shell-node console test test-only stan lint qualite ci check hooks sauvegarde

# Les commandes passent par la clé de service (php, node, worker, database), jamais par le nom de conteneur.
up:
	docker compose --profile frontend up -d --wait

down:
	docker compose --profile frontend down

build:
	docker compose --profile frontend up -d --build --wait

logs:
	docker compose logs -f

restart:
	docker compose --profile frontend restart

shell-php:
	docker compose exec php bash

shell-node:
	docker compose --profile frontend exec node bash

# Raccourcis Symfony : make console cmd="app:bgg:sync-new"
console:
	docker compose exec php php bin/console $(cmd)

# Qualité
test:
	docker compose exec -e APP_ENV=test php vendor/bin/phpunit
	docker compose --profile frontend exec node npm test

stan:
	docker compose exec php vendor/bin/phpstan analyse --no-progress

lint:
	docker compose --profile frontend exec node npm run lint

qualite: stan test lint

# Un test ou un dossier pendant le développement : make test-only T=tests/Functional/AuthTest.php   ou   F=nomDuTest
test-only:
	docker compose exec -e APP_ENV=test php vendor/bin/phpunit $(if $(T),$(T),) $(if $(F),--filter $(F),)

# Les portes de la CI rejouées en environnement vierge (variables minimales, base jetable).
ci:
	outils/ci-local.sh

# Tout ce que la CI vérifie : portes PHP en environnement vierge, puis le front (audit, lint, tests, build).
check: ci
	docker compose --profile frontend exec node sh -c 'npm audit --omit=dev --audit-level=high && npm run lint && npm test && npm run build'

# Active le hook pre-push (une fois par clone) : il lance `make check` et annule le push s'il échoue.
hooks:
	git config core.hooksPath .githooks

# Sauvegarde de la base de développement, restaurée dans une base jetable pour prouver qu'elle se relit.
sauvegarde:
	outils/sauvegarde-locale.sh
