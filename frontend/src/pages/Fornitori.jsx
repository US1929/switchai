import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const GRANDI_FORNITORI = ['Enel Energia', 'Eni Plenitude', 'Edison', 'A2A Energia', 'Iren Luce Gas e Servizi', 'Hera Comm', 'Sorgenia', 'Engie', 'Fastweb Energia', 'Acea Energia', 'Octopus Energy', 'NeN Energia', 'Alperia', "E.ON Energia"];

function isGrande(name) {
  return GRANDI_FORNITORI.some(g => name.includes(g) || g.includes(name));
}

const COLORS = [
  '#0ea5e9','#8b5cf6','#f59e0b','#10b981','#ef4444','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16',
  '#06b6d4','#d946ef','#22c55e','#eab308','#a855f7','#3b82f6','#fb923c','#2dd4bf','#a3e635','#f472b6',
];

function initialColor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = ((h << 5) - h) + name.charCodeAt(i);
  return COLORS[Math.abs(h) % COLORS.length];
}

function initialLetter(name) {
  const clean = name.replace(/[^a-zA-Z0-9]/g, '');
  return clean.charAt(0).toUpperCase() || '?';
}

function RatingStars({ rating }) {
  if (!rating) return null;
  const full = Math.floor(rating);
  const half = rating - full >= 0.5;
  const stars = '★'.repeat(full) + (half ? '½' : '');
  return (
    <span style={{ color: '#f59e0b', fontSize: '0.7rem', letterSpacing: 1 }}>
      {stars}
    </span>
  );
}

export default function Fornitori() {
  const [suppliers, setSuppliers] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/fornitori')
      .then(r => r.json())
      .then(data => { setSuppliers(data); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  const filtered = suppliers.filter(s =>
    s.name.toLowerCase().includes(search.toLowerCase()) ||
    (s.brand || '').toLowerCase().includes(search.toLowerCase())
  );

  const grandi = filtered.filter(s => isGrande(s.name));
  const altri = filtered.filter(s => !isGrande(s.name));

  const renderCard = (s) => {
    const color = initialColor(s.name);
    const letter = initialLetter(s.name);
    const slug = s.slug || s.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    return (
      <Link
        key={s.id || s.name}
        to={`/fornitori/${slug}`}
        style={{
          display: 'flex', flexDirection: 'column', gap: 6, padding: '16px',
          background: '#1e293b', borderRadius: 10, textDecoration: 'none',
          border: '1px solid #334155', transition: 'all 0.15s',
        }}
        onMouseEnter={e => e.target.style.borderColor = '#f59e0b'}
        onMouseLeave={e => e.target.style.borderColor = '#334155'}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{
            width: 36, height: 36, borderRadius: 8, background: color,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 700, fontSize: '0.85rem', color: '#fff', flexShrink: 0, overflow: 'hidden',
          }}>
            {s.logo_url ? (
              <img src={s.logo_url} alt="" style={{ width: 26, height: 26, objectFit: 'contain' }}
                onError={e => { e.target.style.display = 'none'; e.target.parentNode.textContent = letter; }}
              />
            ) : letter}
          </div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <span style={{ color: '#f1f5f9', fontSize: '0.85rem', fontWeight: 600, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {s.name}
            </span>
            {s.rating && <RatingStars rating={s.rating} />}
          </div>
        </div>
        {s.tipo && (
          <span style={{
            fontSize: '0.65rem', color: '#64748b', alignSelf: 'flex-start',
            background: 'rgba(255,255,255,0.04)', padding: '1px 8px', borderRadius: 4,
          }}>
            {s.tipo}
          </span>
        )}
      </Link>
    );
  };

  return (
    <main style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%)', padding: '40px 20px 80px' }}>
      <div style={{ maxWidth: 1000, margin: '0 auto' }}>
        <h1 style={{ fontSize: '2rem', fontWeight: 800, color: '#fff', marginBottom: 8 }}>
          Fornitori ⚡🔥
        </h1>
        <p style={{ color: '#94a3b8', marginBottom: 24, fontSize: '0.95rem', lineHeight: 1.6 }}>
          {loading ? 'Caricamento...' : `${suppliers.length} fornitori di energia nel mercato libero italiano. Dati ufficiali ARERA Portale Offerte.`}
        </p>

        <input
          type="text"
          placeholder="Cerca fornitore..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{
            width: '100%', maxWidth: 400, padding: '10px 16px', borderRadius: 8, border: '1px solid #334155',
            background: '#1e293b', color: '#f1f5f9', fontSize: '0.95rem', outline: 'none', marginBottom: 28,
          }}
        />

        {loading ? (
          <p style={{ color: '#64748b' }}>Caricamento fornitori...</p>
        ) : (
          <div>
            {grandi.length > 0 && (
              <>
                <h2 style={{ color: '#f59e0b', fontSize: '1rem', fontWeight: 700, marginBottom: 12 }}>
                  ⭐ Grandi fornitori ({grandi.length})
                </h2>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 12, marginBottom: 32 }}>
                  {grandi.map(renderCard)}
                </div>
              </>
            )}
            {altri.length > 0 && (
              <>
                <h2 style={{ color: '#94a3b8', fontSize: '1rem', fontWeight: 700, marginBottom: 12 }}>
                  Altri fornitori ({altri.length})
                </h2>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 12 }}>
                  {altri.map(renderCard)}
                </div>
              </>
            )}
            {filtered.length === 0 && (
              <p style={{ color: '#64748b' }}>Nessun fornitore trovato.</p>
            )}
          </div>
        )}
      </div>
    </main>
  );
}
