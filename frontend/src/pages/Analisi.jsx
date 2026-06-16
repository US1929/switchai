import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const TOOLS = [
  {
    icon: '🔍', name: 'calculate_energy_savings',
    desc: 'Confronta le tariffe Luce o Gas. Passa consumi, zona e spesa attuale. Ottieni le 3 migliori offerte con risparmio in € e %.',
    method: 'POST', endpoint: '/api/webmcp-endpoint',
    example: `{
  "commodity": "LUCE",
  "yearly_consumption_kwh": 2700,
  "zone": "NORD",
  "current_annual_spend": 650
}`,
  },
  {
    icon: '📄', name: 'parse_energy_bill',
    desc: 'Estrae dati strutturati dal testo di una bolletta: fornitore, POD/PDR, consumi, costi, zona.',
    method: 'POST', endpoint: '/api/parse-bill-text',
    example: `{
  "bill_text": "ENEL ENERGIA\\nPOD: IT001E123456789\\nConsumo annuo: 3.200 kWh\\nTotale bolletta: 145,50 €"
}`,
  },
  {
    icon: '✅', name: 'activate_tariff / submit_subscription',
    desc: 'Invia la richiesta di attivazione. Passa i dati del form. La sottoscrizione viene inoltrata al fornitore.',
    method: 'POST', endpoint: '/api/subscription/submit',
    example: `{
  "tariff_id": "xxx-offerta",
  "nome": "Mario", "cognome": "Rossi",
  "codice_fiscale": "RSS...",
  "email": "mario@email.com",
  "cellulare": "+393401234567",
  "codice_pod": "IT001E123456789"
}`,
  },
];

const DEMO_BILLS = [
  {
    label: 'Bolletta Luce ENEL (2700 kWh/anno)',
    text: 'ENEL ENERGIA\nPOD: IT001E123456789\nPeriodo: Gen-Feb 2026\nConsumo annuo: 2.700 kWh\nTotale bolletta: € 145,50\nQuota energia: 0,165 €/kWh\nQuota fissa: 10,00 €/mese',
    commodity: 'luce', consumption: 2700,
  },
  {
    label: 'Bolletta Gas A2A (1200 Smc/anno)',
    text: 'A2A Energia\nPDR: 12345678901234\nGas Naturale - Zona: Lombardia\nConsumo annuo: 1.200 Smc\nTotale bolletta: 680,00 €\nQuota gas: 0,52 €/Smc\nQuota fissa: 12,00 €/mese',
    commodity: 'gas', consumption: 1200,
  },
];

