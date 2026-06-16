import React from 'react';
import { Link } from 'react-router-dom';

export default function Footer() {
  return (
    <footer style={{
      padding: '40px 24px 32px',
      borderTop: '1px solid rgba(255,255,255,0.04)',
      textAlign: 'center',
    }}>
      <div style={{ maxWidth: 800, margin: '0 auto', fontSize: 12, color: '#64748b', lineHeight: 2.2 }}>
        <div style={{ marginBottom: 12 }}>
          <span style={{ color: '#f1f5f9', fontWeight: 700, fontSize: 13 }}>SwitchAI</span>
          {' — '}AI agent per cambio automatico fornitore energia. Mercato italiano.
        </div>

        <div style={{ display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: '4px 20px', marginBottom: 16 }}>
          <Link to="/come-funziona" style={{ color: '#94a3b8', textDecoration: 'none' }}>Come funziona</Link>
          <Link to="/per-llm" style={{ color: '#64748b', textDecoration: 'none' }}>Documentazione LLM</Link>
          <Link to="/analisi" style={{ color: '#64748b', textDecoration: 'none' }}>Demo parser</Link>
        </div>

        <div style={{
          paddingTop: 16, borderTop: '1px solid rgba(255,255,255,0.04)',
          display: 'flex', justifyContent: 'center', flexWrap: 'wrap', gap: '4px 20px',
        }}>
          <Link to="/privacy" style={{ color: '#94a3b8', textDecoration: 'none', fontWeight: 600 }}>Privacy Policy</Link>
          <Link to="/cookie" style={{ color: '#94a3b8', textDecoration: 'none', fontWeight: 600 }}>Cookie Policy</Link>
          <span style={{ color: '#64748b' }}>© 2026 SwitchAI</span>
          <a href="https://smithery.ai/servers/us1929/switchai" style={{ color: '#64748b', textDecoration: 'none' }}>Smithery</a>
          {' · '}
          <span style={{ color: '#64748b' }}>switchai.it</span>
        </div>
      </div>
    </footer>
  );
}
