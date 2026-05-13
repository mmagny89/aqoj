#!/bin/sh
set -e

echo "Installing PHP dependencies..."
composer install --no-interaction --no-progress --prefer-dist

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null || true

echo "Starting PHP server on :8080..."
exec php -S 0.0.0.0:8080 -t public/
