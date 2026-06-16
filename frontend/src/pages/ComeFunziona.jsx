import React from 'react';

export default function ComeFunziona() {
  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>

        <div style={{ textAlign: 'center', marginBottom: 56 }}>
          <h1 className="animate-fade-in-up" style={{
            fontSize: 'clamp(32px, 5vw, 46px)', fontWeight: 900,
            letterSpacing: '-1.5px', marginBottom: 16,
          }}>
            <span className="text-gradient">Come funziona</span>
            <br />
            <span style={{ color: '#f1f5f9' }}>l'AI agent</span>
          </h1>
          <p style={{ fontSize: 17, color: '#94a3b8', maxWidth: 560, margin: '0 auto', lineHeight: 1.7 }}>
            SwitchAI non è un comparatore tradizionale. È un <b style={{ color: '#f59e0b' }}>decision engine</b>:
            l'utente dà la bolletta, l'AI fa tutto il resto.
          </p>
        </div>

        {/* ── The architecture ────────────────────────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🏗️ Architettura dell'agente
          </h2>

          <div className="glass-card" style={{ padding: '28px', marginBottom: 20 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
              {[
                {
                  phase: 'INPUT', icon: '📄',
                  title: 'L\'utente carica la bolletta',
                  detail: 'PDF o testo. L\'AI agent (Claude, Gemini, ChatGPT) legge il documento ed estrae: fornitore, POD/PDR, consumo annuo kWh o Smc, spesa annuale, zona tariffaria. Questa è la parte che gli LLM fanno meglio di qualsiasi OCR.',
                },
                {
                  phase: 'ANALYSIS', icon: '🤖',
                  title: 'L\'AI chiama SwitchAI via WebMCP',
                  detail: 'L\'agente invoca il tool calculate_energy_savings con i dati estratti. SwitchAI carica 44+ offerte in tempo reale dai database dei fornitori, calcola il costo annuo per ciascuna, e restituisce le 3 migliori con breakdown energetico e riepilogo in italiano.',
                },
                {
                  phase: 'DECISION', icon: '🧠',
                  title: 'L\'AI spiega il risparmio all\'utente',
                  detail: 'Non mostriamo solo numeri. L\'AI agent spiega in linguaggio naturale perché una certa offerta conviene: "Risparmi 410€ perché il prezzo fisso di Fastweb (0,018 €/kWh) è molto più basso del tuo attuale (0,165 €/kWh). La quota fissa è leggermente più alta ma il risparmio sull\'energia compensa ampiamente."',
                },
                {
                  phase: 'ACTION', icon: '⚡',
                  title: 'L\'AI attiva la nuova tariffa',
                  detail: 'L\'utente conferma. L\'agente chiama submit_subscription con tutti i dati già compilati (nome, indirizzo, POD, IBAN — presi dalla bolletta o chiesti una volta sola). La richiesta va al fornitore via web service. In 24 ore il contratto è attivo.',
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

        {/* ── Comparison: Traditional vs SwitchAI ──────────────────── */}
        <div style={{ marginBottom: 56 }}>
          <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 28, textAlign: 'center' }}>
            🆚 Comparatore tradizionale vs AI Agent
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
                  ['Confronto', 'Lista di prezzi', 'Spiegazione in italiano del perché conviene'],
                  ['Attivazione', 'Form da compilare a mano', 'L\'AI compila tutto, l\'utente conferma'],
                  ['Errori', 'Campi sbagliati, form respinti', 'Validazione AI prima dell\'invio'],
                  ['Tempo totale', '20-30 minuti', '30 secondi'],
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

        {/* ── CTA ──────────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center' }}>
          <a href="/per-llm" className="btn btn-electric btn-lg" style={{ marginRight: 12 }}>
            📖 Documentazione per LLM
          </a>
          <a href="/" className="btn btn-outline btn-lg">
            ⚡ Prova il confronto
          </a>
        </div>
      </div>
    </main>
  );
}
