# STATO LAVORI — AIenergywebmcp (SwitchAI)

> Ultimo aggiornamento: 29 Giugno 2026 (notte fonda)

---

## Cosa e' stato fatto (verificato vs Wattene)

### Parser ARERA — estrazione prezzi
- `arera_parse_components()` in `arera_sync.php`: parsing uniforme componenti con fascia
- Offerte fisse monorarie: somma componenti energia per F1, dedup duplicati ARERA
- Offerte fisse multi-fascia: mapping codici FASCIA_COMPONENTE → F1/F2/F3 in ordine
- Offerte fisse senza codice fascia: assegnazione sequenziale F1/F2/F3
- `prezzoFisso` = F1 per monoraria, media fasce per multi-fascia
- `tariff_loader.php`: somma componenti per fascia in `price_f1/f2/f3_kwh`
- Verifica vs Wattene: Δ energia avg 0,0007 €/kWh (match quasi perfetto)

### PUN/PSV forward ARERA
- `getAreraForwardPun()` in `bill_parser.php`: legge PUN forward da `config.json`
- `_arera_save_forward_params()` in `arera_sync.php`: estrae PUN/PSV dalle offerte variabili e li salva
- Bug fix: cast `(float)"0,1434"` = 0 → `str_replace(',', '.', ...)` prima del cast
- Priorita': forward ARERA (valido 60gg) → spot live (fallback)
- PUN forward attuale: 143,4 €/MWh, PSV: 563,8 €/MWh

### Dispacciamento
- Costante `LUCE_DISPACCIAMENTO = 0.016988` €/kWh (CDISPD Q3 2026)
- Aggiunto al subtotal in `bill_parser.php` (calculateSavingsBreakdown)
- Aggiunto al subtotal in `calc.js` (frontend)
- Aggiunto all'attualizzazione in `api/index.php` (handleV2Analyze)
- `arera_breakdown.dispacciamento` ora valorizzato (era 0)
- Impatto: ~46€/anno recuperati su 2700 kWh

### Sconti energia <Sconto>
- `arera_parse_sconti()` in `arera_sync.php`: parsing elementi `<Sconto>`
- Sconti energia €/kWh/€/Smc senza soglia: sottratti dal prezzo
- Sconti con soglia (VALIDO_FINO > 0): saltati (too complex a sync time)
- Sconti fissi €/anno: NON sottratti (alcuni sono condizionali SDD/dual-fuel)

### Allineamento tariff_loader.php
- Copiato da Megaprogetto/Switchai (versione verificata)
- Aggiunte: `tipo_cliente`, `tipo_fasce`, `regioni`, `province`, `nazionale`
- Aggiunte: `tariffIsExpired()`, `tariffIsAvailableInZone()`, `getZoneRegions()`
- Somma componenti per fascia invece di campi `prezzo f1/f2/f3` (che non esistono)
- Rimozione campo legacy `psv Aprile 2025/`

### API e frontend
- `handleTariffe()` in `api/index.php`: esporta `tipo_cliente`, `tipo_fasce`, `regioni`, `nazionale`, `price_f1/f2/f3_kwh`
- `Home.jsx`: fix `d.top3` → `d.results` (bug 0 offerte dopo incolla JSON)
- `constants.js`: aggiunta `DISPACCIAMENTO: 0.016988`
- `calc.js`: aggiunto dispacciamento al subtotal LUCE

### Segmento residenziale/business
- `detectClientType()` in `bill_parser.php`
- `tipo_cliente` propagato in API, MCP, WebMCP
- `getTariffsForCalculation()` filtra per tipo cliente

### Validazione URL offerte
- `normalizeOfferUrl()` in `arera_sync.php`
- `filter_var(FILTER_VALIDATE_URL)` in `tariff_loader.php`

### Dati ARERA
- Sync completato: 3190 offerte luce, 2411 offerte gas
- `config.json` con PUN/PSV forward generato

