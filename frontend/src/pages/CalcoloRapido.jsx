import { useState, useEffect, useRef, useMemo } from 'react';
import { LUCE } from '../lib/constants.js';

const styles = {
  page: {
    minHeight: '100vh',
    background: 'var(--bg-base)',
    padding: '40px 20px 80px',
  },
  container: { maxWidth: 720, margin: '0 auto' },
  card: {
    background: 'var(--bg-card)',
    borderRadius: 'var(--radius-xl)',
    padding: '32px 28px',
    border: '1px solid var(--border)',
    boxShadow: 'var(--shadow-md)',
  },
  title: { fontSize: '1.6rem', fontWeight: 800, color: '#f1f5f9', marginBottom: 6 },
  subtitle: { fontSize: '0.85rem', color: '#94a3b8', marginBottom: 28, lineHeight: 1.6 },
  label: { fontSize: '0.8rem', color: '#94a3b8', fontWeight: 600, marginBottom: 6, display: 'block' },
  stepIndicator: {
    display: 'flex', gap: 8, marginBottom: 28, justifyContent: 'center',
  },
  stepDot: (active) => ({
    width: 10, height: 10, borderRadius: '50%',
    background: active ? '#f59e0b' : 'rgba(255,255,255,0.08)',
    transition: 'all 0.3s',
  }),
};

// ── SVG Icons ────────────────────────────────────────────────
const IconBolt = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
  </svg>
);

const IconFlame = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
  </svg>
);

const IconHome = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
    <polyline points="9 22 9 12 15 12 15 22" />
  </svg>
);

const IconBuilding = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="4" y="2" width="16" height="20" rx="2" />
    <line x1="9" y1="6" x2="9" y2="6.01" /><line x1="15" y1="6" x2="15" y2="6.01" />
    <line x1="9" y1="10" x2="9" y2="10.01" /><line x1="15" y1="10" x2="15" y2="10.01" />
    <line x1="9" y1="14" x2="9" y2="14.01" /><line x1="15" y1="14" x2="15" y2="14.01" />
    <path d="M9 18h6v4H9z" />
  </svg>
);

const IconCheck = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

const IconArrowDownRight = (props) => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={props?.stroke || '#10b981'} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <line x1="17" y1="7" x2="7" y2="17" />
    <polyline points="17 17 7 17 7 7" />
  </svg>
);

const IconArrowUpRight = (props) => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={props?.stroke || '#ef4444'} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <line x1="7" y1="17" x2="17" y2="7" />
    <polyline points="7 7 17 7 17 17" />
  </svg>
);

const IconEye = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="12" cy="12" r="3" /><path d="M3 12c2-4 6-7 9-7s7 3 9 7c-2 4-6 7-9 7s-7-3-9-7Z" />
  </svg>
);

const IconRefresh = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
    <path d="M21 12a9 9 0 1 1-2.6-6.4" /><polyline points="21 3 21 9 15 9" />
  </svg>
);

const IconClock = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="12" cy="12" r="9" /><polyline points="12 7 12 12 15.5 14" />
  </svg>
);

