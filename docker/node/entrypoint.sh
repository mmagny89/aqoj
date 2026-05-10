#!/bin/sh
set -e

if [ ! -f "package.json" ]; then
    echo "Erreur : package.json introuvable. Le volume est-il correctement monté ?"
    exit 1
fi

if [ ! -f "node_modules/.bin/vite" ]; then
    echo "Installation des dépendances Node.js..."
    npm install --no-progress
fi

echo "Démarrage du serveur Vite sur :5173..."
exec npm run dev
