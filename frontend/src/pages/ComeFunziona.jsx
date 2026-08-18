import React from 'react';
import { Helmet } from 'react-helmet-async';

export default function ComeFunziona() {
  return (
    <>
      <Helmet>
        <title>Come Funziona SwitchAI — Cambio Fornitore Luce e Gas con AI</title>
        <meta name="description" content="Scopri come SwitchAI confronta le offerte luce e gas, analizza la bolletta con l'AI e ti aiuta a cambiare fornitore in pochi secondi. Tre modalità: AI agent, copia-incolla o calcolo rapido." />
      </Helmet>
      <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>

        <div style={{ textAlign: 'center', marginBottom: 56 }}>
          <h1 className="animate-fade-in-up" style={{
            fontSize: 'clamp(32px, 5vw, 46px)', fontWeight: 900,
            letterSpacing: '-1.5px', marginBottom: 16,
          }}>
            <span className="text-gradient">Come funziona</span>
            <br />
            <span style={{ color: '#f1f5f9' }}>SwitchAI</span>
          </h1>
          <p style={{ fontSize: 17, color: '#94a3b8', maxWidth: 560, margin: '0 auto', lineHeight: 1.7 }}>
            SwitchAI confronta <b style={{ color: '#f59e0b' }}>5.600+ offerte</b> Luce e Gas del mercato libero
            italiano in tempo reale. Usalo con la tua AI preferita o direttamente dal sito.
          </p>
        </div>

        {/* ── Modalità d'uso ──────────────────────────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🛠️ Tre modi per usarlo
          </h2>

          <div className="glass-card" style={{ padding: '28px', marginBottom: 20 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
              {[
                {
                  phase: 'MODO 1', icon: '🔌',
                  title: 'Con Claude o ChatGPT (consigliato)',
                  detail: 'Collega il tool MCP (Claude) o importa lo schema OpenAPI (ChatGPT Plus/Pro). L\'AI legge la bolletta, estrae consumi e spesa, chiama SwitchAI, e ti mostra le migliori offerte con spiegazione in italiano. Nessun dato personale esce dalla conversazione con la tua AI.',
                },
                {
                  phase: 'MODO 2', icon: '📋',
                  title: 'Copia e incolla',
                  detail: 'Dalla home di SwitchAI copia il prompt preimpostato, incollalo in qualsiasi AI (anche ChatGPT Free, Gemini, DeepSeek). Allega la bolletta PDF. Quando l\'AI risponde con il JSON, incollalo nel box sulla home e ottieni il confronto completo.',
                },
                {
                  phase: 'MODO 3', icon: '📊',
                  title: 'Calcolo rapido senza AI',
                  detail: 'Inserisci manualmente tipo fornitura, consumo annuo e spesa. SwitchAI confronta tutte le offerte compatibili e mostra risparmio, costo annuo e dettagli. Nessuna AI richiesta.',
                },
              ].map((s, i) => (
                <div key={s.phase} style={{ display: 'flex', gap: 18, alignItems: 'flex-start' }}>
                  <div style={{
                    minWidth: 52, height: 52, borderRadius: 14,
                    background: 'rgba(245,158,11,0.12)', color: '#f59e0b',
                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                    fontSize: 22, flexShrink: 0,
                  }}>
                    {s.icon}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                      <span className="badge badge-best" style={{ fontSize: 9 }}>{s.phase}</span>
                      <span style={{ fontWeight: 700, fontSize: 16, color: '#f1f5f9' }}>{s.title}</span>
                    </div>
                    <p style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.7 }}>{s.detail}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ── Tiers ────────────────────────────────────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🎯 Accesso ai dati
          </h2>

          <div className="glass-card" style={{ padding: '4px', overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  <th style={{ padding: '14px 18px', textAlign: 'left', color: '#64748b', fontWeight: 600, fontSize: 12, textTransform: 'uppercase', width: '35%' }}></th>
                  <th style={{ padding: '14px 18px', textAlign: 'center', color: '#94a3b8', fontWeight: 600, fontSize: 12, textTransform: 'uppercase' }}>Anonimo</th>
                  <th style={{ padding: '14px 18px', textAlign: 'center', color: '#34d399', fontWeight: 600, fontSize: 12, textTransform: 'uppercase' }}>Free (registrato)</th>
                  <th style={{ padding: '14px 18px', textAlign: 'center', color: '#f59e0b', fontWeight: 600, fontSize: 12, textTransform: 'uppercase' }}>API Pro</th>
                </tr>
              </thead>
              <tbody>
                {[
                  ['Offerte disponibili', '~270', '5.600+', '5.600+'],
                  ['Chiamate al giorno', 'Illimitate', '10', '1.000'],
                  ['API key', '—', 'Sì', 'Sì'],
                  ['Richiede registrazione', 'No', 'Sì + email confermata', 'Sì + email confermata'],
                ].map((row, i) => (
                  <tr key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                    <td style={{ padding: '12px 18px', color: '#f1f5f9', fontWeight: 600 }}>{row[0]}</td>
                    <td style={{ padding: '12px 18px', textAlign: 'center', color: '#94a3b8' }}>{row[1]}</td>
                    <td style={{ padding: '12px 18px', textAlign: 'center', color: '#6ee7b7', fontWeight: 600 }}>{row[2]}</td>
                    <td style={{ padding: '12px 18px', textAlign: 'center', color: '#fbbf24', fontWeight: 600 }}>{row[3]}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* ── Subscription flow ────────────────────────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            ⚡ Come attivare un'offerta
          </h2>
          <div className="glass-card" style={{ padding: '24px 28px' }}>
            <p style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>
              Ogni offerta mostrata da SwitchAI include un <b style={{ color: '#f1f5f9' }}>link diretto</b>
              al sito del fornitore. Clicca sul link e completi l'attivazione autonomamente,
              senza passare da SwitchAI. Nessun dato personale viene raccolto o memorizzato in questa fase.
            </p>
            <div style={{ marginTop: 16, display: 'flex', gap: 12, flexWrap: 'wrap' }}>
              {[
                { step: '1', text: 'Scegli l\'offerta migliore per te' },
                { step: '2', text: 'Clicca sul link di attivazione' },
                { step: '3', text: 'Completi l\'attivazione sul sito del fornitore' },
              ].map(s => (
                <div key={s.step} style={{
                  flex: 1, minWidth: 140, padding: '14px 16px', borderRadius: 10, textAlign: 'center',
                  background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)',
                }}>
                  <div style={{
                    width: 28, height: 28, borderRadius: '50%', margin: '0 auto 8px',
                    background: 'rgba(245,158,11,0.15)', color: '#f59e0b',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 13, fontWeight: 800,
                  }}>{s.step}</div>
                  <div style={{ fontSize: 12, color: '#e2e8f0', lineHeight: 1.4 }}>{s.text}</div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ── Comparison: Traditional vs SwitchAI ──────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🆚 Comparatore tradizionale vs SwitchAI
          </h2>

          <div className="glass-card" style={{ padding: '4px', overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  <th style={{ padding: '14px 18px', textAlign: 'left', color: '#64748b', fontWeight: 600, fontSize: 12, textTransform: 'uppercase', width: '35%' }}></th>
                  <th style={{ padding: '14px 18px', textAlign: 'center', color: '#f87171', fontWeight: 600, fontSize: 12, textTransform: 'uppercase' }}>Comparatore</th>
                  <th style={{ padding: '14px 18px', textAlign: 'center', color: '#34d399', fontWeight: 600, fontSize: 12, textTransform: 'uppercase' }}>SwitchAI</th>
                </tr>
              </thead>
              <tbody>
                {[
                  ['Inserimento dati', 'Manuale: decine di campi', 'Automatico: l\'AI legge la bolletta'],
                  ['Offerte confrontate', 'Solo quelle del gestore', 'Tutte 5.600+ del mercato libero'],
                  ['Confronto', 'Lista di prezzi', 'Spiegazione in italiano del perché conviene'],
                  ['Attivazione', 'Form da compilare a mano', 'Link diretto al sito del fornitore'],
                  ['Tempo totale', '20-30 minuti', '30 secondi (analisi)'],
                ].map((row, i) => (
                  <tr key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                    <td style={{ padding: '12px 18px', color: '#f1f5f9', fontWeight: 600 }}>{row[0]}</td>
                    <td style={{ padding: '12px 18px', textAlign: 'center', color: '#94a3b8' }}>{row[1]}</td>
                    <td style={{ padding: '12px 18px', textAlign: 'center', color: '#6ee7b7', fontWeight: 600 }}>{row[2]}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* ── Privacy ─────────────────────────────────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🔒 Privacy e dati
          </h2>
          <div className="glass-card" style={{ padding: '24px 28px' }}>
            <p style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>
              SwitchAI riceve dalla tua AI solo dati numerici (consumo annuo, spesa, zona).
              I dati personali (nome, indirizzo, POD/PDR) restano nella conversazione con la tua AI
              e non vengono mai inviati a SwitchAI. L'attivazione avviene direttamente sul sito
              del fornitore scelto, tramite link fornito da SwitchAI.
              Puoi revocare il consenso in qualsiasi momento scrivendo a info@switchai.it.
            </p>
          </div>
        </div>

        {/* ── CTA ──────────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center', display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
          <a href="/per-llm" className="btn btn-electric btn-lg" style={{ textDecoration: 'none' }}>
            📖 Documentazione per LLM
          </a>
          <a href="/plus" className="btn btn-outline btn-lg" style={{ textDecoration: 'none' }}>
            ⚡ Piani e API
          </a>
          <a href="/" className="btn btn-outline btn-lg" style={{ textDecoration: 'none' }}>
            🏠 Home
          </a>
        </div>
      </div>
    </main>
    </>
  );
}
