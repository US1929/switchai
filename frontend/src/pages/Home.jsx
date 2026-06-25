import React, { useState, useCallback, useEffect, useRef } from 'react';
import { api } from '../lib/api.js';
import { calcLuceCost, calcGasCost, buildBreakdown, deduplicateTariffs, formatEuro, getCurrentPricePerUnit, getCurrentFixedMonthly, getRankingBadges, isPriceAnomalous } from '../lib/calc.js';
import { MERCATO } from '../lib/constants.js';
import TariffCard from '../components/TariffCard.jsx';
import MarketSignal from '../components/MarketSignal.jsx';
import StickyReferenceBar from '../components/StickyReferenceBar.jsx';
import ChatDemo from '../components/ChatDemo.jsx';

function estimateSpend(commodity, consumption) {
  if (commodity === 'luce') return (consumption * 0.18) + 144;
  return (consumption * 0.65) + 144;
}

const STATS = [
  { value: '44+', label: 'Offerte Luce e Gas' },
  { value: '<30 sec', label: 'Per cambiare fornitore' },
  { value: '500€', label: 'Risparmio medio/anno' },
];

	const LLM_PROMPT = `Estrai dalla mia bolletta italiana (ARERA 2.0) questi dati in JSON. NON includere POD, indirizzo o dati personali.

	{
	  "commodity": "LUCE" o "GAS",
	  "consumo_annuo": numero (kWh per luce, Smc per gas — lo trovi nel frontespizio o nella sezione Consumi come "Consumo annuo"),
	  "spesa_annua": numero in € (se non indicata direttamente, stima: importo bolletta × 6 se bimestrale, × 4 se trimestrale, × 12 se mensile),
	  "zona": "NORD", "CENTRO" o "SUD" (dalla provincia/città della fornitura),
	  "tipo_cliente": "residenziale" o "business" (obbligatorio: leggi se la bolletta dice "residenziale", "domestica", "uso domestico" → residenziale; "business", "azienda", "non domestica", "Partita IVA" → business),
	  "spesa_materia_energia": numero in € (dal dettaglio costi, solo la componente energia/gas — escludi trasporto, oneri, imposte, IVA, canone RAI),
	  "quota_fissa_mensile": numero in €/mese (dal Box Offerta o dal dettaglio costi),
	  "canone_rai": numero in € (cerca "Canone RAI", "Canone TV", "canone di abbonamento" nel dettaglio costi bolletta LUCE. Di solito ~90€/anno. Metti 0 se non presente o se è bolletta GAS),
	  "consumo_f1": numero in kWh (opzionale, solo se bolletta multioraria),
	  "consumo_f2": numero in kWh (opzionale),
	  "consumo_f3": numero in kWh (opzionale),
	  "potenza_impegnata": numero in kW (opzionale, default 3.0),
	  "tipo_tariffa": "fisso" o "variabile" (dal Box Offerta),
	  "spread": numero in €/kWh o €/Smc (opzionale, solo se tariffa variabile — es. "PUN + 0,016" → 0.016),
	  "scadenza_offerta": "GG/MM/AAAA" (opzionale, dal Box Offerta),
	  "periodo_riferimento": "GG/MM/AAAA - GG/MM/AAAA" (opzionale, dal frontespizio)
	}

	Metti null per i campi che non trovi. Rispondi SOLO con il JSON.`;

