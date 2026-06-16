/**
 * Utility functions for tariff calculations.
 * Costanti regolatorie ARERA → importate da constants.js (fonte unica).
 */
import { LUCE, GAS, MERCATO } from './constants.js';

export function parseItalianNum(str) {
  if (!str || str === "") return null;
  return parseFloat(String(str).replace(",", "."));
}

export function calcLuceCost(tariff, kwh, pun, formData = {}) {
  const f1 = formData.f1 || 0;
  const f2 = formData.f2 || 0;
  const f3 = formData.f3 || 0;
  const potenza = formData.potenza || 3.0;

  const costo_fisso = parseItalianNum(tariff["costo_fisso"]) || 0;
  const tipo = tariff["tariffa"]?.toLowerCase();
  
  // Costanti regolatorie ARERA (da constants.js — fonte unica)
  const { PERDITE_RETE_BT, QUOTA_FISSA_RETI, TRASPORTO_VAR, ONERI_SISTEMA, ACCISE, COSTO_POTENZA_KW } = LUCE;

  let energyCost = 0;
  if (tipo === "fissa" || tipo === "fisso") {
    const prezzo = parseItalianNum(tariff["prezzo tot kwh"]);
    if (!prezzo || prezzo < 0.01) return null;

    if (f1 > 0 || f2 > 0 || f3 > 0) {
      const pF1 = parseItalianNum(tariff["prezzo f1"]) || prezzo;
      const pF2 = parseItalianNum(tariff["prezzo f2"]) || prezzo;
      const pF3 = parseItalianNum(tariff["prezzo f3"]) || prezzo;
      energyCost = (f1 * pF1) + (f2 * pF2) + (f3 * pF3);
    } else {
      energyCost = kwh * prezzo;
    }
  } else {
    const spread = parseItalianNum(tariff["spread"]) || 0;
    const punBase = parseItalianNum(tariff["Pun"]) || pun;
    // Tariffe variabili: (PUN + spread) × perdite di rete BT
    energyCost = kwh * (punBase + spread) * PERDITE_RETE_BT;
  }

  const costo_potenza = COSTO_POTENZA_KW * potenza;
  const trasporto = kwh * TRASPORTO_VAR;
  const oneri = kwh * ONERI_SISTEMA;
  const accise = kwh * ACCISE;

  const annualFixed = costo_fisso + costo_potenza + QUOTA_FISSA_RETI;
  const subtotal = energyCost + annualFixed + trasporto + oneri + accise;
  const annualIVA = subtotal * 0.10;

  return subtotal + annualIVA;
}

export function calcGasCost(tariff, smc, psv) {
  // Costanti regolatorie ARERA gas (da constants.js — fonte unica)
  const { QUOTA_FISSA_RETI, TRASPORTO_VAR, ONERI_SISTEMA, ACCISE, SOGLIA_IVA_10, IVA_10, IVA_22 } = GAS;

  const costo_fisso = parseItalianNum(tariff["costo_fisso"]) || 0;
  const tipo = tariff["tariffa"]?.toLowerCase();

  let energyCost = 0;
  if (tipo === "fissa" || tipo === "fisso") {
    const prezzo = parseItalianNum(tariff["prezzo tot smc"]);
    if (!prezzo || prezzo < 0.01) return null;
    energyCost = prezzo * smc;
  } else {
    const spread = parseItalianNum(tariff["spread"]) || 0;
    const psvBase = parseItalianNum(tariff["psv Aprile 2025/"]) || psv;
    energyCost = smc * (psvBase + spread);
  }

  const trasporto = smc * TRASPORTO_VAR;
  const oneri = smc * ONERI_SISTEMA;
  const accise = smc * ACCISE;
  const subtotal = energyCost + costo_fisso + QUOTA_FISSA_RETI + trasporto + oneri + accise;

  const iva10 = Math.min(smc, SOGLIA_IVA_10) / (smc || 1) * subtotal * IVA_10;
  const iva22 = Math.max(0, smc - SOGLIA_IVA_10) / (smc || 1) * subtotal * IVA_22;
  const annualIVA = smc > 0 ? iva10 + iva22 : 0;
  
  return subtotal + annualIVA;
}

