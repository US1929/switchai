/**
 * constants.js — Costanti regolatorie ARERA centralizzate.
 *
 * UNICA FONTE per i parametri di calcolo.
 * Aggiornare qui per propagare a TUTTI i calcoli (calcLuceCost, calcGasCost, estimateRegulatedCosts).
 *
 * Fonti ufficiali:
 *   - Perdite rete BT: ARERA Del. 449/2020 (coefficiente perdita BT)
 *   - Trasporto: ARERA Del. 575/2025/R/eel (TRAS + UC3 + UC6)
 *   - Oneri sistema: ARERA Comunicato Q2 2026 (ASOS + ARIM)
 *   - Accise: DL 504/1995 e s.m.i., soglie di esenzione
 *   - IVA: DPR 633/1972, usi domestici/residenziali
 *
 * Data ultimo aggiornamento: 2026-06-26
 */

// ── LUCE ────────────────────────────────────────────────────────
export const LUCE = {
  /** Perdite di rete Bassa Tensione (coefficiente moltiplicativo) — ARERA Del. 449/2020 */
  PERDITE_RETE_BT: 1.102,

  /** Quota fissa annua trasporto + gestione contatore (€/anno) — ARERA Del. 575/2025 */
  QUOTA_FISSA_RETI: 23.04,

  /** Trasporto variabile (€/kWh) — TRAS 0.01190 + UC3 0.00007 + UC6 0.00007 (Del. 575/2025) */
  TRASPORTO_VAR: 0.01204,

  /** Oneri di sistema (€/kWh) — ASOS 0.02866 + ARIM 0.00164 (Comunicato Q2 2026) */
  ONERI_SISTEMA: 0.0303,

  /** Accisa erariale elettricità residenziale (€/kWh) — DL 504/1995 */
  ACCISE: 0.0227,

  /** Soglia esenzione accise (kWh/anno) — DL 504/1995: fino a 150 kWh/mese */
  ACCISE_SOGLIA_ESENTE: 1800,

  /** Soglia compensazione accise (kWh/anno) — l'esenzione si riduce linearmente fino a questo valore */
  ACCISE_SOGLIA_COMPENSATA: 2640,

  /** Quota potenza impegnata (€/kW/anno) — ARERA Del. 575/2025 */
  COSTO_POTENZA_KW: 23.52,

  /** Aliquota IVA agevolata usi domestici */
  IVA: 0.10,

  /** Canone RAI annuale (€/anno) — addebitato in bolletta LUCE, NON cambia con fornitore */
  CANONE_RAI_ANNUO: 90.00,

  /** Prezzo di riferimento tutela (€/kWh) — fallback quando non si hanno dati utente */
  PREZZO_RIFERIMENTO: 0.16,

  /** Quota fissa mensile di riferimento (€/mese) */
  QUOTA_FISSA_RIFERIMENTO: 120, // annuale: 10€/mese × 12
};

// ── GAS ─────────────────────────────────────────────────────────
export const GAS = {
  /** Quota fissa annua trasporto + gestione contatore gas (€/anno) */
  QUOTA_FISSA_RETI: 23.00,

  /** Trasporto variabile gas (€/Smc) — distribuzione */
  TRASPORTO_VAR: 0.15,

  /** Oneri di sistema gas (€/Smc) */
  ONERI_SISTEMA: 0.03,

  /** Accisa gas naturale usi civili (€/Smc) */
  ACCISE: 0.149959,

  /** Addizionale regionale gas (€/Smc) */
  ADDIZIONALE_REGIONALE: 0.0093,

  /** Soglia IVA 10% (Smc/anno); oltre si applica IVA 22% */
  SOGLIA_IVA_10: 480,

  /** Aliquota IVA agevolata */
  IVA_10: 0.10,

  /** Aliquota IVA ordinaria (oltre soglia) */
  IVA_22: 0.22,

  /** Prezzo di riferimento tutela (€/Smc) — fallback */
  PREZZO_RIFERIMENTO: 0.55,

  /** Quota fissa mensile di riferimento (€/mese) */
  QUOTA_FISSA_RIFERIMENTO: 120, // annuale: 10€/mese × 12
};

// ── Indici di mercato (fallback) ─────────────────────────────────
export const MERCATO = {
  /** PUN di riferimento (€/kWh) quando il dato live non è disponibile */
  PUN_REF: 0.125,
  /** PSV di riferimento (€/Smc) quando il dato live non è disponibile */
  PSV_REF: 0.450,
};

/**
 * Restituisce le costanti per la commodity specificata.
 * Usato per accesso dinamico: `getConstants('luce').ONERI_SISTEMA`
 */
export function getConstants(commodity) {
  return commodity === 'luce' ? LUCE : GAS;
}
