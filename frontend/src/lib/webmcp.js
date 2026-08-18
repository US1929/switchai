/**
 * WebMCP — Registrazione strumenti per AI agents
 *
 * Specifica: https://webmachinelearning.github.io/webmcp (W3C Web Machine Learning CG, Draft)
 *
 * Modalità supportata:
 * 1. Imperativa: document.modelContext.registerTool() — per tool complessi
 *
 * Requisiti lato utente:
 * - Chrome 146+ con flag chrome://flags/#enable-webmcp-testing
 * - Model Context Tool Inspector Extension (per debug/test)
 *
 * Nota: document.modelContext è [SecureContext, SameObject] su Document; il fallback
 * navigator.modelContext è solo difensivo (non richiesto dalla spec, zero rischio).
 *
 * Quando un AI agent visita la pagina, trova questi tool registrati
 * e può chiamarli con linguaggio naturale.
 */

const API_BASE = '/api';

/**
 * Tool 1: Confronta tariffe e calcola risparmio
 */
const savingsTool = {
  name: "calculate_energy_savings",
  title: "Confronta tariffe e risparmio",
  description: "Confronta tariffe Luce/Gas e calcola il risparmio annuo. Accetta il testo di una bolletta (bill_text) oppure dati già estratti. "
    + "MODALITÀ 1 (bolletta): passa commodity + bill_text — il tool estrae automaticamente consumi, spesa e zona. "
    + "MODALITÀ 2 (dati noti): passa commodity + yearly_consumption_kwh + current_annual_spend + zone. "
    + "ESEMPIO: {commodity:'LUCE', yearly_consumption_kwh:2700, zone:'NORD', current_annual_spend:850}",
  inputSchema: {
    type: "object",
    properties: {
      commodity: {
        type: "string",
        enum: ["LUCE", "GAS"],
        description: "LUCE per elettricità o GAS per gas metano",
        default: "LUCE"
      },
      bill_text: {
        type: "string",
        description: "(Solo se hai il testo della bolletta) Passa il testo completo per estrarre automaticamente i dati."
      },
      yearly_consumption_kwh: {
        type: "number",
        description: "Consumo annuo in kWh (LUCE). Es: 2700.",
        default: 2700
      },
      yearly_consumption_smc: {
        type: "number",
        description: "Consumo annuo in Smc (GAS). Es: 1000.",
        default: 1000
      },
      zone: {
        type: "string",
        enum: ["NORD", "CENTRO", "SUD"],
        description: "Zona tariffaria italiana.",
        default: "NORD"
      },
      current_supplier: {
        type: "string",
        description: "Nome fornitore attuale es: 'Enel Energia'"
      },
      current_annual_spend: {
        type: "number",
        description: "Spesa annua in € es: 850",
        default: 650
      },
    },
    required: ["commodity"]
  },
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true
  },
  execute: async (params) => {
    const commodity = params.commodity?.toUpperCase();
    if (!['LUCE', 'GAS'].includes(commodity)) {
      return { content: [{ type: "text", text: JSON.stringify({ error: "commodity deve essere 'LUCE' o 'GAS'" }) }] };
    }

    // V2 /api/analyze: unico endpoint con honesty system (switch/evaluate/stay),
    // cost_breakdown, bill_attualization e parsing bill_text nativo.
    // Lo schema input del tool è in inglese; il backend /api/analyze accetta
    // direttamente gli alias inglesi (yearly_consumption_kwh, zone, current_annual_spend, current_supplier).
    const body = { commodity };
    if (params.bill_text && params.bill_text.length > 20) {
      body.bill_text = params.bill_text;
    } else {
      const consumo = params.yearly_consumption_kwh ?? params.yearly_consumption_smc;
      if (!consumo || consumo <= 0) {
        return { content: [{ type: "text", text: JSON.stringify({ error: "Fornire bill_text oppure yearly_consumption_kwh/yearly_consumption_smc > 0" }) }] };
      }
      if (commodity === 'LUCE') body.yearly_consumption_kwh = consumo;
      else body.yearly_consumption_smc = consumo;
      if (params.current_annual_spend) body.current_annual_spend = params.current_annual_spend;
      if (params.zone) body.zone = params.zone;
      if (params.current_supplier) body.current_supplier = params.current_supplier;
    }

    const res = await fetch(`${API_BASE}/analyze`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      return { content: [{ type: "text", text: JSON.stringify({ error: err.error || `Errore API: ${res.status}` }) }] };
    }

    const data = await res.json();
    const icon = commodity === 'LUCE' ? '⚡' : '🔥';
    const label = commodity === 'LUCE' ? 'Luce' : 'Gas';
    const results = data.top3 || [];

    let md = `## ${icon} ${label} — Confronto tariffe\n\n`;
    if (data.agent_summary) md += `${data.agent_summary}\n\n`;

    const best = results[0];
    if (!best) {
      md += `Nessuna offerta trovata per ${label}.`;
      return { content: [{ type: "text", text: md }] };
    }

    md += `### 🏆 Migliori 3 offerte\n\n`;
    md += `| | Fornitore | Offerta | Costo annuo | Risparmio |\n|---|---|---|---|---|\n`;
    const badges = ['🥇', '🥈', '🥉'];
    results.slice(0, 3).forEach((r, i) => {
      md += `| ${badges[i]} | **${r.supplier}** | ${r.tariff_name} | ${r.annual_cost_eur} € | **${r.savings_eur} €** |\n`;
    });

    const bestUrl = best.affiliate_url || best.subscription_url || best.url_offerta || `https://www.switchai.it/offerta/${best.tariff_id}`;
    md += `\n🔗 **[Attiva l'offerta migliore sul sito del fornitore](${bestUrl})**\n`;
    md += `\n*switchai.it · Dati ARERA · ${new Date().toISOString().slice(0, 10)}*`;

    return { content: [{ type: "text", text: md }] };
  }
};

