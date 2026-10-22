/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Contrat de build (headless-react.md, section 2) : sortie dans dist/, copiée
// dans /srv/frontend de l'image PHP. Même origine : l'API s'appelle en relatif.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  build: {
    outDir: 'dist',
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.js'],
    css: false,
  },
  server: {
    // 0.0.0.0 : l'écoute par défaut sur localhost ne sort pas du conteneur.
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Sans port explicite, le websocket de rechargement à chaud échoue en
    // silence alors que la page s'affiche.
    hmr: { clientPort: Number(process.env.NODE_PORT ?? 5173) },
    // Bind mount macOS : les événements du système de fichiers ne remontent
    // pas toujours. Désactivé par défaut.
    watch: { usePolling: process.env.VITE_USE_POLLING === '1' },
    // Le serveur de dev proxifie ce que Caddy sert par PHP, via le nom de
    // service Docker. Caddy ne sert en dev que le site `localhost` : sans SNI
    // ni Host à cette valeur, la poignée TLS échoue (alerte 80, 502).
    proxy: Object.fromEntries(
      ['/api', '/_profiler', '/_wdt', '/bundles'].map((prefix) => [
        prefix,
        {
          target: { protocol: 'https:', host: 'php', port: 443, servername: 'localhost' },
          headers: { host: 'localhost' },
          secure: false,
        },
      ]),
    ),
  },
})
