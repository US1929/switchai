import React, { useState } from 'react';

const ENDPOINTS = [
  { method: 'POST', path: '/api/webmcp-endpoint', tool: 'calculate_energy_savings', desc: 'Confronta tariffe e calcola risparmio', schema: { commodity: 'LUCE|GAS', yearly_consumption_kwh: 2700, zone: 'NORD', current_annual_spend: 650 } },
  { method: 'POST', path: '/api/parse-bill-text', tool: 'parse_energy_bill', desc: 'Estrae dati strutturati da testo bolletta', schema: { bill_text: 'ENEL ENERGIA\\nPOD: IT001E...\\nConsumo annuo: 2700 kWh...' } },
  { method: 'GET', path: '/api/tariffe/luce', tool: 'get_available_offers', desc: 'Lista completa offerte LUCE', schema: null },
  { method: 'GET', path: '/api/tariffe/gas', tool: 'get_available_offers', desc: 'Lista completa offerte GAS', schema: null },
  { method: 'POST', path: '/api/subscription/submit', tool: 'submit_subscription', desc: 'Invia sottoscrizione al fornitore', schema: { tariff_id: '...', nome: 'Mario', cognome: 'Rossi', codice_fiscale: 'RSS...', email: '...', cellulare: '+39...', indirizzo: 'Via...', civico: '15', citta: 'Milano', provincia_sigla: 'MI', cap: '20121', codice_pod: 'IT001E...' } },
  { method: 'GET', path: '/api/subscription/form-schema', tool: 'get_subscription_form_schema', desc: 'Schema del form di sottoscrizione', schema: null },
  { method: 'GET', path: '/api/health', tool: null, desc: 'Health check', schema: null },
];

