import React, { useState, useEffect } from 'react';

export default function CookieBanner() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const accepted = localStorage.getItem('cookie_consent');
    if (!accepted) setVisible(true);
  }, []);

  const accept = () => {
    localStorage.setItem('cookie_consent', 'true');
    setVisible(false);
  };

  if (!visible) return null;

  return (
    <div style={{
      position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 1000,
      background: 'rgba(17, 22, 32, 0.98)',
      borderTop: '1px solid rgba(255,255,255,0.08)',
      backdropFilter: 'blur(20px)',
      WebkitBackdropFilter: 'blur(20px)',
      padding: '16px 24px',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      gap: 20, flexWrap: 'wrap',
    }}>
      <p style={{ fontSize: 13, color: '#94a3b8', maxWidth: 640, lineHeight: 1.6, margin: 0 }}>
        SwitchAI utilizza solo cookie tecnici essenziali per il funzionamento del sito.
        Non utilizziamo cookie di profilazione, marketing o tracciamento.{' '}
        <a href="/privacy" style={{ color: '#60a5fa' }}>Privacy Policy</a>
        {' · '}
        <a href="/cookie" style={{ color: '#60a5fa' }}>Cookie Policy</a>
      </p>
      <button
        onClick={accept}
        style={{
          padding: '10px 28px', borderRadius: 8,
          background: 'linear-gradient(135deg, #f59e0b, #d97706)',
          color: '#fff', fontWeight: 700, fontSize: 13,
          border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
          boxShadow: '0 4px 16px rgba(245,158,11,0.3)',
        }}
      >
        Accetta
      </button>
    </div>
  );
}
