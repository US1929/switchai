import { useState, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

function calcStrength(pw) {
  let score = 0;
  if (pw.length >= 8) score += 1;
  if (pw.length >= 12) score += 1;
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1;
  if (/\d/.test(pw)) score += 1;
  if (/[^a-zA-Z0-9]/.test(pw)) score += 1;
  return Math.min(score, 4);
}

const STRENGTH_LABELS = ['', 'Debole', 'Media', 'Buona', 'Forte'];
const STRENGTH_COLORS = ['', '#ef4444', '#f59e0b', '#22c55e', '#10b981'];

export default function ResetPassword() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const strength = useMemo(() => calcStrength(password), [password]);

  const doReset = async (e) => {
    e.preventDefault();
    setLoading(true); setError('');
    if (password !== confirm) {
      setError('Le password non coincidono');
      setLoading(false);
      return;
    }
    if (strength < 2) {
      setError('La password è troppo debole');
      setLoading(false);
      return;
    }
    try {
      const res = await fetch('/api/auth/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password }),
      });
      const data = await res.json();
      if (res.ok) {
        setDone(true);
      } else {
        setError(data.error || 'Errore durante il reset');
      }
    } catch {
      setError('Errore di connessione');
    } finally {
      setLoading(false);
    }
  };

  if (!token) {
    return (
      <main style={{ padding: '100px 24px', textAlign: 'center' }}>
        <div className="glass-card animate-scale-in" style={{ maxWidth: 380, margin: '0 auto', padding: '36px 30px' }}>
          <div style={{ fontSize: 48, marginBottom: 12 }}>❌</div>
          <h2 style={{ fontSize: 20, fontWeight: 800, color: '#fca5a5', marginBottom: 12 }}>Link non valido</h2>
          <p style={{ color: '#94a3b8', fontSize: 14 }}>Usa il link ricevuto via email.</p>
          <p style={{ marginTop: 16 }}><Link to="/password-dimenticata" style={{ color: '#f59e0b', fontSize: 14 }}>Richiedi un nuovo link</Link></p>
        </div>
      </main>
    );
  }

  if (done) {
    return (
      <main style={{ padding: '100px 24px', textAlign: 'center' }}>
        <div className="glass-card animate-scale-in" style={{ maxWidth: 380, margin: '0 auto', padding: '36px 30px' }}>
          <div style={{ fontSize: 48, marginBottom: 12 }}>✅</div>
          <h2 style={{ fontSize: 20, fontWeight: 800, color: '#6ee7b7', marginBottom: 12 }}>Password reimpostata!</h2>
          <p style={{ color: '#94a3b8', fontSize: 14, marginBottom: 20 }}>Ora puoi accedere con la nuova password.</p>
          <Link to="/accedi" className="btn btn-electric" style={{ textDecoration: 'none', display: 'inline-block' }}>Vai al login</Link>
        </div>
      </main>
    );
  }

  const strengthBar = strength > 0 ? (
    <div style={{ height: 4, borderRadius: 2, background: '#1e293b', overflow: 'hidden', marginTop: 6 }}>
      <div style={{ height: '100%', width: `${(strength / 4) * 100}%`, borderRadius: 2, background: STRENGTH_COLORS[strength], transition: 'width 0.2s, background 0.2s' }} />
    </div>
  ) : null;

  return (
    <main style={{ padding: '100px 24px', textAlign: 'center' }}>
      <div className="glass-card animate-scale-in" style={{ maxWidth: 380, margin: '0 auto', padding: '36px 30px' }}>
        <div style={{ fontSize: 36, marginBottom: 12 }}>🔑</div>
        <h2 style={{ fontSize: 20, fontWeight: 800, color: '#f1f5f9', marginBottom: 4 }}>Nuova password</h2>
        <p style={{ fontSize: 13, color: '#64748b', marginBottom: 24 }}>Scegli una nuova password per il tuo account</p>

        <form onSubmit={doReset} style={{ display: 'flex', flexDirection: 'column', gap: 12, textAlign: 'left' }}>
          <input className="input-field" type="password" placeholder="Nuova password" required
            value={password} onChange={e => setPassword(e.target.value)}
            autoComplete="new-password" />
          {strengthBar}
          {password.length > 0 && (
            <div style={{ fontSize: 11, color: STRENGTH_COLORS[strength], fontWeight: 600, marginTop: -4 }}>
              {STRENGTH_LABELS[strength]}
            </div>
          )}

          <input className="input-field" type="password" placeholder="Conferma nuova password" required
            value={confirm} onChange={e => setConfirm(e.target.value)}
            autoComplete="new-password" />
          {confirm.length > 0 && password !== confirm && (
            <div style={{ fontSize: 11, color: '#ef4444', marginTop: -4 }}>Le password non coincidono</div>
          )}

          {error && (
            <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.1)', color: '#fca5a5', fontSize: 13 }}>
              {error}
            </div>
          )}
          <button type="submit" disabled={loading} className="btn btn-electric" style={{ width: '100%' }}>
            {loading ? '⏳' : 'Reimposta password'}
          </button>
        </form>
      </div>
    </main>
  );
}
