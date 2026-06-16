# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# SwitchAI — AI Agent per Cambio Fornitore Energia (Italia)

## Panoramica
SwitchAI è un AI agent che automatizza il cambio fornitore Luce e Gas nel mercato libero italiano.
- **Dominio**: switchai.it (OVH Pro Web Hosting, PHP 8.5 + Apache, mod_rewrite)
- **Stack**: React 19 + Vite 8 + Tailwind CSS 4 (frontend) | PHP 8.5 API (backend) | Node.js MCP Server
- **Design**: ispirato a Switcho.it / Billoo.it, card allineate a ComparaSemplice
- **Stile**: inline `style` props React (no CSS modules)
- **Dati**: JSON flat-file con `flock()` per locking concorrente, **nessun database SQL**
- **WebMCP**: 4 tool registrati via `navigator.modelContext.registerTool()` (Chrome 146+)
- **MCP Server**: 6 tool per Claude Desktop + endpoint PHP a `/mcp`

## Comandi essenziali

```bash
# Sviluppo locale (servono ENTRAMBI i processi)
cd frontend && npm run dev          # Vite dev server (porta 5173, proxy /api → 8080)
cd frontend/dist && php -S localhost:8080 router.php  # PHP backend (in un altro terminale)

# Build per produzione
cd frontend && npm run build        # Genera dist/ (frontend + PHP copiati automaticamente via copyBackendPlugin)
# Poi caricare TUTTO dist/ su OVH in www/

# Lint
cd frontend && npm run lint         # ESLint

# MCP Server (Claude Desktop)
cd mcp-server && npm install && node index.js
```

## Struttura chiave

```
frontend/dist/        ← BUILD OUTPUT da caricare su OVH (www/)
  api/index.php       ← API Router principale (include endpoint V2 /api/analyze)
  mcp/index.php       ← MCP Server PHP (JSON-RPC via HTTP POST)
  inc/                ← Librerie PHP (tariff_loader, bill_parser, subscription_handler, llm_logger, api_auth)
  router.php          ← Entry point PHP built-in server
  data/               ← market_history.json, subscriptions/, templates/ (JSON flat-file)
frontend/src/
  pages/              ← Home, Sottoscrizione, Analisi, Admin, Stats, Login, Conferma, Activate, ...
  components/         ← TariffCard, StickyReferenceBar, MarketSignal, ChatDemo, Navbar, Footer, ...
  lib/
    calc.js           ← Calcoli tariffari (calcLuceCost, calcGasCost, getCurrentPricePerUnit, savingsToHuman, getRankingBadges, isPriceAnomalous, estimateRegulatedCosts)
    api.js            ← Chiamate API backend
    webmcp.js         ← Registrazione tool WebMCP (navigator.modelContext.registerTool)
    validators.js     ← Validazione form (codice fiscale, P.IVA, email, CAP)
    province.js       ← Mappatura provincia → zona (NORD/CENTRO/SUD)
  App.jsx             ← Router React (react-router-dom v7)
frontend/public/      ← File statici copiati in dist/ (llms.txt, webmcp.json, robots.txt, .htaccess, .well-known/, *.html)
backend/php/          ← Sorgenti PHP (specchiati in dist/ al build)
mcp-server/           ← MCP Server Node.js per Claude Desktop (index.js, server.json, Dockerfile)
```

## File più importanti