/**
 * Tool 2: Analizza il testo di una bolletta
 */
const parseBillTool = {
  name: "parse_energy_bill",
  title: "Analizza bolletta",
  description: "Analizza il testo di una bolletta italiana (luce o gas) ed estrae: "
    + "fornitore, POD/PDR, consumo annuo, spesa annua stimata, zona tariffaria. "
    + "DOPO aver usato questo tool, usa calculate_energy_savings con i dati estratti per confrontare le offerte. "
    + "ESEMPIO: l'utente incolla la bolletta → tu chiami parse_energy_bill → poi calculate_energy_savings con i risultati.",
  inputSchema: {
    type: "object",
    properties: {
      bill_text: {
        type: "string",
        description: "Testo completo della bolletta da analizzare. Puoi estrarre il testo da un PDF."
      }
    },
    required: ["bill_text"]
  },
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: false
  },
  execute: async (params) => {
    if (!params.bill_text || params.bill_text.length < 20) {
      return { content: [{ type: "text", text: JSON.stringify({ error: "Testo bolletta troppo corto. Fornisci il testo completo." }) }] };
    }

    const res = await fetch(`${API_BASE}/parse-bill-text`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: params.bill_text }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
        return { content: [{ type: "text", text: JSON.stringify({ error: err.error || `Errore parsing: ${res.status}` }) }] };
    }

    const data = await res.json();
    const icon = data.commodity === 'LUCE' ? '⚡' : '🔥';
    const label = data.commodity === 'LUCE' ? 'Luce' : 'Gas';
    const unit = data.commodity === 'LUCE' ? 'kWh' : 'Smc';
    const consumo = data.commodity === 'LUCE' ? data.yearly_consumption_kwh : data.yearly_consumption_smc;

    const md = `## ${icon} Dati Bolletta ${label}\n\n`
      + `| | |\n|---|---|\n`
      + `| Fornitore | **${data.current_supplier}** |\n`
      + `| POD/PDR | ${data.pod_pdr || 'non rilevato'} |\n`
      + `| Consumo annuo | **${consumo} ${unit}** |\n`
      + `| Spesa annua | **${data.current_annual_spend} €** |\n`
      + `| Zona | ${data.zone || 'NORD'} |\n`
      + `\n✅ Dati pronti per il confronto. Usa **calculate_energy_savings** con:\n`
      + `\`commodity: "${data.commodity}", yearly_consumption_${unit}: ${consumo}, zone: "${data.zone || 'NORD'}", current_annual_spend: ${data.current_annual_spend}\``;

    return { content: [{ type: "text", text: md }] };
  }
};

/**
 * Tool 3: Elenca tutte le offerte disponibili
 */