// ── Animated Counter ──────────────────────────────────────────
function AnimatedCounter({ target, duration = 1200, prefix = '', suffix = '', decimals = 2 }) {
  const [val, setVal] = useState(0);
  const ref = useRef(null);

  useEffect(() => {
    let start = null;
    const step = (ts) => {
      if (!start) start = ts;
      const progress = Math.min((ts - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      setVal(eased * target);
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }, [target, duration]);

  return <span ref={ref}>{prefix}{val.toFixed(decimals).replace('.', ',')}{suffix}</span>;
}

// ── Savings Ring (SVG) ───────────────────────────────────────
function SavingsRing({ pct, size = 84 }) {
  const r = size / 2 - 6;
  const circumference = 2 * Math.PI * r;
  const fill = Math.min(Math.max(0, Math.abs(pct) / 35), 1) * circumference;
  const color = pct >= 10 ? '#10b981' : pct >= 5 ? '#f59e0b' : '#ef4444';

  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
      <circle cx={size/2} cy={size/2} r={r} fill="none" stroke="rgba(255,255,255,0.05)" strokeWidth="5" />
      <circle cx={size/2} cy={size/2} r={r} fill="none" stroke={color} strokeWidth="5"
        strokeLinecap="round"
        strokeDasharray={circumference}
        strokeDashoffset={circumference - fill}
        transform={`rotate(-90 ${size/2} ${size/2})`}
        style={{ transition: 'stroke-dashoffset 1s ease-out' }}
      />
      <text x={size/2} y={size/2} textAnchor="middle" dominantBaseline="central"
        fill={color} fontWeight="800" fontSize={size * 0.2}>
        {pct > 0 ? `-${Math.round(pct)}%` : '0%'}
      </text>
    </svg>
  );
}

function formatDateIt() {
  return new Date().toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
}

// ── MAIN COMPONENT ───────────────────────────────────────────
export default function CalcoloRapido() {
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [results, setResults] = useState(null);

  const [form, setForm] = useState({
    utility: 'luce',
    customer_type: 'residenziale',
    zona: 'NORD',
    consumption: '',
    power_kw: '3.0',
    bill_amount: '',
    frequency: '6',
    include_canone_rai: true,
  });

  const update = (k, v) => setForm(f => ({ ...f, [k]: v }));

  const isValidStep0 = form.utility && form.customer_type && form.zona;
  const isValidStep1 = parseFloat(form.consumption) > 0 && (form.utility === 'gas' || parseFloat(form.power_kw) > 0);
  const isValidStep2 = parseFloat(form.bill_amount) > 0 && parseInt(form.frequency) > 0;

  const handleSubmit = async () => {
    setLoading(true);
    const consumption = parseFloat(form.consumption);
    const billAmount = parseFloat(form.bill_amount);
    const freq = parseInt(form.frequency);
    const rawAnnualSpend = billAmount * freq;
    const canoneRai = form.utility === 'luce' && form.customer_type === 'residenziale' && form.include_canone_rai
      ? LUCE.CANONE_RAI_ANNUO : 0;
    const currentAnnualSpend = rawAnnualSpend - canoneRai;

    const payload = {
      commodity: form.utility.toUpperCase(),
      tipo_cliente: form.customer_type,
      zona: form.zona,
      zone: form.zona,
      yearly_consumption_kwh: form.utility === 'luce' ? consumption : 0,
      yearly_consumption_smc: form.utility === 'gas' ? consumption : 0,
      current_annual_spend: currentAnnualSpend,
      potenza_impegnata: form.utility === 'luce' ? parseFloat(form.power_kw) : 3.0,
      source: 'WEB',
    };

    try {
      const res = await fetch('/api/calculate-savings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.results?.length > 0) {
        const best = data.results[0];
        setResults({
          provider_name: best.supplier,
          tariff_name: best.tariff_name,
          annual_cost_eur: best.annual_cost_eur,
          monthly_cost_eur: best.monthly_cost_eur || Math.round(best.annual_cost_eur / 12),
          savings_eur: best.savings_eur,
          savings_pct: best.savings_pct,
          current_annual_spend: currentAnnualSpend,
          current_monthly_spend: Math.round(currentAnnualSpend / 12),
          raw_annual_spend: rawAnnualSpend,
          canone_rai_subtracted: canoneRai,
          type: best.type,
          price_per_unit: best.price_per_unit,
          price_per_unit: best.price_per_unit,
          unit: best.unit,
          fixed_fee_monthly: best.fixed_fee_monthly,
          subscription_url: best.subscription_url,
          affiliate_url: best.affiliate_url,
          url_offerta: best.url_offerta,
          supplier_logo: best.supplier_logo || null,
          contract_detail: best.contract_detail || '',
          tariff_id: best.tariff_id || best.id,
          breakdown: best.breakdown,
          offers_analyzed: data.total_count || data.results.length,
          system_total_offers: data.system_total_offers || data.total_count || 0,
          filters_applied: data.filters_applied || {},
          offers_before_filter: data.offers_before_filter || data.system_total_offers || 0,
          offers_after_filter: data.offers_after_filter || 0,
        });
      } else {
        setResults({ error: 'Nessuna offerta trovata per questi parametri.' });
      }
    } catch (e) {
      if (e.name === 'TypeError' && e.message.includes('fetch')) {
        setResults({ error: 'Errore di rete. Verifica la connessione internet e riprova.' });
      } else if (e.name === 'AbortError' || e.message?.includes('timeout')) {
        setResults({ error: 'Il server sta impiegando troppo tempo. Riprova tra qualche minuto.' });
      } else {
        setResults({ error: 'Dati ARERA non disponibili al momento. Il sync notturno potrebbe essere in corso. Riprova più tardi.' });
      }
    } finally {
      setLoading(false);
    }
  };

  if (results) return <ResultsView results={results} form={form} onReset={() => { setResults(null); setStep(0); }} />;

  return (
    <>
      {loading && (
        <div style={{
          position: 'fixed', inset: 0, zIndex: 999,
          background: 'rgba(6,9,19,0.85)', backdropFilter: 'blur(8px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{
              width: 40, height: 40, margin: '0 auto 18px',
              border: '3px solid rgba(255,255,255,0.08)',
              borderTopColor: '#f59e0b', borderRadius: '50%',
            }}
            className="spinner" />
            <p style={{ color: '#94a3b8', fontSize: '0.9rem' }}>Confrontando le migliori offerte...</p>
            <p style={{ color: '#64748b', fontSize: '0.75rem', marginTop: 6 }}>Analisi ARERA delle tariffe attive</p>
          </div>
        </div>
      )}
      <main style={styles.page}>
        <div style={styles.container}>
        <div style={{ marginBottom: 32 }}>
          <h1 style={{ fontSize: '2rem', fontWeight: 800, color: '#f1f5f9', marginBottom: 6 }}>
            Calcolo rapido del risparmio
          </h1>
          <p style={{ color: '#94a3b8', fontSize: '0.9rem', lineHeight: 1.6 }}>
            Inserisci i tuoi dati di consumo e scopri quanto puoi risparmiare cambiando fornitore. Senza bolletta.
          </p>
        </div>

        {/* Step indicator */}
        <div style={styles.stepIndicator}>
          {[0, 1, 2].map(i => (
            <div key={i} style={styles.stepDot(step >= i)} />
          ))}
        </div>

        <div style={styles.card}>
          {/* STEP 0: Utility + Customer Type + Zone */}
          {step === 0 && (
            <>
              <h2 style={styles.title}>
                Tipo di fornitura
              </h2>
              <p style={styles.subtitle}>Seleziona il tipo di utenza e la zona tariffaria.</p>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>Servizio</label>
                <div className="toggle-group">
                  <button
                    className={`toggle-btn luce ${form.utility === 'luce' ? 'active' : ''}`}
                    onClick={() => update('utility', 'luce')}
                  >
                    <IconBolt /> Luce
                  </button>
                  <button
                    className={`toggle-btn gas ${form.utility === 'gas' ? 'active' : ''}`}
                    onClick={() => update('utility', 'gas')}
                  >
                    <IconFlame /> Gas
                  </button>
                </div>
              </div>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>Tipo cliente</label>
                <div className="toggle-group">
                  <button
                    className={`toggle-btn ${form.customer_type === 'residenziale' ? 'active' : ''}`}
                    onClick={() => update('customer_type', 'residenziale')}
                  >
                    <IconHome /> Residenziale
                  </button>
                  <button
                    className={`toggle-btn ${form.customer_type === 'business' ? 'active' : ''}`}
                    onClick={() => update('customer_type', 'business')}
                  >
                    <IconBuilding /> Business
                  </button>
                </div>
              </div>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>Zona tariffaria</label>
                <div className="toggle-group">
                  {['NORD', 'CENTRO', 'SUD'].map(z => (
                    <button
                      key={z}
                      className={`toggle-btn ${form.zona === z ? 'active' : ''}`}
                      onClick={() => update('zona', z)}
                    >
                      {z === 'NORD' ? 'Nord' : z === 'CENTRO' ? 'Centro' : 'Sud'}
                    </button>
                  ))}
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'center', marginTop: 12 }}>
                <button className="btn btn-electric" onClick={() => setStep(1)} disabled={!isValidStep0}>
                  Continua
                </button>
              </div>
            </>
          )}

          {/* STEP 1: Consumption + Power */}
          {step === 1 && (
            <>
              <h2 style={styles.title}>
                Consumi annuali
              </h2>
              <p style={styles.subtitle}>
                Inserisci il tuo consumo annuo{form.utility === 'luce' ? ' e la potenza del contatore' : ''}.
              </p>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>
                  Consumo annuo ({form.utility === 'luce' ? 'kWh' : 'Smc'})
                </label>
                <div style={{ position: 'relative' }}>
                  <input
                    type="number"
                    className="input-field"
                    placeholder={form.utility === 'luce' ? 'es. 2700 kWh' : 'es. 1000 Smc'}
                    value={form.consumption}
                    onChange={e => update('consumption', e.target.value)}
                  />
                  <span style={{ position: 'absolute', right: 14, top: 14, color: '#475569', fontSize: '0.85rem', pointerEvents: 'none' }}>
                    {form.utility === 'luce' ? 'kWh' : 'Smc'}
                  </span>
                </div>
              </div>

              {form.utility === 'luce' && (
                <div style={{ marginBottom: 24 }}>
                  <label style={styles.label}>Potenza impegnata (kW)</label>
                  <select
                    className="input-field"
                    style={{ cursor: 'pointer' }}
                    value={form.power_kw}
                    onChange={e => update('power_kw', e.target.value)}
                  >
                    <option value="3.0">3.0 kW (standard)</option>
                    <option value="4.5">4.5 kW</option>
                    <option value="6.0">6.0 kW</option>
                  </select>
                </div>
              )}

              <div style={{ display: 'flex', justifyContent: 'center', gap: 12, marginTop: 12 }}>
                <button className="btn btn-outline" onClick={() => setStep(0)}>
                  Indietro
                </button>
                <button className="btn btn-electric" onClick={() => setStep(2)} disabled={!isValidStep1}>
                  Continua
                </button>
              </div>
            </>
          )}

          {/* STEP 2: Bill Amount + Frequency */}
          {step === 2 && (
            <>
              <h2 style={styles.title}>
                La tua bolletta
              </h2>
              <p style={styles.subtitle}>
                Inserisci l'importo dell'ultima bolletta e la frequenza. Calcoliamo automaticamente la spesa annua.
              </p>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>Importo ultima bolletta (€)</label>
                <input
                  type="number"
                  step="0.01"
                  className="input-field"
                  placeholder="es. 120.50"
                  value={form.bill_amount}
                  onChange={e => update('bill_amount', e.target.value)}
                />
              </div>

              <div style={{ marginBottom: 24 }}>
                <label style={styles.label}>Frequenza di fatturazione</label>
                <select
                  className="input-field"
                  style={{ cursor: 'pointer' }}
                  value={form.frequency}
                  onChange={e => update('frequency', e.target.value)}
                >
                  <option value="12">Mensile (12 bollette/anno)</option>
                  <option value="6">Bimestrale (6 bollette/anno)</option>
                  <option value="4">Trimestrale (4 bollette/anno)</option>
                  <option value="3">Quadrimestrale (3 bollette/anno)</option>
                  <option value="2">Semestrale (2 bollette/anno)</option>
                </select>
              </div>

              {form.utility === 'luce' && form.customer_type === 'residenziale' && (
                <div style={{
                  padding: '14px 18px', borderRadius: 'var(--radius-lg)',
                  background: 'rgba(245,158,11,0.05)', border: '1px solid rgba(245,158,11,0.12)',
                  marginBottom: 20,
                }}>
                  <label style={{
                    display: 'flex', alignItems: 'flex-start', gap: 10, cursor: 'pointer',
                  }}>
                    <input
                      type="checkbox"
                      checked={form.include_canone_rai}
                      onChange={e => update('include_canone_rai', e.target.checked)}
                      style={{ marginTop: 3, accentColor: '#f59e0b' }}
                    />
                    <div>
                      <div style={{ fontSize: '0.82rem', fontWeight: 700, color: '#f1f5f9' }}>
                        Include il Canone RAI (90€/anno)
                      </div>
                      <div style={{ fontSize: '0.72rem', color: '#94a3b8', marginTop: 2 }}>
                        Il Canone RAI è un'imposta fissa sulla tv, NON cambia con il fornitore.
                        Se la tua bolletta lo include, lo sottraiamo per un confronto corretto.
                      </div>
                    </div>
                  </label>
                </div>
              )}

              {parseFloat(form.bill_amount) > 0 && parseInt(form.frequency) > 0 && (
                <div style={{
                  padding: '14px 18px', borderRadius: 'var(--radius-lg)',
                  background: 'rgba(16,185,129,0.05)', border: '1px solid rgba(16,185,129,0.12)',
                  marginBottom: 24,
                }}>
                  <div style={{ fontSize: '0.75rem', color: '#94a3b8', marginBottom: 4 }}>Spesa annua stimata</div>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#10b981' }}>
                    {(parseFloat(form.bill_amount) * parseInt(form.frequency)).toFixed(0)} €/anno
                  </div>
                  <div style={{ fontSize: '0.7rem', color: '#94a3b8', marginTop: 2 }}>
                    {form.bill_amount} € × {form.frequency} bollette/anno
                  </div>
                </div>
              )}

              <div style={{ display: 'flex', justifyContent: 'center', gap: 12, marginTop: 12 }}>
                <button className="btn btn-outline" onClick={() => setStep(1)}>
                  Indietro
                </button>
                <button className="btn btn-electric" onClick={handleSubmit} disabled={!isValidStep2 || loading}>
                  {loading ? 'Calcolo...' : 'Calcola risparmio'}
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </main>
    </>
  );
}

// ── Confetti ────────────────────────────────────────────────
const CONFETTI_COLORS = ['#10b981', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#f1f5f9', '#34d399'];

function Confetti() {
  const particles = useMemo(() =>
    Array.from({ length: 70 }, (_, i) => ({
      id: i,
      left: Math.random() * 100,
      size: 5 + Math.random() * 7,
      color: CONFETTI_COLORS[Math.floor(Math.random() * CONFETTI_COLORS.length)],
      delay: Math.random() * 1.5,
      duration: 3.5 + Math.random() * 4.5,
      drift: (Math.random() - 0.5) * 120,
      rotate: (Math.random() - 0.5) * 500,
    })), []
  );

  return (
    <div style={{ position: 'fixed', inset: 0, pointerEvents: 'none', zIndex: 1000, overflow: 'hidden' }}>
      {particles.map(p => (
        <div key={p.id}
          className="confetti-particle"
          style={{
            position: 'absolute',
            left: p.left + '%',
            top: '-5%',
            width: p.size,
            height: p.size * (0.6 + Math.random() * 0.8),
            background: p.color,
            borderRadius: 2,
            animationDelay: p.delay + 's',
            animationDuration: p.duration + 's',
            '--drift': p.drift + 'px',
            '--rotate': p.rotate + 'deg',
          }}
        />
      ))}
    </div>
  );
}

// ── RESULTS VIEW ─────────────────────────────────────────────
function ResultsView({ results, form, onReset }) {
  const [techOpen, setTechOpen] = useState(false);

  if (results.error) {
    return (
      <main style={styles.page}>
        <div style={styles.container}>
          <div style={{ ...styles.card, textAlign: 'center' }}>
            <p style={{ color: '#f87171', marginBottom: 20 }}>{results.error}</p>
            <button className="btn btn-outline" onClick={onReset}>Riprova</button>
          </div>
        </div>
      </main>
    );
  }

  const savingsMonth = Math.round((results.savings_eur / 12) * 100) / 100;
  const diffPct = results.savings_pct || 0;
  const newMonthly = Math.round(results.annual_cost_eur / 12);
  const ctaUrl = results.affiliate_url || results.url_offerta || results.subscription_url || '';
  const isExternal = /^https?:\/\//.test(ctaUrl);
  const isLuce = form.utility === 'luce';
  const commodityLabel = isLuce ? 'Luce' : 'Gas';
  const unitLabel = isLuce ? 'kWh' : 'Smc';
  const consumption = parseFloat(form.consumption) || 0;

  const logoInitials = results.provider_name?.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() || '';

  const isAlreadyGood = diffPct <= 0;

  return (
    <main style={styles.page}>
      {isAlreadyGood && <Confetti />}
      <div style={styles.container}>

        {/* ── Hero: Narrativa spreco → risparmio ────────── */}
        <div style={{
          background: 'var(--bg-card)',
          border: '1px solid var(--border)',
          borderRadius: 'var(--radius-xl)',
          padding: '36px 32px',
          marginBottom: 20,
          boxShadow: 'var(--shadow-md)',
        }}>
          <p style={{
            fontSize: '0.7rem', fontWeight: 700, letterSpacing: '0.08em',
            textTransform: 'uppercase', color: 'var(--text-muted)',
            textAlign: 'center', margin: '0 0 14px',
          }}>
            la tua bolletta {commodityLabel.toLowerCase()} · zona {form.zona}
          </p>

          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 4 }}>
            <IconArrowUpRight stroke="#ef4444" />
            <span style={{ fontSize: '0.85rem', color: '#ef4444', fontWeight: 600 }}>
              {isAlreadyGood ? 'la tua bolletta \u00e8 gi\u00e0 competitiva \u{1F389}' : 'stai pagando pi\u00f9 del necessario'}
            </span>
          </div>

          <p style={{
            fontSize: '3.2rem', fontWeight: 900, letterSpacing: '-1px',
            color: diffPct > 0 ? '#ef4444' : '#10b981',
            textAlign: 'center', margin: '4px 0 0',
            fontVariantNumeric: 'tabular-nums',
          }}>
            {diffPct > 0 ? <AnimatedCounter target={savingsMonth} prefix="+" suffix="" decimals={2} /> : '0,00'}
            <sup style={{ fontSize: '1.2rem', fontWeight: 600, marginLeft: 4 }}>/mese</sup>
          </p>
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', fontSize: '0.85rem', margin: '6px 0 0' }}>
            {isAlreadyGood ? 'Continua cos\u00ec, sei gi\u00e0 con una buona tariffa' : 'rispetto alla migliore offerta disponibile oggi'}
          </p>

          <div style={{ height: 1, background: 'var(--border)', margin: '28px 0' }} />

          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 4 }}>
            <IconArrowDownRight stroke="#10b981" />
            <span style={{ fontSize: '0.85rem', color: '#10b981', fontWeight: 600 }}>
              {isAlreadyGood ? 'non c\u0027\u00e8 un risparmio significativo' : 'passando alla migliore offerta risparmi'}
            </span>
          </div>

          <p style={{
            fontSize: '3.2rem', fontWeight: 900, letterSpacing: '-1px',
            color: '#10b981',
            textAlign: 'center', margin: '4px 0 0',
            fontVariantNumeric: 'tabular-nums',
          }}>
            <AnimatedCounter target={diffPct > 0 ? results.savings_eur : 0} prefix="" suffix="" decimals={0} />
            <sup style={{ fontSize: '1.2rem', fontWeight: 600, marginLeft: 4 }}>/anno</sup>
          </p>
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', fontSize: '0.85rem', margin: '6px 0 0' }}>
            {isAlreadyGood ? 'La differenza con il mercato \u00e8 minima' : `${Math.round(diffPct)}% in meno sulla spesa attuale`}
          </p>
        </div>

        {/* ── Barre di confronto + Anello ─────────────── */}
        <div style={{
          display: 'flex', alignItems: 'flex-end', justifyContent: 'center', gap: 24,
          height: 200, padding: '28px 24px 24px',
          background: 'var(--bg-card)', borderRadius: 'var(--radius-xl)',
          border: '1px solid var(--border)',
          marginBottom: 20,
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 1, alignSelf: 'stretch', justifyContent: 'flex-end' }}>
            <span style={{ fontSize: '1.1rem', fontWeight: 700, color: '#ef4444', marginBottom: 8, fontVariantNumeric: 'tabular-nums' }}>
              {results.current_monthly_spend}€
            </span>
            <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 6 }}>
              oggi
            </span>
            <div style={{
              width: 64, height: 160, borderRadius: '10px 10px 4px 4px',
              background: 'linear-gradient(180deg, #ef4444, #b91c1c)',
              transition: 'height 1.1s cubic-bezier(.16,1,.3,1)',
            }} />
          </div>

          <SavingsRing pct={diffPct} size={88} />

          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 1, alignSelf: 'stretch', justifyContent: 'flex-end' }}>
            <span style={{ fontSize: '1.1rem', fontWeight: 700, color: '#10b981', marginBottom: 8, fontVariantNumeric: 'tabular-nums' }}>
              {newMonthly}€
            </span>
            <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 6 }}>
              nuova offerta
            </span>
            <div style={{
              width: 64, height: 160 * (results.annual_cost_eur / Math.max(results.current_annual_spend, 1)),
              minHeight: 40,
              borderRadius: '10px 10px 4px 4px',
              background: 'linear-gradient(180deg, #10b981, #047857)',
              transition: 'height 1.1s cubic-bezier(.16,1,.3,1)',
            }} />
          </div>
        </div>

        {/* ── Trust Badges ─────────────────────────────── */}
        <div style={{
          display: 'flex', justifyContent: 'center', gap: 22, flexWrap: 'wrap',
          padding: '16px 20px', marginBottom: 20,
          background: 'var(--bg-card)', border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)',
        }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            <IconEye /> {results.offers_after_filter || results.offers_analyzed} offerte analizzate
            {results.offers_before_filter > (results.offers_after_filter || 0) && (
              <span style={{ color: '#64748b', fontSize: '0.7rem' }}>
                su {results.offers_before_filter} totali
              </span>
            )}
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            <IconRefresh /> dati aggiornati al {formatDateIt()}
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            <IconClock /> attivazione in 5 minuti
          </span>
        </div>

        {/* ── Offerta consigliata ──────────────────────── */}
        <div style={{
          padding: '32px 28px',
          background: 'linear-gradient(165deg, rgba(30,40,60,0.6), var(--bg-card))',
          border: '1px solid rgba(16,185,129,0.25)',
          borderRadius: 'var(--radius-xl)',
          boxShadow: '0 24px 48px rgba(0,0,0,0.35)',
          marginBottom: 24,
        }}>
          {/* Badge */}
          <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 6,
            background: isAlreadyGood ? 'rgba(245,158,11,0.12)' : 'rgba(16,185,129,0.12)',
            color: isAlreadyGood ? '#f59e0b' : '#10b981',
            fontSize: '0.7rem', fontWeight: 700, letterSpacing: '0.04em',
            textTransform: 'uppercase', padding: '5px 12px',
            borderRadius: '100px', marginBottom: 18,
          }}>
            <IconCheck /> {isAlreadyGood ? 'nessun risparmio significativo' : 'offerta consigliata'}
          </span>

          {isAlreadyGood && (
            <p style={{
              fontSize: '0.85rem', color: '#94a3b8', lineHeight: 1.6,
              margin: '0 0 18px', padding: '12px 16px',
              background: 'rgba(255,255,255,0.02)', borderRadius: 'var(--radius-md)',
              border: '1px solid rgba(255,255,255,0.04)',
            }}>
              Non ti consigliamo di cambiare a meno che tu non abbia problemi con il tuo attuale fornitore.
              In tal caso l&apos;offerta pi&ugrave; conveniente &egrave;:
            </p>
          )}

          {/* Head: logo + name */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 22 }}>
            <div style={{
              width: 56, height: 56, borderRadius: 12,
              background: results.supplier_logo ? '#fff' : '#1e293b',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              flexShrink: 0, overflow: 'hidden',
            }}>
              {results.supplier_logo ? (
                <img src={results.supplier_logo} alt="" style={{ width: 44, height: 44, objectFit: 'contain' }} />
              ) : (
                <span style={{ fontSize: '0.85rem', fontWeight: 800, color: results.supplier_logo ? '#0B0F19' : '#94a3b8', letterSpacing: '-0.5px' }}>
                  {logoInitials}
                </span>
              )}
            </div>
            <div>
              <h3 style={{ margin: '0 0 3px', fontSize: '1.05rem', fontWeight: 700, color: '#f1f5f9' }}>
                {results.provider_name} · {results.tariff_name}
              </h3>
              <p style={{ margin: 0, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                {results.contract_detail || (results.type === 'FISSO' ? 'Prezzo fisso' : 'Prezzo variabile')}
              </p>
            </div>
          </div>

          {/* Price */}
          <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, marginBottom: 4 }}>
            <span style={{ fontSize: '1.8rem', fontWeight: 800, color: '#f1f5f9' }}>
              {results.annual_cost_eur.toFixed(0)}€
              <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500, marginLeft: 4 }}>/anno</span>
            </span>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              ({results.monthly_cost_eur}€/mese)
            </span>
          </div>
          {results.price_per_unit != null && (
            <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', margin: '0 0 22px' }}>
              {results.price_per_unit} €/{unitLabel}
              {results.fixed_fee_monthly ? ` · quota fissa ${results.fixed_fee_monthly} €/mese` : ''}
            </p>
          )}

          {/* Features */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 26 }}>
            {['Nessun costo di attivazione', 'Nessuna interruzione della fornitura', 'Diritto di ripensamento 14 giorni'].map((f, i) => (
              <span key={i} style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: '0.85rem', color: '#f1f5f9' }}>
                <IconCheck /> {f}
              </span>
            ))}
          </div>

          {/* CTA */}
          <a
            href={ctaUrl}
            target={isExternal ? "_blank" : undefined}
            rel={isExternal ? "nofollow noopener" : undefined}
            className="btn btn-success"
            style={{ display: 'flex', width: '100%', justifyContent: 'center', boxShadow: '0 10px 28px rgba(16,185,129,0.22)' }}
          >
            Attiva {results.tariff_name} sul sito del fornitore
          </a>
          <p style={{ textAlign: 'center', fontSize: '0.75rem', color: '#475569', margin: '10px 0 0' }}>
            Verrai reindirizzato al sito del fornitore per completare l'attivazione in autonomia.
          </p>

          {/* Dettagli tecnici */}
          <button
            onClick={() => setTechOpen(o => !o)}
            style={{
              display: 'block', textAlign: 'center', margin: '18px auto 0',
              fontSize: '0.8rem', color: 'var(--text-muted)',
              textDecoration: 'underline', textUnderlineOffset: 3,
              cursor: 'pointer', background: 'none', border: 'none',
              width: '100%', fontFamily: 'inherit',
            }}
          >
            {techOpen ? 'Nascondi' : 'Vedi'} i dettagli dell'analisi tecnica
          </button>
          {techOpen && (
            <div style={{
              marginTop: 16, paddingTop: 16,
              borderTop: '1px dashed var(--border)',
            }}>
              {[
                ['Consumo annuo', `${consumption} ${unitLabel}`],
                ['Spesa annua attuale', `${results.current_annual_spend.toFixed(0)} €`],
                ...(results.canone_rai_subtracted > 0
                  ? [['Canone RAI escluso', `${results.canone_rai_subtracted}€/anno`]]
                  : []),
                ['Spesa annua nuova offerta', `${results.annual_cost_eur.toFixed(2).replace('.', ',')} €`],
                ['Risparmio annuo', `${results.savings_eur.toFixed(2).replace('.', ',')} € (${Math.round(diffPct)}%)`],
                ['Zona / tipo cliente', `${form.zona} · ${form.customer_type === 'residenziale' ? 'residenziale' : 'business'}`],
                ['Metodo di calcolo', 'allineato al comparatore pubblico ARERA'],
                ...(results.offers_analyzed ? [['Offerte confrontate', `${results.offers_analyzed} tariffe attive`]] : []),
              ].map(([label, value]) => (
                <div key={label} style={{
                  display: 'flex', justifyContent: 'space-between',
                  fontSize: '0.8rem', padding: '7px 0',
                  color: 'var(--text-muted)',
                  borderBottom: '1px solid var(--border)',
                }}>
                  <span>{label}</span>
                  <b style={{ color: '#f1f5f9', fontWeight: 600 }}>{value}</b>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* ── Canone RAI note ─────────────────────────── */}
        {results.canone_rai_subtracted > 0 && (
          <div style={{
            padding: '10px 18px', marginBottom: 20,
            background: 'var(--bg-card)', border: '1px solid rgba(245,158,11,0.12)',
            borderRadius: 'var(--radius-lg)',
            fontSize: '0.75rem', color: '#fbbf24', display: 'flex', alignItems: 'flex-start', gap: 8,
          }}>
            <span style={{ flexShrink: 0, marginTop: 1 }}>📺</span>
            <span>
              Canone RAI ({results.canone_rai_subtracted}€/anno) sottratto dalla tua spesa per un confronto corretto
              — è un'imposta fissa, uguale per tutti i fornitori.
              Spesa effettiva con canone: <b>{results.raw_annual_spend}€/anno</b>
            </span>
          </div>
        )}

        {/* ── Nuovo calcolo ──────────────────────────── */}
        <div style={{ textAlign: 'center' }}>
          <button className="btn btn-outline" onClick={onReset}>
            Nuovo calcolo
          </button>
        </div>

      </div>
    </main>
  );
}