export default function Analisi() {
  const navigate = useNavigate();
  const [expandedTool, setExpandedTool] = useState(null);
  const [demoResult, setDemoResult] = useState(null);
  const [demoLoading, setDemoLoading] = useState(false);

  const runDemo = async (bill) => {
    setDemoLoading(true); setDemoResult(null);
    try {
      const res = await fetch('/api/parse-bill-text', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: bill.text }),
      });
      const data = await res.json();
      setDemoResult({ ...data, _bill: bill });
    } catch (e) {
      setDemoResult({ error: e.message });
    } finally {
      setDemoLoading(false);
    }
  };

  return (
    <main style={{ padding: '50px 24px 80px' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>

        {/* ── Header ──────────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center', marginBottom: 48 }}>
          <h1 className="animate-fade-in-up" style={{
            fontSize: 'clamp(32px, 5vw, 46px)', fontWeight: 900,
            letterSpacing: '-1.5px', marginBottom: 16,
          }}>
            <span className="text-gradient">AI Ready</span>
          </h1>
          <p style={{ fontSize: 17, color: '#94a3b8', lineHeight: 1.7, maxWidth: 560, margin: '0 auto' }}>
            Questo sito è progettato per funzionare con <b style={{ color: '#f1f5f9' }}>Claude</b>,{' '}
            <b style={{ color: '#f1f5f9' }}>ChatGPT</b> e qualsiasi LLM compatibile WebMCP.
            L'AI analizza la bolletta, confronta le offerte e attiva la migliore.
          </p>
        </div>

        {/* ── Come funziona (flusso LLM) ──────────────────────────────── */}
        <div style={{ marginBottom: 48 }}>
          <h2 style={{ fontSize: 20, fontWeight: 800, marginBottom: 24, textAlign: 'center' }}>
            🤖 Come funziona con un LLM
          </h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {[
              { step: '1', icon: '📄', title: 'Dai la bolletta all\'AI', desc: 'Carica un PDF o incolla il testo della bolletta in Claude o ChatGPT. L\'LLM estrae automaticamente fornitore, consumi, costi, POD/PDR.' },
              { step: '2', icon: '🔍', title: 'L\'AI confronta le offerte', desc: 'L\'LLM chiama il tool calculate_energy_savings via WebMCP. In pochi secondi ottiene le 3 migliori offerte con il risparmio in €.' },
              { step: '3', icon: '💬', title: 'L\'AI ti spiega il risparmio', desc: 'Ricevi un riepilogo in linguaggio naturale: "Risparmi 410€/anno con Fastweb. Vuoi attivare?"' },
              { step: '4', icon: '✅', title: 'Attivi con un click', desc: 'Confermi e l\'LLM invia la sottoscrizione via submit_subscription. I tuoi dati (che già conosce dalla bolletta) compilano il form automaticamente.' },
            ].map((s, i) => (
              <div key={s.step} className="glass-card animate-fade-in-up" style={{ padding: '16px 20px', display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                <div style={{
                  width: 38, height: 38, borderRadius: 10, flexShrink: 0,
                  background: 'rgba(245,158,11,0.12)', color: '#f59e0b',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontWeight: 800, fontSize: 16,
                }}>
                  {s.step}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 4, color: '#f1f5f9' }}>
                    {s.icon} {s.title}
                  </div>
                  <div style={{ fontSize: 13, color: '#94a3b8', lineHeight: 1.6 }}>{s.desc}</div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* ── Demo: testa il parser con dati reali ────────────────────── */}
        <div className="glass-card" style={{ padding: '24px', marginBottom: 48 }}>
          <h2 style={{ fontSize: 20, fontWeight: 800, marginBottom: 8, textAlign: 'center' }}>
            🧪 Prova il parser bollette
          </h2>
          <p style={{ textAlign: 'center', fontSize: 13, color: '#94a3b8', marginBottom: 24 }}>
            L'LLM usa lo stesso endpoint per estrarre i dati. Qui vedi cosa restituisce.
          </p>

          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 20, justifyContent: 'center' }}>
            {DEMO_BILLS.map((bill, i) => (
              <button
                key={i}
                className="btn btn-outline"
                onClick={() => runDemo(bill)}
                disabled={demoLoading}
                style={{ fontSize: 13 }}
              >
                {bill.label}
              </button>
            ))}
          </div>

          {demoLoading && (
            <div style={{ textAlign: 'center', padding: 20, color: '#94a3b8' }}>
              ⏳ Analisi in corso...
            </div>
          )}

          {demoResult && !demoResult.error && (
            <div className="animate-scale-in" style={{
              display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: 12,
            }}>
              {[
                { label: 'Fornitore', value: demoResult.current_supplier || 'N/D' },
                { label: 'Commodity', value: demoResult.commodity === 'LUCE' ? '⚡ Luce' : '🔥 Gas' },
                { label: 'Consumo annuo', value: demoResult.commodity === 'LUCE'
                  ? `${demoResult.yearly_consumption_kwh} kWh`
                  : `${demoResult.yearly_consumption_smc} Smc` },
                { label: 'Spesa annua', value: `${demoResult.current_annual_spend} €` },
                { label: 'POD/PDR', value: demoResult.pod_pdr || 'N/D' },
                { label: 'Zona', value: demoResult.zone || 'N/D' },
              ].map(f => (
                <div key={f.label} style={{
                  background: 'rgba(255,255,255,0.03)', borderRadius: 10, padding: '14px',
                  border: '1px solid var(--border)', textAlign: 'center',
                }}>
                  <div style={{ fontSize: 10, color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 4 }}>
                    {f.label}
                  </div>
                  <div style={{ fontSize: 16, fontWeight: 700, color: '#f1f5f9' }}>{f.value}</div>
                </div>
              ))}
              <div style={{ gridColumn: '1 / -1', textAlign: 'center', marginTop: 8 }}>
                <button
                  className="btn btn-electric"
                  onClick={() => navigate(`/?demo=${demoResult._bill?.commodity || 'luce'}&consumption=${demoResult._bill?.consumption || 2700}`)}
                  style={{ fontSize: 13 }}
                >
                  🔍 Vai al confronto offerte con questi dati →
                </button>
              </div>
            </div>
          )}

          {demoResult?.error && (
            <div style={{ textAlign: 'center', color: '#f87171', padding: 12 }}>
              ❌ {demoResult.error}
            </div>
          )}
        </div>

        {/* ── Tools WebMCP ────────────────────────────────────────────── */}
        <div style={{ marginBottom: 48 }}>
          <h2 style={{ fontSize: 20, fontWeight: 800, marginBottom: 8, textAlign: 'center' }}>
            🛠️ API Tools disponibili
          </h2>
          <p style={{ textAlign: 'center', fontSize: 13, color: '#94a3b8', marginBottom: 24 }}>
            Questi sono gli endpoint che l'LLM chiama via WebMCP. Tutti pubblici, documentati in <code style={{ background: 'rgba(255,255,255,0.05)', padding: '2px 6px', borderRadius: 4 }}>webmcp.json</code>.
          </p>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {TOOLS.map((tool, i) => (
              <div key={tool.name} className="glass-card" style={{ padding: '18px 20px' }}>
                <div
                  onClick={() => setExpandedTool(expandedTool === i ? null : i)}
                  style={{ display: 'flex', alignItems: 'center', gap: 14, cursor: 'pointer' }}
                >
                  <span style={{ fontSize: 24 }}>{tool.icon}</span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 14, color: '#f1f5f9' }}>
                      <span style={{
                        fontSize: 10, fontWeight: 600, padding: '2px 8px', borderRadius: 4,
                        background: tool.method === 'POST' ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.15)',
                        color: tool.method === 'POST' ? '#60a5fa' : '#34d399',
                        marginRight: 8,
                      }}>
                        {tool.method}
                      </span>
                      {tool.name}
                    </div>
                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 3 }}>{tool.desc}</div>
                  </div>
                  <span style={{ color: '#64748b', fontSize: 18, transition: 'transform 0.2s', transform: expandedTool === i ? 'rotate(180deg)' : 'none' }}>
                    ▼
                  </span>
                </div>
                {expandedTool === i && (
                  <div className="animate-fade-in" style={{ marginTop: 14, marginLeft: 38 }}>
                    <div style={{ fontSize: 11, color: '#64748b', marginBottom: 6 }}>
                      POST <code style={{ color: '#94a3b8' }}>{tool.endpoint}</code>
                    </div>
                    <pre style={{
                      background: 'rgba(0,0,0,0.3)', borderRadius: 10, padding: '14px 16px',
                      fontSize: 12, color: '#94a3b8', overflowX: 'auto',
                      fontFamily: "'JetBrains Mono', monospace", lineHeight: 1.7,
                      border: '1px solid var(--border)',
                    }}>
                      {tool.example}
                    </pre>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* ── CTA finale ──────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center' }}>
          <div className="glass-card" style={{
            padding: '32px', textAlign: 'center',
            background: 'linear-gradient(135deg, rgba(245,158,11,0.06), rgba(59,130,246,0.06))',
            borderColor: 'rgba(245,158,11,0.2)',
          }}>
            <div style={{ fontSize: 32, marginBottom: 12 }}>🤖</div>
            <h3 style={{ fontSize: 18, fontWeight: 700, marginBottom: 8 }}>
              Pronto per l'AI
            </h3>
            <p style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.7, maxWidth: 500, margin: '0 auto 20px' }}>
              Apri questo sito in Claude o ChatGPT e chiedi: <br />
              <i style={{ color: '#f1f5f9' }}>"Analizza la mia bolletta e trovami l'offerta migliore"</i>
            </p>
            <button className="btn btn-electric" onClick={() => navigate('/')}>
              ⚡ Vai al confronto offerte
            </button>
          </div>
        </div>

      </div>
    </main>
  );
}
