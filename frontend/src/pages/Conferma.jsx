import React, { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';

export default function Conferma() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const token = params.get('token') || '';
  const [status, setStatus] = useState('loading'); // loading | confirmed | already | error
  const [data, setData] = useState(null);

  useEffect(() => {
    if (!token) { setStatus('error'); return; }

    fetch(`/api/subscription/conferma?token=${encodeURIComponent(token)}`)
      .then(r => r.json())
      .then(d => {
        if (d.status === 'confirmed') { setStatus('confirmed'); setData(d); }
        else if (d.status === 'already_confirmed') { setStatus('already'); setData(d); }
        else { setStatus('error'); setData(d); }
      })
      .catch(() => setStatus('error'));
  }, [token]);

  return (
    <main style={{ padding: '80px 24px', textAlign: 'center' }}>
      <div className="glass-card animate-scale-in" style={{ maxWidth: 520, margin: '0 auto', padding: '40px 32px' }}>

        {status === 'loading' && (
          <>
            <div style={{ fontSize: 48, marginBottom: 16 }}>⏳</div>
            <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>Verifica in corso...</h2>
            <p style={{ color: '#94a3b8', fontSize: 14 }}>Stiamo verificando il tuo token di conferma.</p>
          </>
        )}

        {status === 'confirmed' && (
          <>
            <div style={{
              width: 72, height: 72, borderRadius: '50%',
              background: 'rgba(16,185,129,0.12)', margin: '0 auto 20px',
              display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 36,
            }}>
              ✅
            </div>
            <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8, color: '#10b981' }}>
              Consenso registrato!
            </h2>
            <p style={{ color: '#94a3b8', fontSize: 14, lineHeight: 1.7, marginBottom: 8 }}>
              La tua richiesta di attivazione è stata confermata.
              {data?.ws_sent === 'success'
                ? ' I tuoi dati sono stati inoltrati al fornitore.'
                : ' Ti contatteremo a breve per completare l\'attivazione.'}
            </p>
            <p style={{ color: '#64748b', fontSize: 12 }}>
              ID: {data?.subscription_id || token.substring(0, 12) + '...'}
            </p>
            <button className="btn btn-electric" onClick={() => navigate('/')} style={{ marginTop: 24 }}>
              ← Torna alla home
            </button>
          </>
        )}

        {status === 'already' && (
          <>
            <div style={{ fontSize: 48, marginBottom: 16 }}>ℹ️</div>
            <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>Già confermata</h2>
            <p style={{ color: '#94a3b8', fontSize: 14 }}>Questa sottoscrizione è già stata confermata in precedenza.</p>
            <button className="btn btn-electric" onClick={() => navigate('/')} style={{ marginTop: 24 }}>
              ← Torna alla home
            </button>
          </>
        )}

        {status === 'error' && (
          <>
            <div style={{ fontSize: 48, marginBottom: 16 }}>❌</div>
            <h2 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>Token non valido</h2>
            <p style={{ color: '#94a3b8', fontSize: 14, lineHeight: 1.7 }}>
              Il link di conferma non è valido o è scaduto. Se hai richiesto l'attivazione da più di 48 ore, potrebbe essere necessario rifare la richiesta.
            </p>
            <button className="btn btn-electric" onClick={() => navigate('/')} style={{ marginTop: 24 }}>
              ← Torna alla home
            </button>
          </>
        )}
      </div>
    </main>
  );
}
