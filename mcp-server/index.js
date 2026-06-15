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
  description: "SwitchAI — Confronto tariffe Luce/Gas e sottoscrizione per il mercato italiano. Dati reali da oltre 44 offerte dei principali fornitori. switchai.it",
});

// ── Tool 1: Calcola risparmio ─────────────────────────────────────────

function buildPrefillUrl(baseUrl, params) {
  const url = new URL(baseUrl);
  const prefill = ['nome','cognome','cf','email','tel','indirizzo','civico','citta','provincia','provincia_sigla','cap','pod','pdr','consumi','spesa'];
  for (const p of prefill) {
    if (params[p]) url.searchParams.set(p, params[p]);
  }
  return url.toString();
}

server.tool(
  "calculate_energy_savings",
  "Confronta le tariffe Luce o Gas e calcola il risparmio annuo. Restituisce le 3 migliori offerte in formato leggibile con link di attivazione. "
  + "FLUSSO: (1) Estrai nome, cognome, CF, email, telefono, indirizzo, consumi e spesa dalla bolletta. "
  + "(2) Passa i dati numerici (consumi, spesa, zona) a questo tool. "
  + "(3) Se hai già estratto i dati personali, passali come parametri opzionali per avere subito il link precompilato. "
  + "(4) Mostra le offerte e chiedi quale preferisce. "
  + "(5) OBBLIGATORIO: di' all'utente che riceverà una email di conferma da SwitchAI e dovrà cliccare sul link per completare. Solo dopo la conferma i dati verranno inoltrati. "
  + "(6) NON attivare mai senza che l'utente abbia letto e accettato esplicitamente.",
  {
    commodity: z.enum(["LUCE", "GAS"]).describe("Tipo di fornitura: LUCE (elettricità) o GAS"),
    yearly_consumption_kwh: z.number().optional().describe("Consumo annuo in kWh (solo per LUCE). Es: 2700"),
    yearly_consumption_smc: z.number().optional().describe("Consumo annuo in Smc (solo per GAS). Es: 1000"),
    zone: z.enum(["NORD", "CENTRO", "SUD"]).optional().default("NORD").describe("Zona tariffaria italiana"),
    current_supplier: z.string().optional().describe("Nome del fornitore attuale (es: 'Enel Energia')"),
    current_annual_spend: z.number().optional().describe("Spesa annua attuale in €. Es: 650"),
    // Dati personali opzionali per prefill URL (NON salvati da SwitchAI)
    nome: z.string().optional().describe("(Opzionale) Nome intestatario per precompilare il form"),
    cognome: z.string().optional().describe("(Opzionale) Cognome per precompilare il form"),
    cf: z.string().optional().describe("(Opzionale) Codice Fiscale per precompilare il form"),
    email: z.string().optional().describe("(Opzionale) Email per precompilare il form"),
    tel: z.string().optional().describe("(Opzionale) Telefono per precompilare il form"),
    indirizzo: z.string().optional().describe("(Opzionale) Via/Piazza per precompilare il form"),
    civico: z.string().optional().describe("(Opzionale) Numero civico"),
    citta: z.string().optional().describe("(Opzionale) Città"),
    provincia_sigla: z.string().optional().describe("(Opzionale) Sigla provincia (es: MI)"),
    cap: z.string().optional().describe("(Opzionale) CAP (5 cifre)"),
    pod: z.string().optional().describe("(Opzionale) Codice POD per Luce"),
    pdr: z.string().optional().describe("(Opzionale) Codice PDR per Gas"),
    consumi: z.number().optional().describe("(Opzionale) Consumo annuo per prefill"),
    spesa: z.number().optional().describe("(Opzionale) Spesa annua per prefill"),
  },
  async (params) => {
    const data = await apiCall("/webmcp-endpoint", "POST", {
      commodity: params.commodity,
      yearly_consumption_kwh: params.yearly_consumption_kwh ?? 0,
      yearly_consumption_smc: params.yearly_consumption_smc ?? 0,
      zone: params.zone,
      current_supplier: params.current_supplier ?? "",
      current_annual_spend: params.current_annual_spend ?? 0,
    });

    const commodity = params.commodity;
    const unit = commodity === 'LUCE' ? 'kWh' : 'Smc';
    const consumo = commodity === 'LUCE' ? (params.yearly_consumption_kwh || 0) : (params.yearly_consumption_smc || 0);
    const icon = commodity === 'LUCE' ? '⚡' : '🔥';
    const label = commodity === 'LUCE' ? 'Luce' : 'Gas';

    // Build markdown output
    let md = `## ${icon} Confronto Tariffe ${label} — Zona ${params.zone}\n\n`;
    md += `Spesa attuale: **${data.current_spend_estimated} €/anno**`;
    if (consumo > 0) md += ` (${consumo} ${unit})`;
    md += `\n`;
    if (commodity === 'LUCE') {
      md += `\n📐 *Tariffe Luce: il prezzo in bolletta = (PUN + spread) × 1,102 (perdite rete ~10,2% ARERA per BT). Per il gas non si applicano perdite di rete.*\n`;
    }
    md += `\n---\n\n`;

    const results = data.results || [];
    if (results.length === 0) {
      md += `*Nessuna offerta trovata per ${label} nella zona ${params.zone}.*\n`;
    } else {
      const badges = ['🥇', '🥈', '🥉'];
      for (let i = 0; i < results.length; i++) {
        const r = results[i];
        md += `### ${badges[i]} ${r.supplier} — ${r.tariff_name}\n`;
        md += `**${r.type === 'FISSO' ? '🔒 Prezzo Fisso' : '📊 Prezzo Variabile'}**`;
        if (r.price_per_unit) md += ` | ${r.price_per_unit} ${unit === 'kWh' ? '€/kWh' : '€/Smc'}`;
        if (r.fixed_fee_monthly) md += ` | Quota fissa ${r.fixed_fee_monthly} €/mese`;
        md += `\n\n`;

        md += `| | |\n|---|---|\n`;
        md += `| Costo annuo | **${r.annual_cost_eur} €/anno** |\n`;
        md += `| Risparmio | **${r.savings_eur} €/anno (${r.savings_pct}%)** |\n`;
        md += `| Al mese | ~${Math.round(r.annual_cost_eur / 12)} €/mese |\n`;

        if (r.breakdown?.explanation) {
          md += `\n${r.breakdown.explanation}\n`;
        }

        // Build prefill URL
        const baseUrl = r.subscription_url || `https://www.switchai.it/sottoscrizione?tariff=${r.tariff_id}&supplier=${encodeURIComponent(r.supplier)}&name=${encodeURIComponent(r.tariff_name)}&commodity=${commodity.toLowerCase()}&annualCost=${r.annual_cost_eur}`;
        const prefillParams = {};
        if (params.nome) prefillParams.nome = params.nome;
        if (params.cognome) prefillParams.cognome = params.cognome;
        if (params.cf) prefillParams.cf = params.cf;
        if (params.email) prefillParams.email = params.email;
        if (params.tel) prefillParams.tel = params.tel;
        if (params.indirizzo) prefillParams.indirizzo = params.indirizzo;
        if (params.civico) prefillParams.civico = params.civico;
        if (params.citta) prefillParams.citta = params.citta;
        if (params.provincia_sigla) prefillParams.provincia_sigla = params.provincia_sigla;
        if (params.cap) prefillParams.cap = params.cap;
        if (params.pod) prefillParams.pod = params.pod;
        if (params.pdr) prefillParams.pdr = params.pdr;
        if (params.consumi) prefillParams.consumi = params.consumi;
        if (params.spesa) prefillParams.spesa = params.spesa;
        const prefillUrl = buildPrefillUrl(baseUrl, prefillParams);

        md += `\n🔗 [Attiva questa offerta](${prefillUrl})\n`;

        if (r.price_warning) {
          md += `\n⚠️ ${r.price_warning}\n`;
        }

        md += `\n`;
      }

      // Add prefill note
      const hasPrefill = params.nome || params.cognome || params.email;
      if (!hasPrefill) {
        md += `---\n📝 **Per precompilare il form con i dati utente**, richiama questo tool aggiungendo i parametri opzionali: nome, cognome, cf, email, tel, indirizzo, civico, citta, provincia_sigla, cap, pod, pdr.\n`;
      }
    }

    md += `\n---\n`;
    md += `## ⚠️ DA COMUNICARE ALL'UTENTE (obbligatorio)\n\n`;
    md += `Prima di attivare qualsiasi offerta, **devi** dire all'utente:\n\n`;
    md += `> "Ti ricordo che per completare l'attivazione **riceverai una email di conferma** da SwitchAI. `
      + `Dovrai cliccare sul link nell'email per confermare. `
      + `Solo dopo la tua conferma i dati verranno inoltrati al fornitore. `
      + `**Devi essere tu a verificare i dati e cliccare Invia** — io posso solo precompilare il modulo."\n`;
    md += `\n*Dati aggiornati | switchai.it*`;

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

// ── Tool 4: Sottoscrizione offerta ────────────────────────────────────

server.tool(
  "submit_subscription",
  "Invia la richiesta di attivazione di una nuova tariffa energia. I dati vengono inoltrati al fornitore e ricevi un ID di sottoscrizione.",
  {
    tariff_id: z.string().describe("ID dell'offerta scelta (dal risultato di calculate_energy_savings)"),
    tariff_name: z.string().optional().describe("Nome dell'offerta (es: 'FASTWEB ENERGIA FIX')"),
    supplier: z.string().optional().describe("Nome fornitore"),
    commodity: z.enum(["luce", "gas"]).optional().default("luce"),
    nome: z.string().describe("Nome dell'intestatario"),
    cognome: z.string().describe("Cognome dell'intestatario"),
    codice_fiscale: z.string().describe("Codice fiscale (16 caratteri)"),
    email: z.string().email().describe("Email"),
    cellulare: z.string().describe("Cellulare (es: +393401234567)"),
    titolo_immobile: z.enum(["Proprietario", "Affittuario", "Comodista", "Usufruttuario"]).optional().default("Proprietario").describe("Titolo sull'immobile"),
    indirizzo: z.string().describe("Via/Piazza della fornitura"),
    civico: z.string().describe("Numero civico"),
    citta: z.string().describe("Città della fornitura"),
    provincia_sigla: z.string().describe("Sigla provincia (2 lettere, es: MI)"),
    cap: z.string().describe("CAP (5 cifre)"),
    codice_pod: z.string().optional().describe("Codice POD per Luce (IT001E...)"),
    codice_pdr: z.string().optional().describe("Codice PDR per Gas (14 cifre)"),
    modalita_pagamento: z.enum(["SDD", "Bollettino"]).optional().default("SDD").describe("Modalità di pagamento"),
    iban: z.string().optional().describe("IBAN (se SDD)"),
    indirizzo_coincide: z.enum(["si", "no"]).optional().default("si").describe("La residenza coincide con la fornitura?"),
  },
  async (params) => {
    const data = await apiCall("/subscription/submit", "POST", {
      tariff_id: params.tariff_id,
      tariff_name: params.tariff_name,
      supplier: params.supplier,
      commodity: params.commodity,
      nome: params.nome,
      cognome: params.cognome,
      codice_fiscale: params.codice_fiscale,
      email: params.email,
      cellulare: params.cellulare,
      titolo_immobile: params.titolo_immobile,
      indirizzo: params.indirizzo,
      civico: params.civico,
      citta: params.citta,
      provincia_sigla: params.provincia_sigla,
      cap: params.cap,
      codice_pod: params.codice_pod,
      codice_pdr: params.codice_pdr,
      modalita_pagamento: params.modalita_pagamento,
      iban: params.iban,
      indirizzo_coincide: params.indirizzo_coincide,
    });

    const statusIcon = data.status === 'pending' ? '📨' : '✅';
    const md = `## ${statusIcon} Sottoscrizione\n\n`
      + `| | |\n|---|---|\n`
      + `| Stato | **${data.status}** |\n`
      + `| ID | \`${data.subscription_id}\` |\n`
      + `| Messaggio | ${data.message} |\n`;

    return {
      content: [{
        type: "text",
        text: md,
      }],
    };
  }
);

// ── Tool 5: Stato sottoscrizione ──────────────────────────────────────

server.tool(
  "get_subscription_status",
  "Verifica lo stato di una richiesta di sottoscrizione già inviata.",
  {
    subscription_id: z.string().describe("ID della sottoscrizione (restituito da submit_subscription)"),
  },
  async (params) => {
    const data = await apiCall(`/subscription/status/${params.subscription_id}`);

    return {
      content: [{
        type: "text",
        text: JSON.stringify(data, null, 2),
      }],
    };
  }
);

// ── Tool 6: Form schema (per pre-compilazione) ────────────────────────

server.tool(
  "get_subscription_form_schema",
  "Recupera lo schema del form di sottoscrizione: campi richiesti, enum validi, struttura a step. Utile per sapere quali dati servono prima di chiamare submit_subscription.",
  {},
  async () => {
    const data = await apiCall("/subscription/form-schema");

    return {
      content: [{
        type: "text",
        text: JSON.stringify(data, null, 2),
      }],
    };
  }
);

// ── Tool 7: Market Indices ─────────────────────────────────────────────

server.tool(
  "get_market_indices",
  "Recupera gli indici di mercato attuali PUN (Luce) e PSV (Gas).",
  {},
  async () => {
    const data = await apiCall("/market-indices");

    return {
      content: [{
        type: "text",
        text: JSON.stringify(data, null, 2),
      }],
    };
  }
);

// ── Avvio ─────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);

console.error("⚡ SwitchAI MCP Server avviato");
console.error(`   API: ${API_BASE}`);
console.error("   Tool: calculate_energy_savings, get_available_offers, parse_energy_bill, submit_subscription, get_subscription_status, get_subscription_form_schema, get_market_indices");
