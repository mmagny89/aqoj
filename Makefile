.PHONY: up down build logs restart shell-backend shell-frontend console test stan lint qualite

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose up --build

logs:
	docker compose logs -f

restart:
	docker compose restart

shell-backend:
	docker compose exec backend sh

shell-frontend:
	docker compose exec frontend sh

# Raccourcis Symfony
console:
	docker compose exec backend php bin/console $(cmd)

# Qualité
test:
	docker compose exec -e APP_ENV=test backend vendor/bin/phpunit
	docker compose exec frontend npm test

stan:
	docker compose exec backend vendor/bin/phpstan analyse --no-progress

lint:
	docker compose exec frontend npm run lint

qualite: stan test lint
