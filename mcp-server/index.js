#!/usr/bin/env node

/**
 * SwitchAI MCP Server
 *
 * Ponte tra Claude Desktop/ChatGPT e l'API SwitchAI (https://switchai.it).
 *
 * Installazione:
 *   1. npm install
 *   2. Configura in Claude Desktop:
 *      {
 *        "mcpServers": {
 *          "switchai": {
 *            "command": "node",
 *            "args": ["/percorso/assoluto/mcp-server/index.js"],
 *            "env": { "SWITCHAI_API_URL": "https://switchai.it/api" }
 *          }
 *        }
 *      }
 *
 * L'utente dice: "Analizza la mia bolletta e trovami l'offerta migliore"
 * Claude usa i tool MCP qui sotto per chiamare l'API SwitchAI.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// ── Configurazione ────────────────────────────────────────────────────

const API_BASE = process.env.SWITCHAI_API_URL || "https://switchai.it/api";

async function apiCall(endpoint, method = "GET", body = null) {
  const url = `${API_BASE}${endpoint}`;
  const opts = {
    method,
    headers: { "Content-Type": "application/json", "Accept": "application/json", "User-Agent": "SwitchAI-MCP/1.0" },
  };
  if (body) opts.body = JSON.stringify(body);

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 30000);
  opts.signal = controller.signal;

  try {
    const res = await fetch(url, opts);
    clearTimeout(timeoutId);
    
    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || `HTTP ${res.status}: ${JSON.stringify(data)}`);
    }

    return data;
  } catch (err) {
    clearTimeout(timeoutId);
    throw err;
  }
}

// ── MCP Server ────────────────────────────────────────────────────────

const server = new McpServer({
  name: "switchai",
  version: "1.0.0",
  description: "SwitchAI — Confronto tariffe Luce/Gas per il mercato italiano. Dati reali da oltre 5.600 offerte ARERA. switchai.it",
});

// ── Tool 1: Calcola risparmio ─────────────────────────────────────────

server.tool(
  "calculate_energy_savings",
  "Confronta le tariffe Luce o Gas e calcola il risparmio annuo. Restituisce le 3 migliori offerte con link di attivazione su switchai.it (sito esterno). "
    + "METODO ARERA: confronto SIMMETRICO — per tariffe variabili usa lo stesso PUN/PSV corrente per entrambi i lati. Il risparmio riflette solo differenze contrattuali (spread + quota fissa), non oscillazioni di mercato. "
  + "FLUSSO: (1) Estrai i dati numerici dalla bolletta: commodity, consumo annuo, spesa annua, zona. "
  + "(2) Passa i dati al tool. "
  + "(3) Il tool restituisce le 3 migliori offerte con link diretto al sito del fornitore per l'attivazione. "
  + "(4) L'utente clicca il link e completa l'attivazione in autonomia sul sito del fornitore. "
  + "I dati personali (nome, CF, POD/PDR) NON vanno nell'URL — switchai.it non ha form di sottoscrizione. "
  + "L'attivazione va direttamente sul sito del fornitore tramite il link fornito.",
  {
    commodity: z.enum(["LUCE", "GAS"]).describe("Tipo di fornitura: LUCE (elettricità) o GAS"),
    yearly_consumption_kwh: z.number().optional().describe("Consumo annuo in kWh (solo per LUCE). Es: 2700"),
    yearly_consumption_smc: z.number().optional().describe("Consumo annuo in Smc (solo per GAS). Es: 1000"),
    zone: z.enum(["NORD", "CENTRO", "SUD"]).optional().default("NORD").describe("Zona tariffaria italiana"),
    current_supplier: z.string().optional().describe("Nome del fornitore attuale (es: 'Enel Energia')"),
    current_annual_spend: z.number().optional().describe("Spesa annua attuale in €. Es: 650"),
    canone_rai: z.number().optional().describe("Canone RAI annuale in € (solo LUCE). ~90€/anno. 0 se assente o GAS."),
    spesa_materia_energia: z.number().optional().describe("Spesa annua materia energia in € (solo componente energia, esclusi oneri/IVA/canone)."),
    quota_fissa_mensile: z.number().optional().describe("Quota fissa mensile in €/mese dal Box Offerta."),
    tipo_cliente: z.enum(["residenziale", "business"]).optional().describe("Tipo cliente: residenziale o business."),
    tariff_type: z.enum(["fisso", "variabile"]).optional().describe("(Opzionale) Tipo tariffa attuale. Per variabili il confronto è simmetrico (stesso PUN)."),
    spread_eur_kwh: z.number().optional().describe("(Opzionale) Spread attuale in €/kWh per tariffe LUCE variabili. Dal Box Offerta."),
    spread_eur_smc: z.number().optional().describe("(Opzionale) Spread attuale in €/Smc per tariffe GAS variabili. Dal Box Offerta."),
  },
  async (params) => {
    const data = await apiCall("/api/analyze", "POST", {
      commodity: params.commodity,
      yearly_consumption_kwh: params.yearly_consumption_kwh ?? 0,
      yearly_consumption_smc: params.yearly_consumption_smc ?? 0,
      zone: params.zone,
      current_supplier: params.current_supplier ?? "",
      current_annual_spend: params.current_annual_spend ?? 0,
      canone_rai: params.canone_rai ?? 0,
      spesa_materia_energia: params.spesa_materia_energia ?? 0,
      quota_fissa_mensile: params.quota_fissa_mensile ?? 0,
      tipo_cliente: params.tipo_cliente ?? "residenziale",
      tariff_type: params.tariff_type ?? null,
      spread_eur_kwh: params.spread_eur_kwh ?? 0,
      spread_eur_smc: params.spread_eur_smc ?? 0,
    });

    const commodity = params.commodity;
    const unit = commodity === 'LUCE' ? 'kWh' : 'Smc';
    const consumo = commodity === 'LUCE' ? (params.yearly_consumption_kwh || 0) : (params.yearly_consumption_smc || 0);
    const icon = commodity === 'LUCE' ? '⚡' : '🔥';
    const label = commodity === 'LUCE' ? 'Luce' : 'Gas';

    // Build markdown output — funnel: decisione prima, dettagli dopo
    const results = data.top3 || [];
    const best = results[0];
    const spesa = data.profile?.spesa_annua_eur ?? 0;

    if (!best) {
      return {
        content: [{ type: "text", text: `*Nessuna offerta trovata per ${label} nella zona ${params.zone}.*` }],
      };
    }

    const savingsMonth = Math.round(best.savings_eur / 12 * 100) / 100;
    const bestUrl = best.affiliate_url || best.subscription_url || best.url_offerta || `https://www.switchai.it/offerta/${best.tariff_id}`;

    // ── Header ──────────────────────────────────────────
    const lossNote = commodity === 'LUCE'
      ? '\n📐 Prezzo bolletta = (PUN + spread) × 1,102 (perdite rete ~10,2% ARERA)\n'
      : '';

    let md = `## ${icon} Bolletta analizzata\n\n`;
    md += `✅ **${consumo} ${unit}/anno** · Zona **${params.zone}** · ${params.current_supplier || 'Fornitore attuale'}\n`;
    md += lossNote;
    md += `\n---\n\n`;

    // ── Spesa attuale + Risparmio (dominante) ────────────
    md += `### 💰 La tua spesa attuale\n\n`;
    md += `# ${spesa} €/anno\n\n`;
    md += `---\n\n`;

    // ── OFFERTA CONSIGLIATA (una sola, grande) ───────────
    md += `## ⭐ Offerta consigliata\n\n`;
    md += `### ${best.supplier} — ${best.tariff_name}\n`;
    md += `**${best.type === 'FISSO' ? '🔒 Prezzo Fisso' : '📊 Prezzo Variabile'}**`;
    if (best.price_per_unit) md += ` | ${best.price_per_unit} ${unit === 'kWh' ? '€/kWh' : '€/Smc'}`;
    if (best.fixed_fee_monthly) md += ` | Quota fissa ${best.fixed_fee_monthly} €/mese`;
    md += `\n\n`;

    // Risparmio — il numero più importante
    md += `| | |\n|---|---|\n`;
    md += `| Costo stimato | **${best.annual_cost_eur} €/anno** |\n`;
    md += `| 🔥 Risparmio | **${best.savings_eur} €/anno (${best.savings_pct}%)** |\n`;
    md += `| Al mese risparmi | **~${savingsMonth} €/mese** |\n`;

    if (best.price_warning) {
      md += `\n⚠️ ${best.price_warning}\n`;
    }

    md += `\n`;

    // ── CTA ─────────────────────────────────────────────
    md += `---\n\n`;
    md += `### 📝 Attivazione\n\n`;
    md += `🔗 **[🟢 VAI AL SITO DEL FORNITORE](${bestUrl})**\n\n`;
    md += `> Clicca il link per andare al sito del fornitore e completare l'attivazione in autonomia.\n\n`;

    // ── Altre offerte (compact) ──────────────────────────
    const others = results.slice(1);
    if (others.length > 0) {
      md += `---\n\n`;
      md += `### 📋 Altre offerte\n\n`;
      const badges = ['🥈', '🥉'];
      for (let i = 0; i < others.length; i++) {
        const r = others[i];
        const otherUrl = r.affiliate_url || r.subscription_url || r.url_offerta || `https://www.switchai.it/offerta/${r.tariff_id}`;
        const warning = r.price_warning ? ' ⚠️' : '';
        md += `**${badges[i]} ${r.supplier}** — ${r.tariff_name} · ${r.annual_cost_eur} €/anno · Risparmio **${r.savings_eur} €**${warning}\n`;
        md += `[Vai al sito del fornitore](${otherUrl})\n\n`;
      }
    }

    // ── Perché questa (compatto) ──────────────────────────
    if (best.breakdown?.explanation) {
      md += `---\n\n`;
      md += `### 📐 Perché ${best.supplier}?\n\n`;
      md += `${best.breakdown.explanation}\n`;
      if (best.type === 'FISSO') {
        md += `\n🔒 Prezzo bloccato: protetto da aumenti del ${commodity === 'LUCE' ? 'PUN' : 'PSV'} per tutta la durata del contratto.\n`;
      }
      md += `\n`;
    }

    // ── Footer ───────────────────────────────────────────
    md += `---\n\n`;
    md += `⚠️ **Simulazione valida con i prezzi di oggi.** I prezzi energia cambiano ogni giorno.\n`;
    md += `\n*switchai.it · Dati ARERA · ${new Date().toISOString().slice(0, 10)}*`;

    return {
      content: [{
        type: "text",
        text: md,
      }],
    };
  }
);

// ── Tool 2: Lista offerte disponibili ──────────────────────────────────

server.tool(
  "get_available_offers",
  "Recupera tutte le offerte disponibili per Luce o Gas. Ritorna nome fornitore, prezzo, tipo (fisso/variabile), costo fisso mensile.",
  {
    commodity: z.enum(["LUCE", "GAS"]).describe("LUCE per elettricità, GAS per gas metano"),
  },
  async (params) => {
    const data = await apiCall(`/tariffe/${params.commodity.toLowerCase()}`);

    return {
      content: [{
        type: "text",
        text: JSON.stringify({
          commodity: data.commodity,
          total_offers: data.count,
          offers: data.offers?.map(o => ({
            id: o.id,
            supplier: o.supplier_name,
            name: o.name,
            type: o.type === "FISSO" ? "Prezzo fisso" : "Prezzo variabile",
            price_per_unit: params.commodity === "LUCE"
              ? `${o.price_mono_kwh} €/kWh`
              : `${o.price_smc} €/Smc`,
            fixed_fee_monthly: `${o.fixed_fee_monthly} €/mese`,
            promo: o.promo_active,
          })),
        }, null, 2),
      }],
    };
  }
);

// ── Tool 3: Analizza bolletta ─────────────────────────────────────────

server.tool(
  "parse_energy_bill",
  "Analizza il testo di una bolletta italiana (luce o gas) ed estrae: fornitore, POD/PDR, consumo annuo, spesa annua stimata, zona tariffaria.",
  {
    bill_text: z.string().describe("Testo completo della bolletta da analizzare. Puoi estrarre il testo da un PDF o riceverlo dall'utente."),
  },
  async (params) => {
    const data = await apiCall("/parse-bill-text", "POST", {
      text: params.bill_text,
    });

    const icon = data.commodity === "LUCE" ? "⚡" : "🔥";
    const label = data.commodity === "LUCE" ? "Luce" : "Gas";
    const unit = data.commodity === "LUCE" ? "kWh" : "Smc";
    const consumo = data.commodity === "LUCE" ? data.yearly_consumption_kwh : data.yearly_consumption_smc;

    const md = `## ${icon} Dati Bolletta ${label}\n\n`
      + `| | |\n|---|---|\n`
      + `| Fornitore | **${data.current_supplier}** |\n`
      + `| POD/PDR | ${data.pod_pdr || 'non rilevato'} |\n`
      + `| Consumo annuo | **${consumo} ${unit}** |\n`
      + `| Spesa annua | **${data.current_annual_spend} €** |\n`
      + `| Zona | ${data.zone} |\n`
      + `\n✅ Dati pronti per il confronto. Usa **calculate_energy_savings** con questi valori.`;

    return {
      content: [{
        type: "text",
        text: md,
      }],
    };
  }
);

// ── Avvio ─────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);

console.error("⚡ SwitchAI MCP Server avviato");
console.error(`   API: ${API_BASE}`);
console.error("   Tool: calculate_energy_savings, get_available_offers, parse_energy_bill, get_market_indices");
