import React from 'react';

export default function Cookie() {
  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 780, margin: '0 auto', fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>
        <h1 style={{ fontSize: 30, fontWeight: 900, color: '#f1f5f9', marginBottom: 8 }}>Cookie Policy</h1>
        <p style={{ color: '#64748b', marginBottom: 40 }}>Ultimo aggiornamento: Giugno 2026</p>

        <Section title="1. Cosa sono i cookie">
          I cookie sono piccoli file di testo che i siti web salvano sul dispositivo dell'utente durante la navigazione.
          Servono a ricordare preferenze, migliorare l'esperienza e garantire il corretto funzionamento tecnico del sito.
        </Section>

        <Section title="2. Cookie utilizzati da SwitchAI">
          <p>SwitchAI utilizza <b>esclusivamente cookie tecnici</b> indispensabili per il funzionamento del sito:</p>
          <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: 12, fontSize: 13 }}>
            <thead>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
                <th style={{ textAlign: 'left', padding: '8px 12px', color: '#f1f5f9' }}>Cookie</th>
                <th style={{ textAlign: 'left', padding: '8px 12px', color: '#f1f5f9' }}>Durata</th>
                <th style={{ textAlign: 'left', padding: '8px 12px', color: '#f1f5f9' }}>Scopo</th>
              </tr>
            </thead>
            <tbody>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                <td style={{ padding: '8px 12px', fontFamily: 'monospace', color: '#60a5fa' }}>cookie_consent</td>
                <td style={{ padding: '8px 12px' }}>1 anno</td>
                <td style={{ padding: '8px 12px' }}>Ricorda che hai accettato la cookie policy</td>
              </tr>
              <tr style={{ borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                <td style={{ padding: '8px 12px', fontFamily: 'monospace', color: '#60a5fa' }}>PHPSESSID</td>
                <td style={{ padding: '8px 12px' }}>Sessione</td>
                <td style={{ padding: '8px 12px' }}>Cookie di sessione PHP (tecnico, essenziale)</td>
              </tr>
            </tbody>
          </table>
        </Section>

        <Section title="3. Cookie di terze parti">
          SwitchAI <b>non utilizza cookie di profilazione</b>, né cookie di terze parti per finalità di marketing, analisi o tracciamento.
          Non sono presenti pixel di Facebook, Google Analytics, o altri strumenti di tracciamento.
        </Section>

        <Section title="4. WebMCP e AI Agent">
          SwitchAI è compatibile con lo standard WebMCP (Google Chrome Labs). Quando un AI agent visita il sito, i tool vengono registrati
          tramite <code style={{ background: 'rgba(255,255,255,0.05)', padding: '2px 6px', borderRadius: 4 }}>navigator.modelContext.registerTool()</code>.
          Questo meccanismo non utilizza cookie e non memorizza dati sul dispositivo. L'unica interazione con il browser è la registrazione
          di funzioni JavaScript accessibili all'AI agent.
        </Section>

        <Section title="5. Come disabilitare i cookie">
          Puoi disabilitare i cookie dalle impostazioni del tuo browser. Di seguito i link alle guide ufficiali:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener" style={{ color: '#60a5fa' }}>Google Chrome</a></li>
            <li><a href="https://support.mozilla.org/it/kb/Attivare%20e%20disattivare%20i%20cookie" target="_blank" rel="noopener" style={{ color: '#60a5fa' }}>Mozilla Firefox</a></li>
            <li><a href="https://support.apple.com/it-it/guide/safari/sfri11471/mac" target="_blank" rel="noopener" style={{ color: '#60a5fa' }}>Safari</a></li>
          </ul>
          La disabilitazione dei cookie tecnici potrebbe compromettere il funzionamento del sito.
        </Section>

        <Section title="6. Contatti">
          Per domande sulla cookie policy: <b>privacy@switchai.it</b>
        </Section>
      </div>
    </main>
  );
}

function Section({ title, children }) {
  return (
    <div style={{ marginBottom: 32 }}>
      <h2 style={{ fontSize: 18, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>{title}</h2>
      <div>{children}</div>
    </div>
  );
}
