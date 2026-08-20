import React from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';

export default function TariffeLuce() {
  return (
    <>
      <Helmet>
        <title>Tariffe Luce 2026: Confronto Offerte Mercato Libero | SwitchAI</title>
        <meta name="description" content="Confronta le migliori tariffe luce 2026 sul mercato libero. Analizza la bolletta con l'AI e scopri quanto puoi risparmiare in un minuto, gratis." />
        <link rel="canonical" href="https://www.switchai.it/tariffe-luce" />
      </Helmet>
    <main style={{ padding: '60px 24px 80px' }}>

      <div style={{ maxWidth: 780, margin: '0 auto', fontSize: 14, color: '#94a3b8', lineHeight: 1.8, fontFamily: 'system-ui, sans-serif' }}>
        {/* ── Header ──────────────────────────────────────────── */}
        <p style={{ color: '#f59e0b', fontWeight: 700, fontSize: 13, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 }}>
          Guida alle tariffe luce
        </p>
        <h1 style={{ fontSize: 30, fontWeight: 900, color: '#f1f5f9', marginBottom: 12, lineHeight: 1.3 }}>
          Tariffe Luce 2026: come confrontarle e quando conviene cambiare fornitore
        </h1>
        <p style={{ color: '#64748b', marginBottom: 40, fontSize: 15, lineHeight: 1.7 }}>
          Se stai leggendo questa pagina probabilmente ti sei fatto una domanda semplice ma non banale: <em style={{ color: '#94a3b8' }}>sto pagando la luce più del dovuto?</em> La risposta, per la maggior parte delle famiglie italiane ancora legate a vecchie condizioni contrattuali, è quasi sempre sì.
        </p>

        {/* ── Mercato libero vs STG ──────────────────────────────── */}
        <Section title="Mercato libero vs Servizio a Tutele Graduali">
          <p>Dal 2024 la maggior tutela per l'energia elettrica non esiste più per i clienti domestici: chi non aveva scelto un fornitore sul mercato libero è confluito nel <strong style={{ color: '#f1f5f9' }}>Servizio a Tutele Graduali (STG)</strong>, gestito da operatori selezionati tramite asta ARERA. Il problema è che le condizioni STG non sono pensate per essere le più convenienti sul mercato: sono una rete di sicurezza, non l'offerta migliore.</p>
          <p style={{ marginTop: 12 }}>Sul mercato libero, invece, ogni fornitore fissa liberamente prezzo della materia energia, condizioni di indicizzazione e sconti. Questo significa che due bollette per lo stesso identico consumo possono differire anche di diverse centinaia di euro l'anno.</p>
        </Section>

        {/* ── Voci di costo ─────────────────────────────────────── */}
        <Section title="Le voci che compongono una tariffa luce">
          <p>Quando confronti offerte, guarda soprattutto:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><strong style={{ color: '#f59e0b' }}>Prezzo della materia energia</strong> (€/kWh) — è la parte su cui i fornitori competono davvero</li>
            <li><strong style={{ color: '#f1f5f9' }}>Tipo di prezzo</strong>: fisso (bloccato per 12-24 mesi) o variabile (indicizzato al PUN, il Prezzo Unico Nazionale)</li>
            <li><strong style={{ color: '#f1f5f9' }}>Costo fisso mensile/annuo</strong> — alcune offerte lo azzerano, altre lo nascondono in bolletta</li>
            <li><strong style={{ color: '#f1f5f9' }}>Oneri di sistema e trasporto</strong> — questi sono regolati e uguali per tutti, non negoziabili</li>
            <li><strong style={{ color: '#f1f5f9' }}>Bonus di attivazione</strong> — utili ma da non far pesare troppo nella scelta rispetto al risparmio strutturale sui 12 mesi</li>
          </ul>
        </Section>

        {/* ── Fisso o variabile ──────────────────────────────────── */}
        <Section title="Fisso o variabile: quale scegliere">
          <p>Non c'è una risposta universale, dipende dal tuo profilo di rischio:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>Un <strong style={{ color: '#10b981' }}>prezzo fisso</strong> ti protegge da rincari improvvisi del mercato all'ingrosso, ma se i prezzi scendono continui a pagare la tariffa bloccata</li>
            <li>Un <strong style={{ color: '#fbbf24' }}>prezzo variabile</strong> segue l'andamento del PUN: conviene quando ti aspetti un mercato stabile o in discesa, espone a rincari nei mesi di picco (tipicamente inverno)</li>
          </ul>
          <p style={{ marginTop: 12 }}>In un contesto di prezzi energetici ancora volatili, molte famiglie preferiscono il fisso per pianificare il budget, ma la scelta corretta dipende dai tuoi consumi e dalla tua tolleranza al rischio.</p>
        </Section>

        {/* ── Quando cambiare ────────────────────────────────────── */}
        <Section title="Quando conviene davvero cambiare">
          <p>Vale la pena valutare un cambio se:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>Sei ancora nel Servizio a Tutele Graduali</li>
            <li>Non hai mai cambiato offerta negli ultimi 2-3 anni</li>
            <li>La tua bolletta attuale ha un prezzo variabile senza sconto sul PUN</li>
            <li>Non ricordi le condizioni del tuo contratto attuale (succede più spesso di quanto pensi)</li>
          </ul>
          <p style={{ marginTop: 12 }}>Il cambio fornitore è <strong style={{ color: '#10b981' }}>gratuito</strong>, non richiede interruzioni di fornitura e per legge lo switch tecnico avviene entro pochi giorni lavorativi.</p>
        </Section>

        {/* ── CTA ────────────────────────────────────────────────── */}
        <div style={{
          background: 'rgba(245,158,11,0.06)', border: '1px solid rgba(245,158,11,0.15)',
          borderRadius: 14, padding: '28px 24px', textAlign: 'center', marginTop: 40,
        }}>
          <p style={{ fontSize: 16, fontWeight: 700, color: '#f1f5f9', marginBottom: 8 }}>
            Confronta le offerte luce sui tuoi consumi reali
          </p>
          <p style={{ color: '#94a3b8', fontSize: 13, marginBottom: 20 }}>
            Carica la tua bolletta e scopri in meno di un minuto se stai pagando più del necessario, confrontando automaticamente 5.600+ offerte disponibili sul mercato libero italiano.
          </p>
          <Link
            to="/calcolo-rapido?commodity=luce"
            style={{
              display: 'inline-block', padding: '14px 32px', borderRadius: 10,
              background: 'linear-gradient(135deg, #f59e0b, #d97706)',
              color: '#fff', fontSize: 15, fontWeight: 700, textDecoration: 'none',
              boxShadow: '0 8px 24px rgba(245,158,11,0.25)',
            }}
          >
            ⚡ Confronta le tariffe luce
          </Link>
        </div>

        {/* ── Disclaimer ─────────────────────────────────────────── */}
        <p style={{
          marginTop: 40, fontSize: 11, color: '#475569', lineHeight: 1.6,
          textAlign: 'center', maxWidth: 600, marginLeft: 'auto', marginRight: 'auto',
        }}>
          Le informazioni su prezzi e mercato riportate in questa pagina sono di carattere generale; per condizioni economiche aggiornate fai sempre riferimento al confronto in tempo reale delle offerte.
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