export function buildBreakdown(tariff, commodity, consumption, currentSpend, newCost, pun, psv) {
  const parts = [];
  if (commodity === "luce") {
    const kwh = consumption;
    const tipo = tariff["tariffa"]?.toLowerCase();
    const spread = parseItalianNum(tariff["spread"]) || 0;
    const costo_fisso = parseItalianNum(tariff["costo_fisso"]) || 0;
    const refPrice = LUCE.PREZZO_RIFERIMENTO;
    const refFixed = LUCE.QUOTA_FISSA_RIFERIMENTO;
    if (tipo === "fissa" || tipo === "fisso") {
      const prezzo = parseItalianNum(tariff["prezzo tot kwh"]);
      if (prezzo) {
        const energyCost = prezzo * kwh;
        const currentEnergy = refPrice * kwh;
        const diff = currentEnergy - energyCost;
        if (diff > 0) parts.push(`Risparmi ${diff.toFixed(0)}€ sulla materia prima: prezzo fisso a ${prezzo.toFixed(4)} €/kWh vs ~${refPrice.toFixed(2)} €/kWh attuali`);
      }
    } else {
      const punEff = parseItalianNum(tariff["Pun"]) || pun;
      const prezzoEff = punEff + spread;
      parts.push(`Tariffa variabile PUN (${punEff.toFixed(4)}) + spread ${spread.toFixed(4)} = ${prezzoEff.toFixed(4)} €/kWh`);
    }
    if (costo_fisso < refFixed) parts.push(`Quota fissa più bassa: ${costo_fisso}€/anno vs ~${refFixed}€/anno media`);
    else if (costo_fisso > refFixed * 1.5) parts.push(`Nota: quota fissa elevata ${costo_fisso}€/anno`);
    if (tariff["prezzo_bloccato"] && tariff["prezzo_bloccato"] !== "12")
      parts.push(`Prezzo bloccato per ${tariff["prezzo_bloccato"]} mesi`);
  } else {
    const smc = consumption;
    const tipo = tariff["tariffa"]?.toLowerCase();
    const spread = parseItalianNum(tariff["spread"]) || 0;
    const costo_fisso = parseItalianNum(tariff["costo_fisso"]) || 0;
    const refPrice = GAS.PREZZO_RIFERIMENTO;
    const refFixed = GAS.QUOTA_FISSA_RIFERIMENTO;
    if (tipo === "fissa" || tipo === "fisso") {
      const prezzo = parseItalianNum(tariff["prezzo tot smc"]);
      if (prezzo) {
        const energyCost = prezzo * smc;
        const currentEnergy = refPrice * smc;
        const diff = currentEnergy - energyCost;
        if (diff > 0) parts.push(`Risparmi ${diff.toFixed(0)}€ sulla materia prima: prezzo fisso a ${prezzo.toFixed(4)} €/Smc vs ~${refPrice.toFixed(2)} €/Smc attuali`);
      }
    } else {
      const psvEff = parseItalianNum(tariff["psv Aprile 2025/"]) || psv;
      const prezzoEff = psvEff + spread;
      parts.push(`Tariffa variabile PSV (${psvEff.toFixed(4)}) + spread ${spread.toFixed(4)} = ${prezzoEff.toFixed(4)} €/Smc`);
    }
    if (costo_fisso < refFixed) parts.push(`Quota fissa contenuta: ${costo_fisso}€/anno`);
  }
  if (tariff["note_costi"]) parts.push(`⚠️ ${tariff["note_costi"]}`);
  return parts;
}

export function formatEuro(n) {
  if (n === null || n === undefined) return "—";
  return new Intl.NumberFormat("it-IT", { style: "currency", currency: "EUR", maximumFractionDigits: 0 }).format(n);
}

