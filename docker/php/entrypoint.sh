#!/bin/sh
set -e

if [ ! -f "composer.json" ]; then
    echo "Erreur : composer.json introuvable. Le volume est-il correctement monté ?"
    exit 1
fi

if [ ! -d "vendor" ]; then
    echo "Installation des dépendances PHP..."
    composer install --no-interaction --prefer-dist --no-progress
fi

echo "Démarrage du serveur Symfony sur :8080..."
exec php -S 0.0.0.0:8080 -t public public/index.php
