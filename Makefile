.PHONY: up down build logs restart shell-php shell-node console test stan lint qualite

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
