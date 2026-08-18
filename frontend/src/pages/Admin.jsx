import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const TABS = [
  { id: 'traffic', label: '📊 Traffico', icon: '📊' },
  { id: 'users', label: '👥 Utenti', icon: '👥' },
  { id: 'apikeys', label: '🔑 API Keys B2B', icon: '🔑' },
  { id: 'affiliates', label: '💰 Affiliazioni', icon: '💰' },
  { id: 'sync', label: '🔄 Sync ARERA', icon: '🔄' },
  { id: 'wattene', label: '🧪 Test Wattene', icon: '🧪' },
  { id: 'testapi', label: '🧪 Test API', icon: '🧪' },
];

export default function Admin() {
  const navigate = useNavigate();
  const token = sessionStorage.getItem('switchai_token');
  const [tab, setTab] = useState('traffic');

  useEffect(() => {
    if (!token) { navigate('/login'); return; }
    fetch('/api/auth/verify', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => { if (!d.valid) { sessionStorage.removeItem('switchai_token'); navigate('/login'); } });
  }, []);

  if (!token) return null;

  return (
    <main style={{ padding: '40px 24px 80px', minHeight: '100vh' }}>
      <div style={{ maxWidth: 1000, margin: '0 auto' }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
          <h1 style={{ fontSize: 24, fontWeight: 900, color: '#f1f5f9' }}>⚙️ Admin</h1>
          <button
            className="btn btn-outline"
            onClick={() => { sessionStorage.removeItem('switchai_token'); navigate('/login'); }}
            style={{ fontSize: 12, padding: '6px 14px' }}
          >
            Logout
          </button>
        </div>

        {/* Tabs */}
        <div style={{ display: 'flex', gap: 8, marginBottom: 28 }}>
          {TABS.map(t => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              style={{
                padding: '10px 20px', borderRadius: 10, border: 'none', cursor: 'pointer',
                background: tab === t.id ? 'rgba(245,158,11,0.12)' : 'rgba(255,255,255,0.04)',
                color: tab === t.id ? '#f59e0b' : '#94a3b8',
                fontSize: 13, fontWeight: 700,
              }}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Content */}
        {tab === 'traffic' && <TrafficTab token={token} />}
        {tab === 'apikeys' && <ApiKeysTab token={token} />}
        {tab === 'affiliates' && <AffiliatesTab token={token} />}
        {tab === 'sync' && <SyncTab token={token} />}
        {tab === 'wattene' && <WatteneTab token={token} />}
        {tab === 'users' && <UsersTab token={token} />}
        {tab === 'testapi' && <TestApiTab token={token} />}
      </div>
    </main>
  );
}

function WatteneTab({ token }) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  const runTest = async () => {
    setLoading(true);
    setResult(null);
    setError(null);
    try {
      const res = await fetch('/api/admin/wattene-test', {
        headers: { 'x-auth-token': token },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setResult(data);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      background: '#0d1324', borderRadius: 16, padding: '28px 24px',
      border: '1px solid rgba(255,255,255,0.05)',
    }}>
      <h2 style={{ fontSize: 18, fontWeight: 800, color: '#f1f5f9', marginBottom: 6 }}>
        🧪 Confronto ARERA vs Wattene
      </h2>
      <p style={{ fontSize: 13, color: '#94a3b8', marginBottom: 20, lineHeight: 1.6 }}>
        Confronto ARERA vs Wattene su 5 offerte note + test coerenza interna su 10 offerte casuali.
        Profilo: 3200 kWh, 3 kW, NORD, residenziale. Esegui settimanalmente.
      </p>

      <button className="btn btn-electric" onClick={runTest} disabled={loading} style={{ fontSize: 13 }}>
        {loading ? '⏳ Esecuzione test...' : '▶️ Esegui test'}
      </button>

      {error && (
        <div style={{
          marginTop: 16, padding: '12px 16px', borderRadius: 8,
          background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)',
          color: '#fca5a5', fontSize: 12,
        }}>
          ❌ {error}
        </div>
      )}

      {result && (
        <div style={{ marginTop: 20 }}>
          {/* ═══ Wattene ═══ */}
          <h3 style={{ fontSize: 15, fontWeight: 700, color: '#f59e0b', marginBottom: 4 }}>⚡ Confronto Wattene</h3>
          <p style={{ fontSize: 11, color: '#64748b', marginBottom: 14 }}>{result.wattene?.note}</p>

          {renderSummary(result.wattene)}
          {renderCases(result.wattene?.cases, 'wattene')}

          {/* ═══ Random ═══ */}
          <h3 style={{ fontSize: 15, fontWeight: 700, color: '#3b82f6', marginBottom: 4, marginTop: 28 }}>🎲 Test su 10 offerte casuali</h3>
          <p style={{ fontSize: 11, color: '#64748b', marginBottom: 14 }}>{result.random?.note}</p>

          {renderSummary(result.random)}
          {renderCases(result.random?.cases, 'random')}
        </div>
      )}
    </div>
  );
}

function renderSummary(section) {
  if (!section) return null;
  return (
    <div>
      <div style={{ display: 'flex', gap: 16, marginBottom: 14, flexWrap: 'wrap' }}>
        {[
          { label: 'Offerte', value: section.total, color: '#94a3b8' },
          { label: 'OK', value: section.passed, color: '#10b981' },
          { label: 'FAIL', value: section.failed, color: section.failed > 0 ? '#ef4444' : '#64748b' },
        ].map(s => (
          <div key={s.label} style={{
            padding: '10px 16px', borderRadius: 10,
            background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)',
            textAlign: 'center', minWidth: 80,
          }}>
            <div style={{ fontSize: 20, fontWeight: 900, color: s.color }}>{s.value}</div>
            <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>{s.label}</div>
          </div>
        ))}
      </div>

      <div style={{
        padding: '12px 16px', borderRadius: 10, marginBottom: 14,
        background: section.all_ok ? 'rgba(16,185,129,0.06)' : 'rgba(239,68,68,0.06)',
        border: `1px solid ${section.all_ok ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)'}`,
        fontSize: 13, color: section.all_ok ? '#6ee7b7' : '#fca5a5', fontWeight: 700,
      }}>
        {section.all_ok ? '✅ TUTTI I TEST SUPERATI' : '❌ Alcuni test non superati'}
      </div>
    </div>
  );
}

