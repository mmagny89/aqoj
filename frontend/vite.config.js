import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    hmr: {
      // Le client HMR tourne dans le navigateur via Caddy (port 80).
      // Sans ça, Vite essaie ws://localhost:5173 — non exposé au host.
      clientPort: 80,
    },
  },
})
