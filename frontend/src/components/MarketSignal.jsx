import React, { useState, useEffect } from 'react';

export default function MarketSignal() {
  const [data, setData] = useState(null);

  useEffect(() => {
    fetch('/api/market-indices')
      .then(r => r.json())
      .then(d => {
        // Mostra solo se il backend ha dati reali e trend calcolato
        const trend = d.trend;
        if (trend && trend.moment) setData(d);
      })
      .catch(() => {});
  }, []);

  // Nessun dato reale → non mostrare nulla
  if (!data) return null;

  const trend = data.trend;
  const momentColors = {
    good:    { bg: 'rgba(16,185,129,0.08)', border: 'rgba(16,185,129,0.2)', text: '#6ee7b7', icon: '☀️' },
    neutral: { bg: 'rgba(148,163,184,0.06)', border: 'rgba(148,163,184,0.15)', text: '#94a3b8', icon: '☁️' },
    alert:   { bg: 'rgba(245,158,11,0.08)', border: 'rgba(245,158,11,0.2)', text: '#fbbf24', icon: '⛈️' },
  };
  const style = momentColors[trend.moment] || momentColors.neutral;

  return (
    <div style={{
      background: style.bg, border: `1px solid ${style.border}`,
      borderRadius: 14, padding: '14px 20px', marginBottom: 24,
      display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap',
    }}>
      <div style={{ fontSize: 28, flexShrink: 0 }}>{trend.icon || style.icon}</div>
      <div style={{ flex: 1, minWidth: 200 }}>
        <div style={{ fontSize: 13, fontWeight: 700, color: style.text, marginBottom: trend.message ? 4 : 0 }}>
          {trend.moment === 'good' ? 'Buon momento per cambiare' :
           trend.moment === 'alert' ? 'Attenzione: prezzi in salita' :
           'Mercato stabile'}
        </div>
        {trend.message && (
          <div style={{ fontSize: 11, color: '#94a3b8', lineHeight: 1.5 }}>
            {trend.message}
          </div>
        )}
      </div>
      <div style={{ display: 'flex', gap: 20, fontSize: 12, color: '#64748b', flexShrink: 0 }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontWeight: 700, color: '#f59e0b' }}>PUN</div>
          <div style={{ fontSize: 14, fontWeight: 800, color: '#f1f5f9' }}>{data.pun_display?.split('(')[0] || '—'}</div>
          {trend.week_change_pct != null && (
            <div style={{ color: trend.week_change_pct > 0 ? '#f87171' : '#34d399', fontSize: 10 }}>
              {trend.week_change_pct > 0 ? '↑' : '↓'} {Math.abs(trend.week_change_pct)}% 7gg
            </div>
          )}
        </div>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontWeight: 700, color: '#3b82f6' }}>PSV</div>
          <div style={{ fontSize: 14, fontWeight: 800, color: '#f1f5f9' }}>{data.psv_display?.split('(')[0] || '—'}</div>
        </div>
      </div>
    </div>
  );
}