export default function Home() {
  const [commodity, setCommodity] = useState('luce');
  const [consumption, setConsumption] = useState(2700);
  const [currentSpend, setCurrentSpend] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [results, setResults] = useState(null);
  const [showAll, setShowAll] = useState(false);
  const [llmResponse, setLlmResponse] = useState('');
  const [llmExtractedData, setLlmExtractedData] = useState(null);
  const [copyFeedback, setCopyFeedback] = useState('');
  const [marketData, setMarketData] = useState(null);
  const [demoOpen, setDemoOpen] = useState(false);
  const resultsRef = useRef(null);

  // Auto-scroll alla sezione risultati quando compaiono
  useEffect(() => {
    if (results && resultsRef.current) {
      resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }, [results]);

  const isLuce = commodity === 'luce';
  const accentColor = isLuce ? '#f59e0b' : '#3b82f6';

  // ── Core analysis (chiamata DIRETTA, no useEffect/stale closure) ─────
  const performAnalysis = useCallback(async (extractedData) => {
    setLoading(true); setError(null); setResults(null); setShowAll(false);

    // Usa dati estratti come fonte primaria; fallback a state per chiamate non-LLM
    const isLuceLocal = extractedData?.commodity
      ? extractedData.commodity.toUpperCase() !== 'GAS'
      : isLuce;
    const consumoLocal = extractedData?.consumo_annuo || consumption;
    const spesaLocal = extractedData?.spesa_annua
      ? String(extractedData.spesa_annua)
      : currentSpend;

    // ── API V2 path (se abbiamo dati estratti dal LLM) ───────────
    if (extractedData?.spesa_annua) {
      try {
        const body = {
          commodity: isLuceLocal ? 'LUCE' : 'GAS',
          spesa_annua_eur: Number(extractedData.spesa_annua),
          zona: extractedData.zona || 'NORD'
        };
        if (isLuceLocal) body.consumo_annuo_kwh = Number(extractedData.consumo_annuo || consumoLocal);
        else body.consumo_annuo_smc = Number(extractedData.consumo_annuo || consumoLocal);
        if (extractedData.tipo_tariffa) body.tariff_type = extractedData.tipo_tariffa;
        if (extractedData.canone_rai > 0) body.canone_rai = Number(extractedData.canone_rai);
        if (extractedData.tipo_cliente) body.tipo_cliente = extractedData.tipo_cliente;
        if (extractedData.spesa_materia_energia > 0) body.spesa_materia_energia = Number(extractedData.spesa_materia_energia);
        if (extractedData.quota_fissa_mensile > 0) body.quota_fissa_mensile = Number(extractedData.quota_fissa_mensile);
        if (extractedData.spread > 0) body.spread_eur_kwh = Number(extractedData.spread);
        if (extractedData.potenza_impegnata) body.potenza_impegnata = Number(extractedData.potenza_impegnata);
        // PUN/PSV live per confronto simmetrico ARERA
        if (punEurKwh > 0) body.pun_eur_kwh = punEurKwh;
        if (psvEurSmc > 0) body.psv_eur_smc = psvEurSmc;

        const r = await fetch('/api/analyze', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const d = await r.json();
        if (d.top3?.length) {
          const items = d.top3.map(o => ({
            tariff: { id: o.tariff_id, brand: o.supplier, supplier_name: o.supplier, offerta: o.tariff_name, tariffa: o.type === 'FISSO' ? 'Fissa' : 'Variabile', 'prezzo tot kwh': o.price_per_unit, 'prezzo tot smc': o.price_per_unit, costo_fisso: (o.fixed_fee_monthly||0)*12, fixed_fee_monthly: o.fixed_fee_monthly, logo: o.supplier_logo, spread: o.spread, Pagamento: o.payment_method, prezzo_bloccato: o.contract_detail?.match(/(\d+)\s*mesi/)?.[1] || null, validita_offerta: o.valid_until, penale_recesso: o.penale_recesso, vantaggi: o.advantages, note_costi: null, extra: { penale_recesso: o.penale_recesso, validita_offerta: o.valid_until, vantaggi: o.advantages, prezzo_bloccato_mesi: o.contract_detail?.match(/(\d+)\s*mesi/)?.[1] || null } },
            annualCost: o.annual_cost_eur, savings: o.savings_eur > 0 ? Math.round(o.savings_eur) : null, savingsPct: o.savings_pct, breakdown: o.breakdown ? [o.breakdown.explanation] : [], hasRealSpend: true,
            priceWarning: o.price_warning,
          }));
          setResults({ items, currentSpend: Number(extractedData.spesa_annua), hasRealSpend: true });
          setLoading(false); return;
        }
        // top3 vuoto: l'API non ha trovato offerte, proviamo metodo tradizionale
        console.warn('API V2: nessuna offerta trovata, provo metodo tradizionale');
      } catch (e) {
        console.warn('API V2 non disponibile, uso metodo tradizionale:', e.message);
      }
    }

    // ── Metodo tradizionale (client-side compute) ────────────────
    try {
      const data = await api.fetchTariffe(isLuceLocal ? 'LUCE' : 'GAS');
      if (!data?.offers?.length) {
        setError('Nessuna offerta disponibile per ' + (isLuceLocal ? 'Luce' : 'Gas') + '. Riprova più tardi.');
        setLoading(false); return;
      }
      const rawTariffs = data.offers.map(t => ({
        id: t.id, brand: t.supplier_name, supplier_name: t.supplier_name, offerta: t.name,
        tariffa: t.type === 'FISSO' ? 'Fissa' : 'Variabile',
        'prezzo tot kwh': t.price_mono_kwh, 'prezzo tot smc': t.price_smc,
        costo_fisso: t.fixed_fee_annual || (t.fixed_fee_monthly * 12), fixed_fee_monthly: t.fixed_fee_monthly,
        spread: t.spread, Pun: t.pun, psv: t.psv, logo: t.logo || null,
        prezzo_bloccato: t.extra?.prezzo_bloccato_mesi, pagamento: t.extra?.modalita_pagamento,
        vantaggi: t.extra?.vantaggi, note_costi: t.extra?.note, validita_offerta: t.extra?.validita_offerta,
        costo_profili: t.extra?.costo_profili, penale_recesso: t.extra?.penale_recesso, extra: t.extra,
      }));
      const deduped = deduplicateTariffs(rawTariffs);
      const hasRealSpend = spesaLocal && !isNaN(parseFloat(spesaLocal));
      const spendBase = hasRealSpend ? parseFloat(spesaLocal) : estimateSpend(isLuceLocal ? 'luce' : 'gas', consumoLocal);
      const computed = deduped.map(t => {
        const punRef = punEurKwh || MERCATO.PUN_REF;
        const psvRef = psvEurSmc || MERCATO.PSV_REF;
        const cost = isLuceLocal ? calcLuceCost(t, consumoLocal, punRef) : calcGasCost(t, consumoLocal, psvRef);
        if (!cost || cost <= 0) return null;
        const savings = hasRealSpend ? (spendBase - cost) : null;
        const savingsPct = hasRealSpend && spendBase > 0 ? ((spendBase - cost) / spendBase) * 100 : null;
        const bd = buildBreakdown(t, isLuceLocal ? 'luce' : 'gas', consumoLocal, spendBase, cost, punRef, psvRef);
        return { tariff: t, annualCost: cost, savings, savingsPct, breakdown: bd, hasRealSpend };
      }).filter(Boolean).sort((a, b) => a.annualCost - b.annualCost);
      setResults({ items: computed, currentSpend: spendBase, hasRealSpend });
    } catch (e2) {
      console.error('Metodo tradizionale fallito:', e2);
      setError('Impossibile caricare le offerte. Verifica la connessione e riprova.');
    } finally { setLoading(false); }
  }, [commodity, consumption, currentSpend, isLuce]);

  const handleLlmPaste = () => {
    try {
      const d = JSON.parse(llmResponse.trim());
      const isLuceLocal = (d.commodity || '').toUpperCase() !== 'GAS';
      setCommodity(isLuceLocal ? 'luce' : 'gas');
      if (d.consumo_annuo) setConsumption(Number(d.consumo_annuo));
      if (d.spesa_annua) setCurrentSpend(String(d.spesa_annua));
      setLlmExtractedData(d);
      setLlmResponse('');
      setResults(null);
      // CHIAMATA DIRETTA con dati freschi — no stale closure, no useEffect
      performAnalysis(d);
    } catch {
      setError('JSON non valido. Copia esattamente la risposta del LLM.');
    }
  };

  // Fetch market indices per calcolo prezzo corrente
  useEffect(() => {
    fetch('/api/market-indices')
      .then(r => r.json())
      .then(d => setMarketData(d))
      .catch(() => {});
  }, []);

  // Calcola prezzo corrente e quota fissa per confronto per-riga
  const punEurKwh = marketData?.pun_eur_kwh || (marketData?.pun_value ? marketData.pun_value / 1000 : 0);
  const psvEurSmc = marketData?.psv_eur_smc || (marketData?.psv_value ? marketData.psv_value / 1000 : 0);
  const currentPricePerUnit = getCurrentPricePerUnit(llmExtractedData, punEurKwh, psvEurSmc, commodity, consumption);
  const currentFixedMonthly = getCurrentFixedMonthly(llmExtractedData, commodity);

  const rankedItems = results ? getRankingBadges(results.items, commodity) : [];
  const displayed = results ? (showAll ? rankedItems : rankedItems.slice(0, 5)) : [];
  const maxSavings = results?.hasRealSpend && results?.items[0]?.savings > 0 ? results.items[0].savings : 0;

  return (
    <main>
      {/* ── Hero ─────────────────────────────────────────────────── */}
      <section style={{ padding: '60px 24px 50px', textAlign: 'center', position: 'relative' }}>
        <div style={{ position: 'absolute', top: '10%', left: '50%', translate: '-50%', width: 600, height: 400, background: `radial-gradient(ellipse, ${accentColor}15, transparent 70%)`, pointerEvents: 'none' }} />
        <div className="container" style={{ position: 'relative' }}>
          <div className="badge badge-tag animate-fade-in" style={{ marginBottom: 24, fontSize: 12, padding: '6px 16px' }}>
            🤖 Progettato per Claude, ChatGPT e Gemini
          </div>
          <h1 className="animate-fade-in-up" style={{ fontSize: 'clamp(36px, 6vw, 56px)', fontWeight: 900, lineHeight: 1.08, letterSpacing: '-2px', marginBottom: 20 }}>
            <span className="text-gradient">Chiedi alla tua AI</span><br/>
            <span style={{ color: '#f1f5f9' }}>di analizzare la bolletta</span><br/>
            <span style={{ color: '#f59e0b' }}>con SwitchAI</span>
          </h1>
          <p className="animate-fade-in-up" style={{ fontSize: 18, color: '#94a3b8', maxWidth: 560, margin: '0 auto 40px', lineHeight: 1.6 }}>
            <b style={{ color: '#f1f5f9' }}>SwitchAI</b> è un motore di confronto tariffe che si usa <b style={{ color: '#f59e0b' }}>parlando con la tua AI</b>. Dai la bolletta a Claude, ChatGPT o Gemini: l'AI la legge, chiama SwitchAI, e ti dice quanto risparmi.
          </p>
          <div className="animate-fade-in-up" style={{ display: 'flex', justifyContent: 'center', gap: 16, flexWrap: 'wrap', marginBottom: 40 }}>
            <a href="#come-usare" className="btn btn-electric" style={{ fontSize: 15, padding: '14px 32px', textDecoration: 'none' }}>
              🤖 Analizza con la tua AI
            </a>
            <a href="#come-usare" className="btn btn-outline" style={{ fontSize: 15, padding: '14px 32px', textDecoration: 'none' }}>
              📋 Prova senza connettere l'AI
            </a>
          </div>
          <div className="animate-fade-in-up" style={{ display: 'flex', justifyContent: 'center', gap: 48, flexWrap: 'wrap' }}>
            {STATS.map(s => <div key={s.label} style={{ textAlign: 'center' }}><div style={{ fontSize: 28, fontWeight: 800, color: '#f1f5f9' }}>{s.value}</div><div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>{s.label}</div></div>)}
          </div>
        </div>
      </section>

      {/* ── Market Signal + Come usare ────────────────────────────── */}
      <section id="come-usare" style={{ background: 'var(--bg-surface)', padding: '40px 24px 60px', borderTop: '1px solid var(--border)', borderBottom: '1px solid var(--border)' }}>
        <div className="container" style={{ maxWidth: 720 }}>
          <MarketSignal />

          <h2 style={{ textAlign: 'center', fontSize: 22, fontWeight: 800, marginBottom: 8, color: '#f1f5f9' }}>Come analizzare la tua bolletta</h2>
          <p style={{ textAlign: 'center', color: '#94a3b8', marginBottom: 28, fontSize: 14 }}>
            Tre modi per usare SwitchAI — scegli quello che preferisci
          </p>

          {/* 3 mode cards */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginBottom: 28 }}>
            {/* Mode 1: Claude MCP */}
            <div className="glass-card animate-fade-in" style={{ padding: '16px 20px', borderColor: 'rgba(16,185,129,0.2)', background: 'rgba(16,185,129,0.03)' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <div style={{ fontSize: 28, flexShrink: 0, marginTop: 2 }}>🔌</div>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                    <span style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>Claude (consigliato)</span>
                    <span style={{ fontSize: 9, padding: '1px 6px', borderRadius: 4, background: 'rgba(16,185,129,0.12)', color: '#6ee7b7', fontWeight: 600 }}>🆓 GRATUITO</span>
                  </div>
                  <p style={{ fontSize: 12, color: '#94a3b8', lineHeight: 1.6, marginBottom: 4 }}>
                    Funziona anche con il piano <b>Free</b>. Apri Claude → Impostazioni → Connettori → Aggiungi connettore personalizzato
                  </p>
                  <code style={{ background: 'rgba(0,0,0,0.3)', padding: '3px 8px', borderRadius: 4, fontSize: 11, color: '#60a5fa', wordBreak: 'break-all' }}>
                    https://www.switchai.it/mcp
                  </code>
                </div>
              </div>
            </div>

            {/* Mode 2: ChatGPT */}
            <div className="glass-card animate-fade-in" style={{ padding: '16px 20px', borderColor: 'rgba(148,163,184,0.12)' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <div style={{ fontSize: 28, flexShrink: 0, marginTop: 2 }}>🧩</div>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                    <span style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>ChatGPT</span>
                    <span style={{ fontSize: 9, padding: '1px 6px', borderRadius: 4, background: 'rgba(245,158,11,0.1)', color: '#fbbf24', fontWeight: 600 }}>💳 RICHIEDE PLUS</span>
                  </div>
                  <p style={{ fontSize: 12, color: '#94a3b8', lineHeight: 1.6, marginBottom: 4 }}>
                    Serve un piano <b>Plus o Pro</b> per creare GPTs personalizzati. Esplora GPT → Crea → Azioni → Importa da URL
                  </p>
                  <code style={{ background: 'rgba(0,0,0,0.3)', padding: '3px 8px', borderRadius: 4, fontSize: 11, color: '#60a5fa', wordBreak: 'break-all' }}>
                    https://www.switchai.it/openapi.json
                  </code>
                  <p style={{ fontSize: 10, color: '#64748b', marginTop: 4 }}>Per Gemini, DeepSeek o ChatGPT Free → usa <b>Copia e incolla</b> qui sotto</p>
                </div>
              </div>
            </div>

            {/* Mode 3: Copia-incolla */}
            <div className="glass-card animate-fade-in" style={{ padding: '16px 20px', borderColor: 'rgba(148,163,184,0.12)' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <div style={{ fontSize: 28, flexShrink: 0, marginTop: 2 }}>📋</div>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                    <span style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>Copia e incolla</span>
                    <span style={{ fontSize: 9, padding: '1px 6px', borderRadius: 4, background: 'rgba(148,163,184,0.08)', color: '#94a3b8', fontWeight: 600 }}>✅ TUTTI I LLM</span>
                  </div>
                  <p style={{ fontSize: 12, color: '#94a3b8', lineHeight: 1.6, marginBottom: 6 }}>
                    <b>1.</b> Clicca sul box per copiare automaticamente il prompt, poi apri il tuo assistente IA preferito (ChatGPT, Claude, Gemini o DeepSeek). Incolla il testo, allega la tua bolletta (luce, gas o dual) e premi invio. Una volta ricevuta la risposta, passa allo step 2!
                  </p>
                  <pre
                    onClick={() => {
                      navigator.clipboard.writeText(LLM_PROMPT);
                      setCopyFeedback('✅ Prompt copiato!');
                      setTimeout(() => setCopyFeedback(''), 2000);
                    }}
                    style={{
                      background: 'rgba(0,0,0,0.3)', border: '1px solid var(--border)', borderRadius: 8,
                      padding: '10px', fontSize: 10, color: '#60a5fa', cursor: 'pointer',
                      whiteSpace: 'pre-wrap', lineHeight: 1.5, maxHeight: 100, overflowY: 'auto',
                      marginBottom: 4,
                    }}
                    title="Clicca per copiare"
                  >{LLM_PROMPT}</pre>
                  <p style={{ fontSize: 10, color: copyFeedback ? '#22c55e' : '#64748b', marginBottom: 12, transition: 'color 0.2s' }}>
                    {copyFeedback || '👆 Clicca sul box per copiare il prompt'}
                  </p>
                  <p style={{ fontSize: 12, color: '#94a3b8', lineHeight: 1.6, marginBottom: 6 }}>
                    <b>2.</b> Incolla qui la risposta JSON e noi confrontiamo le offerte.
                  </p>
                  <textarea
                    className="input-field"
                    value={llmResponse}
                    onChange={e => setLlmResponse(e.target.value)}
                    placeholder='{"commodity":"LUCE","consumo_annuo":2700,"spesa_annua":650,"zona":"NORD"}'
                    rows={2}
                    style={{ fontSize: 12, resize: 'vertical', fontFamily: 'monospace', width: '100%' }}
                  />
                  <button
                    className="btn btn-electric"
                    onClick={handleLlmPaste}
                    disabled={!llmResponse.trim()}
                    style={{ width: '100%', marginTop: 6, fontSize: 13 }}
                  >
                    ✅ Analizza questi dati
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Demo button */}
          <div style={{ textAlign: 'center', marginBottom: 28 }}>
            <button
              onClick={() => setDemoOpen(true)}
              className="btn btn-outline"
              style={{ fontSize: 14, padding: '12px 28px' }}
            >
              🖥️ Guarda un esempio
            </button>
          </div>

          {/* LLM extracted data summary */}
          {llmExtractedData && (
            <div className="glass-card best-offer animate-scale-in" style={{ padding: '16px 18px', marginBottom: 24, borderColor: 'rgba(16,185,129,0.3)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <span style={{ fontSize: 14, fontWeight: 700, color: '#10b981' }}>✅ Dati estratti dal LLM</span>
                <button onClick={() => setLlmExtractedData(null)} style={{ background: 'none', border: 'none', color: '#64748b', cursor: 'pointer', fontSize: 16 }}>✕</button>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 13 }}>
                {[
                  ['Commodity', llmExtractedData.commodity],
                  ['Tipo cliente', llmExtractedData.tipo_cliente],
                  ['Consumo annuo', `${llmExtractedData.consumo_annuo} ${llmExtractedData.commodity === 'GAS' ? 'Smc' : 'kWh'}`],
                  ['Spesa annua', `${llmExtractedData.spesa_annua} €`],
                  ['Zona', llmExtractedData.zona],
                  ['Spesa materia energia', llmExtractedData.spesa_materia_energia ? `${llmExtractedData.spesa_materia_energia} €` : null],
                  ['Quota fissa mensile', llmExtractedData.quota_fissa_mensile ? `${llmExtractedData.quota_fissa_mensile} €/mese` : null],
                  ['Canone RAI', llmExtractedData.canone_rai > 0 ? `${llmExtractedData.canone_rai} €/anno` : null],
                  ['Tipo tariffa', llmExtractedData.tipo_tariffa],
                  ['Spread', llmExtractedData.spread != null ? `${llmExtractedData.spread} €` : null],
                  ['Potenza impegnata', llmExtractedData.potenza_impegnata ? `${llmExtractedData.potenza_impegnata} kW` : null],
                  ['F1/F2/F3', (llmExtractedData.consumo_f1 || llmExtractedData.consumo_f2) ? `${llmExtractedData.consumo_f1||'-'}/${llmExtractedData.consumo_f2||'-'}/${llmExtractedData.consumo_f3||'-'} kWh` : null],
                  ['Scadenza offerta', llmExtractedData.scadenza_offerta],
                  ['Periodo riferimento', llmExtractedData.periodo_riferimento],
                ].filter(([,v]) => v != null).map(([label, value]) => (
                  <div key={label} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', borderRadius: 6, background: 'rgba(255,255,255,0.02)' }}>
                    <span style={{ color: '#94a3b8', fontWeight: 500 }}>{label}</span>
                    <span style={{ color: '#f1f5f9', fontWeight: 600 }}>{value}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Error */}
          {error && <div style={{ marginBottom: 16, padding: '14px 18px', borderRadius: 10, background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.25)', color: '#fca5a5', fontSize: 14 }}>❌ {error}</div>}
        </div>
      </section>

      {/* ── Results ─────────────────────────────────────────────── */}
      {results && (
        <section ref={resultsRef} style={{ padding: '0 0 80px', scrollMarginTop: 20 }}>{/* Sticky reference bar */}
          <StickyReferenceBar
            commodity={commodity}
            currentPricePerUnit={currentPricePerUnit}
            currentFixedMonthly={currentFixedMonthly}
            currentAnnualSpend={results.currentSpend}
            llmData={llmExtractedData}
            punDisplay={marketData?.pun_display}
            psvDisplay={marketData?.psv_display}
          />

          <div className="container" style={{ maxWidth: 880, paddingTop: 30 }}>
            <div className="animate-fade-in" style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 28 }}>
              <div>
                <h2 style={{ fontSize: 24, fontWeight: 800, marginBottom: 4 }}>{results.items.length} offerte trovate</h2>
                <p style={{ color: '#94a3b8', fontSize: 14 }}>
                  {results.hasRealSpend ? <>Spesa attuale: <b style={{ color: '#f1f5f9' }}>{formatEuro(results.currentSpend)}</b></> : <>Spesa stimata: <b style={{ color: '#64748b' }}>{formatEuro(results.currentSpend)}</b> (indicativa)</>}
                </p>
              </div>
              {maxSavings > 0 && (
                <div className="animate-scale-in" style={{ background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.25)', borderRadius: 14, padding: '14px 22px', textAlign: 'center' }}>
                  <div style={{ fontSize: 11, color: '#6ee7b7', fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.5 }}>Risparmio massimo</div>
                  <div style={{ fontSize: 28, fontWeight: 900, color: '#10b981' }}>{formatEuro(maxSavings)}<span style={{ fontSize: 14, fontWeight: 500 }}>/anno</span></div>
                </div>
              )}
            </div>

            {/* Legenda "cosa cambia / cosa resta uguale" */}
            {results.hasRealSpend && (
              <div style={{
                display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap',
                marginBottom: 20, padding: '10px 16px', borderRadius: 10,
                background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                fontSize: 11, color: '#64748b',
              }}>
                <span style={{ fontWeight: 600, color: '#94a3b8' }}>🔄 Cosa cambia:</span>
                <span style={{ background: 'rgba(16,185,129,0.08)', padding: '2px 10px', borderRadius: 4, color: '#6ee7b7' }}>
                  Prezzo {commodity === 'luce' ? 'kWh' : 'Smc'} + Quota fissa
                </span>
                <span style={{ fontWeight: 600, color: '#94a3b8' }}>🔒 Cosa resta uguale:</span>
                <span style={{ background: 'rgba(148,163,184,0.06)', padding: '2px 10px', borderRadius: 4 }}>
                  Trasporto · Oneri · Imposte · IVA{commodity === 'luce' ? ' · Canone RAI' : ''}
                </span>
              </div>
            )}

            <div className="stagger" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              {displayed.map((item, i) => (
                <TariffCard
                  key={`${item.tariff.brand}|${item.tariff.offerta}`}
                  rank={i}
                  tariff={item.tariff}
                  commodity={commodity}
                  annualCost={Math.round(item.annualCost)}
                  savings={item.savings !== null ? Math.round(item.savings) : null}
                  savingsPct={item.savingsPct}
                  breakdown={item.breakdown}
                  currentSpend={results.currentSpend}
                  hasRealSpend={results.hasRealSpend}
                  rankBadge={item.rankBadge}
                  rankLabel={item.rankLabel}
                  currentPricePerUnit={currentPricePerUnit}
                  currentFixedMonthly={currentFixedMonthly}
                  llmData={llmExtractedData}
                  isAnomalous={isPriceAnomalous(isLuce ? (item.tariff['prezzo tot kwh']) : (item.tariff['prezzo tot smc']), commodity)}
                  priceWarning={item.priceWarning}
                />
              ))}
            </div>
            {results.items.length > 5 && (
              <button className="btn btn-outline" onClick={() => setShowAll(!showAll)} style={{ width: '100%', marginTop: 16 }}>{showAll ? 'Mostra solo le prime 5 ↑' : `Mostra tutte le ${results.items.length} ↓`}</button>
            )}
          </div>
        </section>
      )}

      {/* ── How it works ────────────────────────────────────────── */}
      {!results && (
        <section style={{ padding: '80px 24px', borderTop: '1px solid var(--border)' }}>
          <div className="container" style={{ maxWidth: 880 }}>
            <h2 style={{ textAlign: 'center', fontSize: 28, fontWeight: 800, marginBottom: 8 }}>Come funziona (con l'AI)</h2>
            <p style={{ textAlign: 'center', color: '#94a3b8', marginBottom: 48, fontSize: 16 }}>Il modo migliore: chiedi al tuo assistente AI di analizzare la bolletta con SwitchAI.</p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: 24 }}>
              {[
                { icon: '💬', title: '1. Chiedi al tuo AI', desc: '"Ehi Claude, ho questa bolletta Enel da 650€/anno. Trovami l\'offerta migliore con SwitchAI."' },
                { icon: '⚡', title: '2. L\'AI chiama SwitchAI', desc: 'L\'AI invia i tuoi consumi alla nostra API. SwitchAI confronta 44+ offerte in tempo reale e restituisce la migliore con tutti i dettagli.' },
                { icon: '✅', title: '3. Vedi il risparmio e decidi', desc: 'L\'AI ti dice quanto risparmi e perché. Se vuoi attivare, ti guida alla sottoscrizione. Sei tu a decidere.' },
              ].map(step => (
                <div key={step.title} className="glass-card" style={{ padding: '28px 24px', textAlign: 'center' }}>
                  <div style={{ fontSize: 40, marginBottom: 16 }}>{step.icon}</div>
                  <h3 style={{ fontSize: 16, fontWeight: 700, marginBottom: 8, color: '#f1f5f9' }}>{step.title}</h3>
                  <p style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.7 }}>{step.desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}
      {/* ── Demo Modal ──────────────────────────────────────────── */}
      <ChatDemo isOpen={demoOpen} onClose={() => setDemoOpen(false)} />
    </main>
  );
}
