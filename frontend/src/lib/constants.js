/**
 * constants.js — Costanti regolatorie ARERA centralizzate.
 *
 * UNICA FONTE per i parametri di calcolo.
 * Aggiornare qui per propagare a TUTTI i calcoli (calcLuceCost, calcGasCost, estimateRegulatedCosts).
 *
 * Fonti ufficiali:
 *   - Oneri sistema: https://www.arera.it/ (trimestrale, Asos + Arim)
 *   - Perdite rete BT: ARERA, coefficiente perdita BT (annuale)
 *   - Accise: Testo Unico Accise (DL 504/1995), aggiornamenti Legge di Bilancio
 *   - Trasporto/quota fissa: ARERA, tariffe distribuzione (annuale)
 *
 * Data ultimo aggiornamento: 2026-06-15
 */

// ── LUCE ────────────────────────────────────────────────────────
export const LUCE = {
  /** Perdite di rete Bassa Tensione (coefficiente moltiplicativo) */
  PERDITE_RETE_BT: 1.102,

  /** Quota fissa annua trasporto + gestione contatore (€/anno) */
  QUOTA_FISSA_RETI: 24.00,

  /** Trasporto variabile (€/kWh) — componente distribuzione */
  TRASPORTO_VAR: 0.0089,

  /** Oneri di sistema: Asos (rinnovabili) + Arim (altri oneri) (€/kWh) */
  ONERI_SISTEMA: 0.038,

  /** Accisa erariale elettricità residenziale >150kWh/mese (€/kWh) */
  ACCISE: 0.0227,

  /** Quota potenza impegnata (€/kW/anno) */
  COSTO_POTENZA_KW: 21.48,

  /** Aliquota IVA agevolata usi domestici */
  IVA: 0.10,

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

  /** Accisa gas naturale usi civili — scaglione standard (€/Smc) */
  ACCISE: 0.15,

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
