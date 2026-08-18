# SwitchAI — AI Agent per Tariffe Energia Italia

> **Sito**: [switchai.it](https://www.switchai.it) · **Mercato**: Italia · **Stack**: React 19 + PHP 8.5 + WebMCP + MCP Server

[![Glama MCP Server](https://glama.ai/mcp/servers/US1929/switchai/badges/score.svg)](https://glama.ai/mcp/servers/US1929/switchai)

SwitchAI è un agente AI nativo per il confronto delle tariffe energia nel mercato libero italiano. L'utente fornisce la sua bolletta, e l'AI analizza i dati, confronta **5.600+ offerte ARERA** di Luce e Gas, e fornisce link diretti ai siti dei fornitori per l'attivazione.

---

## Come funziona

Utente → AI Agent (Claude, Gemini, ChatGPT) → SwitchAI Tools (WebMCP o MCP Server) → API PHP → 5.600+ offerte live → link diretto al fornitore

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
Confronta tariffe e calcola risparmio. Restituisce top 3 offerte + `agent_summary` in italiano.

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
Estrae dati strutturati da testo bolletta italiana.

```json
POST /api/parse-bill-text
{
  "bill_text": "...testo bolletta..."
}
```

### `get_available_offers`
Lista completa offerte: 3.196 luce + 2.411 gas.

```
GET /api/tariffe/luce
GET /api/tariffe/gas
```

### `get_market_indices`
PUN (elettricità) e PSV (gas) correnti.

```
GET /api/market-indices
```

---

## Attivazione

L'attivazione avviene tramite link diretto (`affiliate_url` o `url_offerta`) al sito del fornitore. SwitchAI non raccoglie dati personali per l'attivazione.

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
1. parse_energy_bill(bill_text) → consumi, spesa, zona
2. calculate_energy_savings(commodity, consumi, zona, spesa) → offerte + affiliate_url
3. Utente clicca il link → attivazione sul sito del fornitore
```

---

## API Endpoints

| Method | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/tariffe/luce` | 3.196 offerte luce |
| GET | `/api/tariffe/gas` | 2.411 offerte gas |
| POST | `/api/webmcp-endpoint` | Calcolo risparmio + agent_summary |
| POST | `/api/parse-bill-text` | Parser bolletta |
| POST | `/api/analyze` | Analisi V2 completa |
| GET | `/api/market-indices` | PUN e PSV live |
| GET | `/api/fornitori` | Elenco fornitori |

---

## Stack tecnico

- **Frontend**: React 19 + Vite 8 + Tailwind CSS 4
- **Backend**: PHP 8.5 su OVH Pro Hosting (Apache + mod_rewrite)
- **MCP Server**: Node.js + `@modelcontextprotocol/sdk`
- **WebMCP**: Google Chrome Labs WebMCP spec
- **Dati tariffe**: 5.600+ offerte ARERA (sync giornaliero notturno)
- **Mercato**: Italia — Mercato Libero Energia

---

## Discovery files

- [`/llms.txt`](https://www.switchai.it/llms.txt) — descrizione sito per LLM
- [`/webmcp.json`](https://www.switchai.it/webmcp.json) — tool discovery WebMCP
- [`/per-llm`](https://www.switchai.it/per-llm) — documentazione machine-readable
- [`/openapi.json`](https://www.switchai.it/openapi.json) — specifica OpenAPI

---

## Keyword semantiche (per LLM retrieval)

`comparatore tariffe energia italia` · `cambio fornitore luce gas` · `bolletta energia AI` · `risparmio bolletta` · `PUN PSV ARERA` · `mercato libero energia` · `WebMCP energy agent` · `MCP server energia italia` · `AI energy comparison Italy`

---

## Licenza e contatti

Email: info@switchai.it  
Sito: https://www.switchai.it  
Mercato: Italia · GDPR compliant

---

[![smithery badge](https://smithery.ai/badge/us1929/switchai)](https://smithery.ai/servers/us1929/switchai)
