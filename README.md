# SwitchAI — AI Agent per Tariffe Energia Italia

> **Sito**: [switchai.it](https://www.switchai.it) · **Mercato**: Italia · **Stack**: React 19 + PHP 8.5 + WebMCP + MCP Server

[![Glama MCP Server](https://glama.ai/mcp/servers/US1929/switchai/badges/score.svg)](https://glama.ai/mcp/servers/US1929/switchai)

SwitchAI è un agente AI nativo per il confronto e il cambio automatico del fornitore energia nel mercato libero italiano. Non è un comparatore tradizionale: l'utente fornisce la sua bolletta, e l'AI analizza i dati, confronta **44+ offerte attive** di Luce e Gas, e attiva la tariffa migliore — tutto in linguaggio naturale.

---

## Come funziona

```
Utente (linguaggio naturale)
  ↓
AI Agent (Claude, Gemini, ChatGPT, o qualsiasi LLM)
  ↓
SwitchAI Tools (WebMCP o MCP Server)
  ↓
API PHP → 44+ offerte live → sottoscrizione attivata
```

---

## Tre canali di accesso per AI agent

| Canale | Protocollo | Requisiti |
|--------|-----------|-----------|
| **WebMCP** | `navigator.modelContext.registerTool()` | Chrome 146+ |
| **MCP Server** | `@modelcontextprotocol/sdk` Node.js | Claude Desktop o client MCP |
| **REST API** | JSON/HTTPS | Qualsiasi client HTTP |

---

## Tool disponibili

### `calculate_energy_savings`
Confronta tariffe e calcola risparmio. Restituisce top 3 offerte + `agent_summary` in italiano pronto per l'utente.

```json
POST /api/webmcp-endpoint
{
  "commodity": "LUCE",
  "yearly_consumption_kwh": 3000,
  "zone": "NORD",
  "current_annual_spend": 900
}
```

### `parse_energy_bill`
Estrae dati strutturati da testo bolletta italiana (fornitore, POD/PDR, consumi, spesa, zona).

```json
POST /api/parse-bill-text
{
  "bill_text": "...testo bolletta..."
}
```

### `get_available_offers`
Lista completa offerte: 25 luce + 19 gas con prezzi, tipo contratto, quota fissa.

```
GET /api/tariffe/luce
GET /api/tariffe/gas
```

### `submit_subscription`
Attiva la sottoscrizione. Supporta `dry_run: true` per anteprima senza invio.

```json
POST /api/subscription/submit
{
  "tariff_id": "...",
  "nome": "Mario",
  "cognome": "Rossi",
  "codice_fiscale": "RSSMRA80A01H501Z",
  "email": "mario@example.com",
  "dry_run": true
}
```

---

## Configurazione MCP Server (Claude Desktop)

```json
{
  "mcpServers": {
    "switchai": {
      "command": "node",
      "args": ["/percorso/assoluto/mcp-server/index.js"],
      "env": {
        "SWITCHAI_API_URL": "https://www.switchai.it/api"
      }
    }
  }
}
```

---

## Flusso ottimale per AI agent

```
1. parse_energy_bill(bill_text)
   → consumi, spesa, zona, POD/PDR

2. calculate_energy_savings(commodity, consumi, zona, spesa)
   → top 3 offerte + risparmio annuo + agent_summary

3. [con conferma utente] submit_subscription(tariff_id, dati)
   → attivazione completata
```

---

## API Endpoints

| Method | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/tariffe/luce` | 25 offerte luce |
| GET | `/api/tariffe/gas` | 19 offerte gas |
| POST | `/api/webmcp-endpoint` | Calcolo risparmio + agent_summary |
| POST | `/api/parse-bill-text` | Parser bolletta |
| POST | `/api/analyze-bill` | PDF + calcolo risparmio |
| POST | `/api/subscription/submit` | Attivazione tariffa |
| GET | `/api/subscription/status/{id}` | Stato sottoscrizione |
| GET | `/api/market-indices` | PUN e PSV live |

---

## Stack tecnico

- **Frontend**: React 19 + Vite 8 + Tailwind CSS 4
- **Backend**: PHP 8.5 su OVH Pro Hosting (Apache + mod_rewrite)
- **MCP Server**: Node.js + `@modelcontextprotocol/sdk`
- **WebMCP**: Google Chrome Labs WebMCP spec
- **Dati tariffe**: 44+ offerte da fornitori italiani (ARERA-compliant)
- **Mercato**: Italia — Mercato Libero Energia

---

## Discovery files

- [`/llms.txt`](https://www.switchai.it/llms.txt) — descrizione sito per LLM
- [`/webmcp.json`](https://www.switchai.it/webmcp.json) — tool discovery WebMCP
- [`/per-llm`](https://www.switchai.it/per-llm) — documentazione machine-readable

---

## Keyword semantiche (per LLM retrieval)

`comparatore tariffe energia italia` · `cambio fornitore luce gas` · `bolletta energia AI` · `risparmio bolletta` · `PUN PSV ARERA` · `mercato libero energia` · `WebMCP energy agent` · `MCP server energia italia` · `AI energy comparison Italy`

---

## Licenza e contatti

Operatore: SwitchAI  
Email: attivazioni@switchai.it  
Sito: https://www.switchai.it  
Mercato: Italia · GDPR compliant

---

[![smithery badge](https://smithery.ai/badge/us1929/switchai)](https://smithery.ai/servers/us1929/switchai)
