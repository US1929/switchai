import React from 'react';

export default function Privacy() {
  return (
    <main style={{ padding: '60px 24px 80px' }}>
      <div style={{ maxWidth: 780, margin: '0 auto', fontSize: 14, color: '#94a3b8', lineHeight: 1.8 }}>
        <h1 style={{ fontSize: 30, fontWeight: 900, color: '#f1f5f9', marginBottom: 8 }}>Privacy Policy</h1>
        <p style={{ color: '#64748b', marginBottom: 40 }}>Ultimo aggiornamento: Giugno 2026</p>

        <Section title="1. Titolare del Trattamento">
          SwitchAI è un servizio di confronto e attivazione tariffe Luce e Gas gestito da Innovasemplice S.p.A. (di seguito "Innovasemplice" o "il Titolare"), con sede in Italia.
          I dati personali raccolti attraverso il sito switchai.it e i tool WebMCP/MCP vengono trattati in conformità al Regolamento UE 2016/679 (GDPR) e al D.Lgs. 196/2003.
        </Section>

        <Section title="2. Dati raccolti">
          <p>SwitchAI raccoglie esclusivamente i dati necessari per il confronto e l'attivazione di offerte Luce e Gas:</p>
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Dati anagrafici</b>: nome, cognome, codice fiscale</li>
            <li><b>Dati di contatto</b>: email, numero di telefono</li>
            <li><b>Dati di fornitura</b>: indirizzo, CAP, città, provincia, POD/PDR</li>
            <li><b>Dati di consumo</b>: consumo annuo kWh (luce) o Smc (gas), potenza contrattuale</li>
            <li><b>Dati di pagamento</b>: IBAN (solo se viene scelta la domiciliazione bancaria SDD)</li>
          </ul>
        </Section>

        <Section title="3. Finalità del trattamento">
          I dati sono trattati per le seguenti finalità:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Confronto e attivazione offerte</b>: per analizzare la bolletta, confrontare le tariffe disponibili e inoltrare la richiesta di attivazione al fornitore selezionato</li>
            <li><b>Contatto</b>: per essere ricontattati da un consulente partner al fine di completare l'attivazione</li>
            <li><b>Obblighi di legge</b>: per adempiere a obblighi previsti dalla normativa vigente</li>
          </ul>
        </Section>

        <Section title="4. Base giuridica">
          Il trattamento si basa sul <b>consenso esplicito</b> dell'interessato, raccolto tramite:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Checkbox</b> nel form di sottoscrizione sul sito switchai.it</li>
            <li><b>Conferma verbale</b> registrata nella conversazione con l'assistente AI (WebMCP/MCP), con snippet testuale archiviato come prova</li>
            <li><b>Double opt-in</b>: dopo l'invio dei dati, l'utente riceve una email con link di conferma. Solo dopo il click sul link i dati vengono inoltrati ai partner</li>
          </ul>
        </Section>

        <Section title="5. Double Opt-In e prova del consenso">
          SwitchAI utilizza un sistema di <b>double opt-in</b> per garantire la validità del consenso:
          <ol style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>L'utente fornisce i dati e accetta esplicitamente la privacy policy</li>
            <li>SwitchAI invia una email di conferma all'indirizzo fornito con link univoco</li>
            <li>Solo dopo il click sul link di conferma, i dati vengono inoltrati ai partner</li>
          </ol>
          Per ogni sottoscrizione vengono registrati: IP, timestamp, fonte del consenso (web/mcp/webmcp), snippet della conversazione (se il consenso è stato dato via AI).
        </Section>

        <Section title="6. Comunicazione a terzi">
          I dati personali sono comunicati esclusivamente a:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li><b>Fornitori di energia</b>: Enel, Eni Plenitude, Edison, A2A, Iren, Hera Comm, Sorgenia, Engie, Fastweb, Illumia, Acea, Octopus Energy, Volty e altri partner</li>
            <li><b>Cloud-Care S.r.l.</b>: piattaforma di gestione lead utilizzata per l'inoltro delle richieste di attivazione</li>
            <li><b>Consulenti partner</b>: rete di consulenti autorizzati per il completamento dell'attivazione</li>
          </ul>
        </Section>

        <Section title="7. Conservazione">
          I dati sono conservati per il tempo necessario all'esecuzione del servizio e per l'adempimento degli obblighi di legge. I log di consenso (IP, timestamp, snippet) sono conservati per 10 anni come prova del consenso prestato.
        </Section>

        <Section title="8. Diritti dell'interessato">
          In qualsiasi momento puoi esercitare i diritti previsti dagli artt. 15-22 del GDPR:
          <ul style={{ marginTop: 8, paddingLeft: 20 }}>
            <li>Accesso, rettifica, cancellazione dei dati</li>
            <li>Limitazione del trattamento</li>
            <li>Portabilità dei dati</li>
            <li>Opposizione al trattamento</li>
            <li>Revoca del consenso</li>
          </ul>
          Per esercitare i tuoi diritti, scrivi a <b>privacy@switchai.it</b>. Hai inoltre il diritto di proporre reclamo al Garante per la Protezione dei Dati Personali.
        </Section>

        <Section title="9. Cookie">
          SwitchAI utilizza esclusivamente cookie tecnici essenziali per il funzionamento del sito. Non utilizza cookie di profilazione né di tracciamento. Per maggiori informazioni, consulta la <a href="/cookie" style={{ color: '#60a5fa' }}>Cookie Policy</a>.
        </Section>

        <Section title="10. Contatti">
          Per qualsiasi richiesta relativa alla privacy: <b>privacy@switchai.it</b>
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
