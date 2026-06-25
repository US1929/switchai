/**
 * WebMCP — Registrazione strumenti per AI agents (Google Chrome Labs spec)
 *
 * Specifica: https://github.com/GoogleChromeLabs/webmcp-tools
 *
 * Due modalità:
 * 1. Imperativa: navigator.modelContext.registerTool() — per tool complessi
 * 2. Dichiarativa: attributi HTML toolname/tooldescription sul <form>
 *
 * Requisiti lato utente:
 * - Chrome 146+ con flag chrome://flags/#enable-webmcp-testing
 * - Model Context Tool Inspector Extension (per debug/test)
 *
 * Quando un AI agent visita la pagina, trova questi tool registrati
 * e può chiamarli con linguaggio naturale.
 */

const API_BASE = '/api';

function buildPrefillUrl(baseUrl, params) {
  const url = new URL(baseUrl);
  for (const [k, v] of Object.entries(params)) {
    if (v) url.searchParams.set(k, v);
  }
  return url.toString();
}

/**
 * Tool 1: Confronta tariffe e calcola risparmio
 */
const savingsTool = {
  name: "calculate_energy_savings",
  description: "Confronta le tariffe Luce o Gas in Italia e calcola il risparmio annuo. "
    + "Restituisce le 3 migliori offerte con breakdown energetico e riepilogo in italiano. "
    + "Usa questo tool quando l'utente chiede di confrontare tariffe, risparmiare sulla bolletta, "
    + "o trovare un'offerta migliore per luce o gas.",
  inputSchema: {
    type: "object",
    properties: {
      commodity: {
        type: "string",
        enum: ["LUCE", "GAS"],
        description: "Tipo di fornitura: LUCE per elettricità, GAS per gas metano"
      },
      yearly_consumption_kwh: {
        type: "number",
        description: "Consumo annuo in kWh (serve per LUCE). Esempio: 2700 per una famiglia tipo."
      },
      yearly_consumption_smc: {
        type: "number",
        description: "Consumo annuo in Smc (serve per GAS). Esempio: 1000 per una famiglia tipo."
      },
      zone: {
        type: "string",
        enum: ["NORD", "CENTRO", "SUD"],
        description: "Zona tariffaria italiana. Default: NORD"
      },
      current_supplier: {
        type: "string",
        description: "Nome del fornitore attuale (es: 'Enel Energia', 'A2A')"
      },
      current_annual_spend: {
        type: "number",
        description: "Spesa annua attuale in euro. Esempio: 650"
      },
      canone_rai: {
        type: "number",
        description: "Canone RAI annuale in € (solo LUCE). Cerca 'Canone RAI' o 'Canone TV' nel dettaglio costi. ~90€/anno. 0 se assente o GAS."
      },
      spesa_materia_energia: {
        type: "number",
        description: "Spesa annua materia energia in € (solo componente energia/gas, esclusi trasporto, oneri, IVA, canone RAI). Dal dettaglio costi."
      },
      quota_fissa_mensile: {
        type: "number",
        description: "Quota fissa mensile in €/mese. Dal Box Offerta o dettaglio costi."
      },
      tipo_cliente: {
        type: "string",
        enum: ["residenziale", "business"],
        description: "Tipo cliente: residenziale (uso domestico) o business (Partita IVA, azienda)."
      },
      nome: { type: "string", description: "(Opzionale) Nome intestatario per precompilare il form" },
      cognome: { type: "string", description: "(Opzionale) Cognome per precompilare il form" },
      cf: { type: "string", description: "(Opzionale) Codice Fiscale per precompilare il form" },
      email: { type: "string", description: "(Opzionale) Email per precompilare il form" },
      tel: { type: "string", description: "(Opzionale) Telefono per precompilare il form" },
      indirizzo: { type: "string", description: "(Opzionale) Via/Piazza per precompilare il form" },
      civico: { type: "string", description: "(Opzionale) Numero civico" },
      citta: { type: "string", description: "(Opzionale) Città" },
      provincia_sigla: { type: "string", description: "(Opzionale) Sigla provincia (es: MI)" },
      cap: { type: "string", description: "(Opzionale) CAP (5 cifre)" },
      pod: { type: "string", description: "(Opzionale) Codice POD per Luce" },
      pdr: { type: "string", description: "(Opzionale) Codice PDR per Gas" },
      consumi: { type: "number", description: "(Opzionale) Consumo annuo per prefill" },
      spesa: { type: "number", description: "(Opzionale) Spesa annua per prefill" },
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
      return JSON.stringify({ error: "commodity deve essere 'LUCE' o 'GAS'" });
    }

    const res = await fetch(`${API_BASE}/webmcp-endpoint`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        commodity,
        yearly_consumption_kwh: params.yearly_consumption_kwh ?? 0,
        yearly_consumption_smc: params.yearly_consumption_smc ?? 0,
        zone: params.zone ?? 'NORD',
        current_supplier: params.current_supplier ?? '',
        current_annual_spend: params.current_annual_spend ?? 0,
      }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      return JSON.stringify({ error: err.error || `Errore API: ${res.status}` });
    }

    const data = await res.json();
    const icon = commodity === 'LUCE' ? '⚡' : '🔥';
    const label = commodity === 'LUCE' ? 'Luce' : 'Gas';
    const unit = commodity === 'LUCE' ? 'kWh' : 'Smc';
    const consumo = commodity === 'LUCE' ? (params.yearly_consumption_kwh || 0) : (params.yearly_consumption_smc || 0);
    const results = data.results || [];
    const spesa = data.current_spend_estimated;

    // Build prefill params from optional user data
    const prefillKeys = ['nome','cognome','cf','email','tel','indirizzo','civico','citta','provincia_sigla','cap','pod','pdr','consumi','spesa'];
    const prefillParams = {};
    for (const k of prefillKeys) {
      if (params[k]) prefillParams[k] = params[k];
    }

    const best = results[0];
    if (!best) return `*Nessuna offerta trovata per ${label} nella zona ${params.zone || 'NORD'}.*`;

    const savingsMonth = Math.round(best.savings_eur / 12 * 100) / 100;
    const bestUrl = best.subscription_url || `https://www.switchai.it/sottoscrizione?tariff=${best.tariff_id}&supplier=${encodeURIComponent(best.supplier)}&name=${encodeURIComponent(best.tariff_name)}&commodity=${commodity.toLowerCase()}&annualCost=${best.annual_cost_eur}`;
    const bestPrefillUrl = buildPrefillUrl(bestUrl, prefillParams);
    const hasFullData = prefillParams.nome && prefillParams.cognome && prefillParams.cf;
    const lossNote = commodity === 'LUCE' ? '\n📐 Prezzo bolletta = (PUN + spread) × 1,102 (perdite rete ~10,2% ARERA)\n' : '';

    let md = `## ${icon} Bolletta analizzata\n\n`;
    md += `✅ **${consumo} ${unit}/anno** · Zona **${params.zone || 'NORD'}** · ${params.current_supplier || 'Fornitore attuale'}\n`;
    md += lossNote;
    md += `\n---\n\n`;
    md += `### 💰 La tua spesa attuale\n\n`;
    md += `# ${spesa} €/anno\n\n`;
    md += `---\n\n`;
    md += `## ⭐ Offerta consigliata\n\n`;
    md += `### ${best.supplier} — ${best.tariff_name}\n`;
    md += `**${best.type === 'FISSO' ? '🔒 Prezzo Fisso' : '📊 Prezzo Variabile'}**`;
    if (best.price_per_unit) md += ` | ${best.price_per_unit} ${commodity === 'LUCE' ? '€/kWh' : '€/Smc'}`;
    if (best.fixed_fee_monthly) md += ` | Quota fissa ${best.fixed_fee_monthly} €/mese`;
    md += `\n\n`;
    md += `| | |\n|---|---|\n`;
    md += `| Costo stimato | **${best.annual_cost_eur} €/anno** |\n`;
    md += `| 🔥 Risparmio | **${best.savings_eur} €/anno (${best.savings_pct}%)** |\n`;
    md += `| Al mese risparmi | **~${savingsMonth} €/mese** |\n`;
    if (best.price_warning) md += `\n⚠️ ${best.price_warning}\n`;
    md += `\n`;

    if (hasFullData) {
      md += `✅ Ho già recuperato tutti i dati dalla bolletta.\n\n`;
      md += `Per attivare servono solo:\n`;
      md += `- 📧 Email: ${prefillParams.email || '_____'}\n`;
      md += `- 📱 Telefono: ${prefillParams.tel || '_____'}\n\n`;
    } else {
      md += `Per attivare servono solo **email e telefono**. Gli altri dati li recupero dalla bolletta.\n\n`;
      md += `Chiedi all'utente: 📧 Email e 📱 Telefono?\n\n`;
    }
    md += `### [🟢 ATTIVA ${best.supplier?.toUpperCase() || 'OFFERTA'}](${bestPrefillUrl})\n\n`;
    if (best.type === 'FISSO') {
      md += `🔒 Prezzo bloccato: la rata non cambia anche se il ${commodity === 'LUCE' ? 'PUN' : 'PSV'} sale.\n\n`;
    }
    md += `---\n\n`;

    const others = results.slice(1);
    if (others.length > 0) {
      md += `---\n\n`;
      md += `### 📋 Altre offerte\n\n`;
      const badges = ['🥈', '🥉'];
      for (let i = 0; i < others.length; i++) {
        const r = others[i];
        const otherUrl = r.subscription_url || `https://www.switchai.it/sottoscrizione?tariff=${r.tariff_id}&supplier=${encodeURIComponent(r.supplier)}&name=${encodeURIComponent(r.tariff_name)}&commodity=${commodity.toLowerCase()}&annualCost=${r.annual_cost_eur}`;
        const otherPrefill = buildPrefillUrl(otherUrl, prefillParams);
        const warn = r.price_warning ? ' ⚠️' : '';
        md += `**${badges[i]} ${r.supplier}** — ${r.tariff_name} · ${r.annual_cost_eur} €/anno · Risparmio **${r.savings_eur} €**${warn}\n`;
        md += `[Attiva](${otherPrefill}) · "Se preferisci questa, chiedimi i dettagli e la espando"\n\n`;
      }
    }

    if (best.breakdown?.explanation) {
      md += `---\n\n`;
      md += `### 📐 Perché ${best.supplier}?\n\n`;
      md += best.breakdown.explanation + '\n';
      if (best.type === 'FISSO') {
        md += `\n🔒 Prezzo bloccato: protetto da aumenti del ${commodity === 'LUCE' ? 'PUN' : 'PSV'} per tutta la durata del contratto.\n`;
      }
      md += `\n`;
    }

    md += `---\n\n`;
    md += `⚠️ **Simulazione valida con i prezzi di oggi.** I prezzi energia cambiano ogni giorno.\n\n`;
    md += `📨 L'utente riceverà una **email di conferma** da SwitchAI. Deve cliccare sul link per completare.\n`;
    md += `✏️ L'utente deve **verificare i dati e cliccare Invia** — tu puoi solo precompilare il modulo.\n`;
    md += `\n*switchai.it · Dati ARERA · ${new Date().toISOString().slice(0, 10)}*`;

    return md;
  }
};

