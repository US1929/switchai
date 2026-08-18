import { useState } from 'react';
import { Link } from 'react-router-dom';

export default function PasswordDimenticata() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const doReset = async (e) => {
    e.preventDefault();
    setLoading(true); setError('');
    try {
      const res = await fetch('/api/auth/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const data = await res.json();
      if (res.ok || data.status === 'sent') {
        setSent(true);
      } else {
        setError(data.error || 'Errore');
      }
    } catch {
      setError('Errore di connessione');
    } finally {
      setLoading(false);
    }
  };

  return (
    <main style={{ padding: '100px 24px', textAlign: 'center' }}>
      <div className="glass-card animate-scale-in" style={{ maxWidth: 380, margin: '0 auto', padding: '36px 30px' }}>
        <div style={{ fontSize: 36, marginBottom: 12 }}>🔑</div>
        <h2 style={{ fontSize: 20, fontWeight: 800, color: '#f1f5f9', marginBottom: 4 }}>Password dimenticata</h2>
        <p style={{ fontSize: 13, color: '#64748b', marginBottom: 24 }}>
          <Link to="/accedi" style={{ color: '#f59e0b' }}>Torna al login</Link>
        </p>

        {sent ? (
          <div style={{ padding: '20px 16px', borderRadius: 12, background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.2)' }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>📧</div>
            <p style={{ color: '#6ee7b7', fontSize: 14, lineHeight: 1.5 }}>
              Se l'email è registrata, riceverai le istruzioni per reimpostare la password. Controlla la tua posta (anche spam).
            </p>
          </div>
        ) : (
          <form onSubmit={doReset} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <p style={{ fontSize: 13, color: '#94a3b8', textAlign: 'left', margin: 0 }}>
              Inserisci l'email associata al tuo account. Ti invieremo un link per reimpostare la password.
            </p>
            <input className="input-field" type="email" placeholder="La tua email" required
              value={email} onChange={e => setEmail(e.target.value)}
              autoComplete="email" />
            {error && (
              <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.1)', color: '#fca5a5', fontSize: 13 }}>
                {error}
              </div>
            )}
            <button type="submit" disabled={loading} className="btn btn-electric" style={{ width: '100%' }}>
              {loading ? '⏳' : 'Invia link di reset'}
            </button>
          </form>
        )}
      </div>
    </main>
  );
}
