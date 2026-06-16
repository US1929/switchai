import React, { useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { api } from '../lib/api.js';

export default function Activate() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const tariffId = params.get('tariff') || '';
  const supplier = params.get('supplier') || '';
  const tariffName = params.get('name') || '';
  const annualCost = params.get('annualCost') || '';

  const [form, setForm] = useState({ user_name: '', user_email: '', user_phone: '', pod_pdr: '' });
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);
  const [err, setErr] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSending(true); setErr(null);
    try {
      await api.activateTariff({ ...form, tariff_id: tariffId, tariff_name: tariffName });
      setDone(true);
    } catch (ex) {
      setErr(ex.message);
    } finally {
      setSending(false);
    }
  };

  const isValid = form.user_name && form.user_email && form.user_phone && form.pod_pdr;

  const FIELDS = [
    { key: 'user_name', label: 'Nome completo', type: 'text', icon: '👤' },
    { key: 'user_email', label: 'Email', type: 'email', icon: '📧' },
    { key: 'user_phone', label: 'Telefono', type: 'tel', icon: '📱' },
    { key: 'pod_pdr', label: 'POD (Luce) o PDR (Gas)', type: 'text', icon: '🔢' },
  ];

  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 520, margin: '0 auto' }}>

        {done ? (
          /* ── Success state ────────────────────────────────────────── */
          <div className="glass-card animate-scale-in" style={{ padding: '48px 32px', textAlign: 'center' }}>
            <div style={{
              width: 72, height: 72, borderRadius: '50%',
              background: 'rgba(16,185,129,0.12)', margin: '0 auto 20px',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 36,
            }}>
              ✅
            </div>
            <h2 style={{ fontSize: 24, fontWeight: 800, marginBottom: 8 }}>Richiesta inviata!</h2>
            <p style={{ color: '#94a3b8', fontSize: 15, lineHeight: 1.7, marginBottom: 28 }}>
              Abbiamo ricevuto la tua richiesta di attivazione per{' '}
              <b style={{ color: '#f1f5f9' }}>{tariffName}</b> di{' '}
              <b style={{ color: '#f1f5f9' }}>{supplier}</b>.
              Ti contatteremo a breve per completare l'attivazione.
            </p>
            <button className="btn btn-electric" onClick={() => navigate('/')}>
              ← Torna alla home
            </button>
          </div>
        ) : (
          <>
            {/* ── Header ──────────────────────────────────────────────── */}
            <div style={{ marginBottom: 32 }}>
              <h1 style={{ fontSize: 28, fontWeight: 900, letterSpacing: '-0.5px', marginBottom: 8 }}>
                Attiva la tua offerta
              </h1>
              {supplier && (
                <div className="glass-card animate-fade-in" style={{
                  padding: '16px 20px', display: 'flex', alignItems: 'center', gap: 14,
                }}>
                  <div style={{
                    width: 42, height: 42, borderRadius: 10,
                    background: 'rgba(245,158,11,0.12)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 20, flexShrink: 0,
                  }}>
                    ⚡
                  </div>
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 15, color: '#f1f5f9' }}>{supplier}</div>
                    <div style={{ fontSize: 13, color: '#94a3b8' }}>{tariffName}</div>
                  </div>
                  {annualCost && (
                    <div style={{ marginLeft: 'auto', textAlign: 'right', flexShrink: 0 }}>
                      <div style={{ fontSize: 18, fontWeight: 800, color: '#10b981' }}>{annualCost}€</div>
                      <div style={{ fontSize: 11, color: '#64748b' }}>/anno</div>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* ── Form ───────────────────────────────────────────────── */}
            <form onSubmit={handleSubmit} className="glass-card" style={{ padding: '28px 26px' }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                {FIELDS.map(f => (
                  <div key={f.key}>
                    <label style={{
                      display: 'block', fontSize: 12, fontWeight: 700,
                      color: '#64748b', textTransform: 'uppercase',
                      letterSpacing: 0.8, marginBottom: 8,
                    }}>
                      {f.label}
                    </label>
                    <div style={{ position: 'relative' }}>
                      <span style={{
                        position: 'absolute', left: 14, top: '50%', translate: '0 -50%',
                        fontSize: 17, pointerEvents: 'none',
                      }}>
                        {f.icon}
                      </span>
                      <input
                        type={f.type}
                        required
                        className="input-field"
                        placeholder={f.label}
                        value={form[f.key]}
                        onChange={e => setForm({ ...form, [f.key]: e.target.value })}
                        style={{ paddingLeft: 42 }}
                      />
                    </div>
                  </div>
                ))}

                {err && (
                  <div style={{
                    padding: '12px 16px', borderRadius: 10,
                    background: 'rgba(239,68,68,0.1)',
                    border: '1px solid rgba(239,68,68,0.25)',
                    color: '#fca5a5', fontSize: 13,
                  }}>
                    ⚠️ {err}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={!isValid || sending}
                  className="btn btn-success btn-lg"
                  style={{ width: '100%', marginTop: 4 }}
                >
                  {sending ? '⏳ Invio in corso...' : '✅ Conferma attivazione'}
                </button>

                <p style={{ fontSize: 12, color: '#64748b', textAlign: 'center', lineHeight: 1.6 }}>
                  I tuoi dati sono al sicuro. Ti contatteremo solo per completare l'attivazione.
                  Nessun impegno vincolante.
                </p>
              </div>
            </form>

            <button
              onClick={() => navigate('/')}
              className="btn btn-outline"
              style={{ width: '100%', marginTop: 16 }}
            >
              ← Annulla e torna indietro
            </button>
          </>
        )}
      </div>
    </main>
  );
}