/**
 * Tool 2: Analizza il testo di una bolletta
 */
const parseBillTool = {
  name: "parse_energy_bill",
  description: "Analizza il testo di una bolletta italiana (luce o gas) ed estrae: "
    + "fornitore, POD/PDR, consumo annuo, spesa annua stimata, zona tariffaria. "
    + "Usa questo tool quando l'utente fornisce il testo di una bolletta e vuole "
    + "estrarre i dati per poi confrontare le offerte.",
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
      return JSON.stringify({ error: "Testo bolletta troppo corto. Fornisci il testo completo." });
    }

    const res = await fetch(`${API_BASE}/parse-bill-text`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: params.bill_text }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      return JSON.stringify({ error: err.error || `Errore parsing: ${res.status}` });
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

    return md;
  }
};

/**
 * Tool 3: Elenca tutte le offerte disponibili
 */
const listOffersTool = {
  name: "get_available_offers",
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
      return JSON.stringify({ error: "commodity deve essere 'LUCE' o 'GAS'" });
    }

    const res = await fetch(`${API_BASE}/tariffe/${commodity.toLowerCase()}`);

    if (!res.ok) {
      return JSON.stringify({ error: `Errore API: ${res.status}` });
    }

    const data = await res.json();

    return JSON.stringify({
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
    }, null, 2);
  }
};