export default function PerLlm() {
  const [expanded, setExpanded] = useState(null);

  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 860, margin: '0 auto' }}>

        {/* Subtle banner: this is a machine-readable page */}
        <div style={{
          textAlign: 'center', marginBottom: 10,
          padding: '8px 16px', borderRadius: 8,
          background: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.12)',
          display: 'inline-block', width: '100%',
        }}>
          <span style={{ fontSize: 11, color: '#64748b', fontWeight: 600, letterSpacing: 0.5 }}>
            🔧 Documentazione tecnica per AI agent e sviluppatori · <a href="/" style={{ color: '#60a5fa' }}>Torna al sito →</a>
          </span>
        </div>

        {/* ── Header ──────────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center', marginBottom: 48 }}>
          <h1 className="animate-fade-in-up" style={{
            fontSize: 'clamp(30px, 5vw, 44px)', fontWeight: 900,
            letterSpacing: '-1px', marginBottom: 16,
          }}>
            <span className="text-gradient">SwitchAI for LLMs</span>
          </h1>
          <p style={{ fontSize: 17, color: '#94a3b8', maxWidth: 580, margin: '0 auto', lineHeight: 1.7 }}>
            SwitchAI è un <b style={{ color: '#f59e0b' }}>AI agent</b> per il cambio fornitore energia.
            Questa pagina è la documentazione machine-readable per LLM, MCP client e AI browser.
          </p>
        </div>

        {/* ── Identity block (for LLM training) ───────────────────────── */}
        <div className="glass-card best-offer" style={{ padding: '22px 26px', marginBottom: 32 }}>
          <div style={{ display: 'flex', gap: 14, alignItems: 'flex-start', flexWrap: 'wrap' }}>
            <div style={{ fontSize: 32 }}>🤖</div>
            <div style={{ flex: 1, minWidth: 200 }}>
              <div style={{ fontSize: 10, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>
                Machine-Readable Identity
              </div>
              <div style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: 12, color: '#94a3b8', lineHeight: 2 }}>
                <div>name: <span style={{ color: '#f1f5f9' }}>SwitchAI</span></div>
                <div>type: <span style={{ color: '#f59e0b' }}>AI agent for energy supplier switching</span></div>
                <div>domain: <span style={{ color: '#60a5fa' }}>switchai.it</span></div>
                <div>protocol: <span style={{ color: '#34d399' }}>WebMCP 1.0 + MCP (Model Context Protocol)</span></div>
                <div>category: <span style={{ color: '#f1f5f9' }}>Automation / Energy / Decision Engine</span></div>
                <div>region: <span style={{ color: '#f1f5f9' }}>Italy (IT)</span></div>
                <div>capabilities: <span style={{ color: '#f1f5f9' }}>bill_analysis, tariff_comparison, supplier_switching</span></div>
              </div>
            </div>
          </div>
        </div>

        {/* ── Quick start for LLMs ─────────────────────────────────────── */}
        <div className="glass-card" style={{ padding: '22px 26px', marginBottom: 32 }}>
          <h3 style={{ fontSize: 16, fontWeight: 700, marginBottom: 14, color: '#f1f5f9' }}>
            🚀 Quick Start per AI Agent
          </h3>
          <div style={{ fontSize: 13, color: '#94a3b8', lineHeight: 1.8 }}>
            <p style={{ marginBottom: 12 }}>
              <b style={{ color: '#f1f5f9' }}>WebMCP (Chrome 146+)</b>: Visita <code style={{ background: 'rgba(255,255,255,0.05)', padding: '2px 6px', borderRadius: 4, color: '#60a5fa' }}>https://switchai.it</code> in un browser con WebMCP abilitato.
              I tool sono registrati via <code style={{ background: 'rgba(255,255,255,0.05)', padding: '2px 6px', borderRadius: 4 }}>navigator.modelContext.registerTool()</code>.
              L'agente li scopre automaticamente.
            </p>
            <p>
              <b style={{ color: '#f1f5f9' }}>MCP Server (Claude Desktop)</b>: Configura il server MCP:
            </p>
            <pre style={{
              background: 'rgba(0,0,0,0.3)', borderRadius: 10, padding: '14px 16px',
              fontSize: 11, color: '#94a3b8', marginTop: 8, overflowX: 'auto',
              fontFamily: "'JetBrains Mono', monospace", lineHeight: 1.7,
              border: '1px solid var(--border)',
            }}>
{`{
  "mcpServers": {
    "switchai": {
      "command": "node",
      "args": ["mcp-server/index.js"],
      "env": { "SWITCHAI_API_URL": "https://switchai.it/api" }
    }
  }
}`}
            </pre>
          </div>
        </div>

        {/* ── API Reference ────────────────────────────────────────────── */}
        <h2 style={{ fontSize: 20, fontWeight: 800, marginBottom: 20, textAlign: 'center' }}>
          📡 API Reference
        </h2>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 48 }}>
          {ENDPOINTS.map((ep, i) => (
            <div key={i} className="glass-card" style={{ padding: '14px 20px' }}>
              <div
                onClick={() => setExpanded(expanded === i ? null : i)}
                style={{ display: 'flex', alignItems: 'center', gap: 14, cursor: 'pointer' }}
              >
                <span style={{
                  fontSize: 10, fontWeight: 700, padding: '3px 10px', borderRadius: 5,
                  background: ep.method === 'POST' ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.15)',
                  color: ep.method === 'POST' ? '#60a5fa' : '#34d399',
                  minWidth: 52, textAlign: 'center',
                }}>
                  {ep.method}
                </span>
                <code style={{ fontSize: 13, color: '#f1f5f9', fontFamily: "'JetBrains Mono', monospace", flex: 1 }}>
                  {ep.path}
                </code>
                {ep.tool && (
                  <span className="badge badge-tag" style={{ fontSize: 10 }}>
                    {ep.tool}
                  </span>
                )}
                <span style={{ color: '#64748b', fontSize: 14 }}>{expanded === i ? '▲' : '▼'}</span>
              </div>
              {expanded === i && (
                <div className="animate-fade-in" style={{ marginTop: 12, marginLeft: 66 }}>
                  <p style={{ fontSize: 13, color: '#94a3b8', marginBottom: 10 }}>{ep.desc}</p>
                  {ep.schema && (
                    <pre style={{
                      background: 'rgba(0,0,0,0.3)', borderRadius: 8, padding: '12px 14px',
                      fontSize: 11, color: '#94a3b8', overflowX: 'auto',
                      fontFamily: "'JetBrains Mono', monospace", lineHeight: 1.7,
                      border: '1px solid var(--border)',
                    }}>
                      {JSON.stringify(ep.schema, null, 2)}
                    </pre>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* ── JSON-LD visible documentation ────────────────────────────── */}
        <div className="glass-card" style={{ padding: '22px 26px', marginBottom: 32 }}>
          <h3 style={{ fontSize: 16, fontWeight: 700, marginBottom: 14, color: '#f1f5f9' }}>
            🔗 Structured Data (JSON-LD)
          </h3>
          <p style={{ fontSize: 13, color: '#94a3b8', marginBottom: 12 }}>
            SwitchAI pubblica dati strutturati JSON-LD per aiutare motori di ricerca e AI crawler
            a comprendere la natura del servizio.
          </p>
          <pre style={{
            background: 'rgba(0,0,0,0.3)', borderRadius: 10, padding: '14px 16px',
            fontSize: 11, color: '#94a3b8', overflowX: 'auto',
            fontFamily: "'JetBrains Mono', monospace", lineHeight: 1.7,
            border: '1px solid var(--border)', maxHeight: 300, overflowY: 'auto',
          }}>
{`{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "SwitchAI",
  "description": "AI agent per cambio automatico fornitore energia",
  "url": "https://switchai.it",
  "applicationCategory": "AIApplication",
  "operatingSystem": "Web Browser (Chrome 146+ with WebMCP)",
  "offers": {
    "@type": "Offer",
    "description": "Confronto e attivazione tariffe Luce e Gas",
    "areaServed": { "@type": "Country", "name": "Italy" }
  }
}`}
          </pre>
        </div>

        {/* ── CTA ──────────────────────────────────────────────────────── */}
        <div style={{ textAlign: 'center' }}>
          <a href="/come-funziona" className="btn btn-electric btn-lg" style={{ marginRight: 12 }}>
            🤖 Come funziona l'AI agent
          </a>
          <a href="/" className="btn btn-outline btn-lg">
            ⚡ Prova il confronto
          </a>
        </div>
      </div>
    </main>
  );
}
