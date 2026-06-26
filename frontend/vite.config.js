import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { mkdirSync, cpSync, existsSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

// webmcp.json è un file statico in public/ — copiato da migliorie/webmcp.json
// (descrizioni ricche, parameter descriptions, suggested_flows con descriptions)

/**
 * Copia i file PHP del backend nella cartella dist/ dopo il build.
 * Cerca i file in ../backend/php/ rispetto alla root del frontend.
 */
function copyBackendPlugin() {
  return {
    name: 'copy-backend-php',
    closeBundle() {
      const backendDir = resolve(__dirname, '..', 'backend', 'php')
      const distDir = resolve(__dirname, 'dist')

      const toCopy = [
        { src: 'api/index.php', dest: 'api/index.php' },
        { src: 'mcp/index.php', dest: 'mcp/index.php' },
        { src: 'inc/tariff_loader.php', dest: 'inc/tariff_loader.php' },
        { src: 'inc/bill_parser.php', dest: 'inc/bill_parser.php' },
        { src: 'inc/subscription_handler.php', dest: 'inc/subscription_handler.php' },
        { src: 'inc/llm_logger.php', dest: 'inc/llm_logger.php' },
        { src: 'inc/api_auth.php', dest: 'inc/api_auth.php' },
        { src: 'inc/db_mysql.php', dest: 'inc/db_mysql.php' },
        { src: 'inc/auth.php', dest: 'inc/auth.php' },
        { src: 'inc/arera_sync.php', dest: 'inc/arera_sync.php' },
        { src: 'risorse.php', dest: 'risorse.php' },
        { src: 'router.php', dest: 'router.php' },
      ]

      // Crea directory data/offerte per ARERA sync
      const dataOfferteDir = resolve(distDir, 'data', 'offerte')
      try { mkdirSync(dataOfferteDir, { recursive: true }) } catch {}

      // Copia risorse SEO
      const resourcesDir = resolve(__dirname, '..', 'backend', 'php', 'resources')
      const resourcesDest = resolve(distDir, 'resources')
      if (existsSync(resourcesDir)) {
        try { mkdirSync(resourcesDest, { recursive: true }) } catch {}
        const resourceFiles = ['index.php', 'bolletta-luce.php', 'bolletta-gas.php', 'glossario.php', 'fisso-indicizzato.php', 'calcolo.php', 'come-leggere.php', '_header.php', '_footer.php']
        for (const f of resourceFiles) {
          const src = resolve(resourcesDir, f)
          const dest = resolve(resourcesDest, f)
          if (existsSync(src)) { cpSync(src, dest); console.log(`  ✅ Copiato resources/${f} → dist/resources/${f}`) }
        }
      }

      // Copia .env da frontend/ (NON da public/ — così non viene auto-copiato da Vite)
      // Cerca in frontend/ (default), poi fallback a public/ per retrocompatibilità
      const envSource = resolve(__dirname, '.env')
      const envFallback = resolve(__dirname, 'public', '.env')
      const envDest = resolve(distDir, '.env')
      if (existsSync(envSource)) {
        cpSync(envSource, envDest)
        console.log('  ✅ Copiato .env → dist/.env')
      } else if (existsSync(envFallback)) {
        cpSync(envFallback, envDest)
        console.log('  ✅ Copiato public/.env → dist/.env (fallback)')
      } else {
        console.warn('  ⚠️  .env non trovato — il backend PHP non funzionerà senza variabili d\'ambiente')
      }

      for (const { src, dest } of toCopy) {
        const srcPath = resolve(backendDir, src)
        const destPath = resolve(distDir, dest)
        if (existsSync(srcPath)) {
          try { mkdirSync(dirname(destPath), { recursive: true }) } catch {}
          cpSync(srcPath, destPath)
          console.log(`  ✅ Copiato ${src} → dist/${dest}`)
        } else {
          console.warn(`  ⚠️  File backend non trovato: ${src}`)
        }
      }
      console.log('  📦 Backend PHP copiato in dist/')
    }
  }
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss(), copyBackendPlugin()],
  server: {
    proxy: {
      // Proxy per sviluppo: chiamate /api/* al backend PHP
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/proxy': {
        target: process.env.VITE_PROXY_TARGET || 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/proxy/, ''),
        secure: true,
      },
    },
  },
})
