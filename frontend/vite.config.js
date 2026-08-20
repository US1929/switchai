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
        { src: 'inc/llm_logger.php', dest: 'inc/llm_logger.php' },
        { src: 'inc/api_auth.php', dest: 'inc/api_auth.php' },
        { src: 'inc/db_mysql.php', dest: 'inc/db_mysql.php' },
        { src: 'inc/auth.php', dest: 'inc/auth.php' },
        { src: 'inc/arera_sync.php', dest: 'inc/arera_sync.php' },
        { src: 'risorse.php', dest: 'risorse.php' },
        { src: 'router.php', dest: 'router.php' },
        { src: 'cron_arera.php', dest: 'cron_arera.php' },
      ]

      // Crea directory data/offerte per ARERA sync e copia i JSON delle offerte
      // (db-offerte-luce.json, db-offerte-gas.json, wattene_snapshot.json per il test di confronto)
      const dataOfferteDir = resolve(distDir, 'data', 'offerte')
      try { mkdirSync(dataOfferteDir, { recursive: true }) } catch {}
      const offerteSrcDir = resolve(backendDir, 'data', 'offerte')
      if (existsSync(offerteSrcDir)) {
        const offerteFiles = ['db-offerte-luce.json', 'db-offerte-gas.json', 'fornitori-enrichment.json', 'wattene_snapshot.json', 'config.json']
        for (const f of offerteFiles) {
          const src = resolve(offerteSrcDir, f)
          if (existsSync(src)) {
            cpSync(src, resolve(dataOfferteDir, f))
            console.log(`  ✅ Copiato data/offerte/${f} → dist/data/offerte/${f}`)
          } else {
            console.warn(`  ⚠️  File dati offerte non trovato: data/offerte/${f}`)
          }
        }
      } else {
        console.warn('  ⚠️  Directory backend/php/data/offerte non trovata — il backend non avrà offerte disponibili')
      }

      // Copia market_history.json da frontend/data/ (se presente) per storico trend mercato
      const marketHistorySrc = resolve(__dirname, 'data', 'market_history.json')
      if (existsSync(marketHistorySrc)) {
        const dataDir = resolve(distDir, 'data')
        try { mkdirSync(dataDir, { recursive: true }) } catch {}
        cpSync(marketHistorySrc, resolve(dataDir, 'market_history.json'))
        console.log('  ✅ Copiato data/market_history.json → dist/data/market_history.json')
      }

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