export function deduplicateTariffs(tariffs) {
  const map = new Map();
  for (const t of tariffs) {
    const key = `${t.brand}|${t.offerta}`;
    if (!map.has(key)) {
      map.set(key, { ...t, azioni: [t.azione] });
    } else {
      const existing = map.get(key);
      if (!existing.azioni.includes(t.azione)) existing.azioni.push(t.azione);
    }
  }
  return Array.from(map.values());
}

/**
 * Calcola il prezzo energia attuale dell'utente (€/kWh o €/Smc).
 * Per tariffe variabili: PUN/PSV live + spread utente.
 * Per tariffe fisse: spesa_materia_energia / consumo_annuo (approssimazione).
 * Fallback: stima dal mercato tutelato.
 */
export function getCurrentPricePerUnit(llmData, punEurKwh, psvEurSmc, commodity, consumption) {
  if (!llmData) {
    // Nessun dato LLM: stima conservativa (da constants.js)
    return commodity === 'luce' ? LUCE.PREZZO_RIFERIMENTO : GAS.PREZZO_RIFERIMENTO;
  }

  const tipo = (llmData.tipo_tariffa || '').toLowerCase();
  const spread = parseFloat(llmData.spread) || 0;

  if ((tipo === 'variabile' || tipo === 'variable') && spread > 0) {
    // Tariffa variabile: prezzo attuale = (indice di mercato + spread) × perdite di rete
    if (commodity === 'luce' && punEurKwh > 0) {
      return (punEurKwh + spread) * LUCE.PERDITE_RETE_BT;
    } else if (commodity === 'gas' && psvEurSmc > 0) {
      return psvEurSmc + spread;
    }
  }

  // Tariffa fissa o fallback: stima da spesa_materia / consumo
  const spesaMateria = parseFloat(llmData.spesa_materia_energia) || 0;
  const consumo = parseFloat(llmData.consumo_annuo) || consumption || 0;
  if (spesaMateria > 0 && consumo > 0) {
    const raw = spesaMateria / consumo;
    // Sanity check: se è mensile (molto basso), moltiplica × 12
    if (commodity === 'luce' && raw < 0.03) return raw * 12;
    if (commodity === 'gas' && raw < 0.10) return raw * 12;
    return raw;
  }

  // Fallback: prezzi di riferimento (da constants.js)
  return commodity === 'luce' ? LUCE.PREZZO_RIFERIMENTO : GAS.PREZZO_RIFERIMENTO;
}

/**
 * Calcola la quota fissa mensile attuale dell'utente.
 */
export function getCurrentFixedMonthly(llmData, commodity) {
  if (llmData?.quota_fissa_mensile) {
    const v = parseFloat(llmData.quota_fissa_mensile);
    if (v > 0) return v;
  }
  // Stima: quota fissa riferimento (da constants.js) / 12
  return (commodity === 'luce' ? LUCE.QUOTA_FISSA_RIFERIMENTO : GAS.QUOTA_FISSA_RIFERIMENTO) / 12;
}

/**
 * Converte risparmio annuo in altre unità.
 */
export function savingsToHuman(savingsEur) {
  if (!savingsEur || savingsEur <= 0) return null;
  return {
    anno: savingsEur,
    mese: Math.round(savingsEur / 12),
    settimana: Math.round(savingsEur / 52),
    giorno: (savingsEur / 365).toFixed(2),
  };
}

/**
 * Assegna etichette di ranking alle prime 3 offerte.
 */
