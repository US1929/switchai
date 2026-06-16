/**
 * Client API per il backend PHP
 */
const API_BASE = import.meta.env.VITE_API_URL || '/api';

export const api = {
  calculateSavings: async (params) => {
    const res = await fetch(`${API_BASE}/calculate-savings`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(params),
    });
    if (!res.ok) throw new Error((await res.json()).error || 'Errore calcolo');
    return res.json();
  },

  activateTariff: async (data) => {
    const res = await fetch(`${API_BASE}/activate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    if (!res.ok) throw new Error((await res.json()).error || "Errore attivazione");
    return res.json();
  },

  fetchTariffe: async (commodity) => {
    const res = await fetch(`${API_BASE}/tariffe/${commodity.toLowerCase()}`);
    if (!res.ok) throw new Error('Errore caricamento tariffe');
    return res.json();
  },

  analyzeBillText: async (text) => {
    const res = await fetch(`${API_BASE}/analyze`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bill_text: text }),
    });
    if (!res.ok) {
      const e = await res.json().catch(() => ({}));
      throw new Error(e.message || 'Errore analisi bolletta');
    }
    return res.json();
  }
};
