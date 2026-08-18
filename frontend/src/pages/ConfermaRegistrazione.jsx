import React, { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

export default function ConfermaRegistrazione() {
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const token = searchParams.get('token');
    if (!token) {
      setStatus('error');
      setMessage('Token mancante. Usa il link ricevuto via email.');
      return;
    }
    fetch('/api/auth/confirm-email', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    })
      .then(r => r.json())
      .then(d => {
        if (d.status === 'verified' || d.status === 'already_verified') {
          setStatus('success');
          setMessage('Email confermata con successo! Ora puoi accedere.');
        } else {
          setStatus('error');
          setMessage(d.error || 'Errore durante la conferma');
        }
      })
      .catch(() => {
        setStatus('error');
        setMessage('Errore di connessione');
      });
  }, [searchParams]);

  return (
    <main style={{ padding: '100px 24px', textAlign: 'center' }}>
      <div className="glass-card animate-scale-in" style={{ maxWidth: 380, margin: '0 auto', padding: '36px 30px' }}>
        {status === 'loading' && (
          <>
            <div className="spinner" style={{
              width: 36, height: 36, margin: '0 auto 16px',
              border: '3px solid rgba(255,255,255,0.08)',
              borderTopColor: '#f59e0b', borderRadius: '50%',
            }} />
            <p style={{ color: '#94a3b8' }}>Conferma in corso...</p>
          </>
        )}
        {status === 'success' && (
          <>
            <div style={{ fontSize: 48, marginBottom: 12 }}>✅</div>
            <h2 style={{ fontSize: 20, fontWeight: 800, color: '#6ee7b7', marginBottom: 12 }}>Email confermata!</h2>
            <p style={{ color: '#94a3b8', fontSize: 14, marginBottom: 20, lineHeight: 1.5 }}>{message}</p>
            <Link to="/accedi" className="btn btn-electric" style={{ textDecoration: 'none', display: 'inline-block' }}>
              Vai al login
            </Link>
          </>
        )}
        {status === 'error' && (
          <>
            <div style={{ fontSize: 48, marginBottom: 12 }}>❌</div>
            <h2 style={{ fontSize: 20, fontWeight: 800, color: '#fca5a5', marginBottom: 12 }}>Errore</h2>
            <p style={{ color: '#94a3b8', fontSize: 14, marginBottom: 20, lineHeight: 1.5 }}>{message}</p>
            <Link to="/accedi" style={{ color: '#f59e0b', fontSize: 14 }}>Torna al login</Link>
          </>
        )}
      </div>
    </main>
  );
}