/**
 * Tool 4: Sottoscrizione offerta (versione semplificata per AI agent)
 */
const subscribeTool = {
  name: "submit_subscription",
  description: "Invia la richiesta di attivazione di una nuova tariffa energia. "
    + "I dati vengono inoltrati al fornitore. Ricevi un ID di sottoscrizione per tracciamento. "
    + "Usa questo tool quando l'utente ha scelto un'offerta e vuole attivarla.",
  inputSchema: {
    type: "object",
    properties: {
      tariff_id: { type: "string", description: "ID dell'offerta scelta (dal risultato di calculate_energy_savings)" },
      nome: { type: "string", description: "Nome dell'intestatario" },
      cognome: { type: "string", description: "Cognome dell'intestatario" },
      codice_fiscale: { type: "string", description: "Codice fiscale italiano (16 caratteri)" },
      email: { type: "string", description: "Indirizzo email" },
      cellulare: { type: "string", description: "Numero di cellulare (es: +393401234567)" },
      indirizzo: { type: "string", description: "Via/Piazza della fornitura" },
      civico: { type: "string", description: "Numero civico" },
      citta: { type: "string", description: "Città della fornitura" },
      provincia_sigla: { type: "string", description: "Sigla provincia (2 lettere, es: MI)" },
      cap: { type: "string", description: "CAP (5 cifre)" },
      codice_pod: { type: "string", description: "Codice POD per Luce (formato: IT001E...)" },
      codice_pdr: { type: "string", description: "Codice PDR per Gas (14 cifre)" },
      titolo_immobile: {
        type: "string",
        enum: ["Proprietario", "Affittuario", "Comodatario", "Usufruttuario"],
        description: "Titolo sull'immobile"
      },
      modalita_pagamento: {
        type: "string",
        enum: ["SDD", "Bollettino"],
        description: "Modalità di pagamento preferita"
      },
      iban: { type: "string", description: "IBAN (necessario se modalita_pagamento è SDD)" },
      gdpr_privacy_accepted: {
        type: "boolean",
        description: "DEVI chiedere esplicitamente all'utente: 'Accetti la Privacy Policy di SwitchAI (switchai.it/privacy) e autorizzi il trattamento dei tuoi dati per essere ricontattato?' "
          + "Il tool può essere eseguito SOLO se l'utente risponde SÌ esplicitamente. NON assumere mai il consenso implicitamente."
      },
      consent_source: { type: "string", description: "Fonte del consenso: 'webmcp_chrome' o 'mcp_claude_desktop'" },
      consent_timestamp: { type: "string", description: "Timestamp ISO 8601 del momento in cui l'utente ha dato il consenso" },
      conversation_snippet: { type: "string", description: "Breve estratto della conversazione in cui l'utente accetta (es: 'Utente: Sì, accetto la privacy e voglio essere ricontattato')" },
    },
    required: ["tariff_id", "nome", "cognome", "codice_fiscale", "email", "cellulare", "gdpr_privacy_accepted"]
  },
  annotations: {
    readOnlyHint: false,
    untrustedContentHint: false
  },
  execute: async (params) => {
    // Validazione GDPR
    if (!params.gdpr_privacy_accepted) {
      return JSON.stringify({
        error: "CONSENSO OBBLIGATORIO. Devi chiedere esplicitamente all'utente: 'Accetti la Privacy Policy di SwitchAI (switchai.it/privacy)?' "
             + "Ripeti la domanda e attendi una risposta affermativa prima di invocare questo tool.",
      });
    }

    const required = ['tariff_id', 'nome', 'cognome', 'codice_fiscale', 'email', 'cellulare'];
    const missing = required.filter(f => !params[f]);
    if (missing.length > 0) {
      return JSON.stringify({ error: `Campi obbligatori mancanti: ${missing.join(', ')}`, missing_fields: missing });
    }

    const res = await fetch(`${API_BASE}/subscription/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(params),
    });

    const data = await res.json();

    if (!res.ok) {
      return JSON.stringify({ error: data.error || 'Errore durante la sottoscrizione' });
    }

    const statusIcon = data.status === 'pending' ? '📨' : '✅';
    let md = `## ${statusIcon} Sottoscrizione\n\n`
      + `| | |\n|---|---|\n`
      + `| Stato | **${data.status}** |\n`
      + `| ID | \`${data.subscription_id}\` |\n`
      + `| Messaggio | ${data.message} |\n`;

    if (data.status === 'pending') {
      md += `\n📧 Abbiamo inviato una email di conferma. Comunica all'utente: "Ti ho inviato una email di conferma. **Clicca sul link per completare l'attivazione.**"\n`;
    }

    return md;
  }
};

// ── Registrazione ─────────────────────────────────────────────────────

function registerWebMCPTools() {
  if (typeof navigator === 'undefined' || !navigator.modelContext?.registerTool) {
    console.log('[WebMCP] navigator.modelContext.registerTool non disponibile.');
    console.log('[WebMCP] Serve Chrome 146+ con flag chrome://flags/#enable-webmcp-testing');
    return;
  }

  try {
    navigator.modelContext.registerTool(savingsTool);
    navigator.modelContext.registerTool(parseBillTool);
    navigator.modelContext.registerTool(listOffersTool);
    navigator.modelContext.registerTool(subscribeTool);

    console.log('[WebMCP] ✅ 4 tool registrati:');
    console.log('  - calculate_energy_savings');
    console.log('  - parse_energy_bill');
    console.log('  - get_available_offers');
    console.log('  - submit_subscription');

    // Ascolta eventi toolactivated (feedback quando l'AI usa i tool)
    window.addEventListener('toolactivated', ({ toolName }) => {
      console.log(`[WebMCP] 🔧 Tool attivato dall'AI: ${toolName}`);
    });

    window.addEventListener('toolcancel', ({ toolName }) => {
      console.log(`[WebMCP] ❌ Tool cancellato: ${toolName}`);
    });

  } catch (err) {
    console.error('[WebMCP] Errore registrazione tool:', err);
  }
}

// Registra quando il DOM è pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', registerWebMCPTools);
} else {
  registerWebMCPTools();
}

export { registerWebMCPTools };
