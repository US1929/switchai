# SwitchAI — AI Agent per Cambio Fornitore Energia (Italia)

## Panoramica
SwitchAI è un AI agent che automatizza il cambio fornitore Luce e Gas nel mercato libero italiano.
- **Dominio**: switchai.it (OVH Pro Web Hosting, PHP 8.5 + Apache)
- **Stack**: React 19 + Vite 8 (frontend) | PHP 8.5 API (backend) | Node.js MCP Server
- **Design**: ispirato a Switcho.it / Billoo.it, card allineate a ComparaSemplice
- **WebMCP**: 4 tool registrati via `navigator.modelContext.registerTool()` (Chrome 146+)
- **MCP Server**: 6 tool per Claude Desktop

## Comandi essenziali

```bash
# Sviluppo locale
cd frontend && npm run dev          # Vite dev server (porta 5173, proxy /api → 8080)
cd frontend/dist && php -S localhost:8080 router.php  # PHP backend

# Build per produzione
cd frontend && npm run build        # Genera dist/ (frontend + PHP copiati automaticamente)
# Poi caricare TUTTO dist/ su OVH in www/

# MCP Server (Claude Desktop)
cd mcp-server && npm install && node index.js
```

## Struttura chiave

```
frontend/dist/  ← BUILD OUTPUT da caricare su OVH (www/)
frontend/src/   ← Codice React
backend/php/    ← Sorgenti PHP
mcp-server/     ← MCP Server per Claude
```

## File più importanti

- `frontend/src/pages/Home.jsx` — Hero + confronto interattivo
- `frontend/src/pages/Sottoscrizione.jsx` — Form wizard 4 step
- `frontend/src/components/TariffCard.jsx` — Card offerta (formato ComparaSemplice)
- `frontend/src/lib/webmcp.js` — Registrazione tool WebMCP
- `backend/php/api/index.php` — API Router (20 endpoint)
- `backend/php/inc/tariff_loader.php` — Carica 44+ offerte da fonti proprietarie
- `backend/php/inc/subscription_handler.php` — Double opt-in GDPR + invio sottoscrizione
- `frontend/vite.config.js` — Build + copia automatica PHP in dist/
- `mcp-server/index.js` — 6 tool MCP per Claude Desktop

## Regole

- **Mai esporre gli URL delle fonti dati** (tariff_loader.php). Sono in costanti PHP private.
- **Tutti i file statici** (llms.txt, webmcp.json, robots.txt, sitemap.xml, *.html) vanno in `frontend/public/`
- **Dopo ogni modifica backend**: `npm run build` copia automaticamente i PHP in dist/
- **.env è in .gitignore** — usare `.env.example` per i template
- **WS_ENABLED=false** sul server finché non si è pronti per invio a web service
- **Credenziali stats**: in `public/.env` (STATS_USER + STATS_PASSWORD_HASH)
- **Mai hardcodare token o API key** — sempre da `getenv()`, usare `public/.env.example` come template

## GDPR Double Opt-In

1. `POST /api/subscription/submit` → pending + email conferma all'utente
2. Utente clicca `/conferma?token=xxx` → confirmed
3. Solo dopo la conferma i dati sensibili vengono inviati via email e (se WS_ENABLED=true) al web service

## Mercato
Italia — Mercato Libero Energia — zone NORD, CENTRO, SUD — 106 province — 44+ offerte da 13 fornitori
