# Documentazione Calcolo Spesa Annua — Switchai

> Allineamento ARERA v4.0 (Febbraio 2026) — Delibera 386/2025/R/com
> Data documento: 23 Giugno 2026

---

## Indice

1. [Fonti ufficiali](#1-fonti-ufficiali)
2. [Costanti regolatorie](#2-costanti-regolatorie)
3. [Algoritmo di calcolo — Luce](#3-algoritmo-di-calcolo--luce)
4. [Algoritmo di calcolo — Gas](#4-algoritmo-di-calcolo--gas)
5. [Dettaglio modifiche v4.0](#5-dettaglio-modifiche-v40)
6. [ARERA Breakdown (API)](#6-arera-breakdown-api)
7. [Confronto con concorrenti](#7-confronto-con-concorrenti)
8. [Gap noti](#8-gap-noti)
9. [File interessati](#9-file-interessati)

---

## 1. Fonti ufficiali

### Documenti ARERA

| Fonte | Descrizione | Link |
|---|---|---|
| **PDF "Regole per il calcolo della Spesa Annua Stimata" v4.0** (46 pagine) | Specifica ufficiale dell'algoritmo — febbraio 2026 | Scaricato da `ilportaleofferte.it` |
| **Delibera 575/2025/R/eel** | Tariffe di rete, trasporto, quota fissa, costo potenza — Q2 2026 | arera.it |
| **Delibera 449/2020/R/eel** | Coefficiente perdite rete BT (λ = 10.2%) | arera.it |
| **Delibera 386/2025/R/com** | Razionalizzazione corrispettivi, obblighi informativi — in vigore dal 01/04/2026 | arera.it |
| **Comunicato ARERA Q2 2026** | Oneri generali di sistema (ASOS + ARIM) | arera.it |
| **Delibera 566/2021/R/eel** | Mercato capacità (MC1, MC2, MC3) | arera.it |
| **DL 504/1995** (Testo Unico Accise) | Aliquote accisa energia elettrica e gas naturale | normattiva.it |
| **Delibera 553/207/R/eel** | Ridefinizione tempistiche determinazione corrispettivi dispacciamento | arera.it |
| **Portale Offerte (AU)** | Portale ufficiale di confronto: `ilportaleofferte.it` | acquirenteunico.it |

### Dati di mercato

| Fonte | Dato | Frequenza |
|---|---|---|
| `portaleenergia.it` API | PUN (Prezzo Unico Nazionale) €/MWh | Giornaliero |
| `portaleenergia.it` API | PSV (Punto di Scambio Virtuale) €/MWh | Giornaliero |
| ARERA XML (sync) | Offerte luce/gas con componenti, spread, sconti | Settimanale via SII |
| Forward PUN/PSV | Quotazioni trimestrali futures (NON implementato) | Trimestrale |

---

## 2. Costanti regolatorie

### 2.1 Luce

| Costante | Valore | Unità | Fonte | Note |
|---|---|---|---|---|
| `PERDITE_RETE_BT` | 1.102 | coeff. | Del. 449/2020 | λ = 10.2%, invariato da 2020 |
| `QUOTA_FISSA_RETI` | 23.04 | €/anno | Del. 575/2025 | Trasporto + gestione contatore |
| `TRASPORTO_VAR` | 0.01204 | €/kWh | Del. 575/2025 | TRAS 0.00698 + DIS 0.00492 + UC3 0.00007 + UC6 0.00007 |
| `ONERI_SISTEMA` | 0.0303 | €/kWh | Comunicato Q2 2026 | ASOS 0.02866 + ARIM 0.00164 |
| `ACCISE` | 0.0227 | €/kWh | DL 504/1995 art. 52 | Aliquota residenti oltre soglia |
| `ACCISE_SOGLIA_ESENTE` | 1800 | kWh | DL 504/1995 | 150 kWh/mese × 12 — esenti per residenti ≤3kW |
| `ACCISE_SOGLIA_COMPENSATA` | 2640 | kWh | DL 504/1995 | 220 kWh/mese × 12 — oltre scatta compensazione |
| `COSTO_POTENZA_KW` | 23.52 | €/kW/anno | Del. 575/2025 | Quota potenza impegnata |
| `IVA` | 0.10 | % | DPR 633/72 | 10% usi domestici |

### 2.2 Gas

| Costante | Valore | Unità | Fonte | Note |
|---|---|---|---|---|
| `QUOTA_FISSA_RETI` | 23.00 | €/anno | Del. ARERA | Trasporto + gestione contatore |
| `TRASPORTO_VAR` | 0.15 | €/Smc | Del. ARERA | Componente variabile trasporto |
| `ONERI_SISTEMA` | 0.03 | €/Smc | Del. ARERA | UG2 + RE + UG3 |
| `ACCISE` | 0.149959 | €/Smc | DL 504/1995 | Valore preciso (arrotondamento da 0.15) |
| `ADDIZIONALE_REGIONALE` | 0.0093 | €/Smc | DL 504/1995 + var. regionali | Media nazionale |
| `SOGLIA_IVA_10` | 480 | Smc | DPR 633/72 | Sotto: 10%, sopra: 22% |
| `IVA_10` | 0.10 | % | DPR 633/72 | Aliquota agevolata |
| `IVA_22` | 0.22 | % | DPR 633/72 | Aliquota ordinaria |

### 2.3 Mercato (fallback)

| Costante | Valore | Unità | Note |
|---|---|---|---|
| `PUN_REF` | 0.125 | €/kWh | Usato quando PUN live non disponibile |
| `PSV_REF` | 0.450 | €/Smc | Usato quando PSV live non disponibile |

---

## 3. Algoritmo di calcolo — Luce

### 3.1 Formula generale (ARERA §3 — Offerta Mercato Libero EE)

```
SPESA_ANNUA =
    SpesaMateriaPrimaEnergia
    + SpesaCommercializzazione
    + SpesaDispacciamento
    + SpesaTariffaUsoReteElettrica
    + SpesaOneriGeneraliSistema
    + SpesaComponenteImpresaUnaTantum
    - ScontoVendita
    - ScontoUnaTantum (se IVA_SCONTO='SI')
    + SpesaImposte (Accise + IVA)
    - ScontoUnaTantum (se IVA_SCONTO='NO')
```

### 3.2 Offerta a PREZZO FISSO

```
SpesaMateriaPrima = ∑(P_fissa_fascia_j × CONSUMO_fascia_j) + P_fissa_fissa + P_potenza × POTENZA
```

λ NON si applica: le offerte fisse hanno già il prezzo comprensivo di tutti i costi.

Nel nostro codice:
```
energyCost = kwh × prezzo_tot_kwh    (monorario)
          oppure ∑(fascia × prezzo_fascia)    (multiorario)
```

### 3.3 Offerta a PREZZO VARIABILE

**ARERA spec (§3.3.1.5):**
```
SpesaEnergia = P_fix_FER + {
    [∑(IDX_F1_Qi)/4 × (1+λ) + SPREAD] × CONSF1
    + [∑(IDX_F23_Qi)/4 × (1+λ) + SPREAD] × (CONSF2 + CONSF3)
} + P_vol_FER × consumo + P_pot_QE × POTENZA + CRPPE × consumo
```

**Semplificazione Switchai** (monorario, forward non disponibile):
```
energyCost = consumo × (PUN_corrente × 1.102 + SPREAD)
```

**PRIMA del fix (sbagliato):**
```
energyCost = consumo × (PUN + SPREAD) × 1.102
```
Errore: λ applicato anche allo SPREAD, ma SPREAD è trasmesso dal venditore già comprensivo di perdite (ARERA v3.01, Ott 2021).

**DOPO il fix (corretto):**
```
energyCost = consumo × (PUN × 1.102 + SPREAD)
```

### 3.4 Componenti regolate (uguali per tutti i fornitori)

```
costo_potenza    = COSTO_POTENZA_KW × potenza = 23.52 × 3.0 = 70.56 €/anno
trasporto        = consumo × TRASPORTO_VAR = 2700 × 0.01204 = 32.51 €/anno
oneri_sistema    = consumo × ONERI_SISTEMA = 2700 × 0.0303 = 81.81 €/anno
quota_fissa_reti = QUOTA_FISSA_RETI = 23.04 €/anno
```

### 3.5 Accise (solo residenti ≤3kW)

**Logica completa (ARERA §3.1.6.1):**

```
Se consumo ≤ 1800 kWh:
    accise = 0

Se 1800 < consumo ≤ 2640 kWh:
    accise = (consumo - 1800) × 0.0227

Se consumo > 2640 kWh:
    esenzione_residua = max(0, 1800 - (consumo - 2640))
    accise = (consumo - esenzione_residua) × 0.0227
```

**Esempio con 2700 kWh:**
```
esenzione_residua = max(0, 1800 - (2700 - 2640)) = max(0, 1740) = 1740
accise = (2700 - 1740) × 0.0227 = 960 × 0.0227 = 21.79 €
```

**Confronto vecchio metodo:**
```
Vecchio: max(0, 2700-1800) × 0.0227 = 900 × 0.0227 = 20.43 €
Differenza: 21.79 - 20.43 = +1.36 €
```

### 3.6 IVA

```
IVA = 10% su (Energia + Commercializzazione + Dispacciamento + Rete + Oneri + Accise)
```

### 3.7 Esempio completo (2700 kWh, offerta variabile, PUN=0.1434, spread=0.0275)

```
Componente            Calcolo              Valore (€)
─────────────────────────────────────────────────────
Energia       2700 × (0.1434×1.102+0.0275)   500.92
Costo pot.    23.52 × 3.0                     70.56
Trasporto     2700 × 0.01204                   32.51
Oneri sistema 2700 × 0.0303                    81.81
Accise        (2700-1740) × 0.0227             21.79
Quota fissa   costo_fisso_fornitore           variabile
─────────────────────────────────────────────────────
Subtotale                                      707.59 + quota_fissa
IVA 10%                                        70.76 + quota_fissa×10%
─────────────────────────────────────────────────────
TOTALE                                         778.35 + quota_fissa×1.10
```

---

## 4. Algoritmo di calcolo — Gas

### 4.1 Formula generale (ARERA §4 — Offerta Mercato Libero Gas)

```
SPESA_ANNUA =
    SpesaMateriaPrimaGas
    + SpesaCommercializzazione
    + SpesaTariffaUsoReteGas
    + SpesaOneriGeneraliSistema
    + SpesaComponenteImpresaUnaTantum
    - ScontoVendita
    + SpesaImposte (Accise + Addizionale Regionale + IVA)
```

### 4.2 Offerta a PREZZO FISSO

```
SpesaGas = P_vol_QE_G × CONSUMO_Tot + ∑CR_QE × CONSUMO_Tot
```

Nel nostro codice:
```
energyCost = consumo × prezzo_tot_smc
```

### 4.3 Offerta a PREZZO VARIABILE

```
SpesaGas = ∑[CONSUMO_Mese_i × (IDX_i + SPREAD)] + P_vol_QE_G × CONSUMO_Tot
```

Nel nostro codice:
```
energyCost = consumo × (PSV + SPREAD)
```

NOTA: Per il gas NON si applica λ (perdite di rete). Il coefficiente λ è specifico per l'energia elettrica.

### 4.4 Componenti regolate

```
trasporto   = consumo × TRASPORTO_VAR = 1000 × 0.15 = 150 €/anno
oneri       = consumo × ONERI_SISTEMA = 1000 × 0.03 = 30 €/anno
accise      = consumo × ACCISE = 1000 × 0.149959 = 149.96 €/anno
addizionale = consumo × ADDIZIONALE_REGIONALE = 1000 × 0.0093 = 9.30 €/anno
rete_fissa  = QUOTA_FISSA_RETI = 23 €/anno
```

### 4.5 IVA Gas

L'IVA si applica progressivamente in base al consumo annuo:

```
Se consumo ≤ 480 Smc:
    IVA = 10% del subtotale

Se consumo > 480 Smc:
    IVA = (480/consumo × subtotale × 10%) + ((consumo-480)/consumo × subtotale × 22%)
```

**Esempio con 1000 Smc, subtotale = 887.26:**
```
scaglione_10 = min(1000, 480) = 480 → 480/1000 × 887.26 × 0.10 = 42.59
scaglione_22 = max(0, 1000-480) = 520 → 520/1000 × 887.26 × 0.22 = 101.50
IVA totale = 42.59 + 101.50 = 144.09
```

### 4.6 Esempio completo (1000 Smc, offerta fissa, prezzo=0.45 €/Smc)

```
Componente            Calcolo              Valore (€)
─────────────────────────────────────────────────────
Energia       1000 × 0.45                     450.00
Quota fissa   7 × 12                           84.00
Trasporto     1000 × 0.15                     150.00
Oneri sistema 1000 × 0.03                      30.00
Accise        1000 × 0.149959                  149.96
Addizionale   1000 × 0.0093                     9.30
─────────────────────────────────────────────────────
Subtotale                                      873.26
IVA (10+22%)                                  143.51
─────────────────────────────────────────────────────
TOTALE                                       1,016.77
```

---

## 5. Dettaglio modifiche v4.0

### 5.1 λ (perdite rete) — Fixato

| | Prima | Dopo |
|---|---|---|
| **Formula** | `(PUN + SPREAD) × 1.102` | `PUN × 1.102 + SPREAD` |
| **Base normativa** | Interpretazione errata | ARERA v3.01 (Ott 2021): "Applicazione delle perdite di rete solo sui valori forward, lo spread è trasmesso dai venditori già comprensivo delle perdite" |
| **Impatto (2700 kWh, PUN=0.1434, spread=0.0275)** | 0.1883 €/kWh → 508.52€ | 0.1855 €/kWh → 500.92€ |
| **Differenza** | — | **-7.56€/anno** |

### 5.2 Accise luce — Fixato

| | Prima | Dopo |
|---|---|---|
| **Formula** | `max(0, consumo-1800) × 0.0227` | Tiered: ≤1800=0, ≤2640=(c-1800)×0.0227, >2640=(c-esenzione_residua)×0.0227 |
| **Impatto (2700 kWh)** | 20.43€ | 21.79€ |
| **Differenza** | — | **+1.36€/anno** |

### 5.3 Accise gas — Precisione

| | Prima | Dopo |
|---|---|---|
| **Valore** | 0.15 €/Smc | 0.149959 €/Smc |
| **Impatto (1000 Smc)** | 150.00€ | 149.96€ |
| **Differenza** | — | **-0.04€/anno** |

### 5.4 Addizionale regionale gas — Nuovo

| | Prima | Dopo |
|---|---|---|
| **Valore** | 0 | 0.0093 €/Smc |
| **Impatto (1000 Smc)** | 0€ | 9.30€ |
| **Differenza** | — | **+9.30€/anno** |

### 5.5 Costanti nuove

| Costante | Valore | Descrizione |
|---|---|---|
| `ACCISE_SOGLIA_COMPENSATA` | 2640 | Soglia compensazione accise luce (DL 504/1995) |
| `GAS_ACCISE` | 0.149959 | Aliquota precisa gas (era 0.15) |
| `GAS_ADDIZIONALE_REGIONALE` | 0.0093 | Addizionale regionale gas (media nazionale) |

---

## 6. ARERA Breakdown (API)

### 6.1 Response `POST /api/calculate-savings`

Nuovo campo `arera_breakdown` in ogni risultato:

```json
{
  "results": [
    {
      "tariff_id": "...",
      "annual_cost_eur": 430.68,
      "arera_breakdown": {
        "materia_prima": 17.82,
        "commercializzazione": 144.00,
        "dispacciamento": 0,
        "tariffa_rete": 126.11,
        "oneri_sistema": 81.81,
        "accise": 21.79,
        "iva": 39.15,
        "totale": 430.68
      }
    }
  ]
}
```

### 6.2 Mappatura componenti ARERA → Switchai

| Componente ARERA | Switchai breakdown | Calcolo |
|---|---|---|
| SpesaMateriaPrimaEnergia | `materia_prima` | `energyCost` |
| SpesaCommercializzazione | `commercializzazione` | `costo_fisso × 12` |
| SpesaDispacciamento | `dispacciamento` | 0 (incluso nel prezzo materia) |
| SpesaTariffaUsoReteElettrica | `tariffa_rete` | `trasporto + quota_fissa_reti + costo_potenza` |
| SpesaOneriGeneraliSistema | `oneri_sistema` | `consumo × ONERI_SISTEMA` |
| SpesaImposte (accise) | `accise` | Accise luce/gas + addizionale |
| SpesaImposte (IVA) | `iva` | IVA su totale imponibile |

---

## 7. Confronto con concorrenti

### 7.1 Wattene.it

| Caratteristica | Wattene | Switchai |
|---|---|---|
| **Offerte coperte** | ~96% ARERA (esclude <50k utenze, no link, costi recesso) | 100% ARERA (3150 luce, 2390 gas) |
| **Forward prices** | PUN/PSV trimestrale futures | PUN/PSV corrente |
| **Sconti** | Applica β (%), γ (€/kWh), sconto vendita | Non applicati (dati non importati) |
| **Commercializzazione** | Separata (PCV + QVD) | Inclusa in costo_fisso |
| **Correzioni manuali** | Corregge errori dati ARERA | Import raw |
| **Calcolo** | Stessa formula ARERA (identica al Portale Offerte) | Stessa formula ARERA |

### 7.2 Il Portale Offerte (AU)

Il Portale Offerte ufficiale di Acquirente Unico usa ESATTAMENTE l'algoritmo descritto nel PDF v4.0. Le differenze principali rispetto alla nostra implementazione:

1. **Forward prices**: Il Portale usa quotazioni trimestrali forward (PING_M_F1_Qi) pubblicate da AU nel SII. Noi usiamo il PUN/PSV corrente.
2. **Perdite di rete**: Il Portale applica λ SOLO sul forward medio dei 4 trimestri, NON sul prezzo totale.
3. **Sconti**: Il Portale processa sconti percentuali e fissi da ARERA XML. Noi non li estraiamo ancora.
4. **Scaglioni**: Il Portale gestisce scaglioni di consumo (es. prime 120 Smc a prezzo A, oltre a prezzo B). Noi no.

---

## 8. Gap noti

### 8.1 Priorità alta

| Gap | Dettaglio | Impatto |
|---|---|---|
| **Forward prices** | Le offerte variabili usano il PUN/PSV corrente invece delle quotazioni forward trimestrali fornite da AU | La stima 12 mesi potrebbe differire per offerte variabili |
| **Sconti non processati** | Il sync ARERA non importa/processa i campi `sconto` delle offerte | Offerte con sconti vengono mostrate senza sconto, risultando più care del reale |
| **Commercializzazione separata** | Alcune offerte hanno PCV (commercializzazione) come componente regolata separata | Potenziale doppio conteggio o sottostima |

### 8.2 Priorità media

| Gap | Dettaglio |
|---|---|
| **Scaglioni consumo** | Alcune offerte hanno prezzi diversi per scaglioni (es. primi 1000 kWh a X€, il resto a Y€) |
| **Validità condizioni** | Offerte con prezzi promozionali validi solo primi N mesi |
| **Dispacciamento separato** | Non modelliamo dispacciamento come componente separata nell'API |
| **Zone climatiche gas** | L'addizionale regionale varia per regione (noi usiamo media 0.0093) |
| **Correzioni ARERA** | Wattene corregge errori nei dati pubblicati da AU. Noi no. |

### 8.3 Priorità bassa

| Gap | Dettaglio |
|---|---|
| **Canone TV** | Escluso dal calcolo (come da specifica ARERA). OK. |
| **Bonus sociali** | Non inclusi nella stima. OK. |
| **Depositi cauzionali** | Non inclusi. OK. |
| **Spese attivazione** | Non incluse (Componente Una-Tantum non processata). OK per ora. |

---

## 9. File interessati

### Frontend (JavaScript)

| File | Ruolo |
|---|---|
| `frontend/src/lib/constants.js` | **Fonte unica** costanti ARERA lato frontend |
| `frontend/src/lib/calc.js` | `calcLuceCost()`, `calcGasCost()`, `estimateRegulatedCosts()` |

### Backend (PHP)

| File | Ruolo |
|---|---|
| `backend/php/inc/bill_parser.php` | **Fonte unica** costanti ARERA lato backend + `calculateSavings()`, `calculateSavingsBreakdown()`, `getAreraConstants()` |
| `backend/php/inc/tariff_loader.php` | Normalizzazione offerte ARERA (`normalizeLuceOffer()`, `normalizeGasOffer()`) |
| `backend/php/api/index.php` | Endpoint `POST /api/calculate-savings`, `GET /api/arera-constants` |
| `backend/php/data/offerte/config.json` | PUN/PSV reference values (`PUN: 0.1434`, `PSV: 0.563775`) |
| `backend/php/data/offerte/db-offerte-luce.json` | ~3150 offerte luce (array JSON, ~9 MB) |
| `backend/php/data/offerte/db-offerte-gas.json` | ~2390 offerte gas (array JSON, ~5.4 MB) |

### Loghi fornitori

`frontend/public/loghi/` — uno per ogni fornitore ARERA.

---

## Appendice A: Verifica calcolo

### A.1 Luce — Offerta fissa (POWER FIX CASA, PIUENERGIA)

```
prezzo_tot_kwh = 0.14
costo_fisso = 102 €/anno (8.50 €/mese)
consumo = 2700 kWh, potenza = 3.0 kW

energyCost = 2700 × 0.14 = 378.00
costo_potenza = 23.52 × 3.0 = 70.56
trasporto = 2700 × 0.01204 = 32.51
oneri = 2700 × 0.0303 = 81.81
accise = 21.79 (compensato)
quota_fissa = 102.00 + 23.04 = 125.04
subtotale = 378.00 + 70.56 + 32.51 + 81.81 + 21.79 + 125.04 = 709.71
IVA = 709.71 × 0.10 = 70.97
TOTALE = 709.71 + 70.97 = 780.68
```

### A.2 Gas — Offerta variabile (+CASA GAS, PIUENERGIA)

```
prezzo_tot_smc = 0.8138 (PSV=0.5638 + spread=0.25)
costo_fisso = 144 €/anno (12 €/mese)
consumo = 1000 Smc

energyCost = 1000 × 0.8138 = 813.80
trasporto = 1000 × 0.15 = 150.00
oneri = 1000 × 0.03 = 30.00
accise = 1000 × 0.149959 = 149.96
addizionale = 1000 × 0.0093 = 9.30
quota_fissa = 144.00 + 23.00 = 167.00
subtotale = 813.80 + 150.00 + 30.00 + 149.96 + 9.30 + 167.00 = 1320.06
IVA_10 = 480/1000 × 1320.06 × 0.10 = 63.36
IVA_22 = 520/1000 × 1320.06 × 0.22 = 151.02
IVA = 63.36 + 151.02 = 214.38
TOTALE = 1320.06 + 214.38 = 1534.44
```

---

## Appendice B: Storia modifiche documento ARERA

| Versione | Data | Modifica |
|---|---|---|
| 1.00 | 30/06/2018 | Prima stesura |
| 2.00 | 20/12/2018 | Integrazione offerte mercato libero |
| 3.00 | 11/02/2020 | Scaglioni consumo, offerte FLAT, componenti regolate |
| 3.01 | 07/10/2021 | **λ solo su forward, NON su spread** — aggiornamento critico |
| 3.02 | 21/01/2022 | Offerte PLACET, eliminazione FLAT |
| 3.03 | 02/10/2023 | Fine Maggior Tutela non domestici |
| 3.04 | 23/01/2025 | Corrispettivo Dispacciamento TIDE |
| **4.0** | **16/02/2026** | **Del. 386/2025/R/com — in vigore dal 01/04/2026** |
