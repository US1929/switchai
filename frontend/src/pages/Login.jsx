import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

export default function Login() {
  const [user, setUser] = useState('');
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
        body: JSON.stringify({ username: user, password: pass }),
      });
      const data = await res.json();
      if (data.token) {
        sessionStorage.setItem('switchai_token', data.token);
        navigate('/admin');
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
        <h2 style={{ fontSize: 20, fontWeight: 800, color: '#f1f5f9', marginBottom: 24 }}>Accesso Stats</h2>

        <form onSubmit={doLogin} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <input
            className="input-field"
            type="text"
            placeholder="Username"
            value={user}
            onChange={e => setUser(e.target.value)}
            autoComplete="username"
          />
          <input
            className="input-field"
            type="password"
            placeholder="Password"
            value={pass}
            onChange={e => setPass(e.target.value)}
            autoComplete="current-password"
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
      </div>
    </main>
  );
}
