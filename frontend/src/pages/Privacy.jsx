import React from 'react';

export default function Privacy() {
  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 780, margin: '0 auto', fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>
        <h1 style={{ fontSize: 30, fontWeight: 900, color: '#f1f5f9', marginBottom: 8 }}>Privacy Policy</h1>
        <p style={{ color: '#64748b', marginBottom: 40 }}>Ultimo aggiornamento: Luglio 2026</p>

        <Section title="1. Titolare del Trattamento">
          SwitchAI è un servizio di confronto tariffe Luce e Gas nel mercato libero italiano.
          I dati personali raccolti attraverso il sito switchai.it e i tool WebMCP/MCP vengono
          trattati in conformità al Regolamento UE 2016/679 (GDPR) e al D.Lgs. 196/2003.
          Per informazioni: <b>info@switchai.it</b>
        </Section>

        <Section title="2. Dati raccolti">
          <p>SwitchAI raccoglie esclusivamente i dati necessari per la registrazione dell'account e il confronto delle offerte:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Dati di registrazione</b>: nome, cognome, email, password (crittografata)</li>
            <li><b>Dati di consumo</b>: consumo annuo kWh (luce) o Smc (gas), spesa annua, zona tariffaria — forniti volontariamente dall'utente durante il confronto, non memorizzati</li>
            <li><b>Dati tecnici</b>: indirizzo IP, user agent, pagine visitate (solo cookie tecnici)</li>
          </ul>
          <p style={{ marginTop: 8 }}>SwitchAI <b>non raccoglie</b> dati sensibili, codice fiscale, POD/PDR, IBAN o dati di pagamento. L'attivazione delle offerte avviene direttamente sul sito del fornitore tramite link di reindirizzamento.</p>
        </Section>

        <Section title="3. Finalità del trattamento">
          I dati sono trattati per le seguenti finalità:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Registrazione account</b>: per creare e gestire l'account utente e le API key</li>
            <li><b>Confronto offerte</b>: per analizzare la bolletta e confrontare le tariffe disponibili</li>
            <li><b>Reindirizzamento</b>: per fornire link di affiliazione ai siti dei fornitori per l'attivazione</li>
            <li><b>Miglioramento del servizio</b>: analisi aggregata anonima dell'utilizzo</li>
          </ul>
        </Section>

        <Section title="4. Base giuridica">
          Il trattamento si basa sul <b>consenso</b> dell'interessato per i dati di registrazione
          e sul <b>legittimo interesse</b> per i dati tecnici anonimi necessari al funzionamento del servizio.
        </Section>

        <Section title="5. Comunicazione a terzi">
          I dati personali degli account registrati non vengono comunicati a terzi.
          I dati di consumo anonimi (senza dati personali) possono essere utilizzati in forma aggregata.
          I link di reindirizzamento portano ai siti ufficiali dei fornitori di energia o, in alcuni casi,
          a portali di affiliazione (es. <b>TradeDoubler</b>) per l'attivazione convenzionata —
          nessun dato personale viene trasmesso tramite questi link.
        </Section>

        <Section title="6. Conservazione">
          I dati dell'account sono conservati fino alla richiesta di cancellazione da parte dell'utente.
          I log tecnici anonimi sono conservati per un massimo di 12 mesi.
        </Section>

        <Section title="7. Diritti dell'interessato">
          In qualsiasi momento puoi esercitare i diritti previsti dagli artt. 15-22 del GDPR:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>Accesso, rettifica, cancellazione dei dati</li>
            <li>Limitazione del trattamento</li>
            <li>Portabilità dei dati</li>
            <li>Opposizione al trattamento</li>
            <li>Revoca del consenso</li>
          </ul>
          Per esercitare i tuoi diritti, scrivi a <b>info@switchai.it</b>.
          Hai il diritto di proporre reclamo al Garante per la Protezione dei Dati Personali.
        </Section>

        <Section title="8. Cookie">
          SwitchAI utilizza esclusivamente cookie tecnici essenziali per il funzionamento del sito.
          Non utilizza cookie di profilazione né di tracciamento.
          Per maggiori informazioni, consulta la <a href="/cookie" style={{ color: '#60a5fa' }}>Cookie Policy</a>.
        </Section>

        <Section title="9. Contatti">
          Per qualsiasi richiesta relativa alla privacy: <b>info@switchai.it</b><br />
          Per informazioni generali: <b>info@switchai.it</b>
        </Section>
      </div>
    </main>
  );
}

function Section({ title, children }) {
  return (
    <div style={{ marginBottom: 32 }}>
      <h2 style={{ fontSize: 18, fontWeight: 700, color: '#f1f5f9', marginBottom: 10 }}>{title}</h2>
      <div>{children}</div>
    </div>
  );
}