- `frontend/src/pages/Home.jsx` — Hero + 3 mode card (Claude MCP, ChatGPT, Copia-incolla) + confronto interattivo
- `frontend/src/pages/Sottoscrizione.jsx` — Form wizard 4 step per attivazione offerta
- `frontend/src/components/TariffCard.jsx` — Card offerta v5.0: barra proporzionale, ranking 🥇🥈🥉, risparmio mensile, warning prezzo anomalo, accordion dettagli
- `frontend/src/components/StickyReferenceBar.jsx` — Barra sticky con prezzo energia attuale, quota fissa, spesa annua (usa PUN/PSV live + spread)
- `frontend/src/components/MarketSignal.jsx` — Segnali trend mercato (visibile solo con dati reali, mai placeholder)
- `frontend/src/lib/calc.js` — Tutti i calcoli tariffari: costi, risparmio, ranking, anomalie, costi regolati
- `frontend/src/lib/webmcp.js` — Registrazione 4 tool WebMCP
- `backend/php/api/index.php` — API Router (20+ endpoint)
- `backend/php/inc/tariff_loader.php` — Carica 44+ offerte da fonti proprietarie (URL in costanti PHP private)
- `backend/php/inc/bill_parser.php` — Estrae dati da testo bolletta italiana
- `backend/php/inc/subscription_handler.php` — Double opt-in GDPR + invio sottoscrizione
- `backend/php/inc/api_auth.php` — Autenticazione API key per endpoint protetti
- `backend/php/inc/llm_logger.php` — Logging chiamate LLM per analytics
- `backend/php/router.php` — Router PHP built-in server: API → api/index.php, MCP → mcp/index.php, file statici, SPA fallback → index.html
- `frontend/vite.config.js` — Build + copyBackendPlugin (copia automatica PHP in dist/) + proxy /api→8080 e /proxy→esterno
- `frontend/public/.htaccess` — Apache: HTTPS+www redirect, routing API/MCP/SPA, protezione inc/data/logs, CORS, cache
- `mcp-server/index.js` — 6 tool MCP per Claude Desktop (stdio via @modelcontextprotocol/sdk)

## Discovery files (Chrome WebMCP + LLM)

- `frontend/public/llms.txt` → `https://www.switchai.it/llms.txt` — descrizione sito per LLM
- `frontend/public/webmcp.json` → `https://www.switchai.it/webmcp.json` — tool discovery WebMCP
- `frontend/public/.well-known/mcp/server-card.json` — MCP server card per discovery automatica
- `frontend/public/openapi.json` → `https://www.switchai.it/openapi.json` — specifica OpenAPI
- `frontend/public/per-llm.html` → `https://www.switchai.it/per-llm` — documentazione machine-readable per LLM

## Router PHP (`router.php`) — logica di routing

```
/mcp              → mcp/index.php (MCP Server JSON-RPC)
/api/*            → api/index.php (router principale)
/offerta/*        → api/index.php (pagine dinamiche SEO)
/sitemap.xml      → api/index.php (generata dinamicamente)
File esistenti    → serviti direttamente
Altre rotte       → index.html (SPA fallback)
/inc/*, /data/*   → 404 (protetti)
```

## Regole

- **Mai esporre gli URL delle fonti dati** (tariff_loader.php). Sono in costanti PHP private.
- **Tutti i file statici** (llms.txt, webmcp.json, robots.txt, sitemap.xml, *.html) vanno in `frontend/public/`
- **Dopo ogni modifica backend**: `npm run build` copia automaticamente i PHP in dist/ via `copyBackendPlugin`
- **.env è in .gitignore** — usare `public/.env.example` per i template
- **WS_ENABLED=false** sul server finché non si è pronti per invio a web service
- **Credenziali stats**: in `public/.env` (STATS_USER + STATS_PASSWORD_HASH)
- **Mai hardcodare token o API key** — sempre da `getenv()`, usare `public/.env.example` come template
- **Sistema onestà**: consiglia switch solo se risparmio >50€/anno e >5% rispetto alla spesa attuale
- **MarketSignal**: mostrare solo con dati trend reali, mai placeholder

## GDPR Double Opt-In

1. `POST /api/subscription/submit` → pending + email conferma all'utente
2. Utente clicca `/conferma?token=xxx` → confirmed
3. Solo dopo la conferma i dati sensibili vengono inviati via email e (se WS_ENABLED=true) al web service

## Mercato
Italia — Mercato Libero Energia — zone NORD, CENTRO, SUD — 106 province — 44+ offerte da 13 fornitori
