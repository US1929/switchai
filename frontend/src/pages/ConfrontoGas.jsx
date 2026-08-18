import React from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';

export default function ConfrontoGas() {
  return (
    <>
      <Helmet>
        <title>Confronto Offerte Gas 2026: Trova la Tariffa Migliore | SwitchAI</title>
        <meta name="description" content="Confronta le offerte gas disponibili nella tua zona. Carica la bolletta, l'AI calcola risparmio e tariffa migliore sul mercato libero. Gratis." />
        <link rel="canonical" href="https://www.switchai.it/confronto-gas" />
      </Helmet>
    <main style={{ padding: '60px 24px 80px' }}>

      <div style={{ maxWidth: 780, margin: '0 auto', fontSize: 14, color: '#94a3b8', lineHeight: 1.8, fontFamily: 'system-ui, sans-serif' }}>
        {/* ── Header ──────────────────────────────────────────── */}
        <p style={{ color: '#f59e0b', fontWeight: 700, fontSize: 13, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 }}>
          Guida alle tariffe gas
        </p>
        <h1 style={{ fontSize: 30, fontWeight: 900, color: '#f1f5f9', marginBottom: 12, lineHeight: 1.3 }}>
          Confronto offerte gas: come scegliere la tariffa giusta nel 2026
        </h1>
        <p style={{ color: '#64748b', marginBottom: 40, fontSize: 15, lineHeight: 1.7 }}>
          Anche per il gas naturale, come per la luce, la maggior tutela per i clienti domestici è terminata (gennaio 2024). Chi non ha ancora scelto attivamente un'offerta sul mercato libero rischia di pagare condizioni pensate come rete di sicurezza, non come offerta competitiva.
        </p>

        {/* ── Come si forma il prezzo ─────────────────────────────── */}
        <Section title="Come si forma il prezzo del gas in bolletta">
          <p>Il costo del gas che paghi è composto da:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><strong style={{ color: '#f59e0b' }}>Materia prima gas naturale</strong> (€/Smc) — qui i fornitori competono, indicizzando spesso al <strong style={{ color: '#f1f5f9' }}>PSV</strong> (Punto di Scambio Virtuale), il principale indice di riferimento del mercato italiano del gas</li>
            <li><strong style={{ color: '#f1f5f9' }}>Quota fissa</strong> — canone mensile o annuo, variabile da offerta a offerta</li>
            <li><strong style={{ color: '#f1f5f9' }}>Trasporto e distribuzione, oneri di sistema, imposte</strong> — regolati da ARERA, uguali per tutti a parità di zona e classe di consumo</li>
          </ul>
        </Section>

        {/* ── Fisso o indicizzato ────────────────────────────────── */}
        <Section title="Fisso o indicizzato al PSV">
          <p>Le offerte gas seguono la stessa logica della luce:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><strong style={{ color: '#10b981' }}>Prezzo fisso</strong>: utile per chi vuole prevedibilità in bolletta, specialmente in inverno quando i consumi (e storicamente i prezzi) salgono</li>
            <li><strong style={{ color: '#fbbf24' }}>Prezzo indicizzato al PSV</strong>: può convenire se il mercato è stabile, ma espone a rincari nei mesi di maggior domanda europea di gas</li>
          </ul>
          <p style={{ marginTop: 12 }}>Per un uso tipicamente stagionale come il riscaldamento, molte famiglie preferiscono bloccare il prezzo prima dell'inverno, quando la domanda (e quindi la volatilità) è più bassa.</p>
        </Section>

        {/* ── Consumi ────────────────────────────────────────────── */}
        <Section title="I consumi contano più del prezzo unitario">
          <p>Un errore comune è confrontare solo il prezzo al metro cubo. In realtà per il gas conta moltissimo la tua <strong style={{ color: '#f1f5f9' }}>classe di consumo annuo</strong> (misurata in Smc): un'offerta ottima per chi consuma 1.400 Smc/anno può essere mediocre per chi ne consuma 500, perché la quota fissa incide diversamente sul totale.</p>
          <p style={{ marginTop: 12 }}>Per questo un confronto affidabile deve partire dai tuoi consumi reali, quelli che trovi nella tua bolletta attuale, e non da una media nazionale.</p>
        </Section>

        {/* ── Quando cambiare ────────────────────────────────────── */}
        <Section title="Quando conviene cambiare fornitore gas">
          <p>Ha senso valutare uno switch se:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>Sei ancora nel servizio di tutela graduale</li>
            <li>Il tuo contratto attuale è indicizzato al PSV senza alcuno sconto</li>
            <li>Non hai mai confrontato le offerte disponibili nella tua zona (il mercato gas ha differenze significative tra Nord, Centro e Sud Italia)</li>
          </ul>
          <p style={{ marginTop: 12 }}>Anche qui, il cambio è <strong style={{ color: '#10b981' }}>gratuito</strong> e senza interruzioni di fornitura: cambia solo chi ti fattura, non l'infrastruttura fisica che porta il gas a casa tua.</p>
        </Section>

        {/* ── CTA ────────────────────────────────────────────────── */}
        <div style={{
          background: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.15)',
          borderRadius: 14, padding: '28px 24px', textAlign: 'center', marginTop: 40,
        }}>
          <p style={{ fontSize: 16, fontWeight: 700, color: '#f1f5f9', marginBottom: 8 }}>
            Confronta le offerte gas sui tuoi consumi reali
          </p>
          <p style={{ color: '#94a3b8', fontSize: 13, marginBottom: 20 }}>
            Invece di stimare a occhio, carica la tua bolletta gas: SwitchAI estrae automaticamente fornitore, zona tariffaria e consumo annuo, e confronta le offerte gas disponibili nella tua area.
          </p>
          <Link
            to="/calcolo-rapido?commodity=gas"
            style={{
              display: 'inline-block', padding: '14px 32px', borderRadius: 10,
              background: 'linear-gradient(135deg, #3b82f6, #2563eb)',
              color: '#fff', fontSize: 15, fontWeight: 700, textDecoration: 'none',
              boxShadow: '0 8px 24px rgba(59,130,246,0.25)',
            }}
          >
            🔥 Confronta le tariffe gas
          </Link>
        </div>

        {/* ── Disclaimer ─────────────────────────────────────────── */}
        <p style={{
          marginTop: 40, fontSize: 11, color: '#475569', lineHeight: 1.6,
          textAlign: 'center', maxWidth: 600, marginLeft: 'auto', marginRight: 'auto',
        }}>
          I riferimenti a PSV e dinamiche di mercato hanno finalità informativa generale; per il confronto economico aggiornato fai riferimento alle offerte attive al momento della verifica.
        </p>
      </div>
    </main>
    </>
  );
}

function Section({ title, children }) {
  return (
    <div style={{
      background: '#111620', border: '1px solid rgba(255,255,255,0.06)',
      borderRadius: 14, padding: '24px', marginBottom: 20,
    }}>
      <h2 style={{ fontSize: 18, fontWeight: 700, color: '#f1f5f9', marginBottom: 12 }}>{title}</h2>
      <div style={{ fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>{children}</div>
    </div>
  );
}
