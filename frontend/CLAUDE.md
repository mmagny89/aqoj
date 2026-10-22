# Règles du front — À quoi on joue

React 18, Vite 6, Tailwind v4 (plugin `@tailwindcss/vite`, configuration dans le CSS), React Router 6. Servi par Vite en dev derrière Caddy ; l'API est appelée en relatif (`/api`).

- Appels API : `src/api.js` uniquement (jeton `aqoj_token` dans `localStorage`, redirection vers `/connexion` sur 401).
- Lint : `npm run lint` ; `react-hooks/set-state-in-effect` est en avertissement (chargements lancés depuis un effet : à refondre page par page), tout le reste doit passer.
- Tests : Vitest + Testing Library (`npm test`), requêtes par rôle ou texte visible plutôt que par classe CSS ; `src/test/setup.js` nettoie le DOM et le `localStorage`.
- Aucune ressource externe chargée par l'application, hors images des jeux (CDN `*.geekdo-images.com`, autorisé par la CSP de Caddy).
- Les pages affichent des données déjà calculées par l'API : pas de logique de recommandation ou de rareté côté React.
