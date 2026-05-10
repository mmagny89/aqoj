.PHONY: up down build logs restart shell-backend shell-frontend

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
