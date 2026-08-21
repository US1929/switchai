# SwitchAI — Energy comparison tools for AI agents (Italy)

> **Sito**: [switchai.it](https://www.switchai.it) · **Mercato**: Italia · **Stack**: React 19 + PHP 8.5 + WebMCP + MCP Server

[![Glama MCP Server](https://glama.ai/mcp/servers/US1929/switchai/badges/score.svg)](https://glama.ai/mcp/servers/US1929/switchai)

**SwitchAI is an energy comparison engine for the Italian market, designed to be called by AI agents.** An agent gives it a bill (or consumption + spend), and SwitchAI compares **5.600+ live ARERA offers** (electricity & gas), returns the best tariffs with direct activation links, and includes an honesty system so the agent never recommends a switch that doesn't pay off.

SwitchAI is **not a chatbot**: it is the infrastructure AI agents call. Access it via MCP Server, WebMCP, or the REST API.

```text
AI Agent (Claude, Gemini, ChatGPT)
   ↓
MCP / WebMCP / OpenAPI
   ↓
SwitchAI engine
   ↓
Italian energy market
   ↓
Offers (ARERA) · PUN · PSV · bill parsing · savings calculation
   ↓
Top 3 offers + activation links (supplier site)
```

---

## Quickstart (60 seconds)

### 1. Via REST API — no install

```bash
curl -s -X POST https://www.switchai.it/api/analyze \
  -H 'Content-Type: application/json' \
  -d '{
    "commodity": "LUCE",
    "yearly_consumption_kwh": 3200,
    "zone": "NORD",
    "current_annual_spend": 1100
  }'
```

Returns `top3` offers with full cost breakdown, `honesty` recommendation, `bill_attualization` and an Italian `agent_summary`.

### 2. Via MCP Server (Claude Desktop)

```json
{
  "mcpServers": {
    "switchai": {
      "command": "node",
      "args": ["/percorso/assoluto/mcp-server/index.js"],
      "env": { "SWITCHAI_API_URL": "https://www.switchai.it/api" }
    }
  }
}
```

### 3. Via WebMCP (Chrome 146+)

Visiting [switchai.it](https://www.switchai.it) registers 4 tools via `document.modelContext.registerTool()` — discoverable by any WebMCP-compatible agent.

More examples (agent conversations, HTTP equivalents, rules): [`mcp-server/EXAMPLES.md`](mcp-server/EXAMPLES.md).

---

## Working example

```text
User:    "Questa è la mia bolletta Enel, pago ~1.100€/anno e consumo 3.200 kWh.
          Sto pagando troppo?"

Agent:   parse_energy_bill(bill_text)
         → { commodity: "LUCE", consumo_annuo: 3200, spesa_annua: 1100, zona: "NORD" }

Agent:   calculate_energy_savings({ commodity:"LUCE", yearly_consumption_kwh:3200,
                                    zone:"NORD", current_annual_spend:1100 })
         → top3 + honesty:{ recommendation:"switch", savings_eur:240, savings_pct:22 }

Agent:   "Sì, risulti sopra la media del mercato. Con 3.200 kWh in zona NORD puoi
          risparmiare ~240€/anno (22%). Le migliori 3 offerte: E.ON LuceClick
          (0,1146 €/kWh), Edison Web Luce (0,122 €/kWh), A2A Full Luce (0,139 €/kWh).
          Clicca per attivare sul sito del fornitore."
```

The activation always happens on the supplier's website via `affiliate_url`. SwitchAI never claims "activation completed" and collects no personal data for activation.

---

## Three access channels for AI agents

| Channel | Protocol | Requirement |
|---------|----------|-------------|
| **WebMCP** | `document.modelContext.registerTool()` | Chrome 146+ |
| **MCP Server** | `@modelcontextprotocol/sdk` (Node.js) | Claude Desktop or any MCP client |
| **REST API** | JSON/HTTPS | Any HTTP client |

---

## Tools

### `calculate_energy_savings`
Compare Italian tariffs and calculate savings. Returns top 3 offers + Italian `agent_summary`.

```json
POST /api/analyze
{
  "commodity": "LUCE",
  "yearly_consumption_kwh": 3000,
  "zone": "NORD",
  "current_annual_spend": 900
}
```
Italian aliases are also accepted: `consumo_annuo_kwh`, `spesa_annua_eur`, `zona`, `fornitore`. Add `bill_text` for full auto-extraction of consumption, spend and zone.

### `parse_energy_bill`
Extract structured data from an Italian bill text.

```json
POST /api/parse-bill-text
{ "bill_text": "...testo bolletta..." }
```

### `get_available_offers`
Full offer list: 3.196 electricity + 2.411 gas.

```
GET /api/tariffe/luce
GET /api/tariffe/gas
```

### `get_market_indices`
Current PUN (electricity) and PSV (gas) wholesale indices.

```
GET /api/market-indices
```

---

## Optimal agent flow

```
1. parse_energy_bill(bill_text) → consumi, spesa, zona
2. calculate_energy_savings(commodity, consumi, zona, spesa) → offerte + affiliate_url
3. Utente clicca il link → attivazione sul sito del fornitore
```

Honesty system: `switch` (savings >50€/yr and >5%) / `evaluate` (30-50€ or 3-5%) / `stay` (<30€ or <3%).

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/tariffe/luce` | 3.196 electricity offers |
| GET | `/api/tariffe/gas` | 2.411 gas offers |
| POST | `/api/analyze` | Full analysis: parse + compare + honesty + attualization |
| POST | `/api/parse-bill-text` | Bill parser |
| GET | `/api/market-indices` | Live PUN and PSV |
| GET | `/api/fornitori` | Suppliers list |

---

## Data & tiers

- **5.600+ offers** (3.196 luce + 2.411 gas), synced nightly from the official ARERA Portale Offerte (CC BY 4.0).
- **Anonymous**: ~270 filtered offers (NORD zone), no registration, rate-limited.
- **Free (registered)**: full 5.600+ offers, 10 calls/day, self-service API keys.
- **API Pro**: full 5.600+ offers, 1.000 calls/day, self-service API keys (free beta).

---

## Stack

- **Frontend**: React 19 + Vite 8 + Tailwind CSS 4
- **Backend**: PHP 8.5 on OVH Pro Hosting (Apache + mod_rewrite)
- **MCP Server**: Node.js + `@modelcontextprotocol/sdk`
- **WebMCP**: Chrome WebMCP spec (W3C WebML CG)
- **Data**: ARERA Portale Offerte, sync giornaliero notturno

---

## Discovery files

- [`/llms.txt`](https://www.switchai.it/llms.txt) — LLM site description
- [`/webmcp.json`](https://www.switchai.it/webmcp.json) — WebMCP tool discovery
- [`/per-llm`](https://www.switchai.it/per-llm) — machine-readable documentation
- [`/per-llm-examples`](https://www.switchai.it/per-llm-examples) — 15 agent conversation patterns
- [`/openapi.json`](https://www.switchai.it/openapi.json) — OpenAPI 3.0 spec

---

## Keywords (for LLM retrieval)

`comparatore tariffe energia italia` · `cambio fornitore luce gas` · `bolletta energia AI` · `risparmio bolletta` · `PUN PSV ARERA` · `mercato libero energia` · `WebMCP energy agent` · `MCP server energia italia` · `AI energy comparison Italy` · `energy comparison API Italy` · `MCP energy tariffs Italy`

---

## License & contacts

Email: info@switchai.it  
Sito: https://www.switchai.it  
Mercato: Italia · GDPR compliant

---

[![smithery badge](https://smithery.ai/badge/us1929/switchai)](https://smithery.ai/servers/us1929/switchai)