# SwitchAI — Documento Completo di Progetto

> **Dominio**: [switchai.it](https://www.switchai.it) — attivo su OVH Pro Web Hosting  
> **Stack**: React 19 + Vite 8 | PHP 8.5 API | WebMCP (Google Chrome Labs) | MCP Server (PHP + Node.js) | Tailwind CSS 4  
> **Design**: ispirato a Switcho.it + Billoo.it, card allineate a ComparaSemplice  
> **Ultimo aggiornamento**: 29 Giugno 2026
> **Versione**: 5.4.0

---

## 0. Cos'è SwitchAI e perché esiste

SwitchAI è un **motore di confronto tariffe energia** progettato per essere usato dagli **AI agent** (Claude, ChatGPT, Gemini) prima che dagli umani.

### Tre modalità d'uso

| Chi | Come | Strumento |
|-----|------|-----------|
| **LLM / AI Agent** | L'utente dà la bolletta al chatbot → l'AI estrae i dati → chiama l'API SwitchAI → mostra il risparmio → precompila il form di attivazione | `/mcp`, `/api/analyze`, WebMCP |
| **Utente umano (manuale)** | Inserisce manualmente consumo e spesa sul sito → confronta offerte → si sottoscrive | `switchai.it` |
| **Utente umano (prefill)** | L'LLM ha precompilato il form con i dati della bolletta → l'utente verifica, completa e invia | `/sottoscrizione?nome=...&pod=...` |

### Perché questa architettura

Dopo aver testato 10 bollette PDF reali (Enel, Octopus, A2A, NeN, Eni Plenitude), abbiamo scoperto che:

1. **Il parsing PDF lato server è inaffidabile su OVH** — hosting condiviso senza `pdftotext`, senza Python, senza OCR. Alcuni PDF funzionano, altri no.
2. **Gli LLM sono già perfetti per estrarre dati dalle bollette** — Claude, GPT e Gemini leggono PDF nativamente e estraggono consumi, costi, POD, nome, indirizzo con precisione quasi perfetta.
3. **Non ha senso competere con gli LLM sul loro terreno** — meglio dare loro un'API pulita e lasciare che facciano il lavoro sporco.

**Decisione architetturale**: SwitchAI NON tenta di sostituire l'LLM nel parsing. SwitchAI è il motore di calcolo che l'LLM interroga DOPO aver estratto i dati. L'LLM estrae ANCHE i dati personali (nome, indirizzo, POD) e li usa per precompilare il form di sottoscrizione, con un guardrail privacy: i dati servono solo per l'attivazione e non vengono conservati dopo la sessione.

---

## 1. Architettura

```
UTENTE → carica bolletta in Claude/ChatGPT
   ↓
LLM → estrae: nome, cognome, indirizzo, POD, consumo, spesa, zona
   ↓
LLM → rassicura: "I tuoi dati servono solo per l'attivazione, non vengono conservati"
   ↓
LLM → chiama SwitchAI API:
   POST /api/analyze  (REST — solo dati numerici)
   POST /mcp          (MCP Server — solo dati numerici)
   WebMCP             (browser agent)
   ↓
SwitchAI → confronta 5.000+ offerte (da sync ARERA quotidiano) → restituisce top 3 + risk + agent_summary + subscription_url
   ↓
LLM → presenta il risultato all'utente in italiano
   ↓
[Se l'utente vuole attivare] → LLM elenca i dati estratti e chiede consenso:
   "Vuoi che precompili il modulo con questi dati? Dovrai verificare e inviare tu.
    Riceverai una mail di conferma prima dell'inoltro."
   ↓
LLM → costruisce URL precompilato: /sottoscrizione?tariff=ID&...&nome=Mario&pod=IT001E...
   ↓
Utente → apre il form precompilato → verifica → completa → invia → double opt-in GDPR
```

### Canali di accesso

| Canale | Tecnologia | Requisiti |
|--------|-----------|-----------|
| **MCP Server** | JSON-RPC 2.0 via HTTP POST | Qualsiasi client MCP |
| **WebMCP** | `navigator.modelContext.registerTool()` | Chrome 146+ |
| **REST API** | JSON over HTTPS | Qualsiasi client HTTP |
| **Website** | React SPA + form manuale/prefill | Qualsiasi browser |

---

## 2. API Endpoints

### V2 — Endpoint Unificato (raccomandato per LLM)

```
POST /api/analyze
```

Una chiamata sostituisce 2-3 round-trip. Accetta `bill_text` o dati strutturati.

**Input**:
```json
{
  "commodity": "LUCE",
  "consumo_annuo_kwh": 2700,
  "spesa_annua_eur": 650,
  "zona": "NORD"
}
```

**Output** (~300 token compact):
```json
{
  "bill_token": "sha256:...",
  "profile": { "commodity": "LUCE", "consumo_annuo": 2700, "spesa_annua_eur": 650 },
  "top3": [{ "supplier": "Fastweb", "annual_cost_eur": 240, "savings_eur": 410, "subscription_url": "https://..." }],
  "risk": { "raccomandazione": "fisso", "motivazione": "PUN volatile (60%)" },
  "agent_summary": "Spesa attuale 650€/anno. Migliore: Fastweb 240€/anno, risparmio 410€ (63%).",
  "why_better": { "savings_breakdown": {...}, "cost_comparison": {...}, "key_reasons": [...] },
  "cost_breakdown": { "current": {...}, "best_offer": {...}, "chart_data": {...} },
  "bill_attualization": { "bolletta_originale": {...}, "oggi": {...}, "confronto": {...} },
  "honesty": { "recommendation": "switch|evaluate|stay", "badge": "✅ CONVIENE" },
  "_prefill_instructions": "Parametri URL supportati: nome, cognome, cf, email, tel, indirizzo..."
}
```

### MCP Server — Flusso LLM-nativo

```
POST /mcp
```

4 tool JSON-RPC 2.0. **L'LLM estrae i dati personali dalla bolletta**, il tool riceve solo dati numerici (consumo, spesa, zona) e restituisce le offerte con `subscription_url`.

**Flusso corretto per l'LLM** (istruito via tool description):

1. **Estrai tu (LLM)** i dati personali: nome, cognome, indirizzo, civico, CAP, città, provincia, POD/PDR, consumo annuo, spesa annua
2. **Rassicura l'utente**: "Questi dati vengono usati solo per l'attivazione e non vengono conservati dopo la sessione"
3. **Chiama il tool** passando solo consumi, spesa e zona (non il testo integrale)
4. **Se l'utente vuole attivare**, elenca i dati estratti e chiedi: "Vuoi che precompili il modulo? Dovrai verificare e inviare tu. Riceverai una mail di conferma."
5. **Costruisci l'URL** aggiungendo al `subscription_url` i query params: nome, cognome, cf, email, tel, indirizzo, civico, citta, provincia, provincia_sigla, cap, pod, pdr, consumi, spesa

Tool disponibili:
- `analyze_energy_bill` — confronto + risk + subscription_url + prefill_instructions
- `get_available_offers` — 25 luce + 19 gas
- `get_market_indices` — PUN/PSV live
- `get_subscription_form_schema` — schema form 4 step

**Registrato su**: npm (`@us1929/switchai-mcp`), Smithery, GitHub, Reddit r/mcp

### Tutti gli endpoint (21)

Tariffe: `/api/health`, `/api/status`, `/api/tariffe/luce`, `/api/tariffe/gas`, `/api/fornitori`, `/api/webmcp-endpoint`, `/api/market-indices`, `/api/analyze`

Bollette: `/api/parse-bill-text`, `/api/parse-bill`, `/api/analyze-bill`

Sottoscrizione: `/api/subscription/submit`, `/api/subscription/conferma`, `/api/subscription/status/{id}`, `/api/subscription/form-schema`

Sistema: `/api/auth/login`, `/api/auth/verify`, `/api/stats/traffic`, `/api/test-email`, `/api/trigger-scraper`, `/api/arera-constants`

Admin B2B: `/api/admin/api-keys`, `/api/admin/api-keys/create`, `/api/admin/api-keys/{hash}`

---

## 3. Parser ARERA 3.0

Basato sulla Delibera 501/2014/R/com (Bolletta 2.0).

### Priorità di matching

1. **"Consumo annuo" esplicito** (sezione ARERA standard) → confidenza 1.0
2. Pattern secondari → confidenza 0.4-0.8
3. Default ARERA (2700 kWh / 1000 Smc) → confidenza 0.2

### Test su 10 bollette reali

| # | Fornitore | Tipo | Consumo | POD/PDR | Stampa |
|---|-----------|------|---------|---------|:---:|
| 1 | A2A Energia | GAS | 861 Smc | 00102400093892 | ✅ |
| 2 | A2A Energia | LUCE | 2.997 kWh | IT006E00093892 | ✅ |
| 3 | Enel Energia | LUCE | 1.717 kWh | IT001E19943343 | ✅ |
| 4 | Octopus Energy | LUCE | 2.810 kWh | IT012E00550124 | ✅ |
| 5 | Octopus Energy | LUCE | 2.700 kWh | IT012E00550124 | ✅ |
| 6 | Octopus Energy | LUCE | 2.714 kWh | IT012E00550124 | ✅ |
| 7 | Octopus Energy | LUCE | 2.858 kWh | IT012E00550124 | ✅ |
| 8 | NeN Energia | GAS | 466 Smc | 05260200787772 | ✅ |
| 9 | Eni Plenitude | LUCE | 1.205 kWh | IT012E00361856 | ✅ |
| 10 | Eni Plenitude | LUCE | 1.210 kWh | IT012E00361856 | ✅ |

**Risultato**: 10/10 consumi corretti. Il parser gestisce bollette combinate LUCE+GAS e formati "Consumo annuo dal GG/MM/AAAA".

**Nota**: Il parser PHP estrae solo dati tecnici (consumi, POD, spesa, zona). I dati personali (nome, indirizzo) vengono estratti dall'LLM, che è molto più preciso su testo non strutturato.

### Confidence scoring + LLM advice

Ogni campo ha un punteggio 0-1. Se <0.5, l'API suggerisce all'LLM cosa chiedere all'utente.

### Auto-learning

Template salvati in `data/templates/{fingerprint}.json` per migliorare parsing futuro.

---

## 4. Perché abbiamo tolto il parsing PDF lato server

1. **OVH shared hosting non ha `pdftotext`** — il comando non è disponibile
2. **Il parsing PHP nativo funziona solo su alcuni PDF** — Enel sì, Octopus no
3. **Niente OCR senza Python** — impossibile su hosting condiviso
4. **Gli LLM fanno meglio** — Claude/GPT/Gemini estraggono dati da PDF con precisione superiore

**Conclusione**: il parsing PDF lato server è stato rimosso dal frontend. L'API `/api/parse-bill` e `/api/parse-bill-text` restano disponibili per chi vuole testarle, ma il flusso principale è: LLM estrae → chiama `/api/analyze`.

---

## 5. PUN/PSV — Metodo Forward ARERA + MarketSignal

### Priorità PUN (v5.4.0)

Il calcolo confronto usa il PUN in questo ordine:

1. **PUN forward ARERA** — da `data/offerte/config.json`, salvato automaticamente a ogni sync mensile. È il valore ufficiale del Portale Offerte (media forward 4 trimestri solari). Valido per 60 giorni.
2. **PUN spot live** — da `portaleenergia.it/api/dashboard?period=today` (fallback se il forward non è disponibile o è scaduto).

**Metodo simmetrico**: stesso PUN per entrambi i lati del confronto (spesa attuale e nuove offerte). Il risparmio riflette solo differenze contrattuali (spread + quota fissa), non oscillazioni di mercato.

### Fonte Dati

- **PUN forward**: Portale Offerte ARERA → incluso in ogni offerta variabile (campo `Pun`) → estratto da `arera_sync.php` e salvato in `config.json` con timestamp
- **PUN/PSV spot**: `portaleenergia.it/api/dashboard?period=today` con cache 24h ±3 ore
- **Storico**: `data/market_history.json` (ultimi 90 giorni) per trend analysis
- **Warning visibile**: se il forward non è disponibile, l'`agent_summary` include: *"⚠️ PUN forward ARERA non disponibile (sync scaduto o assente). Esegui il sync ARERA dal pannello Admin per aggiornare."*
- **Badge fonte**: `GET /api/market-indices` include `pun_source` (`forward_arera` o `spot_live`), `pun_forward_label`, `pun_forward_age_days`

### Widget MarketSignal

Mostrato in homepage solo quando ci sono dati reali di trend (mai placeholder). Stati: ☀️ good, ☁️ neutral, ⛈️ alert.

### Endpoint

`GET /api/market-indices` → `pun`, `psv`, `pun_source`, `pun_forward_label`, `pun_forward_age_days`, `trend { direction, icon, moment, message, week_change_pct, month_change_pct }`

---

## 6. Form Sottoscrizione — Prefill via LLM

### Architettura del prefill

Quando l'utente usa un LLM per analizzare la bolletta, il form di sottoscrizione può arrivare **precompilato** con i dati estratti:

```
LLM estrae dalla bolletta:
  nome, cognome, cf, email, tel, indirizzo, civico, citta,
  provincia, provincia_sigla, cap, pod, pdr, consumi, spesa
       ↓
LLM chiede consenso esplicito all'utente
       ↓
LLM costruisce URL:
  /sottoscrizione?tariff=ID&supplier=X&name=Y&commodity=luce&annualCost=500
  &nome=Mario&cognome=Rossi&cf=RSSMRA80A01H501U
  &indirizzo=Via+Roma&civico=15&citta=Milano&cap=20121&pod=IT001E123456789
  &consumi=2700&spesa=650
       ↓
Form precompilato → badge verde "🤖 Dati precompilati dalla tua bolletta"
       ↓
Utente verifica, completa i campi mancanti, invia
```

### Parametri URL supportati dal form

| Parametro | Campo form | Esempio |
|-----------|-----------|--------|
| `tariff` | ID offerta | `ff96f52a-...` |
| `supplier` | Fornitore | `Fastweb+Energia` |
| `name` | Nome tariffa | `FASTWEB+ENERGIA+FIX` |
| `commodity` | Tipo | `luce` o `gas` |
| `annualCost` | Costo annuo € | `457` |
| `nome` | Nome | `Mario` |
| `cognome` | Cognome | `Rossi` |
| `cf` | Codice Fiscale | `RSSMRA80A01H501U` |
| `email` | Email | `mario.rossi@email.com` |
| `tel` | Cellulare | `+393401234567` |
| `indirizzo` | Via/Piazza | `Via+Roma` |
| `civico` | Numero civico | `15` |
| `citta` | Città | `Milano` |
| `provincia` | Provincia (nome) | `Milano` |
| `provincia_sigla` | Provincia (sigla) | `MI` |
| `cap` | CAP | `20121` |
| `pod` | POD (14 cifre) | `IT001E123456789` |
| `pdr` | PDR (14 cifre) | `12345678901234` |
| `consumi` | Consumo annuo | `2700` |
| `spesa` | Spesa annua € | `650` |

### Guardrail Privacy per LLM

Ogni tool MCP include nelle istruzioni:
1. **Rassicurare l'utente**: "I tuoi dati vengono usati solo per l'attivazione dell'offerta e non vengono conservati dopo la sessione"
2. **Chiedere consenso esplicito** prima di includere dati personali nell'URL
3. **Spiegare il flusso**: l'utente deve verificare i dati e cliccare Invia personalmente
4. **Menzionare la mail di conferma**: prima dell'inoltro arriva una mail di conferma (double opt-in)

### Campi mai richiesti all'LLM

Il form di sottoscrizione **non chiede mai** all'LLM di raccogliere:
- Password o dati di accesso
- Dati bancari completi (solo IBAN, opzionale, solo al momento dell'invio)
- Dati di carte di credito

---

## 7. Double Opt-In GDPR

1. `POST /api/subscription/submit` → pending + email conferma all'utente
2. Clicca `/conferma?token=xxx` → confirmed
3. Solo dopo conferma: dati completi via email + (se WS_ENABLED) web service

### Campi GDPR obbligatori

- `gdpr_privacy_accepted: true` — senza → 400 error
- `consent_source`, `consent_timestamp`, `conversation_snippet`
- Audit trail completo in `data/subscriptions/{id}.json`

---

## 8. Storage — Flat-File + MySQL

SwitchAI usa un'architettura ibrida:

### Flat-File (dati operativi)
- `data/offerte/` — JSON ARERA (db-offerte-luce.json ~8.6 MB, db-offerte-gas.json ~5.2 MB) — generati da `arera_sync.php`
- `data/subscriptions/` — JSON con `flock()` atomico
- `data/api_clients/` — B2B API keys (SHA-256)
- `data/ratelimit/` — rate limiting per IP
- `data/templates/` — auto-learning parser
- `data/logs/traffic_YYYY_MM.jsonl` — rotazione mensile

### MySQL (dati transazionali)
- **Server**: songmeeswitchai.mysql.db (OVH, 2 GB)
- Tabelle: `users`, `api_keys`, `rate_log`, `affiliate_links`
- Gestito via `db_mysql.php` con PDO, InnoDB, foreign keys
- Usato per: autenticazione B2B, API key management, rate limiting, link affiliazione

### Rate Limiting

- **B2C**: 30 richieste/ora per IP — **solo su POST pesanti e write**. GET pubbliche esenti
- **B2B**: quota mensile per chiave API (free 100, pro 1000, enterprise unlimited)
- B2C via flat-file con `flock()`, B2B via MySQL `rate_log`

### ARERA Sync

Script `arera_sync.php` scarica gli XML ufficiali da `ilportaleofferte.it`:
- Parser XMLReader (streaming, memory-efficient, ~30-60 secondi)
- Filtra offerte scadute (data_fine < oggi), deduplica, risolve brand da P.IVA
- Salva atomicamente (write .tmp → rename) in `data/offerte/`
- Triggerabile da Admin panel (`POST /api/admin/sync-arera`) o via cron OVH
- Risultato: ~3.150 offerte LUCE + ~2.390 GAS da 21+ fornitori

### Sistema Affiliazioni

- Tabella MySQL `affiliate_links`: tariff_id → affiliate_url, network, is_active
- Admin panel (`/admin` → tab 💰 Affiliazioni): cerca offerte, associa link, rimuovi
- Le offerte con link affiliazione mostrano CTA esterno (nuova tab) invece del form interno
- Backend arricchisce i risultati API con `affiliate_url` automaticamente

---

## 9. Motore di Calcolo — Costanti Regolatorie ARERA

### Architettura "Totale vs Totale" (Full Cost Approach)

SwitchAI mostra il **costo totale stimato** della bolletta (materia energia + trasporto + oneri + imposte + IVA), non solo la componente energia. Questo evita il mismatch tra "risparmio di 500€" mostrato da altri comparatori e la bolletta reale di 80€/mese.

### Costanti centralizzate

Tutti i parametri ARERA sono in **un'unica fonte** per frontend e backend:

| File | Scope |
|------|-------|
| `frontend/src/lib/constants.js` | JS: `LUCE`, `GAS`, `MERCATO` — importati da `calc.js`, `Home.jsx` |
| `backend/php/inc/bill_parser.php` | PHP: 17 `define()` con guard `if (!defined(...))` + `getAreraConstants()` |
| `GET /api/arera-constants` | API pubblica che espone i valori correnti in JSON |

### Valori LUCE — ARERA v4.0 (aggiornati 29 Giugno 2026, Q3 2026)

| Costante | Valore | Descrizione |
|----------|--------|-------------|
| `PERDITE_RETE_BT` | 1.102 | Coefficiente perdite Bassa Tensione (~10,2%). Applicato SOLO al PUN, non allo spread |
| `ONERI_SISTEMA` | 0.030295 €/kWh | ASOS + ARIM (Q3 2026) |
| `ACCISE` | 0.0227 €/kWh | Accisa erariale. Formula: `max(0, min(kWh, 2640) - 1800) × 0.0227`. Esente ≤1800, compensata >2640 |
| `TRASPORTO_VAR` | 0.01473 €/kWh | TRAS + UC3 + UC6 + gestione contatore (Q3 2026, allineato Portale Offerte) |
| `COSTO_POTENZA_KW` | 23.76 €/kW/anno | Quota potenza (1,98 €/kW/mese × 12, Q3 2026) |
| `QUOTA_FISSA_RETI` | 23.04 €/anno | Trasporto fisso + gestione contatore |
| `CANONE_RAI_ANNUO` | 90.00 €/anno | Canone RAI (solo LUCE residenziale, non si applica a business/P.IVA) |
| `IVA` | 10% (residenziale) / 22% (business) | IVA agevolata usi domestici |

### Accise DL 504/1995 — Formula unificata (v5.4.0)

La formula precedente aveva un bug nel ramo `else` (consumi >2640 kWh/anno) che sovrastimava le accise. Fix applicato in 6 posizioni (PHP ×3, JS ×3):

```php
// CORRETTO (singola formula, sostituisce 3 if/elseif/else)
$accise = max(0, min($yearlyKwh, LUCE_ACCISE_SOGLIA_COMPENSATA) - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
// Esente ≤1800 kWh, tassato 1800-2640, compensato >2640
```

### Potenza impegnata — Propagazione (v5.4.0)

Il valore `potenza_impegnata` dalla bolletta (es. 4,5 kW) non veniva passato a `calculateSavingsBreakdown()`, che usava sempre il default 3,0 kW. Impatto: ~39€/anno di differenza sul costo finale. Fix: propagato in `handleV2Analyze`, `mcp_analyze`, e frontend `calcLuceCost`.

### Valori GAS — ARERA v4.0

| Costante | Valore | Descrizione |
|----------|--------|-------------|
| `TRASPORTO_VAR` | 0.15 €/Smc | Trasporto variabile distribuzione |
| `ONERI_SISTEMA` | 0.03 €/Smc | Oneri sistema gas |
| `ACCISE` | 0.149959 €/Smc | Accisa gas usi civili (valore preciso) |
| `ADDIZIONALE_REGIONALE` | 0.0093 €/Smc | Addizionale regionale gas |
| `QUOTA_FISSA_RETI` | 23.00 €/anno | Trasporto fisso + gestione contatore |
| `SOGLIA_IVA_10` | 480 Smc/anno | Soglia IVA agevolata (oltre → 22%) |

### Metodo ARERA Simmetrico (novità v4.0)

Per le tariffe **variabili** (indicizzate PUN/PSV), SwitchAI applica il **confronto simmetrico**: entrambe le tariffe (attuale e nuova) sono calcolate con lo **stesso PUN/PSV corrente**. Il risparmio riflette solo le differenze contrattuali reali (spread + quota fissa), non le oscillazioni di mercato. Questo evita l'errore comune di confrontare la spesa storica (PUN vecchio) con la spesa futura stimata (PUN forward), che gonfiava artificialmente i risparmi.

> **Nota**: Questi valori vengono aggiornati periodicamente seguendo le delibere ARERA. Per modificarli basta toccare un solo file per lato (JS `constants.js` e PHP `bill_parser.php`). L'endpoint `/api/arera-constants` espone i valori correnti in JSON.

---

## 10. WebMCP Tools (4)

Registrati in `webmcp.json` + `webmcp.js`:
1. `calculate_energy_savings` — confronto tariffe + subscription_url
2. `parse_energy_bill` — analisi bolletta
3. `get_available_offers` — lista offerte
4. `submit_subscription` — attivazione (GDPR)

+ `/.well-known/webmcp.json` e `/.well-known/mcp/server-card.json`

---

## 11. MCP Server Pubblico

**URL**: `POST https://www.switchai.it/mcp`  
**Web**: `https://www.switchai.it/mcp` (registrabile come connettore in Claude web → Impostazioni → Connettori)

7 tool (PHP + Node.js), JSON-RPC 2.0, zero autenticazione.

**Flusso LLM-nativo**: i tool istruiscono l'LLM a estrarre i dati personali dalla bolletta e a usarli per precompilare il form di sottoscrizione, con guardrail privacy integrato nella tool description.

### Disclaimer GDPR nell'output

Ogni risposta del tool `calculate_energy_savings` include nel CTA:

```
🔗 🟢 APRI IL FORM SU SWITCHAI.IT

> ⚠️ Questo link apre switchai.it, un sito esterno a questo assistente.
> ✏️ L'utente deve verificare i dati e cliccare Invia sul sito.
> 📨 Dopo l'invio, riceverà una email di conferma da SwitchAI.
> 🔐 Solo dopo aver cliccato il link nell'email, i dati verranno inoltrati al fornitore.
> 🛑 NON dire "tutto fatto" o "ho attivato". La sottoscrizione NON è ancora partita.
```

Il tool description istruisce l'LLM a:
- Avvisare che il link apre un sito esterno (switchai.it)
- Spiegare il double opt-in (email di conferma obbligatoria)
- NON dichiarare completata l'attivazione prima della conferma
- Precompilare il form con tutti i dati estratti dalla bolletta

### Tool disponibili (7)

- `calculate_energy_savings` — confronto + risk + subscription_url + prefill_instructions
- `parse_energy_bill` — analisi bolletta ARERA
- `get_available_offers` — 25 luce + 19 gas
- `get_market_indices` — PUN/PSV live con trend
- `get_subscription_form_schema` — schema form 4 step
- `submit_subscription` — invio con double opt-in GDPR
- `get_subscription_status` — verifica stato sottoscrizione

**Registrato su**:
- npm: `@us1929/switchai-mcp`
- Glama: `glama.ai/mcp/servers/US1929/switchai` (in review)
- GitHub: `github.com/US1929/switchai`

---

## 12. File per Crawler e LLM Discovery

| File | URL | Scopo |
|------|-----|-------|
| `llms.txt` | `/llms.txt` | LLM site description |
| `webmcp.json` | `/webmcp.json` | WebMCP tool discovery |
| `openapi.json` | `/openapi.json` | OpenAPI 3.0 spec |
| `robots.txt` | `/robots.txt` | Allow ClaudeBot, GPTBot, Google-Extended |
| `sitemap.xml` | `/sitemap.xml` | Dinamica (5 statiche + 30+ offerte) |
| `.well-known/mcp/server-card.json` | `/.well-known/mcp/server-card.json` | Smithery metadata |

---

## 13. SEO e Indicizzazione Google

### Struttura URL canonici

Gli URL senza estensione (es. `/per-llm`) fanno 301 esplicito alla versione `.html` (es. `/per-llm.html`), che è il canonical. Questo evita il loop MultiViews di Apache su OVH.

```
/per-llm          → 301 → /per-llm.html       (canonical)
/come-funziona    → 301 → /come-funziona.html  (canonical)
/privacy          → 301 → /privacy.html        (canonical)
/cookie           → 301 → /cookie.html         (canonical)
```

### Meta tag su tutte le pagine statiche

Ogni pagina HTML statica ha:
- `<link rel="canonical">` — URL `.html` canonico
- `<meta property="og:title">`, `og:description`, `og:url`, `og:type` — Open Graph
- `<script type="application/ld+json">` — JSON-LD structured data
- `<meta name="description">` — descrizione SEO
- `<meta name="robots" content="index, follow">`

### Sitemap

Generata dinamicamente da `api/index.php`. ~386 URL:
- **13 pagine statiche**: `/`, `/per-llm`, `/come-funziona`, `/faq`, `/privacy`, `/cookie`, `/risorse/` + 6 sub-pagine
- **373 pagine fornitore**: `/fornitori/{slug}` — indicizzate, contenuto ricco (offerte, stats, JSON-LD Organization)
- **NO pagine /offerta/{uuid}** — sono `noindex, follow` (thin content, evitano penalizzazione doorway pages)
- Le pagine offerta restano accessibili (per link diretti) ma non indicizzate, con `canonical → homepage`

### Pagine Fornitore (`/fornitori/{slug}`)

Create per SEO, **index, follow**, contenuto unico:
- Stats: N offerte Luce/Gas, N fissi/variabili
- Tabella completa con prezzi, quote fisse, dettagli
- JSON-LD Organization
- CC BY 4.0 attribution
- Link a `/offerta/{id}` per i dettagli (noindex)

### API: X-Robots-Tag

Tutte le risposte API JSON hanno header `X-Robots-Tag: noindex, nofollow` per evitare indicizzazione di dati grezzi.

### Apache MultiViews su OVH

OVH ha MultiViews attivo di default. Gli rewrite interni (URL pulito → file `.html`) causano redirect loop. Soluzione: **301 espliciti** da URL pulito → `.html`, che vengono processati prima di MultiViews.

---

## 14. UX — Tre Modalità di Interazione

La homepage guida l'utente con CTA chiare nell'hero:

- `🤖 Analizza con la tua AI` → scrolla alla sezione #come-usare
- `📋 Prova senza connettere l'AI` → scrolla alla card paste prompt

### Sezione "Come analizzare la tua bolletta"

Tre card distinte con icona e istruzioni step-by-step:

| Modo | Icona | Chi | Come |
|------|-------|-----|------|
| **Claude (consigliato)** | 🔌 | Claude web, Desktop, mobile | Impostazioni → Connettori → `https://www.switchai.it/mcp` |
| **ChatGPT** | 🧩 | ChatGPT Plus/Pro | Esplora GPT → Azioni → Importa `https://www.switchai.it/openapi.json` |
| **Copia e incolla** | 📋 | Qualsiasi AI (Claude, ChatGPT, Gemini, DeepSeek) | Prompt pre-compilato → incolla JSON → analisi automatica |

### Prompt copia-incolla (sempre visibile)

Card dedicata con:
- Box prompt cliccabile per copiare (14 campi: commodity, consumo_annuo, spesa_annua, zona, spesa_materia_energia, quota_fissa_mensile, F1/F2/F3, potenza, tipo_tariffa, spread, scadenza_offerta, periodo_riferimento)
- Textarea per incollare la risposta JSON del LLM
- Pulsante "Analizza questi dati" → auto-compila e avvia il confronto

**Dati sensibili MAI richiesti**: POD, indirizzo, CF — servono solo al momento della sottoscrizione.

### Perché 14 campi e non 4

Per un LLM estrarre 14 campi o 4 costa lo stesso sforzo. Ma con 14 campi possiamo:
- Separare la componente negoziabile (materia energia) da quella regolata (trasporto, oneri)
- Fare analisi multioraria (F1/F2/F3) per consigliare bioraria vs monoraria
- Attualizzare bollette variabili con PUN/PSV odierno (serve `spread` e `tipo_tariffa`)
- Avvisare se l'offerta sta per scadere (`scadenza_offerta`)
- Annualizzare correttamente (serve `periodo_riferimento`)

### Sistema di Onestà

SwitchAI non consiglia mai il cambio se non c'è un vantaggio reale:
- **✅ CONVIENE** — risparmio >50€/anno e >5%
- **⚠️ VALUTA** — risparmio modesto (30-50€/anno o 3-5%)
- **❌ NON CONVIENE** — risparmio trascurabile o nullo

### Attualizzazione bollette variabili

Per bollette a tariffa variabile (PUN/PSV + spread), ricalcoliamo il costo con l'indice di mercato odierno, così il confronto è sempre aggiornato.

---

## 15. UX — Interfaccia Risultati e Confronto Offerte

### TariffTable (nuovo — da v5.3)

Sostituisce le card con un layout a tabella compatta, come il Portale Offerte ARERA:

1. **Header colonne**: Fornitore, Offerta, Prezzo, Quota, Costo/anno, Risparmio
2. **TariffTableRow** — ogni riga è espandibile:
   - Logo fornitore, nome offerta, badge Fisso/Variabile, codice offerta
   - **Grafico mensile** (Recharts LineChart): proiezione 12 mesi con stagionalità
   - **Prezzi per fascia F1/F2/F3** — breakdown per tariffe multiorarie
   - **Composizione prezzo** — Energia + Oneri sistema + Trasporto con valori ARERA reali
   - **SavingsBreakdownModal** — modale con calcolo risparmio dettagliato
   - CTA: "Attiva Online" (usa link affiliazione se disponibile, altrimenti form interno)
   - Link "Vedi su ARERA" (url_offerta), codice_offerta, tag validità
3. **MarketPositionBar** — barra comparativa (migliore offerta vs media mercato)
4. **BillCostChart** — grafico a ciambella (Recharts PieChart) con ripartizione spesa:
   - Materia energia (con badge CAMBIA), Trasporto, Oneri, Imposte/IVA/Canone RAI
5. **CostBreakdownCard** — barre colorate con percentuali per ogni categoria
6. **Toggle Mese/Anno** — "Bolletta attuale" vs "Proiezione annuale"
7. Mostra 5 offerte inizialmente, espandibile a tutte con pulsante

### StickyReferenceBar

Barra sticky con i 3 numeri chiave della bolletta attuale. Per tariffe variabili, include il metodo ARERA: "Il confronto usa lo STESSO PUN per entrambi i lati. Il risparmio riflette solo differenze contrattuali (spread + quota fissa)."

### Legenda e offerte dinamiche

- Conteggio offerte **dinamico** (fetch da `/api/tariffe/luce` + `/api/tariffe/gas`)
- Legenda "Cosa cambia / Cosa resta uguale" con Canone RAI (solo LUCE residenziale)
- Offerte con prezzo impossibile (fisso < 0.05 €/kWh, gas < 0.15 €/Smc) escluse automaticamente

---

## 16. Pagine del Sito

| URL | Tipo | Contenuto |
|-----|------|-----------|
| `/` | React SPA | Hero + confronto + guida + FAQ |
| `/per-llm` → `/per-llm.html` | HTML statico (canonical) | Documentazione per AI agent |
| `/come-funziona` → `/come-funziona.html` | HTML statico (canonical) | Architettura + confronto vs tradizionale |
| `/faq` → `/faq.html` | HTML statico (canonical) | Domande frequenti |
| `/privacy` → `/privacy.html` | HTML statico (canonical) | Privacy policy GDPR |
| `/cookie` → `/cookie.html` | HTML statico (canonical) | Cookie policy (solo tecnici) |
| `/risorse/` | PHP (router) | Indice guide SEO |
| `/risorse/come-funziona-bolletta-luce` | PHP | Guida bolletta luce ARERA 2.0 |
| `/risorse/come-funziona-bolletta-gas` | PHP | Guida bolletta gas |
| `/risorse/glossario-energia` | PHP | Glossario termini energetici |
| `/risorse/prezzo-fisso-vs-indicizzato` | PHP | Fisso vs Variabile |
| `/risorse/calcolo-spesa-annua` | PHP | Come si calcola la SAS |
| `/risorse/come-leggere-bolletta` | PHP | Guida lettura bolletta |
| `/sottoscrizione` | React SPA | Form wizard 4 step (supporta prefill via URL params) |
| `/conferma` | React SPA | Double opt-in conferma |
| `/admin` | React SPA (auth) | Dashboard: Traffico, API Keys, Affiliazioni, Sync ARERA |
| `/login` | React SPA | Accesso admin |

---

## 17. Deploy

```bash
cd /Users/djanc/Documents/Progetti_IA/AIenergywebmcp/frontend
npm run build
# Carica TUTTO dist/ su OVH in www/
```

### .env su OVH

```env
LUCE_JSON_URL=<url>
GAS_JSON_URL=<url>
ACTIVATION_EMAIL=attivazioni@switchai.it
API_KEY=<key>
WS_ENABLED=false
WS_SUBSCRIPTION_TOKEN=<token>
WS_SUBSCRIPTION_URL=<url>
STATS_USER=admin
STATS_PASSWORD_HASH=<bcrypt>
```

---

## 18. GitHub Repository

**URL**: [github.com/US1929/switchai](https://github.com/US1929/switchai)

Contenuto: README, llms.txt, webmcp.json, openapi.json, CLAUDE.md, mcp-server/

Topics: `mcp`, `webmcp`, `energy`, `tariffs`, `italy`, `ai-agent`, `llm`, `electricity`, `gas`, `arera`, `switchai`

---

## 19. Changelog v5.4.0 — 29 Giugno 2026

### 🔧 Bug Fix (15 issue da code review)

| Issue | Fix |
|-------|-----|
| **C1** Accise DL 504/1995 sbagliate >2640 kWh | Formula unificata `max(0, min(kWh, 2640)-1800) × 0.0227` in 6 posizioni |
| **C2** IVA business ignorata (sempre 10%) | `$tipoCliente === 'business' ? 0.22 : 0.10` in `calculateSavingsBreakdown` |
| **C3** `CANONE_RAI_ANNUO` undefined → PHP 8.x Fatal Error | `define('CANONE_RAI_ANNUO', 90.00)` |
| **C4** `.env` esposto via PHP built-in server | `router.php` ora blocca richieste a `*.env` |
| **C5** StickyReferenceBar: PSV mostrava PUN per gas | `isLuce ? punDisplay : psvDisplay` |
| **H1** Spread retro-stima ignorava `LUCE_PERDITE_RETE_BT` | Aggiunto `× 1.102` nella retro-stima |
| **H2** `getTariffsForCalculation` ignorava zona/tipoCliente | Ora filtra per `$tipoCliente` |
| **H3** Rate limit consumato prima di verificare API key | Auth check spostato PRIMA del rate limit |
| **H4** `$attualization` dead code (referenziata prima dell'assegnazione) | Spostata dopo il calcolo |
| **M1** `$unitPrice` assegnato due volte identico | Rimosso duplicato |
| **M2** `Comodista` → `Comodatario` MCP server | Allineato enum |
| **M3** Riga duplicata "METODO ARERA" in MCP server | Rimossa |
| **M4** Field legacy `"psv Aprile 2025/"` in calc.js (3 posizioni) | Sostituito con `"psv"` |
| **M5** Home.jsx: HTTP status check mancante + stale closure | `r.ok` + `marketData` in deps |

### 🚀 Nuove Features

| Feature | Dettaglio |
|---------|-----------|
| **PUN forward ARERA** | Da config.json (sync mensile), non più spot. Priorità: forward → spot live. Warning se scaduto |
| **Potenza reale** | `potenza_impegnata` ora propagata in tutte le path di calcolo (API + frontend) |
| **Filtro fornitori** | Chip cliccabili con conteggio, gruppo "Grandi fornitori" (14), toggle rapido |
| **Confronto checkbox** | Checkbox su ogni offerta → barra flottante → modale comparativo affiancato |
| **Pagine `/fornitori/{slug}`** | 373 pagine SEO indicizzate con stats, tabella offerte, JSON-LD Organization |
| **CC BY 4.0** | Attribuzione dati ARERA nel footer (React + pagine statiche) |
| **JSON-LD arricchito** | `offers` come array, `sku`, `category`, `priceValidUntil`, `areaServed` |
| **PUN source tracking** | `/api/market-indices` include `pun_source`, badge nell'`agent_summary` |

### 📊 Costanti ARERA Q3 2026

Allineate al Portale Offerte (verificate contro Wattene):

| Costante | Q2 | Q3 | Delta |
|----------|-----|-----|-------|
| `TRASPORTO_VAR` | 0.01204 | 0.01473 | +0.00269 |
| `COSTO_POTENZA_KW` | 23.52 | 23.76 | +0.24 |
| `ONERI_SISTEMA` | 0.0303 | 0.030295 | -0.000005 |
| Differenza costo annuo (1600 kWh, 3 kW) | | | +5€ su 578€ (1%) vs Wattene |

### 🗑️ Pulizia

- **Sezione Plus** eliminata (tutto il codice portato nel main)
- `DOCUMENTAZIONE_CALCOLO_ARERA.md` salvato nella root
- Sitemap: rimossi 5000+ URL UUID, sostituiti con 373 `/fornitori/` parlanti
- Pagine `/offerta/`: `noindex, follow` + `canonical → homepage`

### 📋 Da Fare / Backlog

| Priorità | Task | Note |
|----------|------|------|
| 🔴 Alta | **Forward PUN trimestrale reale** | Ora usiamo il PUN forward dal config.json (salvato dal sync). Il valore è quello ufficiale ARERA ma va verificato mensilmente. Futuro: integrare GME forward curve per calcolo autonomo |
| 🔴 Alta | **Sync ARERA automatico via cron** | Su OVH è configurabile. Eseguire 1 volta/settimana per aggiornare offerte + PUN forward |
| 🟡 Media | **F1/F2/F3 nativo** | SwitchAI usa prezzo medio monorario. Offerte biorarie (es. A2A Full Luce) andrebbero calcolate con prezzi per fascia |
| 🟡 Media | **Gas: costo materia depurato** | `spesa_materia_energia` non gestito per GAS. La stima spread gas è meno precisa |
| 🟡 Media | **Pagine `/fornitori/` arricchite** | Aggiungere logo, descrizione fornitore, rating, link affiliazione |
| 🟢 Bassa | **Admin: upload manuale PUN forward** | In caso il sync fallisca, permettere upload manuale del valore |
| 🟢 Bassa | **CDISPD nei calcoli** | Aggiungere componente dispacciamento (dal documento ARERA) |

---

## 20. Riferimenti

- **WebMCP Spec**: [GoogleChromeLabs/webmcp-tools](https://github.com/GoogleChromeLabs/webmcp-tools)
- **MCP Spec**: [modelcontextprotocol.io](https://modelcontextprotocol.io)
- **llms.txt**: [llmstxt.org](https://llmstxt.org)
- **ARERA Bolletta 2.0**: Delibera 501/2014/R/com
- **Portale Offerte ARERA**: [ilportaleofferte.it](https://www.ilportaleofferte.it) — dati sotto licenza CC BY 4.0
- **Algoritmo ARERA**: `DOCUMENTAZIONE_CALCOLO_ARERA.md` (root)
- **Wattene**: [wattene.it](https://wattene.it) — benchmark di confronto

---

> **Versione**: 5.4.0 — 29 Giugno 2026  
> **Dominio**: switchai.it · **Hosting**: OVH Pro · **PHP**: 8.5.0 · **MySQL**: 2 GB  
> **Offerte**: 5.500+ da Portale Offerte ARERA (sync mensile) · **Fornitori**: 373  
> **Tools**: 4 WebMCP + 7 MCP pubblici · **Endpoint API**: 28+
> **Offerte**: 5.000+ da sync ARERA giornaliero (21 fornitori)  
> **Parser**: ARERA 3.0 — 10/10 PDF testati  
> **Modello**: API-first, LLM-native. Niente parsing PDF lato server.  
> **Novità v5.3**: ARERA v4.0 (Del. 575/2025): oneri 0.0303, trasporto 0.01204, potenza 23.52, perdite solo su PUN · Metodo simmetrico PUN (stesso PUN per entrambe le tariffe variabili) · Accise con soglie DL 504/1995 · Addizionale regionale gas · Canone RAI solo residenziale · MySQL per utenti/API keys/affiliazioni · ARERA sync da Portale Offerte (ilportaleofferte.it) · Interfaccia risultati: TariffTable + BillCostChart (ciambella) + CostBreakdownCard + MarketPositionBar · Offerte dinamiche (fetch count live) · SEO: 7 pagine /risorse/ con sitemap · Admin: sync ARERA, gestione affiliazioni · Filtro offerte scadute e prezzi impossibili
