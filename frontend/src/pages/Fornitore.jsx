import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';

const COLORS = ['#0ea5e9','#8b5cf6','#f59e0b','#10b981','#ef4444','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];

function initialColor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = ((h << 5) - h) + name.charCodeAt(i);
  return COLORS[Math.abs(h) % COLORS.length];
}

function initialLetter(name) {
  const clean = name.replace(/[^a-zA-Z0-9]/g, '');
  return clean.charAt(0).toUpperCase() || '?';
}

function formatEuro(v) {
  return (v || 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function RatingStars({ rating, count }) {
  if (!rating) return null;
  const full = Math.floor(rating);
  const half = rating - full >= 0.5;
  const stars = '★'.repeat(full) + (half ? '½' : '');
  return (
    <span style={{ color: '#f59e0b', fontSize: '0.9rem', letterSpacing: 2 }}>
      {stars} <span style={{ color: '#94a3b8', fontSize: '0.75rem', fontWeight: 600 }}>{rating.toFixed(1)}</span>
      {count ? <span style={{ color: '#64748b', fontSize: '0.7rem', fontWeight: 400 }}> ({count.toLocaleString('it-IT')})</span> : null}
    </span>
  );
}

export default function Fornitore() {
  const { slug } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch(`/api/fornitori/${slug}`)
      .then(r => r.ok ? r.json() : Promise.reject('Non trovato'))
      .then(d => { setData(d); setLoading(false); })
      .catch(e => { setError(e); setLoading(false); });
  }, [slug]);

  if (loading) return (
    <main style={{ minHeight: '100vh', background: '#0f172a', padding: '60px 20px', color: '#94a3b8' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>Caricamento...</div>
    </main>
  );

  if (error || !data) return (
    <main style={{ minHeight: '100vh', background: '#0f172a', padding: '60px 20px', color: '#94a3b8' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>
        <h1 style={{ color: '#fff' }}>Fornitore non trovato</h1>
        <p><Link to="/fornitori" style={{ color: '#f59e0b' }}>← Tutti i fornitori</Link></p>
      </div>
    </main>
  );

  const color = initialColor(data.name);
  const letter = initialLetter(data.name);

  return (
    <main style={{ minHeight: '100vh', background: 'linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%)', padding: '40px 20px 80px' }}>
      <div style={{ maxWidth: 800, margin: '0 auto' }}>
        <Link to="/fornitori" style={{ color: '#f59e0b', fontSize: '0.85rem', textDecoration: 'none', display: 'inline-block', marginBottom: 20 }}>
          ← Tutti i fornitori
        </Link>

        {/* Header */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 20 }}>
          <div style={{
            width: 56, height: 56, borderRadius: 12, background: color, overflow: 'hidden',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 700, fontSize: '1.3rem', color: '#fff',
          }}>
            {data.logo_url ? (
              <img src={data.logo_url} alt="" style={{ width: 40, height: 40, objectFit: 'contain' }}
                onError={e => { e.target.style.display = 'none'; e.target.parentNode.textContent = letter; }}
              />
            ) : letter}
          </div>
          <div style={{ flex: 1 }}>
            <h1 style={{ fontSize: '1.6rem', fontWeight: 800, color: '#fff', margin: 0 }}>{data.name}</h1>
            <RatingStars rating={data.rating} count={data.recensioni} />
          </div>
        </div>

        {/* Stats row */}
        <div style={{ display: 'flex', gap: 12, marginBottom: 24, flexWrap: 'wrap' }}>
          <StatBox num={data.totali} lbl="Offerte totali" />
          <StatBox num={data.luce} lbl="Offerte Luce" />
          <StatBox num={data.gas} lbl="Offerte Gas" />
          <StatBox num={data.fisse} lbl="Prezzo Fisso" />
          <StatBox num={data.variabili} lbl="Prezzo Variabile" />
        </div>

        {/* Info card */}
        {(data.descrizione || data.fondazione || data.sito_web || data.trustpilot_url) && (
          <div className="glass-card" style={{ padding: '20px 24px', marginBottom: 24 }}>
            {data.descrizione && (
              <p style={{ color: '#cbd5e1', fontSize: '0.9rem', lineHeight: 1.7, margin: '0 0 14px 0' }}>
                {data.descrizione}
              </p>
            )}
            <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', fontSize: '0.8rem' }}>
              {data.fondazione && (
                <span style={{ color: '#64748b' }}>Fondazione: <strong style={{ color: '#94a3b8' }}>{data.fondazione}</strong></span>
              )}
              {data.tipo && (
                <span style={{ color: '#64748b' }}>Tipo: <strong style={{ color: '#94a3b8' }}>{data.tipo}</strong></span>
              )}
              {data.rating && (
                <span style={{ color: '#64748b' }}>Trustpilot: <strong style={{ color: '#f59e0b' }}>{data.rating}/5</strong>
                  {data.recensioni ? <span style={{ color: '#64748b' }}> ({data.recensioni.toLocaleString('it-IT')} recensioni)</span> : null}
                </span>
              )}
            </div>
            {(data.sito_web || data.trustpilot_url) && (
              <div style={{ marginTop: 14, display: 'flex', gap: 14, flexWrap: 'wrap' }}>
                {data.sito_web && (
                  <a href={data.sito_web} target="_blank" rel="noreferrer"
                    style={{ color: '#60a5fa', fontSize: '0.8rem', textDecoration: 'none' }}>
                    🌐 {data.sito_web.replace(/^https?:\/\//, '').replace(/\/$/, '')}
                  </a>
                )}
                {data.trustpilot_url && (
                  <a href={data.trustpilot_url} target="_blank" rel="noreferrer"
                    style={{ color: '#f59e0b', fontSize: '0.8rem', textDecoration: 'none' }}>
                    ⭐ Recensioni Trustpilot
                  </a>
                )}
              </div>
            )}
          </div>
        )}

        {/* Tabella offerte */}
        <div style={{
          background: '#1e293b', borderRadius: 12, border: '1px solid #334155',
          overflow: 'hidden', marginBottom: 24,
        }}>
          <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 80px', gap: 0,
            padding: '12px 16px', background: '#0f172a', color: '#64748b', fontWeight: 600,
            fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em'
          }}>
            <span>Offerta</span><span>Prezzo</span><span>Quota fissa</span><span>Tipo</span>
          </div>
          {data.offerte.slice(0, 50).map((o, i) => {
            const isLuce = o.commodity === 'LUCE';
            const unit = isLuce ? 'kWh' : 'Smc';
            const prezzo = isLuce ? (o.price_mono_kwh ?? null) : (o.price_smc ?? null);
            return (
              <div key={o.id || i} style={{
                display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 80px', gap: 0,
                padding: '10px 16px', borderTop: i > 0 ? '1px solid #334155' : 'none',
                color: '#e2e8f0', fontSize: '0.85rem', alignItems: 'center',
              }}>
                <span style={{ color: '#f1f5f9', fontWeight: 500 }}>{o.name}</span>
                <span>{prezzo !== null ? formatEuro(prezzo) + ' €/' + unit : '—'}</span>
                <span style={{ color: '#94a3b8' }}>{o.fixed_fee_monthly !== null ? formatEuro(o.fixed_fee_monthly) + ' €/mese' : '—'}</span>
                <span style={{ color: o.type === 'FISSO' ? '#10b981' : '#f59e0b', fontWeight: 600, fontSize: '0.75rem' }}>
                  {o.type === 'FISSO' ? 'FISSO' : 'VAR.'}
                </span>
              </div>
            );
          })}
          {data.offerte.length > 50 && (
            <div style={{ padding: '12px 16px', color: '#64748b', fontSize: '0.85rem', textAlign: 'center', borderTop: '1px solid #334155' }}>
              +{data.offerte.length - 50} offerte non mostrate
            </div>
          )}
        </div>
      </div>
    </main>
  );
}

function StatBox({ num, lbl }) {
  return (
    <div style={{
      background: '#1e293b', border: '1px solid #334155', borderRadius: 10,
      padding: '10px 18px', textAlign: 'center', minWidth: 80,
    }}>
      <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#f59e0b' }}>{num}</div>
      <div style={{ fontSize: '0.7rem', color: '#64748b', textTransform: 'uppercase', marginTop: 2 }}>{lbl}</div>
    </div>
  );
}