function renderCases(cases, type) {
  if (!cases?.length) return null;

  return cases.map((c, i) => (
    <div key={i} style={{
      padding: '14px', borderRadius: 10, marginBottom: 8,
      background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: type === 'wattene' ? 8 : 4 }}>
        <span>{c.status === 'OK' ? '✅' : c.status === 'FAIL' ? '❌' : '🔍'}</span>
        <div>
          <div style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9' }}>{c.brand}{c.type ? ` (${c.type})` : ''}</div>
          <div style={{ fontSize: 11, color: '#94a3b8' }}>{c.offerta}</div>
        </div>
      </div>

      {type === 'wattene' && c.status !== 'NOT_FOUND' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10, fontSize: 11 }}>
          <div>
            <div style={{ color: '#64748b' }}>Prezzo €/kWh</div>
            <div style={{ color: c.prezzo_ok ? '#10b981' : '#ef4444', fontWeight: 600 }}>
              {c.prezzo_nostro?.toFixed(6)} vs {c.prezzo_wattene?.toFixed(6)}
            </div>
            <div style={{ fontSize: 10, color: '#64748b' }}>
              diff: {(c.diff_prezzo_mill > 0 ? '+' : '') + c.diff_prezzo_mill?.toFixed(3)} mill
            </div>
          </div>
          <div>
            <div style={{ color: '#64748b' }}>PCV €/anno</div>
            <div style={{ color: c.pcv_ok ? '#10b981' : '#ef4444', fontWeight: 600 }}>
              {c.pcv_nostro?.toFixed(2)} vs {c.pcv_wattene?.toFixed(2)}
            </div>
            <div style={{ fontSize: 10, color: '#64748b' }}>
              diff: {(c.diff_pcv_eur > 0 ? '+' : '') + c.diff_pcv_eur?.toFixed(2)}€
            </div>
          </div>
          <div>
            <div style={{ color: '#64748b' }}>Dispacciamento</div>
            <div style={{ color: '#94a3b8', fontWeight: 600 }}>{c.dispacciamento?.toFixed(5)} €/kWh</div>
          </div>
        </div>
      )}

      {type === 'random' && c.checks && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3, marginTop: 4 }}>
          {c.checks.map((ch, j) => (
            <div key={j} style={{ fontSize: 11, display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ color: ch.ok ? '#10b981' : '#ef4444', fontSize: 10 }}>{ch.ok ? '●' : '○'}</span>
              <span style={{ color: '#94a3b8' }}>{ch.check}:</span>
              <span style={{ color: ch.ok ? '#e2e8f0' : '#fca5a5', fontWeight: 600 }}>{ch.value}</span>
            </div>
          ))}
        </div>
      )}

      {type === 'wattene' && c.sconti_attivi?.length > 0 && (
        <div style={{
          marginTop: 8, padding: '6px 10px', borderRadius: 6,
          background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.2)',
          fontSize: 11, color: '#fbbf24',
        }}>
          ⚠️ Sconti: {c.sconti_attivi.map(s => s.nome || '?').join(', ')}
        </div>
      )}
    </div>
  ));
}

// ── Users Tab ────────────────────────────────────────────────────