export function getRankingBadges(items, commodity) {
  if (!items || items.length === 0) return items;
  const result = items.map((item, i) => ({ ...item, rankBadge: null, rankLabel: null }));

  // Badge per la prima (miglior risparmio o minor costo)
  if (result[0]) {
    if (result[0].savings && result[0].savings > 0) {
      result[0].rankBadge = '🥇';
      result[0].rankLabel = 'Miglior risparmio';
    } else {
      result[0].rankBadge = '🥇';
      result[0].rankLabel = 'Costo più basso';
    }
  }

  // Badge per la seconda: "Miglior equilibrio" se ha risparmio buono e quota fissa contenuta
  if (result[1]) {
    result[1].rankBadge = '🥈';
    const fixed = result[1].tariff?.fixed_fee_monthly || (result[1].tariff?.costo_fisso ? Math.round(result[1].tariff.costo_fisso / 12 * 100) / 100 : null);
    if (result[1].savings && result[1].savings > 20 && fixed !== null && fixed <= 15) {
      result[1].rankLabel = 'Miglior equilibrio';
    } else {
      result[1].rankLabel = 'Buona alternativa';
    }
  }

  // Badge per la terza: "Prezzo stabile" se è a prezzo fisso/bloccato
  if (result[2]) {
    result[2].rankBadge = '🥉';
    if (result[2].tariff?.tariffa === 'Fissa' && result[2].tariff?.prezzo_bloccato) {
      result[2].rankLabel = 'Prezzo stabile';
    } else if (result[2].tariff?.tariffa === 'Fissa') {
      result[2].rankLabel = 'Prezzo fisso';
    } else {
      result[2].rankLabel = 'Opzione variabile';
    }
  }

  return result;
}

/**
 * Verifica se un prezzo unitario è sospettosamente basso.
 */
export function isPriceAnomalous(pricePerUnit, commodity) {
  if (pricePerUnit == null || pricePerUnit <= 0) return false;
  const threshold = commodity === 'luce' ? 0.05 : 0.20;
  return pricePerUnit < threshold;
}

/**
 * Stima i costi non negoziabili (trasporto, oneri, imposte) che rimangono uguali.
 * Restituisce oggetto con breakdown per il grafico "cosa cambia / cosa resta uguale".
 */
export function estimateRegulatedCosts(commodity, consumption, potenza = 3.0) {
  // Costanti da constants.js (fonte unica)
  if (commodity === 'luce') {
    const { TRASPORTO_VAR, ONERI_SISTEMA, ACCISE, COSTO_POTENZA_KW, QUOTA_FISSA_RETI, IVA } = LUCE;
    const kwh = consumption || 2700;
    const trasporto = kwh * TRASPORTO_VAR;
    const oneri = kwh * ONERI_SISTEMA;
    const accise = kwh * ACCISE;
    const costoPotenza = COSTO_POTENZA_KW * potenza;
    const quotaFissaReti = QUOTA_FISSA_RETI;
    const subtotalRegulated = trasporto + oneri + accise + costoPotenza + quotaFissaReti;
    const ivaRegulated = subtotalRegulated * IVA;
    return {
      trasporto: Math.round(trasporto),
      oneri: Math.round(oneri),
      accise: Math.round(accise),
      costoPotenza: Math.round(costoPotenza),
      quotaFissaReti: Math.round(quotaFissaReti),
      totale: Math.round(subtotalRegulated + ivaRegulated),
      label: 'Trasporto, oneri, imposte',
      note: 'Questi costi sono uguali con qualsiasi fornitore',
    };
  } else {
    const { TRASPORTO_VAR, ONERI_SISTEMA, ACCISE, QUOTA_FISSA_RETI, SOGLIA_IVA_10, IVA_10, IVA_22 } = GAS;
    const smc = consumption || 1000;
    const trasporto = smc * TRASPORTO_VAR;
    const oneri = smc * ONERI_SISTEMA;
    const accise = smc * ACCISE;
    const quotaFissaReti = QUOTA_FISSA_RETI;
    const subtotalRegulated = trasporto + oneri + accise + quotaFissaReti;
    const iva10 = Math.min(smc, SOGLIA_IVA_10) / (smc || 1) * subtotalRegulated * IVA_10;
    const iva22 = Math.max(0, smc - SOGLIA_IVA_10) / (smc || 1) * subtotalRegulated * IVA_22;
    return {
      trasporto: Math.round(trasporto),
      oneri: Math.round(oneri),
      accise: Math.round(accise),
      costoPotenza: 0,
      quotaFissaReti: Math.round(quotaFissaReti),
      totale: Math.round(subtotalRegulated + (smc > 0 ? iva10 + iva22 : 0)),
      label: 'Trasporto, oneri, imposte',
      note: 'Questi costi sono uguali con qualsiasi fornitore',
    };
  }
}
