import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const TIERS = [
  {
    name: 'Free',
    price: '0€',
    period: '/mese',
    badge: null,
    features: [
      { text: 'Tutte le 5.600+ offerte ARERA', included: true },
      { text: '10 chiamate al giorno', included: true },
      { text: 'Confronto Luce e Gas', included: true },
      { text: 'Analisi bolletta con AI', included: true },
      { text: 'API keys self-service', included: true },
      { text: 'Offerte filtrate per anonimi (270)', included: false },
      { text: '1.000 chiamate al giorno', included: false },
    ],
    cta: 'Registrati gratis',
    ctaLink: '/registrati',
  },
  {
    name: 'API Pro',
    price: '0€',
    period: '/mese (beta)',
    badge: 'Consigliato per sviluppatori',
    popular: true,
    features: [
      { text: 'Tutte le 5.600+ offerte ARERA', included: true },
      { text: '1.000 chiamate al giorno', included: true },
      { text: 'Confronto Luce e Gas', included: true },
      { text: 'Analisi bolletta con AI', included: true },
      { text: 'API keys illimitate (self-service)', included: true },
      { text: 'Dashboard con usage e statistiche', included: true },
      { text: 'Accesso MCP + WebMCP completo', included: true },
    ],
    cta: 'Inizia gratis',
    ctaLink: '/registrati',
  },
];

export default function Plus() {
  const [rateLimit, setRateLimit] = useState(null);

  useEffect(() => {
    fetch('/api/auth/rate-limit-info')
      .then(r => r.json())
      .catch(() => {});
  }, []);

  return (
    <main style={{ minHeight: '100vh', background: 'var(--bg-base)', padding: '60px 20px 100px' }}>
      <div style={{ maxWidth: 800, margin: '0 auto', textAlign: 'center', marginBottom: 48 }}>
        <div style={{ fontSize: 40, marginBottom: 12 }}>⚡</div>
        <h1 style={{ fontSize: 32, fontWeight: 900, color: '#f1f5f9', marginBottom: 12 }}>
          SwitchAI<span style={{ color: '#f59e0b' }}>+</span>
        </h1>
        <p style={{ fontSize: 15, color: '#94a3b8', maxWidth: 520, margin: '0 auto', lineHeight: 1.6 }}>
          Accedi a tutte le 5.600+ offerte del mercato libero. Crea API keys per le tue integrazioni.
          Sblocca il pieno potenziale di SwitchAI.
        </p>
      </div>

      <div style={{ display: 'flex', gap: 20, justifyContent: 'center', flexWrap: 'wrap', maxWidth: 640, margin: '0 auto' }}>
        {TIERS.map(t => (
          <div key={t.name} style={{
            flex: 1, minWidth: 260, maxWidth: 320,
            padding: '32px 28px', borderRadius: 16,
            background: t.popular ? 'linear-gradient(145deg, rgba(245,158,11,0.08) 0%, rgba(245,158,11,0.02) 100%)' : 'rgba(255,255,255,0.03)',
            border: t.popular ? '1px solid rgba(245,158,11,0.2)' : '1px solid rgba(255,255,255,0.06)',
            position: 'relative',
            display: 'flex', flexDirection: 'column',
          }}>
            {t.badge && (
              <div style={{
                position: 'absolute', top: -10, left: '50%', transform: 'translateX(-50%)',
                padding: '4px 16px', borderRadius: 20, fontSize: 11, fontWeight: 700,
                background: 'linear-gradient(135deg, #f59e0b, #d97706)',
                color: '#fff', whiteSpace: 'nowrap',
              }}>
                {t.badge}
              </div>
            )}
            <h3 style={{ fontSize: 18, fontWeight: 800, color: '#f1f5f9', marginBottom: 4 }}>{t.name}</h3>
            <div style={{ marginBottom: 20 }}>
              <span style={{ fontSize: 32, fontWeight: 900, color: '#f1f5f9' }}>{t.price}</span>
              <span style={{ fontSize: 13, color: '#64748b', marginLeft: 4 }}>{t.period}</span>
            </div>

            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 24 }}>
              {t.features.map((f, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ fontSize: 14, color: f.included ? '#6ee7b7' : '#475569' }}>
                    {f.included ? '✓' : '—'}
                  </span>
                  <span style={{ fontSize: 13, color: f.included ? '#e2e8f0' : '#475569' }}>
                    {f.text}
                  </span>
                </div>
              ))}
            </div>

            <Link to={t.ctaLink} className={`btn ${t.popular ? 'btn-electric' : 'btn-outline'}`}
              style={{ textDecoration: 'none', textAlign: 'center', display: 'block' }}>
              {t.cta}
            </Link>
          </div>
        ))}
      </div>

      <div style={{ maxWidth: 600, margin: '48px auto 0', padding: '24px', borderRadius: 12, background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.04)' }}>
        <h3 style={{ fontSize: 14, fontWeight: 700, color: '#94a3b8', marginBottom: 16, textAlign: 'center' }}>❓ Domande frequenti</h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {[
            { q: 'Devo registrarmi per usare SwitchAI?', a: 'No, l\'analisi di base funziona anche senza registrazione (270 offerte filtrate per zona NORD). Per vedere tutte le 5.600+ offerte devi registrarti e confermare l\'email — è gratis e richiede 30 secondi.' },
            { q: 'Cosa succede dopo la registrazione?', a: 'Ricevi una email di conferma. Clicca il link per attivare l\'account. Solo dopo la conferma puoi accedere e creare API key.' },
            { q: 'Che differenza c\'è tra Free e API Pro?', a: 'Entrambi vedono tutte le 5.600+ offerte. Free ha 10 chiamate al giorno, API Pro ne ha 1.000. Entrambi possono creare API key self-service.' },
            { q: 'Cosa succede se supero il limite giornaliero?', a: 'Riceverai un errore 429. Il limite si azzera ogni giorno. Se hai bisogno di più chiamate, passando a API Pro (gratuito in beta) hai 1.000/giorno.' },
            { q: 'Come uso le API key?', a: 'Passa la chiave nell\'header X-API-Key o x-api-key. Funziona con curl, Python, JavaScript e qualsiasi linguaggio. Gli esempi sono nella tua Dashboard.' },
            { q: 'Quali dati personali vengono salvati?', a: 'Solo email, nome, cognome e (opzionale) azienda/P.IVA. SwitchAI non memorizza POD, PDR, indirizzi, consumi o dati di fornitura. Le bollette analizzate vengono processate dalle AI e non sono conservate.' },
          ].map((faq, i) => (
            <details key={i} style={{ fontSize: 13 }}>
              <summary style={{ color: '#e2e8f0', fontWeight: 600, cursor: 'pointer', padding: '8px 0' }}>
                {faq.q}
              </summary>
              <p style={{ color: '#64748b', padding: '4px 0 8px', lineHeight: 1.5 }}>{faq.a}</p>
            </details>
          ))}
        </div>
      </div>
    </main>
  );
}