### Costi fissi multipli (29 giugno)
- `arera_sync.php`: somma TUTTI i componenti €/anno che matchano keyword (COMMERCIALIZZAZIONE, CCV, FISSA, QUOTA, quota fissa), non solo il primo
- Conversione automatica €/mese (um=05) → €/anno (×12)
- Fallback single-value se nessun keyword match trovato (primo non-potenza)
- Residenziale LUCE: media 142.77€/anno, mediana 144€/anno. Business: media 207.36€/anno

### Fix conteggio sync (29 giugno)
- `api/index.php`: `$o['uso']` inesistente → `$o['tipo_cliente']`
- `'domestico'` → `'residenziale'`
- Conteggio ora corretto: 1707 privati + 1483 aziende LUCE

### Test routine indipendente (29 giugno)
- `tests/verify_offers.php`: verifica coerenza interna su campione stratificato
- 8 check: profili crescenti, campi obbligatori, componenti fascia, sconti, costi, spread
- Eseguibile dopo ogni sync: `php tests/verify_offers.php [--verbose] [--sample=N]`
- NON dipende da Wattene né servizi esterni — solo dati ARERA ufficiali

### Gas spesa_materia_energia (29 giugno)
- `api/index.php`: fallback spread estimation (LUCE e GAS) ora usa `spesaMateriaEnergia` quando disponibile
- `Home.jsx`: invia `spread_eur_smc` per GAS (prima solo `spread_eur_kwh`)
- `webmcp.js`: aggiunto campo `spread_eur_smc` per tool MCP GAS

### F1/F2/F3 PUN forward (29 giugno)
- `arera_sync.php`: salvati `PUN_F1`, `PUN_F2`, `PUN_F3` in config.json con rapporti GME (F1: +7%, F3: -10% vs F2)
- `bill_parser.php`: nuova funzione `getAreraForwardPunByFascia()` restituisce array F1/F2/F3
- `bill_parser.php`: variabili multioraria usano PUN distinto per fascia (non più lo stesso per tutte)
- `calc.js`: multi-fascia anche per tariffe variabili con stessi rapporti GME
- Nota trasparenza: ARERA pubblica un solo PUN forward; F1/F3 sono stime statistiche

### Fix anti-promo sconti (29 giugno)
- Euristica in `arera_sync.php` (lines 499-518): se uno sconto fisso ridurrebbe il costo_fisso sotto 24€/anno (2€/mese, minimo vitale PCV) partendo da base >20€ con sconto >50% del base, viene spostato in `sconti_non_applicati`
- `fix_sconti.php`: post-processing per JSON già generato (101 offerte fixate: 59 LUCE + 42 GAS)
- Flag `has_sconti_condizionali` ricalcolato, costo_fisso ripristinato dal componente "Corrispettivo annuo"
- E.ON "Bonus E.ON" (30€/mese, promo Porta un Amico) non più applicato: PCV E.ON LuceClick ora 109.23€ (da 0)
- Residua accuratezza Wattene: Octopus=✅ A2A=✅ E.ON=✅ Edison=⚠️ (2 mill diff, bioraria)
- `vite.config.js`: copyBackendPlugin ora copia `data/offerte/*.json` e `market_history.json`
- Eliminata dipendenza da copia manuale post-build

### Sconti condizionali (29 giugno)
- `arera_sync.php`: parsato `CONDIZIONE_APPLICAZIONE` da `<Sconto>` ARERA (`00` = incondizionale)
- Sconti incondizionali applicati di qualsiasi tipo (€/kWh, €/Smc, €/anno, €/mese), non solo energia
- Sconti fissi incondizionali sottratti dal `costo_fisso` annuale
- Sconti mensili (€/mese) convertiti in annuale (×12)
- Dati sconto salvati in JSON output: `sconti_applicati`, `sconti_non_applicati`, `has_sconti_condizionali`, `sconto_note`
- `tariff_loader.php`: esposti campi sconto in normalizzazione LUCE e GAS
- `api/index.php`: `has_sconti_condizionali` e `sconto_note` in `handleTariffe()`
- `TariffCard.jsx`: badge blu per offerte con sconti condizionali non applicati
- Risultato: 247 LUCE + 191 GAS con sconti applicati, 862 LUCE + 673 GAS con badge condizionali (dopo fix anti-promo)