function UsersTab({ token }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);

  const loadUsers = async () => {
    setLoading(true);
    try {
      const r = await fetch('/api/admin/users', { headers: { 'x-auth-token': token } });
      const d = await r.json();
      setUsers(d.users || []);
    } catch { setUsers([]); }
    setLoading(false);
  };

  useEffect(() => { loadUsers(); }, []);

  const updateUser = async (userId, updates) => {
    try {
      await fetch(`/api/admin/users/${userId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
        body: JSON.stringify(updates),
      });
      loadUsers();
    } catch {}
  };

  const toggleTier = (u) => {
    const newTier = u.tier === 'api_pro' ? 'free' : 'api_pro';
    updateUser(u.id, { tier: newTier });
  };

  const toggleDisabled = (u) => {
    updateUser(u.id, { disabled: u.disabled ? 0 : 1 });
  };

  return (
    <div className="glass-card" style={{ padding: '16px 22px' }}>
      <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>👥 Utenti registrati ({users.length})</h3>

      {loading && <div style={{ color: '#64748b', padding: 20 }}>⏳ Caricamento...</div>}

      {!loading && users.length === 0 && (
        <p style={{ color: '#64748b', fontSize: 13 }}>Nessun utente registrato.</p>
      )}

      {users.map(u => (
        <div key={u.id} style={{
          padding: '12px 14px', borderRadius: 8, marginBottom: 8,
          background: u.disabled ? 'rgba(239,68,68,0.05)' : 'rgba(255,255,255,0.03)',
          border: `1px solid ${u.disabled ? 'rgba(239,68,68,0.15)' : 'rgba(255,255,255,0.06)'}`,
          opacity: u.disabled ? 0.6 : 1,
        }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
            <div style={{ flex: 1, minWidth: 200 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontWeight: 700, color: '#f1f5f9', fontSize: 13 }}>
                  {u.nome} {u.cognome}
                </span>
                <span style={{
                  fontSize: 10, fontWeight: 600, padding: '2px 8px', borderRadius: 6,
                  background: u.tier === 'api_pro' ? 'rgba(245,158,11,0.15)' : 'rgba(255,255,255,0.06)',
                  color: u.tier === 'api_pro' ? '#f59e0b' : '#94a3b8',
                }}>
                  {u.tier?.toUpperCase()}
                </span>
                {!u.email_verified && (
                  <span style={{ fontSize: 10, color: '#fbbf24', fontWeight: 600 }}>⏳ NON VERIFICATO</span>
                )}
                {u.disabled && (
                  <span style={{ fontSize: 10, color: '#f87171', fontWeight: 600 }}>DISABILITATO</span>
                )}
              </div>
              <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>{u.email}</div>
              <div style={{ fontSize: 10, color: '#64748b', marginTop: 1 }}>
                Iscritto: {u.created_at?.slice(0, 10)} · Consumo gg: {u.daily_usage || 0}
              </div>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button onClick={() => toggleTier(u)}
                style={{
                  fontSize: 11, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600,
                  background: 'rgba(245,158,11,0.1)', border: '1px solid rgba(245,158,11,0.2)',
                  color: '#f59e0b',
                }}
              >
                {u.tier === 'api_pro' ? '⬇️ Free' : '⬆️ API Pro'}
              </button>
              {!u.email_verified && (
                <button onClick={() => updateUser(u.id, { resend_confirmation: 1 })}
                  style={{
                    fontSize: 11, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600,
                    background: 'rgba(59,130,246,0.1)', border: '1px solid rgba(59,130,246,0.2)',
                    color: '#60a5fa',
                  }}
                >
                  Reinvia conferma
                </button>
              )}
              <button onClick={() => toggleDisabled(u)}
                style={{
                  fontSize: 11, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600,
                  background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                  color: '#f87171',
                }}
              >
                {u.disabled ? '✅ Attiva' : '⛔ Disabilita'}
              </button>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Test API Tab ─────────────────────────────────────────────────

function TestApiTab({ token }) {
  const [method, setMethod] = useState('GET');
  const [endpoint, setEndpoint] = useState('/api/tariffe/luce');
  const [headersStr, setHeadersStr] = useState('');
  const [body, setBody] = useState('');
  const [apiKey, setApiKey] = useState('');
  const [showApiKeyInput, setShowApiKeyInput] = useState(false);
  const [response, setResponse] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const runTest = async () => {
    setLoading(true); setError(''); setResponse(null);
    try {
      let parsedHeaders = {};
      try {
        if (headersStr.trim()) parsedHeaders = JSON.parse(headersStr);
      } catch { setError('Headers JSON non valido'); setLoading(false); return; }

      let parsedBody = null;
      if (body.trim() && method !== 'GET') {
        try { parsedBody = JSON.parse(body); } catch { setError('Body JSON non valido'); setLoading(false); return; }
      }

      const payload = { method, endpoint, headers: parsedHeaders, body: parsedBody };
      if (apiKey.trim()) {
        payload.api_key = apiKey.trim();
      } else {
        payload.use_admin_token = true;
      }

      const r = await fetch('/api/admin/test-api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
        body: JSON.stringify(payload),
      });
      const data = await r.json();
      setResponse(data);
    } catch (e) {
      setError(e.message || 'Errore');
    }
    setLoading(false);
  };

  return (
    <div>
      <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 20 }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>🧪 Test API (proxy interno)</h3>
        <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 16, lineHeight: 1.6 }}>
          Simula una chiamata API come se arrivasse da un programma terzo. Il server chiama sé stesso
          come se fosse un client esterno, usando il sistema di autenticazione reale.
        </p>

        <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
          <select value={method} onChange={e => setMethod(e.target.value)}
            style={{
              padding: '10px 14px', borderRadius: 8, background: '#1e293b',
              border: '1px solid rgba(255,255,255,0.08)', color: '#f1f5f9',
              fontWeight: 600, fontSize: 13,
            }}
          >
            {['GET', 'POST', 'PATCH', 'DELETE'].map(m => (
              <option key={m} value={m}>{m}</option>
            ))}
          </select>
          <input className="input-field" value={endpoint} onChange={e => setEndpoint(e.target.value)}
            placeholder="/api/tariffe/luce" style={{ flex: 1, minWidth: 200, fontFamily: 'monospace' }}
          />
        </div>

        <div style={{ marginBottom: 12 }}>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
            Headers extra (JSON)
          </label>
          <input className="input-field" value={headersStr} onChange={e => setHeadersStr(e.target.value)}
            placeholder='{"x-test": "true"}' style={{ width: '100%', fontFamily: 'monospace' }}
          />
        </div>

        {method !== 'GET' && (
          <div style={{ marginBottom: 12 }}>
            <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
              Body (JSON)
            </label>
            <textarea className="input-field" value={body} onChange={e => setBody(e.target.value)}
              placeholder='{"key": "value"}' rows={4}
              style={{ width: '100%', fontFamily: 'monospace', resize: 'vertical' }}
            />
          </div>
        )}

        <div style={{ marginBottom: 12 }}>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
            <input type="checkbox" checked={showApiKeyInput} onChange={e => { setShowApiKeyInput(e.target.checked); if (!e.target.checked) setApiKey(''); }}
              style={{ accentColor: '#f59e0b' }} />
            Usa API key (invece del token admin)
          </label>
          {showApiKeyInput && (
            <input className="input-field" type="text" value={apiKey} onChange={e => setApiKey(e.target.value)}
              placeholder="sk_..." style={{ width: '100%', fontFamily: 'monospace' }}
            />
          )}
        </div>

        <button className="btn btn-electric" onClick={runTest} disabled={loading || !endpoint} style={{ fontSize: 13 }}>
          {loading ? '⏳ Invio...' : '🚀 Invia richiesta'}
        </button>

        {error && (
          <div style={{ marginTop: 12, padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', fontSize: 12, color: '#f87171' }}>
            ❌ {error}
          </div>
        )}
      </div>

      {/* ── Guida rapida ── */}
      <div className="glass-card" style={{ padding: '16px 22px', marginBottom: 20 }}>
        <details>
          <summary style={{ cursor: 'pointer', fontSize: 13, fontWeight: 700, color: '#f59e0b', userSelect: 'none' }}>
            📖 Guida rapida — esempi di chiamate
          </summary>
          <div style={{ marginTop: 14, display: 'flex', flexDirection: 'column', gap: 8 }}>
            {[
              { method: 'GET', endpoint: '/api/health', body: '', desc: 'Health check — verifica che l\'API risponda' },
              { method: 'GET', endpoint: '/api/status', body: '', desc: 'Statistiche — conteggio offerte e fornitori' },
              { method: 'GET', endpoint: '/api/tariffe/luce', body: '', desc: 'Lista offerte LUCE (anonimo = ~142, loggato = 3.000+)' },
              { method: 'GET', endpoint: '/api/tariffe/gas', body: '', desc: 'Lista offerte GAS (anonimo = ~128, loggato = 2.400+)' },
              { method: 'GET', endpoint: '/api/fornitori', body: '', desc: 'Elenco fornitori disponibili' },
              { method: 'GET', endpoint: '/api/market-indices', body: '', desc: 'PUN e PSV correnti (sync ARERA notturno)' },
              { method: 'GET', endpoint: '/api/auth/api-keys', body: '', desc: 'Lista API keys dell\'utente loggato (auth)' },
              { method: 'POST', endpoint: '/api/analyze', body: JSON.stringify({ commodity: 'LUCE', consumo_annuo_kwh: 2700, spesa_annua_eur: 650, zona: 'NORD' }, null, 2), desc: 'Analisi V2 completa — confronto + honnesty + breakdown' },
              { method: 'POST', endpoint: '/api/calculate-savings', body: JSON.stringify({ commodity: 'LUCE', yearly_consumption_kwh: 2700, zone: 'NORD', current_annual_spend: 650 }, null, 2), desc: 'Calcolo risparmio rapido (versione web)' },
              { method: 'POST', endpoint: '/api/webmcp-endpoint', body: JSON.stringify({ commodity: 'LUCE', yearly_consumption_kwh: 2700, zone: 'NORD', current_annual_spend: 650 }, null, 2), desc: 'Endpoint legacy (deprecato — usa /api/analyze)' },
              { method: 'POST', endpoint: '/api/parse-bill-text', body: JSON.stringify({ text: 'Fornitore: Enel Energia\nConsumo annuo: 2700 kWh\nSpesa annua: 650 €\nPOD: IT001E123456789' }, null, 2), desc: 'Parser bolletta — estrai dati da testo grezzo' },
            ].map((ex, i) => (
              <div key={i} style={{
                padding: '10px 14px', borderRadius: 8,
                background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)',
                display: 'flex', alignItems: 'flex-start', gap: 10,
              }}>
                <span style={{
                  fontSize: 10, fontWeight: 700, padding: '2px 8px', borderRadius: 4, flexShrink: 0, marginTop: 1,
                  background: ex.method === 'GET' ? 'rgba(16,185,129,0.12)' : 'rgba(59,130,246,0.12)',
                  color: ex.method === 'GET' ? '#34d399' : '#60a5fa',
                }}>
                  {ex.method}
                </span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 12, color: '#f1f5f9', fontWeight: 600, fontFamily: 'monospace', wordBreak: 'break-all' }}>
                    {ex.endpoint}
                  </div>
                  <div style={{ fontSize: 11, color: '#64748b', marginTop: 1 }}>{ex.desc}</div>
                  {ex.method !== 'GET' && ex.body && (
                    <pre style={{
                      marginTop: 4, padding: '4px 8px', borderRadius: 4,
                      background: 'rgba(0,0,0,0.3)', fontSize: 10, color: '#94a3b8',
                      maxHeight: 60, overflow: 'hidden', whiteSpace: 'pre-wrap',
                    }}>
                      {ex.body.slice(0, 200)}
                    </pre>
                  )}
                </div>
                <button onClick={() => { setMethod(ex.method); setEndpoint(ex.endpoint); setBody(''); setResponse(null); setError(''); if (ex.body) setBody(ex.body); }}
                  style={{
                    fontSize: 10, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                    background: 'rgba(245,158,11,0.1)', border: '1px solid rgba(245,158,11,0.2)',
                    color: '#f59e0b',
                  }}>
                  Carica
                </button>
              </div>
            ))}
          </div>
        </details>
      </div>

      {response && (
        <div className="glass-card" style={{ padding: '16px 22px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
            <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>
              📥 Risposta ({method} {endpoint})
            </h3>
            <span style={{
              fontSize: 11, padding: '3px 10px', borderRadius: 6,
              background: response.status && response.status < 300 ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)',
              color: response.status && response.status < 300 ? '#6ee7b7' : '#fca5a5',
              fontWeight: 700,
            }}>
              HTTP {response.status || '?'}
            </span>
          </div>

          {response.error && (
            <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', fontSize: 12, color: '#f87171', marginBottom: 10 }}>
              ❌ {response.error}
            </div>
          )}

          {response.tier && (
            <div style={{ fontSize: 11, color: '#64748b', marginBottom: 8 }}>
              Tier: <strong style={{ color: '#f59e0b' }}>{response.tier}</strong>
              · Rate limit: {response.remaining !== undefined ? `${response.remaining}/${response.limit}` : 'N/A'}
            </div>
          )}

          <pre style={{
            padding: 16, borderRadius: 8, background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.05)',
            fontSize: 11, color: '#e2e8f0', maxHeight: 400, overflow: 'auto', whiteSpace: 'pre-wrap',
            wordBreak: 'break-all', fontFamily: 'monospace', margin: 0,
          }}>
            {(() => {
              try { return JSON.stringify(response.data || response.body || response, null, 2); }
              catch { return String(response); }
            })()}
          </pre>
        </div>
      )}
    </div>
  );
}

// ── Traffic Tab ────────────────────────────────────────────────────

function TrafficTab({ token }) {
  const [data, setData] = useState(null);
  const [hours, setHours] = useState(24);

  useEffect(() => {
    fetch(`/api/stats/traffic?hours=${hours}`, { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setData);
  }, [hours, token]);

  return (
    <div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
        {[1, 6, 24, 168, 720].map(h => (
          <button key={h} onClick={() => setHours(h)}
            style={{
              padding: '6px 14px', borderRadius: 8, border: 'none', cursor: 'pointer',
              background: hours === h ? 'rgba(59,130,246,0.15)' : 'rgba(255,255,255,0.04)',
              color: hours === h ? '#60a5fa' : '#64748b', fontSize: 12, fontWeight: 600,
            }}>
            {h === 1 ? '1h' : h === 6 ? '6h' : h === 24 ? '24h' : h === 168 ? '7gg' : '30gg'}
          </button>
        ))}
      </div>

      {!data && <div style={{ color: '#64748b', padding: 20 }}>⏳ Caricamento...</div>}

      {data && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {/* Total */}
          <div className="glass-card" style={{ padding: '16px 22px', textAlign: 'center' }}>
            <div style={{ fontSize: 36, fontWeight: 900, color: '#f1f5f9' }}>{data.total}</div>
            <div style={{ fontSize: 12, color: '#64748b' }}>chiamate totali</div>
          </div>

          {/* By type */}
          <div className="glass-card" style={{ padding: '16px 22px' }}>
            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>Per tipo</h3>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              {[
                { key: 'llm', label: '🤖 LLM', color: '#a78bfa' },
                { key: 'human', label: '👤 Umani', color: '#34d399' },
                { key: 'bot', label: '🔧 Bot', color: '#f59e0b' },
                { key: 'unknown', label: '❓ Altro', color: '#64748b' },
              ].map(t => (
                <div key={t.key} style={{
                  flex: 1, minWidth: 100, padding: 12, borderRadius: 8,
                  background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)', textAlign: 'center',
                }}>
                  <div style={{ fontSize: 22, fontWeight: 800, color: t.color }}>{data.by_type[t.key] || 0}</div>
                  <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>{t.label}</div>
                </div>
              ))}
            </div>
          </div>

          {/* By endpoint */}
          {Object.keys(data.by_endpoint).length > 0 && (
            <div className="glass-card" style={{ padding: '16px 22px' }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>Per endpoint</h3>
              {Object.entries(data.by_endpoint).sort((a, b) => b[1] - a[1]).map(([ep, count]) => (
                <div key={ep} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, padding: '3px 0' }}>
                  <code style={{ color: '#94a3b8' }}>{ep}</code>
                  <span style={{ color: '#f1f5f9', fontWeight: 700 }}>{count}</span>
                </div>
              ))}
            </div>
          )}

          {/* LLM visitors */}
          {Object.keys(data.llm_visitors).length > 0 && (
            <div className="glass-card best-offer" style={{ padding: '16px 22px' }}>
              <h3 style={{ fontSize: 13, fontWeight: 700, color: '#10b981', marginBottom: 10 }}>🤖 Visitatori LLM</h3>
              {Object.entries(data.llm_visitors).map(([name, info]) => (
                <div key={name} style={{
                  padding: '10px 12px', borderRadius: 8, marginBottom: 6,
                  background: 'rgba(16,185,129,0.05)', border: '1px solid rgba(16,185,129,0.1)',
                }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9' }}>{name}</span>
                    <span style={{ fontSize: 18, fontWeight: 800, color: '#10b981' }}>{info.calls}</span>
                  </div>
                  <div style={{ fontSize: 10, color: '#64748b', marginTop: 2 }}>
                    Prime: {info.first_seen} · Ultima: {info.last_seen}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// ── API Keys Tab ────────────────────────────────────────────────────

function ApiKeysTab({ token }) {
  const [clients, setClients] = useState([]);
  const [newName, setNewName] = useState('');
  const [newTier, setNewTier] = useState('basic');
  const [creating, setCreating] = useState(false);
  const [newKey, setNewKey] = useState(null);

  const loadClients = () => {
    fetch('/api/admin/api-keys', { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setClients);
  };

  useEffect(() => { loadClients(); }, []);

  const createKey = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    const r = await fetch('/api/admin/api-keys/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ name: newName, tier: newTier }),
    });
    const d = await r.json();
    setNewKey(d.api_key);
    setNewName('');
    setCreating(false);
    loadClients();
  };

  const disableKey = async (hash) => {
    await fetch(`/api/admin/api-keys/${hash}`, { method: 'DELETE', headers: { 'x-auth-token': token } });
    loadClients();
  };

  return (
    <div>
      {/* Create form */}
      <div className="glass-card" style={{ padding: '20px 22px', marginBottom: 20 }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>➕ Crea nuova chiave API</h3>
        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
          <div style={{ flex: 1, minWidth: 200 }}>
            <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, marginBottom: 6, display: 'block' }}>Nome cliente</label>
            <input className="input-field" value={newName} onChange={e => setNewName(e.target.value)} placeholder="Agenzia Energia Srl" />
          </div>
          <div style={{ minWidth: 140 }}>
            <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, marginBottom: 6, display: 'block' }}>Tier</label>
            <select className="input-field" value={newTier} onChange={e => setNewTier(e.target.value)} style={{ padding: '14px 12px' }}>
              <option value="basic">Basic — 1.000/mese</option>
              <option value="pro">Pro — 5.000/mese</option>
              <option value="premium">Premium — 20.000/mese</option>
            </select>
          </div>
          <button className="btn btn-electric" onClick={createKey} disabled={creating || !newName.trim()}>
            {creating ? '⏳' : 'Crea chiave'}
          </button>
        </div>
        {newKey && (
          <div style={{ marginTop: 14, padding: '14px 16px', borderRadius: 10, background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.25)' }}>
            <div style={{ fontSize: 11, color: '#6ee7b7', fontWeight: 700, textTransform: 'uppercase', marginBottom: 4 }}>🔑 Nuova chiave (copiala ora — non verrà più mostrata)</div>
            <code style={{ fontSize: 14, color: '#10b981', fontFamily: 'monospace', wordBreak: 'break-all', userSelect: 'all' }}>{newKey}</code>
          </div>
        )}
      </div>

      {/* Clients list */}
      <div className="glass-card" style={{ padding: '16px 22px' }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>📋 Clienti API registrati</h3>
        {clients.length === 0 && (
          <p style={{ color: '#64748b', fontSize: 13 }}>Nessun cliente API registrato. Creane uno qui sopra.</p>
        )}
        {clients.map(c => {
          const disabled = c.disabled ?? false;
          return (
            <div key={c.api_key_hash} style={{
              padding: '12px 14px', borderRadius: 8, marginBottom: 8,
              background: disabled ? 'rgba(239,68,68,0.05)' : 'rgba(255,255,255,0.03)',
              border: `1px solid ${disabled ? 'rgba(239,68,68,0.15)' : 'rgba(255,255,255,0.06)'}`,
              opacity: disabled ? 0.6 : 1,
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                <div>
                  <span style={{ fontWeight: 700, color: '#f1f5f9', fontSize: 13 }}>{c.client_name}</span>
                  <span style={{
                    marginLeft: 8, fontSize: 10, fontWeight: 600, padding: '2px 8px', borderRadius: 6,
                    background: c.tier === 'premium' ? 'rgba(245,158,11,0.15)' : c.tier === 'pro' ? 'rgba(59,130,246,0.15)' : 'rgba(255,255,255,0.06)',
                    color: c.tier === 'premium' ? '#f59e0b' : c.tier === 'pro' ? '#60a5fa' : '#94a3b8',
                  }}>
                    {c.tier?.toUpperCase()}
                  </span>
                  {disabled && <span style={{ marginLeft: 8, fontSize: 10, color: '#f87171', fontWeight: 600 }}>DISATTIVATA</span>}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ fontSize: 11, color: '#64748b' }}>{c.calls_current_month || 0} / {c.monthly_quota} chiamate</span>
                  {!disabled && (
                    <button
                      onClick={() => disableKey(c.api_key_hash)}
                      style={{
                        fontSize: 11, padding: '4px 10px', borderRadius: 6,
                        background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                        color: '#f87171', cursor: 'pointer', fontWeight: 600,
                      }}
                    >
                      Disattiva
                    </button>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Affiliates Tab ────────────────────────────────────────────────

function AffiliatesTab({ token }) {
  const [links, setLinks] = useState([]);
  const [brandAffiliates, setBrandAffiliates] = useState([]);
  const [offers, setOffers] = useState([]);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ tariff_id: '', affiliate_url: '', network: '', supplier: '', tariff_name: '', commodity: '' });
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [brandForm, setBrandForm] = useState({ brand: '', default_url: '', impression_url: '', network: '' });
  const [brandSaving, setBrandSaving] = useState(false);
  const [brandShowForm, setBrandShowForm] = useState(false);

  const loadLinks = () => {
    fetch('/api/admin/affiliates', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => setLinks(d.affiliates || []));
  };

  const loadBrandAffiliates = () => {
    fetch('/api/admin/brand-affiliates', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => setBrandAffiliates(d.brand_affiliates || []));
  };

  const loadOffers = async () => {
    try {
      const [luce, gas] = await Promise.all([
        fetch('/api/tariffe/luce').then(r => r.json()),
        fetch('/api/tariffe/gas').then(r => r.json()),
      ]);
      const all = [...(luce.offers || []), ...(gas.offers || [])];
      setOffers(all);
    } catch { /* offerte non disponibili */ }
  };

  useEffect(() => { loadLinks(); loadBrandAffiliates(); loadOffers(); }, []);

  // Brands da offerte caricate + brand già configurati + fallback fornitori noti
  const KNOWN_BRANDS = [
    'A2A Energia', 'ACEA Energia', 'Alperia', 'Azzurra Energia', 'CVA Energie',
    'Dolomiti Energia', 'E.ON Energia', 'Edison Energia', 'Enel Energia',
    'Engie', 'Eni Plenitude', 'Estra', 'Fastweb Energia', 'Hera Comm',
    'Illumia', 'Iren Mercato', 'NeN', 'Octopus Energy', 'Poste Energia',
    'Pulsee', 'Segnoverde', 'Sorgenia', 'Volty', 'WeMi',
  ];
  const uniqueBrands = [...new Set([
    ...offers.map(o => o.supplier_name || '').filter(Boolean),
    ...brandAffiliates.map(b => b.brand).filter(Boolean),
    ...KNOWN_BRANDS,
  ])].sort();

  const saveBrandLink = async () => {
    if (!brandForm.brand || !brandForm.default_url) return;
    setBrandSaving(true);
    await fetch('/api/admin/brand-affiliates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify(brandForm),
    });
    setBrandSaving(false);
    setBrandShowForm(false);
    setBrandForm({ brand: '', default_url: '', impression_url: '', network: '' });
    loadBrandAffiliates();
  };

  const deleteBrandLink = async (brand) => {
    await fetch('/api/admin/brand-affiliates', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ brand }),
    });
    loadBrandAffiliates();
  };

  const saveLink = async () => {
    if (!form.tariff_id || !form.affiliate_url) return;
    setSaving(true);
    await fetch('/api/admin/affiliates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify(form),
    });
    setSaving(false);
    setShowForm(false);
    setForm({ tariff_id: '', affiliate_url: '', network: '', supplier: '', tariff_name: '', commodity: '' });
    loadLinks();
  };

  const deleteLink = async (tariffId) => {
    await fetch('/api/admin/affiliates', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
      body: JSON.stringify({ tariff_id: tariffId }),
    });
    loadLinks();
  };

  const pickOffer = (offer) => {
    setForm({
      tariff_id: offer.id || '',
      affiliate_url: '',
      network: '',
      supplier: offer.supplier_name || '',
      tariff_name: offer.name || '',
      commodity: offer.commodity || '',
    });
    setShowForm(true);
  };

  const linkMap = new Map(links.map(l => [l.tariff_id, l]));
  const filtered = offers.filter(o => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (o.supplier_name || '').toLowerCase().includes(q)
        || (o.name || '').toLowerCase().includes(q)
        || (o.id || '').toLowerCase().includes(q);
  }).slice(0, 100);

  return (
    <div>
      {/* Brand defaults section */}
      <div className="glass-card" style={{ padding: '16px 22px', marginBottom: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
          <div>
            <div style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>🏷️ Tracker di default per fornitore</div>
            <div style={{ fontSize: 11, color: '#64748b' }}>Usato per tutte le offerte del fornitore senza link specifico. Sovrascrivibile offerta per offerta.</div>
          </div>
          {!brandShowForm && (
            <button className="btn btn-electric" onClick={() => setBrandShowForm(true)} style={{ fontSize: 12, whiteSpace: 'nowrap' }}>
              ➕ Aggiungi tracker
            </button>
          )}
        </div>

        {brandShowForm && (
          <div className="glass-card" style={{ padding: '12px 16px', marginBottom: 12, background: 'rgba(59,130,246,0.04)' }}>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'end' }}>
              <div style={{ flex: '1 1 200px' }}>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Fornitore</label>
                <select
                  className="input-field"
                  value={brandForm.brand}
                  onChange={e => setBrandForm({...brandForm, brand: e.target.value})}
                  style={{ fontSize: 12, padding: '8px 10px', width: '100%' }}
                >
                  <option value="">— Seleziona fornitore —</option>
                  {uniqueBrands.map(b => <option key={b} value={b}>{b}</option>)}
                </select>
              </div>
              <div style={{ flex: '1 1 120px' }}>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Network</label>
                <input className="input-field" value={brandForm.network} onChange={e => setBrandForm({...brandForm, network: e.target.value})} placeholder="tradedoubler" style={{ fontSize: 12, padding: '8px 10px', width: '100%' }} />
              </div>
              <div style={{ flex: '3 1 300px' }}>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>URL Click (default)</label>
                <input className="input-field" value={brandForm.default_url} onChange={e => setBrandForm({...brandForm, default_url: e.target.value})} placeholder="https://clk.tradedoubler.com/click?p=..." style={{ fontSize: 12, padding: '8px 10px', width: '100%' }} />
              </div>
              <div style={{ flex: '3 1 300px' }}>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>URL Impression (pixel)</label>
                <input className="input-field" value={brandForm.impression_url} onChange={e => setBrandForm({...brandForm, impression_url: e.target.value})} placeholder="https://imp.tradedoubler.com/imp?type(inv)..." style={{ fontSize: 12, padding: '8px 10px', width: '100%' }} />
              </div>
              <div style={{ display: 'flex', gap: 6, paddingBottom: 2 }}>
                <button className="btn btn-electric" onClick={saveBrandLink} disabled={brandSaving || !brandForm.brand || !brandForm.default_url} style={{ fontSize: 12 }}>
                  {brandSaving ? '⏳' : '💾 Salva'}
                </button>
                <button className="btn btn-outline" onClick={() => { setBrandShowForm(false); setBrandForm({ brand: '', default_url: '', impression_url: '', network: '' }); }} style={{ fontSize: 12 }}>Annulla</button>
              </div>
            </div>
          </div>
        )}

        {brandAffiliates.length > 0 && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            {brandAffiliates.map(b => (
              <div key={b.brand} style={{
                padding: '8px 12px', borderRadius: 8,
                background: 'rgba(59,130,246,0.05)', border: '1px solid rgba(59,130,246,0.12)',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
              }}>
                <div style={{ flex: 1, minWidth: 180 }}>
                  <span style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9' }}>{b.brand}</span>
                  {b.network && <span style={{ marginLeft: 6, fontSize: 9, color: '#64748b', background: 'rgba(255,255,255,0.06)', padding: '1px 6px', borderRadius: 4 }}>{b.network}</span>}
                  <br />
                  <a href={b.default_url} target="_blank" rel="noreferrer" style={{ fontSize: 10, color: '#60a5fa', wordBreak: 'break-all' }}>
                    {b.default_url.length > 80 ? b.default_url.slice(0, 80) + '...' : b.default_url}
                  </a>
                </div>
                <button
                  onClick={() => deleteBrandLink(b.brand)}
                  style={{
                    fontSize: 11, padding: '4px 10px', borderRadius: 6,
                    background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                    color: '#f87171', cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                  }}
                >
                  Rimuovi
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Stats */}
      <div className="glass-card" style={{ padding: '16px 22px', marginBottom: 20, textAlign: 'center' }}>
        <div style={{ fontSize: 36, fontWeight: 900, color: '#f59e0b' }}>{links.length}</div>
        <div style={{ fontSize: 12, color: '#64748b' }}>link specifici per offerta</div>
      </div>

      {/* Add form */}
      <div style={{ marginBottom: 20 }}>
        {!showForm ? (
          <button className="btn btn-electric" onClick={() => setShowForm(true)} style={{ fontSize: 13 }}>
            ➕ Nuovo link affiliazione
          </button>
        ) : (
          <div className="glass-card" style={{ padding: '16px 22px' }}>
            <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>➕ Aggiungi/Modifica link</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                <div style={{ flex: 1, minWidth: 200 }}>
                  <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Tariff ID</label>
                  <input className="input-field" value={form.tariff_id} onChange={e => setForm({...form, tariff_id: e.target.value})} placeholder="ff96f52a-..." />
                </div>
                <div style={{ flex: 1, minWidth: 120 }}>
                  <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>Network</label>
                  <input className="input-field" value={form.network} onChange={e => setForm({...form, network: e.target.value})} placeholder="tradedoubler, awin..." />
                </div>
              </div>
              <div>
                <label style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>URL Affiliazione</label>
                <input className="input-field" value={form.affiliate_url} onChange={e => setForm({...form, affiliate_url: e.target.value})} placeholder="https://tracking.com/redirect?offer=..." style={{ width: '100%' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button className="btn btn-electric" onClick={saveLink} disabled={saving || !form.tariff_id || !form.affiliate_url} style={{ fontSize: 13 }}>
                  {saving ? '⏳' : '💾 Salva'}
                </button>
                <button className="btn btn-outline" onClick={() => setShowForm(false)} style={{ fontSize: 13 }}>Annulla</button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Offerte + link */}
      <div className="glass-card" style={{ padding: '16px 22px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14, flexWrap: 'wrap', gap: 10 }}>
          <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9' }}>📋 Offerte e link</h3>
          <input
            className="input-field"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="🔍 Cerca fornitore o tariffa..."
            style={{ fontSize: 12, padding: '8px 12px', width: 250 }}
          />
        </div>

        {/* Lista link esistenti */}
        {links.length > 0 && (
          <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 11, color: '#f59e0b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 8 }}>
              💰 {links.length} link di affiliazione
            </div>
            {links.map(l => (
              <div key={l.tariff_id} style={{
                padding: '10px 12px', borderRadius: 8, marginBottom: 6,
                background: 'rgba(245,158,11,0.05)', border: '1px solid rgba(245,158,11,0.15)',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
              }}>
                <div style={{ flex: 1, minWidth: 200 }}>
                  <div style={{ fontSize: 12, fontWeight: 700, color: '#f1f5f9' }}>
                    {l.supplier} — {l.tariff_name}
                    {l.network && <span style={{ marginLeft: 6, fontSize: 9, color: '#64748b', background: 'rgba(255,255,255,0.06)', padding: '1px 6px', borderRadius: 4 }}>{l.network}</span>}
                  </div>
                  <a href={l.affiliate_url} target="_blank" rel="noreferrer" style={{ fontSize: 10, color: '#f59e0b', wordBreak: 'break-all' }}>
                    {l.affiliate_url.length > 80 ? l.affiliate_url.slice(0, 80) + '...' : l.affiliate_url}
                  </a>
                </div>
                <button
                  onClick={() => deleteLink(l.tariff_id)}
                  style={{
                    fontSize: 11, padding: '4px 10px', borderRadius: 6,
                    background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)',
                    color: '#f87171', cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                  }}
                >
                  Rimuovi
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Offerte disponibili */}
        <div style={{ fontSize: 11, color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 8 }}>
          📦 {offers.length > 0 ? `${offers.length} offerte caricate` : 'Nessuna offerta caricata — esegui ARERA sync'}
        </div>
        {filtered.map(o => {
          const linked = linkMap.get(o.id);
          return (
            <div key={o.id} style={{
              padding: '8px 12px', borderRadius: 8, marginBottom: 4, fontSize: 12,
              background: linked ? 'rgba(16,185,129,0.05)' : 'rgba(255,255,255,0.02)',
              border: `1px solid ${linked ? 'rgba(16,185,129,0.15)' : 'rgba(255,255,255,0.04)'}`,
              display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
            }}>
              <div style={{ flex: 1, minWidth: 180 }}>
                <span style={{ fontWeight: 600, color: '#f1f5f9' }}>{o.supplier_name}</span>
                <span style={{ color: '#94a3b8', marginLeft: 4 }}>— {o.name}</span>
                <span style={{
                  marginLeft: 6, fontSize: 9, fontWeight: 600, padding: '1px 6px', borderRadius: 4,
                  background: o.type === 'FISSO' ? 'rgba(59,130,246,0.12)' : 'rgba(168,85,247,0.12)',
                  color: o.type === 'FISSO' ? '#60a5fa' : '#a78bfa',
                }}>
                  {o.type === 'FISSO' ? 'FISSO' : 'VARIABILE'}
                </span>
                <span style={{ marginLeft: 4, fontSize: 9, color: '#64748b' }}>{o.commodity}</span>
                {linked && <span style={{ marginLeft: 6, fontSize: 9, color: '#6ee7b7', fontWeight: 600 }}>💰 AFFILIATO</span>}
              </div>
              <button
                onClick={() => linked ? deleteLink(o.id) : pickOffer(o)}
                style={{
                  fontSize: 11, padding: '4px 10px', borderRadius: 6, cursor: 'pointer', fontWeight: 600, flexShrink: 0,
                  background: linked ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)',
                  border: `1px solid ${linked ? 'rgba(239,68,68,0.2)' : 'rgba(245,158,11,0.2)'}`,
                  color: linked ? '#f87171' : '#f59e0b',
                }}
              >
                {linked ? 'Rimuovi' : '+ Affilia'}
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Sync ARERA Tab ────────────────────────────────────────────────

function SyncTab({ token }) {
  const [syncResult, setSyncResult] = useState(null);
  const [syncing, setSyncing] = useState(false);
  const [stats, setStats] = useState(null);

  const loadStats = () => {
    fetch('/api/admin/data-stats', { headers: { 'x-auth-token': token } })
      .then(r => r.json()).then(setStats).catch(() => {});
  };

  useEffect(() => { loadStats(); }, [token]);

  const triggerSync = async () => {
    setSyncing(true);
    setSyncResult(null);
    try {
      const r = await fetch('/api/admin/sync-arera', {
        method: 'POST',
        headers: { 'x-auth-token': token },
      });
      const d = await r.json();
      if (d.results && Array.isArray(d.results)) {
        setSyncResult(d);
      } else if (d.status) {
        setSyncResult(d);
      } else {
        setSyncResult({ status: 'completed', ...d });
      }
      loadStats();
    } catch (e) {
      setSyncResult({ status: 'error', message: 'Sync fallito: ' + (e.message || 'errore sconosciuto') });
    }
    setSyncing(false);
  };

  return (
    <div>
      <PunForm token={token} stats={stats} onUpdate={loadStats} />

      <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 20 }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 12 }}>🔄 Sincronizzazione offerte ARERA</h3>
        <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 16, lineHeight: 1.6 }}>
          Scarica le offerte ufficiali dal Portale Offerte ARERA (ilportaleofferte.it) e le salva in 
          <code style={{ background: 'rgba(255,255,255,0.04)', padding: '1px 4px', borderRadius: 3 }}>data/offerte/db-offerte-luce.json</code> e 
          <code style={{ background: 'rgba(255,255,255,0.04)', padding: '1px 4px', borderRadius: 3 }}>db-offerte-gas.json</code>.
          {' '}Circa 5.000+ offerte da tutti i fornitori italiani. Il processo può richiedere 30-60 secondi.
        </p>
        <button
          className="btn btn-electric"
          onClick={triggerSync}
          disabled={syncing}
          style={{ fontSize: 13 }}
        >
          {syncing ? '⏳ Sync in corso...' : '🔄 Avvia sincronizzazione'}
        </button>
        {syncing && (
          <div style={{ marginTop: 12, padding: '10px 14px', borderRadius: 8, background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.2)', fontSize: 12, color: '#fbbf24' }}>
            ⏳ Sync in corso... (30-60 secondi, scarica XML dal Portale Offerte ARERA)
          </div>
        )}
        {syncResult && syncResult.status === 'completed' && (
          <div style={{ marginTop: 16 }}>
            <div style={{
              padding: '14px 18px', borderRadius: 10,
              background: 'rgba(16,185,129,0.08)', border: '1px solid rgba(16,185,129,0.2)',
              fontSize: 13, color: '#6ee7b7', marginBottom: 12,
            }}>
              ✅ {syncResult.message}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 10 }}>
              {[
                { label: '⚡ LUCE', color: '#f59e0b', total: syncResult.luce?.total, sub: `${syncResult.luce?.privati || 0} privati · ${syncResult.luce?.aziende || 0} aziende` },
                { label: '🔥 GAS', color: '#3b82f6', total: syncResult.gas?.total, sub: `${syncResult.gas?.privati || 0} privati · ${syncResult.gas?.aziende || 0} aziende` },
                { label: '📦 TOTALE', color: '#10b981', total: syncResult.totale, sub: `${syncResult.elapsed}s` },
              ].map(s => (
                <div key={s.label} style={{
                  padding: 14, borderRadius: 10, textAlign: 'center',
                  background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)',
                }}>
                  <div style={{ fontSize: 24, fontWeight: 900, color: s.color }}>{s.total?.toLocaleString() || '...'}</div>
                  <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>{s.label}</div>
                  <div style={{ fontSize: 9, color: '#64748b', marginTop: 2 }}>{s.sub}</div>
                </div>
              ))}
            </div>
          </div>
        )}
        {syncResult && syncResult.status === 'error' && (
          <div style={{ marginTop: 12, padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', fontSize: 12, color: '#f87171' }}>
            ❌ {syncResult.message}
          </div>
        )}
      </div>

      {stats && (
        <div className="glass-card" style={{ padding: '16px 22px' }}>
          <h3 style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>📊 Stato sistema</h3>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            {[
              { label: 'Utenti', value: stats.users, color: '#60a5fa' },
              { label: 'API Keys', value: stats.api_keys, color: '#a78bfa' },
              { label: 'Rate Logs', value: stats.rate_logs, color: '#f59e0b' },
              { label: 'Affiliati', value: stats.affiliates, color: '#10b981' },
              { label: 'MySQL', value: stats.mysql === 'connected' ? '✅' : '❌', color: stats.mysql === 'connected' ? '#10b981' : '#ef4444' },
            ].map(s => (
              <div key={s.label} style={{
                flex: 1, minWidth: 80, padding: 12, borderRadius: 8, textAlign: 'center',
                background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)',
              }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: s.color }}>{s.value}</div>
                <div style={{ fontSize: 10, color: '#64748b', marginTop: 2 }}>{s.label}</div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// ── PUN/PSV Upload Form ──────────────────────────────────────────

function PunForm({ token, stats, onUpdate }) {
  const prices = stats?.prices;
  const [pun, setPun] = useState('');
  const [psv, setPsv] = useState('');
  const [punF1, setPunF1] = useState('');
  const [punF3, setPunF3] = useState('');
  const [saving, setSaving] = useState(false);
  const [result, setResult] = useState(null);

  useEffect(() => {
    if (prices) {
      setPun(prices.PUN?.toString() || '');
      setPsv(prices.PSV?.toString() || '');
      setPunF1(prices.PUN_F1?.toString() || '');
      setPunF3(prices.PUN_F3?.toString() || '');
    }
  }, [prices]);

  const savePrices = async () => {
    setSaving(true);
    setResult(null);
    try {
      const body = {};
      if (pun) body.pun = parseFloat(pun);
      if (psv) body.psv = parseFloat(psv);
      if (punF1) body.pun_f1 = parseFloat(punF1);
      if (punF3) body.pun_f3 = parseFloat(punF3);
      const r = await fetch('/api/admin/update-prices', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-auth-token': token },
        body: JSON.stringify(body),
      });
      const d = await r.json();
      setResult(d);
      onUpdate();
    } catch (e) {
      setResult({ status: 'error', message: 'Errore: ' + (e.message || 'sconosciuto') });
    }
    setSaving(false);
  };

  return (
    <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 20 }}>
      <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 12 }}>
        ⚡ Prezzi energia (PUN / PSV)
      </h3>
      <p style={{ fontSize: 12, color: '#94a3b8', marginBottom: 16, lineHeight: 1.6 }}>
        Aggiorna manualmente i prezzi di riferimento PUN (energia elettrica) e PSV (gas).
        {' '}I valori vengono usati per il calcolo del prezzo di riferimento nella barra sticky e nei confronti.
        {prices?.updated_at && (
          <span style={{ display: 'block', marginTop: 4, fontSize: 11, color: '#64748b' }}>
            Ultimo aggiornamento: {prices.updated_at}
          </span>
        )}
      </p>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12, marginBottom: 16 }}>
        <div>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
            PUN (€/kWh)
            {prices?.PUN_label && <span style={{ color: '#94a3b8', fontWeight: 400, textTransform: 'none' }}> — {prices.PUN_label}</span>}
          </label>
          <input className="input-field" type="number" step="0.0001" min="0" value={pun} onChange={e => setPun(e.target.value)} placeholder="0.1434" style={{ width: '100%' }} />
        </div>
        <div>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
            PUN F1 (€/kWh)
          </label>
          <input className="input-field" type="number" step="0.0001" min="0" value={punF1} onChange={e => setPunF1(e.target.value)} placeholder="0.1520" style={{ width: '100%' }} />
        </div>
        <div>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
            PUN F3 (€/kWh)
          </label>
          <input className="input-field" type="number" step="0.0001" min="0" value={punF3} onChange={e => setPunF3(e.target.value)} placeholder="0.1320" style={{ width: '100%' }} />
        </div>
        <div>
          <label style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase', fontWeight: 600, display: 'block', marginBottom: 4 }}>
            PSV (€/Smc)
          </label>
          <input className="input-field" type="number" step="0.001" min="0" value={psv} onChange={e => setPsv(e.target.value)} placeholder="0.56378" style={{ width: '100%' }} />
        </div>
      </div>

      <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
        <button className="btn btn-electric" onClick={savePrices} disabled={saving} style={{ fontSize: 13 }}>
          {saving ? '⏳ Salvataggio...' : '💾 Aggiorna prezzi'}
        </button>
        {result?.status === 'ok' && (
          <span style={{ fontSize: 12, color: '#6ee7b7', fontWeight: 600 }}>✅ {result.message}</span>
        )}
        {result?.status === 'error' && (
          <span style={{ fontSize: 12, color: '#f87171', fontWeight: 600 }}>❌ {result.message}</span>
        )}
      </div>
    </div>
  );
}
