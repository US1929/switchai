# SwitchAI MCP Server — Quickstart & Examples

Install the server and connect an AI agent (Claude Desktop, or any MCP client) to SwitchAI in under a minute.

## 1. Install

```bash
cd mcp-server
npm install
node index.js          # stdio transport
```

Or install globally from npm:

```bash
npm install -g @us1929/switchai-mcp
```

The server reads `SWITCHAI_API_URL` (default: `https://switchai.it/api`). Override it for local development:

```bash
SWITCHAI_API_URL=http://localhost:8080/api node index.js
```

## 2. Configure Claude Desktop

Add to `claude_desktop_config.json`:

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

Restart Claude Desktop. The 4 tools are now available:
`calculate_energy_savings`, `parse_energy_bill`, `get_available_offers`, `get_market_indices`.

## 3. Minimal invocation (via MCP client)

The agent should call `calculate_energy_savings` with either a bill text or structured inputs:

```
calculate_energy_savings({
  commodity: "LUCE",
  yearly_consumption_kwh: 3200,
  zone: "NORD",
  current_annual_spend: 1100
})
```

Expected result (abridged):

```json
{
  "top3": [
    { "name": "E.ON LuceClick - Amico new", "price_mono_kwh": 0.1146, "annual_cost": 1480, "affiliate_url": "https://..." },
    { "name": "Edison Web Luce", "price_mono_kwh": 0.122, "annual_cost": 1520, "affiliate_url": "https://..." },
    { "name": "A2A Full Luce", "price_mono_kwh": 0.139, "annual_cost": 1600, "affiliate_url": "https://..." }
  ],
  "honesty": { "recommendation": "switch", "savings_eur": 240, "savings_pct": 22 },
  "agent_summary": "Con 3.200 kWh in zona NORD puoi risparmiare circa 240€ all'anno (22%)..."
}
```

## 4. Same call over plain HTTP

The MCP server is a thin wrapper over the REST API — you can call it directly:

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

Both Italian and English parameter names are accepted
(`consumo_annuo_kwh` / `yearly_consumption_kwh`, `spesa_annua_eur` / `current_annual_spend`, `zona` / `zone`, `fornitore` / `current_supplier`).

## 5. Typical agent conversation

```text
User:  "Questa è la mia bolletta, pago 1.100€/anno per 3.200 kWh. Sto pagando troppo?"

Agent: parse_energy_bill({ bill_text: "..." })
       → { commodity: "LUCE", consumo_annuo: 3200, spesa_annua: 1100, zona: "NORD" }

Agent: calculate_energy_savings({ commodity: "LUCE", yearly_consumption_kwh: 3200,
                                  zone: "NORD", current_annual_spend: 1100 })
       → top3 + honesty + agent_summary

Agent: "Con 3.200 kWh in zona NORD puoi risparmiare ~240€/anno (22%). Ecco le 3 migliori:
        E.ON LuceClick (0,1146 €/kWh), Edison Web Luce (0,122 €/kWh), A2A Full Luce
        (0,139 €/kWh). Clicca per attivare sul sito del fornitore."
```

## Rules for the agent

- The activation always happens on the supplier's website via `affiliate_url`. Never claim "activation completed".
- If savings < 30€/yr or < 3%, `honesty.recommendation` is `stay`: tell the user it's not worth switching.
- For variable/indexed tariffs, `bill_attualization` re-prices the bill at today's PUN/PSV — prefer it over the historical bill cost.