const listOffersTool = {
  name: "get_available_offers",
  title: "Elenco offerte",
  description: "Recupera tutte le offerte disponibili per Luce o Gas in Italia. "
    + "Restituisce nome fornitore, nome offerta, tipo (fisso/variabile), prezzo per unità e costo fisso mensile. "
    + "Usa questo tool quando l'utente vuole vedere tutte le offerte disponibili senza fare un calcolo specifico.",
  inputSchema: {
    type: "object",
    properties: {
      commodity: {
        type: "string",
        enum: ["LUCE", "GAS"],
        description: "LUCE per elettricità, GAS per gas metano"
      }
    },
    required: ["commodity"]
  },
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: false
  },
  execute: async (params) => {
    const commodity = params.commodity?.toUpperCase();
    if (!['LUCE', 'GAS'].includes(commodity)) {
      return { content: [{ type: "text", text: JSON.stringify({ error: "commodity deve essere 'LUCE' o 'GAS'" }) }] };
    }

    const res = await fetch(`${API_BASE}/tariffe/${commodity.toLowerCase()}`);

    if (!res.ok) {
      return { content: [{ type: "text", text: JSON.stringify({ error: `Errore API: ${res.status}` }) }] };
    }

    const data = await res.json();

    return { content: [{ type: "text", text: JSON.stringify({
      commodity: data.commodity,
      total_offers: data.count,
      offers: (data.offers || []).map(o => ({
        id: o.id,
        supplier: o.supplier_name,
        name: o.name,
        type: o.type === 'FISSO' ? 'Prezzo fisso' : 'Prezzo variabile',
        price_per_unit: commodity === 'LUCE'
          ? `${o.price_mono_kwh} €/kWh`
          : `${o.price_smc} €/Smc`,
        fixed_fee_monthly: `${o.fixed_fee_monthly} €/mese`,
      })),
    }, null, 2) }] };
  }
};

/**
 * Tool 5: Indici di mercato PUN/PSV
 */
const marketIndicesTool = {
  name: "get_market_indices",
  title: "Indici di mercato PUN/PSV",
  description: "Recupera PUN (prezzo energia elettrica all'ingrosso) e PSV (prezzo gas) correnti, aggiornati giornalmente dal sync ARERA notturno. Usa questo tool quando l'utente chiede il prezzo del kWh oggi, il prezzo del gas, o gli indici di mercato.",
  inputSchema: {
    type: "object",
    properties: {},
  },
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true
  },
  execute: async () => {
    const res = await fetch(`${API_BASE}/market-indices`);
    if (!res.ok) {
      return { content: [{ type: "text", text: JSON.stringify({ error: `Errore API: ${res.status}` }) }] };
    }
    const data = await res.json();
    return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
  }
};

// ── Registrazione ─────────────────────────────────────────────────────

async function registerWebMCPTools() {
  // Chrome 150+: document.modelContext, Chrome 146-149: navigator.modelContext
  const ctx = document.modelContext || navigator.modelContext || {};
  if (!ctx.registerTool) {
    console.log('[WebMCP] API modelContext non disponibile. Serve Chrome 146+ con flag enable-webmcp-testing.');
    return;
  }

  const tools = [savingsTool, parseBillTool, listOffersTool, marketIndicesTool];
  // Il campo `title` è opzionale nella spec ma alcune build Chrome early-flag
  // lo rigettavano. Feature-check: se il primo registerTool() rejecta con title,
  // ri-registra tutto senza title.
  const stripTitle = (t) => {
    const { title, ...rest } = t;
    return rest;
  };

  try {
    await ctx.registerTool(tools[0]);
    // Primo OK → registra i restanti con title
    for (let i = 1; i < tools.length; i++) {
      await ctx.registerTool(tools[i]);
    }
  } catch (err) {
    // Fallback senza title (bug Chrome early-flag)
    console.warn('[WebMCP] registerTool con title fallito, riprovo senza title:', err);
    try {
      for (const t of tools) {
        await ctx.registerTool(stripTitle(t));
      }
    } catch (err2) {
      console.error('[WebMCP] Errore registrazione tool:', err2);
      return;
    }
  }

  console.log('[WebMCP] ✅ 4 tool registrati:');
  console.log('  - calculate_energy_savings');
  console.log('  - parse_energy_bill');
  console.log('  - get_available_offers');
  console.log('  - get_market_indices');
}

// Registra quando il DOM è pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', registerWebMCPTools);
} else {
  registerWebMCPTools();
}

export { registerWebMCPTools };