### Trasparenza Canone RAI (29 giugno)
- Il Canone RAI è di 90€/anno, addebitato in 10 rate da 9€ in bolletta. Valore corretto automaticamente per normalizzazione mensile (90€ / 12 mesi = 7,5€/mese)
- `BillCostChart.jsx`: slice separata "Canone RAI" nel donut (colore arancione)
- `CostBreakdownCard.jsx`: barra separata Canone RAI con asterisco se auto-corretto
- `Home.jsx`: `canoneRai` scorporato da "Imposte e IVA", `canoneRaiAutoCorrected` flag
- `api/index.php`: `canone_rai_stimato` nella risposta per segnalare correzione automatica (es. 9€ → 90€)

### MCP Headroom integrato (29 giugno)
- `~/.config/opencode/opencode.jsonc`: MCP server headroom via stdio (3 tool: compress, retrieve, stats)
- `.zshrc`: auto-avvio proxy Headroom all'apertura terminale

---

## Verifica vs Wattene (29 giugno 2026)

### Luce (292 offerte fisse matchate, 2700 kWh, 3 kW)
- Δ energia avg: 0,0007 €/kWh
- Δ totale avg: 14,85€
- Within 15€: 228/292 (78%)
- Within 30€: 253/292 (87%)

### Gas (158 offerte fisse matchate, 1000 Smc)
- Δ totale avg: 12,61€
- Within 15€: 126/158 (80%)

### Outlier (>15€) — cause note
1. Sconti con soglia non applicati (es. Atena "40% primi 840 kWh")
2. Sconti condizionali non applicati (es. E.ON, Iren — SDD/dual-fuel only)
3. Dati ARERA anomali (es. Cogeser +447€)
4. Componenti fissi multipli non sommati (logica prende primo keyword match)

---

## Cosa MANCA (backlog)

### Alta priorita'
- [x] **Sconti fissi condizionali**: parsato `CONDIZIONE_APPLICAZIONE`. Sconti incondizionali (00) applicati di qualsiasi tipo (€/kWh, €/Smc, €/anno, €/mese). Condizionali esposti in UI. 306 LUCE + 233 GAS con sconti applicati
- [x] **Componenti fissi multipli**: sommati tutti i componenti €/anno con keyword commercializzazione/CCV/quota (non solo il primo). Residenziale media 142.77€, business 207.36€
- [x] **UI: badge sconti non applicati**: badge blu in TariffCard "Prezzo base — sconti condizionali (SDD/dual) possono ridurre il costo"
- [x] **F1/F2/F3 PUN forward per variabili multioraria**: rapporti statistici GME (F1 +7%, F3 -10% vs F2). Stime applicate a backend e frontend. ARERA pubblica un solo PUN forward — documentato.

### Media priorita'
- [ ] **Sync ARERA automatico via cron** su OVH (1x/settimana)
- [ ] **Pagine /fornitori/ arricchite**: logo, descrizione, rating
- [x] **Gas: spesa_materia_energia** ora usata per retro-stima spread (molto più precisa di spesaAnnua/consumo). Fix anche spread_eur_smc nel frontend e WebMCP.

### Bassa priorita'
- [ ] **Admin: upload manuale PUN forward** se sync fallisce
- [ ] **CDISPD gas**: verificare se serve (solo 1 offerta gas su 2552 ha dispacciamento)

---

## Come riprendere il lavoro

1. Apri un nuovo progetto opencode in `/Users/djanc/Documents/Progetti_IA/AIenergywebmcp`
2. Digita: "leggi STATO_LAVORI.md"
3. Continua dal backlog

