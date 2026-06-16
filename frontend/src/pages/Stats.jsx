import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

export default function Stats() {
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [hours, setHours] = useState(24);
  const [loading, setLoading] = useState(true);
  const token = sessionStorage.getItem('switchai_token');

  // Redirect to login if not authenticated
  useEffect(() => {
    if (!token) { navigate('/login'); return; }
    fetch('/api/auth/verify', { headers: { 'x-auth-token': token } })
      .then(r => r.json())
      .then(d => { if (!d.valid) { sessionStorage.removeItem('switchai_token'); navigate('/login'); } })
      .catch(() => {});
  }, []); // eslint-disable-line

  useEffect(() => {
    if (!token) return;
    setLoading(true);
    fetch(`/api/stats/traffic?hours=${hours}`, {
      headers: { 'x-auth-token': token },
    })
      .then(r => r.json())
      .then(setData)
      .finally(() => setLoading(false));
  }, [hours, token]);

  if (!token) return null;

  return (
    <main style={{ padding: '50px 24px 80px' }}>
      <div style={{ maxWidth: 700, margin: '0 auto' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 32, flexWrap: 'wrap', gap: 12 }}>
          <h1 style={{ fontSize: 26, fontWeight: 900, color: '#f1f5f9' }}>📊 Traffico</h1>
          <select
            value={hours}
            onChange={e => setHours(Number(e.target.value))}
            className="input-field"
            style={{ width: 'auto', padding: '8px 16px', fontSize: 13 }}
          >
            <option value={1}>Ultima ora</option>
            <option value={6}>Ultime 6 ore</option>
            <option value={24}>Ultime 24 ore</option>
            <option value={168}>Ultimi 7 giorni</option>
            <option value={720}>Ultimi 30 giorni</option>
          </select>
        </div>

        {loading && (
          <div style={{ textAlign: 'center', padding: 40, color: '#64748b' }}>⏳ Caricamento...</div>
        )}

        {data && (
          <>
            {/* Totale */}
            <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 16, textAlign: 'center' }}>
              <div style={{ fontSize: 40, fontWeight: 900, color: '#f1f5f9' }}>{data.total}</div>
              <div style={{ fontSize: 13, color: '#64748b' }}>chiamate totali</div>
            </div>

            {/* Per tipo */}
            {Object.keys(data.by_type).length > 0 && (
              <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 16 }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>Per tipo</h3>
                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                  {[
                    { key: 'llm', label: '🤖 LLM', color: '#a78bfa' },
                    { key: 'human', label: '👤 Umani', color: '#34d399' },
                    { key: 'bot', label: '🔧 Bot', color: '#f59e0b' },
                    { key: 'unknown', label: '❓ Sconosciuti', color: '#64748b' },
                  ].map(t => (
                    <div key={t.key} style={{
                      flex: 1, minWidth: 110, padding: '14px', borderRadius: 10,
                      background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)',
                      textAlign: 'center',
                    }}>
                      <div style={{ fontSize: 24, fontWeight: 800, color: t.color }}>
                        {data.by_type[t.key] || 0}
                      </div>
                      <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>{t.label}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Per endpoint */}
            {Object.keys(data.by_endpoint).length > 0 && (
              <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 16 }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: '#f1f5f9', marginBottom: 14 }}>Per endpoint</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {Object.entries(data.by_endpoint)
                    .sort((a, b) => b[1] - a[1])
                    .map(([ep, count]) => (
                      <div key={ep} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                        <code style={{ color: '#94a3b8', fontSize: 12 }}>{ep}</code>
                        <span style={{ color: '#f1f5f9', fontWeight: 700 }}>{count}</span>
                      </div>
                    ))}
                </div>
              </div>
            )}

            {/* LLM visitors */}
            {Object.keys(data.llm_visitors).length > 0 && (
              <div className="glass-card best-offer" style={{ padding: '20px 24px', marginBottom: 16 }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: '#10b981', marginBottom: 14 }}>🤖 Visitatori LLM</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  {Object.entries(data.llm_visitors).map(([name, info]) => (
                    <div key={name} style={{
                      padding: '12px 14px', borderRadius: 8,
                      background: 'rgba(16,185,129,0.05)', border: '1px solid rgba(16,185,129,0.1)',
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                        <span style={{ fontSize: 13, fontWeight: 700, color: '#f1f5f9' }}>{name}</span>
                        <span style={{ fontSize: 20, fontWeight: 800, color: '#10b981' }}>{info.calls}</span>
                      </div>
                      <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
                        Prime: {info.first_seen} · Ultima: {info.last_seen}
                        {info.ips?.length > 0 && <span> · IP: {info.ips.join(', ')}</span>}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {data.total === 0 && (
              <div style={{ textAlign: 'center', padding: 40, color: '#64748b', fontSize: 14 }}>
                Nessuna chiamata registrata nel periodo selezionato.
              </div>
            )}
          </>
        )}
      </div>
    </main>
  );
}
