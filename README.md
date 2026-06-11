# SwitchAI MCP Server

MCP (Model Context Protocol) server per il confronto tariffe energia nel mercato libero italiano. 4 tool a disposizione degli AI agent.

## Tool disponibili

| Tool | Descrizione |
|------|-------------|
| `analyze_energy_bill` | Confronta tariffe Luce/Gas e calcola risparmio annuo |
| `get_available_offers` | Elenca 44+ offerte luce e gas |
| `get_market_indices` | PUN e PSV correnti da fonte pubblica |
| `get_subscription_form_schema` | Schema del form di sottoscrizione |

## Installazione (Claude Desktop)

```json
{
  "mcpServers": {
    "switchai": {
      "command": "npx",
      "args": ["@us1929/switchai-mcp"],
      "env": {
        "SWITCHAI_API_URL": "https://www.switchai.it/api"
      }
    }
  }
}
```

Oppure eseguilo localmente:

```bash
cd mcp-server && npm install && node index.js
```

## Mercato

Italia — 44+ offerte da 13 fornitori — zone NORD, CENTRO, SUD

## Sito

https://www.switchai.it

## Licenza

MIT