---

## File modificati (riepilogo)

| File | Modifiche |
|------|-----------|
| `backend/php/inc/arera_sync.php` | arera_parse_components, arera_parse_sconti, somma energia per fascia, sconti energia, PUN forward fix |
| `backend/php/inc/bill_parser.php` | dispacciamento, arera_breakdown, accise unificate, IVA business, potenza, detectClientType |
| `backend/php/inc/tariff_loader.php` | Allineato a Megaprogetto: tipo_cliente, tariffIsExpired, tariffIsAvailableInZone, somma componenti per fascia |
| `backend/php/api/index.php` | handleTariffe tipo_cliente, dispacciamento attualizzazione, handleV2Analyze |
| `frontend/src/lib/constants.js` | DISPACCIAMENTO 0.016988 |
| `frontend/src/lib/calc.js` | dispacciamento al subtotal |
| `frontend/src/pages/Home.jsx` | d.top3 → d.results |
| `backend/php/data/offerte/*.json` | Rigenerati dal sync (3190 luce, 2410 gas) |
| `backend/php/data/offerte/config.json` | PUN 0.1434, PSV 0.56378 |
| `backend/php/inc/arera_sync.php` | Costi fissi multipli: somma componenti commercializzazione/CCV/quota (era solo primo match) |
| `backend/php/api/index.php` | Fix conteggio sync: `$o['uso']` → `$o['tipo_cliente']` (era tutto "aziende") |
| `frontend/vite.config.js` | copyBackendPlugin copia anche `data/offerte/*.json` e `market_history.json` |
| `frontend/src/components/TariffCard.jsx` | Badge sconti condizionali non applicati |
| `frontend/src/components/BillCostChart.jsx` | Slice Canone RAI separata con asterisco auto-correzione |
| `frontend/src/components/CostBreakdownCard.jsx` | Barra Canone RAI separata |
| `frontend/src/pages/Home.jsx` | canoneRai scorporato da imposte, flag autoCorrected |
| `~/.config/opencode/opencode.jsonc` | MCP server headroom (stdio, 3 tool) |
| `~/.zshrc` | Auto-avvio proxy Headroom |

## Costanti ARERA attuali (Q3 2026)

| Costante | Valore | File |
|----------|--------|------|
| PERDITE_RETE_BT | 1.102 | bill_parser.php, constants.js |
| TRASPORTO_VAR (luce) | 0.01473 €/kWh | bill_parser.php, constants.js |
| ONERI_SISTEMA (luce) | 0.030295 €/kWh | bill_parser.php, constants.js |
| ACCISE (luce) | 0.0227 €/kWh | bill_parser.php, constants.js |
| ACCISE_SOGLIA_ESENTE | 1800 kWh | bill_parser.php, constants.js |
| ACCISE_SOGLIA_COMPENSATA | 2640 kWh | bill_parser.php, constants.js |
| COSTO_POTENZA_KW | 23.76 €/kW/anno | bill_parser.php, constants.js |
| QUOTA_FISSA_RETI (luce) | 23.04 €/anno | bill_parser.php, constants.js |
| DISPACCIAMENTO (luce) | 0.016988 €/kWh | bill_parser.php, constants.js |
| IVA residenziale | 10% | bill_parser.php, constants.js |
| IVA business | 22% | bill_parser.php |
| CANONE_RAI | 90 €/anno | bill_parser.php, constants.js |
| TRASPORTO_VAR (gas) | 0.15 €/Smc | bill_parser.php, constants.js |
| ONERI_SISTEMA (gas) | 0.03 €/Smc | bill_parser.php, constants.js |
| ACCISE (gas) | 0.149959 €/Smc | bill_parser.php, constants.js |
| ADDIZIONALE_REGIONALE (gas) | 0.0093 €/Smc | bill_parser.php, constants.js |
