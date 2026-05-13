#!/bin/sh
set -e

if [ ! -f "package.json" ]; then
    echo "Erreur : package.json introuvable. Le volume est-il correctement monté ?"
    exit 1
fi

echo "Installation des dépendances Node.js..."
npm install --no-progress

echo "Démarrage du serveur Vite sur :5173..."
exec npm run dev
