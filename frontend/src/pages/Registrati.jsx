import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';

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
const REQS = [
  { key: 'len', label: 'Almeno 8 caratteri', test: p => p.length >= 8 },
  { key: 'case', label: 'Maiuscole e minuscole', test: p => /[a-z]/.test(p) && /[A-Z]/.test(p) },
  { key: 'num', label: 'Almeno un numero', test: p => /\d/.test(p) },
  { key: 'sym', label: 'Almeno un simbolo (!@#$%^&*)', test: p => /[^a-zA-Z0-9]/.test(p) },
];

export default function Registrati() {
  const [form, setForm] = useState({ email: '', password: '', passwordConfirm: '', nome: '', cognome: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const strength = useMemo(() => calcStrength(form.password), [form.password]);
  const reqsMet = useMemo(() => REQS.map(r => ({ ...r, met: r.test(form.password) })), [form.password]);

  const doRegister = async (e) => {
    e.preventDefault();
    setLoading(true); setError(''); setSuccess('');
    if (form.password !== form.passwordConfirm) {
      setError('Le password non coincidono');
      setLoading(false);
      return;
    }
    if (strength < 2) {
      setError('La password è troppo debole. Scegline una più sicura.');
      setLoading(false);
      return;
    }
    try {
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: form.email, password: form.password, nome: form.nome, cognome: form.cognome }),
      });
      const data = await res.json();
      if (res.ok) {
        setSuccess(data.message || 'Registrazione completata! Controlla la tua email.');
      } else {
        setError(data.error || 'Errore durante la registrazione');
      }
    } catch {
      setError('Errore di connessione');
    } finally {
      setLoading(false);
    }
  };

  const update = (field, value) => setForm(f => ({ ...f, [field]: value }));

  const strengthBar = strength > 0 ? (
    <div style={{ height: 4, borderRadius: 2, background: '#1e293b', overflow: 'hidden', marginTop: 6 }}>
      <div style={{ height: '100%', width: `${(strength / 4) * 100}%`, borderRadius: 2, background: STRENGTH_COLORS[strength], transition: 'width 0.2s, background 0.2s' }} />
    </div>
  ) : null;

  return (
    <main style={{ padding: '100px 24px', textAlign: 'center' }}>
      <div className="glass-card animate-scale-in" style={{ maxWidth: 400, margin: '0 auto', padding: '36px 30px' }}>
        <div style={{ fontSize: 36, marginBottom: 12 }}>🚀</div>
        <h2 style={{ fontSize: 20, fontWeight: 800, color: '#f1f5f9', marginBottom: 4 }}>Registrati su SwitchAI</h2>
        <p style={{ fontSize: 13, color: '#64748b', marginBottom: 24 }}>
          Già registrato? <Link to="/accedi" style={{ color: '#f59e0b' }}>Accedi</Link>
        </p>

        {success ? (
          <div style={{ padding: '20px 16px', borderRadius: 12, background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.2)' }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>📧</div>
            <p style={{ color: '#6ee7b7', fontSize: 14, lineHeight: 1.5 }}>{success}</p>
          </div>
        ) : (
          <form onSubmit={doRegister} style={{ display: 'flex', flexDirection: 'column', gap: 12, textAlign: 'left' }}>
            <input className="input-field" type="email" placeholder="Email" required
              value={form.email} onChange={e => update('email', e.target.value)}
              autoComplete="email" />
            <div style={{ display: 'flex', gap: 8 }}>
              <input className="input-field" type="text" placeholder="Nome" required style={{ flex: 1 }}
                value={form.nome} onChange={e => update('nome', e.target.value)}
                autoComplete="given-name" />
              <input className="input-field" type="text" placeholder="Cognome" required style={{ flex: 1 }}
                value={form.cognome} onChange={e => update('cognome', e.target.value)}
                autoComplete="family-name" />
            </div>

            <input className="input-field" type="password" placeholder="Password" required
              value={form.password} onChange={e => update('password', e.target.value)}
              autoComplete="new-password" />
            {strengthBar}
            {form.password.length > 0 && (
              <div style={{ fontSize: 11, color: STRENGTH_COLORS[strength], fontWeight: 600, marginTop: -4 }}>
                {STRENGTH_LABELS[strength]}
              </div>
            )}

            <div style={{ fontSize: 11, color: '#94a3b8', display: 'flex', flexDirection: 'column', gap: 3, padding: '8px 10px', borderRadius: 6, background: 'rgba(0,0,0,0.15)' }}>
              {reqsMet.map(r => (
                <div key={r.key} style={{ color: r.met ? '#22c55e' : '#64748b' }}>
                  {r.met ? '✓' : '○'} {r.label}
                </div>
              ))}
            </div>

            <input className="input-field" type="password" placeholder="Conferma password" required
              value={form.passwordConfirm} onChange={e => update('passwordConfirm', e.target.value)}
              autoComplete="new-password" />
            {form.passwordConfirm.length > 0 && form.password !== form.passwordConfirm && (
              <div style={{ fontSize: 11, color: '#ef4444', marginTop: -4 }}>✗ Le password non coincidono</div>
            )}
            {form.passwordConfirm.length > 0 && form.password === form.passwordConfirm && (
              <div style={{ fontSize: 11, color: '#22c55e', marginTop: -4 }}>✓ Le password coincidono</div>
            )}

            {error && (
              <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(239,68,68,0.1)', color: '#fca5a5', fontSize: 13 }}>
                {error}
              </div>
            )}
            <button type="submit" disabled={loading} className="btn btn-electric" style={{ width: '100%' }}>
              {loading ? '⏳' : 'Crea account'}
            </button>
            <p style={{ fontSize: 11, color: '#475569', marginTop: 8 }}>
              Registrandoti accetti la{' '}
              <a href="/privacy" target="_blank" rel="noopener noreferrer" style={{ color: '#64748b', textDecoration: 'underline' }}>
                Privacy Policy
              </a>
            </p>
          </form>
        )}
      </div>
    </main>
  );
}
