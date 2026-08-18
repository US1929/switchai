import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

export default function Login() {
  const [email, setEmail] = useState('');
  const [pass, setPass] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const doLogin = async (e) => {
    e.preventDefault();
    setLoading(true); setError('');
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password: pass }),
      });
      const data = await res.json();
      if (data.token) {
        sessionStorage.setItem('switchai_token', data.token);
        if (data.is_admin) {
          navigate('/admin');
        } else {
          navigate('/dashboard');
        }
      } else {
        setError(data.error || 'Credenziali errate');
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
        <div style={{ fontSize: 36, marginBottom: 12 }}>🔐</div>
        <h2 style={{ fontSize: 20, fontWeight: 800, color: '#f1f5f9', marginBottom: 4 }}>Accedi a SwitchAI</h2>
        <p style={{ fontSize: 13, color: '#64748b', marginBottom: 24 }}>
          Non hai un account? <Link to="/registrati" style={{ color: '#f59e0b' }}>Registrati</Link>
        </p>

        <form onSubmit={doLogin} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <input
            className="input-field"
            type="email"
            placeholder="Email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            autoComplete="email"
            required
          />
          <input
            className="input-field"
            type="password"
            placeholder="Password"
            value={pass}
            onChange={e => setPass(e.target.value)}
            autoComplete="current-password"
            required
          />
          {error && (
            <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.1)', color: '#fca5a5', fontSize: 13 }}>
              {error}
            </div>
          )}
          <button type="submit" disabled={loading} className="btn btn-electric" style={{ width: '100%' }}>
            {loading ? '⏳' : 'Accedi'}
          </button>
        </form>

        <div style={{ fontSize: 12, color: '#475569', marginTop: 16, display: 'flex', justifyContent: 'center', gap: 16 }}>
          <Link to="/password-dimenticata" style={{ color: '#64748b' }}>Password dimenticata?</Link>
          <Link to="/registrati" style={{ color: '#64748b' }}>Richiedi conferma</Link>
        </div>
      </div>
    </main>
  );
}
