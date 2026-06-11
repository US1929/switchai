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
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
  };
  if (body) opts.body = JSON.stringify(body);

  const res = await fetch(url, opts);
  const data = await res.json();

  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}: ${JSON.stringify(data)}`);
  }

  return data;
}

// ── MCP Server ────────────────────────────────────────────────────────

const server = new McpServer({
  name: "switchai",
  version: "1.0.0",
  description: "SwitchAI — Confronto tariffe Luce/Gas e sottoscrizione per il mercato italiano. Dati reali da oltre 44 offerte dei principali fornitori. switchai.it",
});

// ── Tool 1: Calcola risparmio ─────────────────────────────────────────

server.tool(
  "calculate_energy_savings",
  "Confronta le tariffe Luce o Gas e calcola il risparmio annuo. Ricevi le 3 migliori offerte con breakdown energetico e riepilogo in linguaggio naturale.",
  {
    commodity: z.enum(["LUCE", "GAS"]).describe("Tipo di fornitura: LUCE (elettricità) o GAS"),
    yearly_consumption_kwh: z.number().optional().describe("Consumo annuo in kWh (solo per LUCE). Es: 2700"),
    yearly_consumption_smc: z.number().optional().describe("Consumo annuo in Smc (solo per GAS). Es: 1000"),
    zone: z.enum(["NORD", "CENTRO", "SUD"]).optional().default("NORD").describe("Zona tariffaria italiana"),
    current_supplier: z.string().optional().describe("Nome del fornitore attuale (es: 'Enel Energia')"),
    current_annual_spend: z.number().optional().describe("Spesa annua attuale in €. Es: 650"),
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

    return {
      content: [{
        type: "text",
        text: JSON.stringify({
          summary: data.agent_summary,
          current_spend: data.current_spend_estimated,
          top_offers: data.results?.map(r => ({
            supplier: r.supplier,
            tariff_name: r.tariff_name,
            annual_cost: r.annual_cost_eur,
            savings_eur: r.savings_eur,
            savings_pct: r.savings_pct,
            type: r.type,
            explanation: r.breakdown?.explanation,
            activation_url: r.activation_url,
          })),
          comparison_id: data.comparison_id,
        }, null, 2),
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

    return {
      content: [{
        type: "text",
        text: JSON.stringify({
          commodity: data.commodity === "LUCE" ? "Luce ⚡" : "Gas 🔥",
          current_supplier: data.current_supplier,
          pod_pdr: data.pod_pdr,
          yearly_consumption: data.commodity === "LUCE"
            ? `${data.yearly_consumption_kwh} kWh`
            : `${data.yearly_consumption_smc} Smc`,
          current_annual_spend: `${data.current_annual_spend} €`,
          zone: data.zone,
          ready_to_compare: true,
          next_step: "Usa calculate_energy_savings con questi dati per trovare l'offerta migliore",
        }, null, 2),
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

    return {
      content: [{
        type: "text",
        text: JSON.stringify({
          status: data.status,
          subscription_id: data.subscription_id,
          message: data.message,
          next_step: "La richiesta è stata inoltrata. Il fornitore ti contatterà entro 24 ore.",
          check_status: `Usa get_subscription_status con ID ${data.subscription_id} per verificare lo stato`,
        }, null, 2),
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

// ── Avvio ─────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);

console.error("⚡ SwitchAI MCP Server avviato");
console.error(`   API: ${API_BASE}`);
console.error("   Tool: calculate_energy_savings, get_available_offers, parse_energy_bill, submit_subscription, get_subscription_status, get_subscription_form_schema");
