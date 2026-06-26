<?php
$title = 'Prezzo fisso o variabile? Guida alla scelta dell\'offerta luce e gas 2026';
$description = 'Prezzo fisso vs indicizzato: quale offerta luce conviene? Guida completa con simulazione storica su dati PUN 2022-2026. Meglio fisso o variabile nel 2026?';
$canonical = 'https://www.switchai.it/risorse/prezzo-fisso-vs-indicizzato';
require __DIR__ . '/_header.php';
?>
<h1>Prezzo fisso o indicizzato? Guida alla scelta dell'offerta luce e gas</h1>
<p style="font-size:17px;color:var(--text-secondary);margin-bottom:24px">La domanda che tutti si fanno: <strong>meglio un'offerta a prezzo fisso o una indicizzata?</strong> La risposta dipende dal mercato, dal tuo profilo di consumo e dalla tua propensione al rischio. In questa guida analizziamo le differenze, i pro e i contro, e ti aiutiamo a scegliere. Dati aggiornati ARERA v4.0.</p>

<div class="card" style="background:rgba(167,139,250,.06);border-color:rgba(167,139,250,.15)">
<p style="margin-bottom:0">📌 <strong>In breve:</strong> il prezzo fisso ti dà <strong>certezza</strong> per 12-24 mesi, l'indicizzato segue il mercato (PUN per la luce, PSV per il gas) e può costare meno se i prezzi scendono. Non esiste una risposta universalmente giusta: tutto dipende dal <strong>momento storico</strong> e dalla tua <strong>propensione al rischio</strong>.</p>
</div>

<h2 id="prezzo-fisso">1. Prezzo fisso: certezza e tranquillità</h2>
<p>Con un'offerta a <strong>prezzo fisso</strong>, il fornitore blocca il prezzo dell'energia (€/kWh per la luce, €/Smc per il gas) per un periodo determinato, tipicamente <strong>12 o 24 mesi</strong>. Il prezzo non cambia, qualunque cosa accada ai mercati energetici.</p>

<div class="card">
<h3>Vantaggi del prezzo fisso</h3>
<ul>
<li><strong>Certezza del costo:</strong> sai esattamente quanto pagherai per kWh per tutto il periodo contrattuale</li>
<li><strong>Protezione dai rialzi:</strong> se il PUN sale a 0,30 €/kWh, tu paghi sempre quello concordato</li>
<li><strong>Budget prevedibile:</strong> ideale per chi ha un budget familiare stretto o non vuole sorprese in bolletta</li>
<li><strong>Zero sorprese:</strong> nessuna variazione mensile, una sola comunicazione all'anno dalla scadenza</li>
</ul>
</div>

<div class="card">
<h3>Svantaggi del prezzo fisso</h3>
<ul>
<li><strong>Non benefici dei cali:</strong> se il PUN scende da 0,12 a 0,06 €/kWh, tu continui a pagare il vecchio prezzo</li>
<li><strong>Premio di assicurazione:</strong> il prezzo fisso include un "premio" implicito (il fornitore si copre dal rischio rialzo) che in media vale 0,005-0,015 €/kWh in più</li>
<li><strong>Penali di uscita:</strong> in alcune offerte, recedere prima della scadenza comporta una penale</li>
<li><strong>Mercato in calo = perdita:</strong> in un contesto di prezzi in discesa (come 2024-2026), il fisso ti fa pagare di più rispetto all'indicizzato</li>
</ul>
</div>

<h2 id="prezzo-indicizzato">2. Prezzo indicizzato/variabile: il mercato in tempo reale</h2>
<p>Con un'offerta a <strong>prezzo indicizzato</strong> (o variabile), il costo dell'energia è legato a un indice di mercato: il <strong>PUN</strong> (Prezzo Unico Nazionale) per la luce, il <strong>PSV</strong> (Punto di Scambio Virtuale) per il gas. A questo si aggiunge uno <strong>spread</strong>, il margine del fornitore. Il prezzo cambia ogni mese in base all'andamento del mercato all'ingrosso.</p>

<div class="card">
<h3>Vantaggi del prezzo indicizzato</h3>
<ul>
<li><strong>Segue il mercato al ribasso:</strong> se il PUN cala, la bolletta cala automaticamente</li>
<li><strong>Costo inferiore in mercati stabili:</strong> storicamente, l'indicizzato costa meno del fisso in periodi di prezzi stabili o in calo</li>
<li><strong>Nessuna penale:</strong> puoi cambiare fornitore quando vuoi, senza penali</li>
<li><strong>Trasparenza:</strong> il prezzo è determinato da un indice pubblico e indipendente (PUN/PSV), non deciso arbitrariamente dal fornitore</li>
</ul>
</div>

<div class="card">
<h3>Svantaggi del prezzo indicizzato</h3>
<ul>
<li><strong>Volatilità:</strong> la bolletta può variare anche del 30-50% da un mese all'altro</li>
<li><strong>Rischio rialzo:</strong> se il PUN sale (come nel 2022), paghi molto di più senza preavviso</li>
<li><strong>Budget variabile:</strong> difficile pianificare la spesa energetica annuale</li>
<li><strong>Stress psicologico:</strong> guardare il prezzo del PUN ogni mese non è per tutti</li>
</ul>
</div>

<h2 id="spread">3. Come funziona lo spread</h2>
<p>Lo <strong>spread</strong> è il margine fisso che il fornitore aggiunge al prezzo dell'energia. È espresso in €/kWh (luce) o €/Smc (gas) e rappresenta il guadagno del fornitore. È importante capire come funziona perché <strong>è l'unica voce che confronti quando scegli un'offerta indicizzata</strong>.</p>

<div class="card">
<h3>Formula del prezzo finale (luce indicizzata)</h3>
<p>Il prezzo che paghi per l'energia luce si calcola così:</p>
<blockquote>
<strong>Prezzo finale = (PUN × 1,102) + spread + (quota fissa / consumo annuo)</strong>
</blockquote>
<p>Il coefficiente <strong>1,102</strong> (λ) rappresenta le <strong>perdite di rete</strong> (10,2% obbligatorie per legge). Attenzione: <strong>λ si applica solo al PUN, non allo spread</strong>. Questa è una regola introdotta con la versione ARERA v4.0 (Febbraio 2026).</p>
<blockquote>
✅ <strong>Corretto:</strong> PUN × 1,102 + spread<br>
❌ <strong>Sbagliato (v4.0):</strong> (PUN + spread) × 1,102
</blockquote>
</div>

<div class="card">
<h3>Formula del prezzo finale (gas indicizzato)</h3>
<blockquote>
<strong>Prezzo finale = PSV + spread + (quota fissa / consumo annuo)</strong>
</blockquote>
<p>Per il gas non esistono perdite di rete come per la luce, quindi nessun coefficiente λ. Il prezzo è semplicemente PSV + spread.</p>
</div>

<div class="card" style="background:rgba(245,158,11,.04);border-color:rgba(245,158,11,.15)">
<h3>💡 Come valutare se lo spread è buono</h3>
<p>Ecco i valori di riferimento per valutare uno spread:</p>
<table>
<thead><tr><th>Prodotto</th><th>Spread basso</th><th>Spread medio</th><th>Spread alto</th></tr></thead>
<tbody>
<tr><td><strong>Luce</strong></td><td>&lt; 0,01 €/kWh</td><td>0,01 – 0,03 €/kWh</td><td>&gt; 0,03 €/kWh</td></tr>
<tr><td><strong>Gas</strong></td><td>&lt; 0,05 €/Smc</td><td>0,05 – 0,15 €/Smc</td><td>&gt; 0,15 €/Smc</td></tr>
</tbody>
</table>
<p>Più lo spread è basso, più l'offerta indicizzata è competitiva. Ricorda: lo spread è <strong>fisso</strong> (non varia col mercato), quindi una volta scelto, paghi sempre quello per tutta la durata del contratto.</p>
</div>

<h2 id="quando-conviene-fisso">4. Quando conviene il prezzo fisso</h2>
<p>Il prezzo fisso è come un'<strong>assicurazione</strong> contro i rialzi del mercato. Conviene in queste situazioni:</p>

<div class="card">
<table>
<thead><tr><th>Scenario</th><th>Perché conviene il fisso</th></tr></thead>
<tbody>
<tr><td><strong>Mercato in salita</strong></td><td>Se i prezzi all'ingrosso stanno salendo (PUN in aumento), bloccare il prezzo oggi ti protegge dai futuri rincari</td></tr>
<tr><td><strong>Bassa propensione al rischio</strong></td><td>Se l'idea di una bolletta che cambia ogni mese ti stressa, il fisso è la scelta giusta</td></tr>
<tr><td><strong>Budget familiare rigido</strong></td><td>Se hai bisogno di prevedere esattamente la spesa energetica, il prezzo fisso ti dà questa certezza</td></tr>
<tr><td><strong>Tassi di interesse in salita</strong></td><td>Storicamente, tassi alti spingono al rialzo i prezzi delle materie prime, energia inclusa</td></tr>
<tr><td><strong>Crisi geopolitiche</strong></td><td>Eventi come guerre o sanzioni possono far impennare i prezzi overnight</td></tr>
</tbody>
</table>
</div>

<h2 id="quando-conviene-indicizzato">5. Quando conviene il prezzo indicizzato</h2>
<p>Il prezzo indicizzato è come <strong>comprare direttamente all'ingrosso</strong>. Conviene in queste situazioni:</p>

<div class="card">
<table>
<thead><tr><th>Scenario</th><th>Perché conviene l'indicizzato</th></tr></thead>
<tbody>
<tr><td><strong>Mercato in discesa</strong></td><td>Se il PUN/PSV sta calando, l'indicizzato ti fa beneficiare subito dei ribassi, senza aspettare la scadenza del contratto fisso</td></tr>
<tr><td><strong>Propensione al rischio</strong></td><td>Se sei disposto ad accettare variazioni mensili pur di pagare meno in media sul lungo periodo</td></tr>
<tr><td><strong>Flessibilità</strong></td><td>Se vuoi poter cambiare fornitore senza penali in qualsiasi momento</td></tr>
<tr><td><strong>Mercato stabile o saturo</strong></td><td>In fasi di mercato con prezzi bassi e stabili (come 2024-2026), l'indicizzato performa meglio del fisso</td></tr>
<tr><td><strong>Consumi elevati</strong></td><td>Più consumi, più il risparmio di 0,01-0,02 €/kWh si traduce in centinaia di euro risparmiati all'anno</td></tr>
</tbody>
</table>
</div>

<h2 id="simulazione-storica">6. Simulazione storica su dati PUN 2022-2026</h2>
<p>Vediamo cosa sarebbe successo negli ultimi 5 anni scegliendo un'offerta fissa vs una indicizzata. I dati sono basati sul <strong>PUN medio annuo reale</strong> e su spread/offerte fisse tipiche del periodo.</p>

<div class="card">
<h3>Scenario: consumo luce 2.700 kWh/anno, potenza 3 kW</h3>
<p>Ipotesi: offerta fissa 0,14 €/kWh bloccata a gennaio di ogni anno; offerta indicizzata con spread 0,02 €/kWh (PUN × 1,102 + spread).</p>
<table>
<thead><tr><th>Anno</th><th>PUN medio (€/kWh)</th><th>Indicizzato (€/anno)</th><th>Fisso (€/anno)</th><th>Risultato</th></tr></thead>
<tbody>
<tr><td><strong>2022</strong></td><td>0,307</td><td style="color:#ef4444">926 €</td><td style="color:#10b981">738 €</td><td>✅ Vince il <strong>fisso</strong> (-188 €/anno)</td></tr>
<tr><td><strong>2023</strong></td><td>0,127</td><td style="color:#10b981">444 €</td><td style="color:#ef4444">504 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-60 €/anno)</td></tr>
<tr><td><strong>2024</strong></td><td>0,109</td><td style="color:#10b981">409 €</td><td style="color:#ef4444">504 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-95 €/anno)</td></tr>
<tr><td><strong>2025</strong></td><td>0,095</td><td style="color:#10b981">388 €</td><td style="color:#ef4444">504 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-116 €/anno)</td></tr>
<tr><td><strong>2026*</strong></td><td>0,088</td><td style="color:#10b981">377 €</td><td style="color:#ef4444">504 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-127 €/anno)</td></tr>
</tbody>
</table>
<p style="font-size:13px;color:var(--text-muted)">*2026: stima basata su andamento primo semestre (gennaio-giugno 2026). Spread medio 0,02 €/kWh, quota fissa esclusa dal calcolo.</p>
</div>

<blockquote>📊 <strong>Lezione dalla storia:</strong> nel 2022 (crisi energetica post-Ucraina) il fisso ha salvato centinaia di euro. Dal 2023 in poi, con il PUN in calo costante, l'indicizzato ha surclassato il fisso. Dal 2022 al 2026, <strong>l'indicizzato avrebbe fatto risparmiare in 4 anni su 5</strong>.</blockquote>

<div class="card">
<h3>Scenario alternativo: cosa sarebbe successo bloccando il fisso a inizio 2023</h3>
<p>Se avessi bloccato un prezzo fisso a 0,14 €/kWh a gennaio 2023 per 24 mesi, questi sarebbero stati i costi confrontati con l'indicizzato:</p>
<table>
<thead><tr><th>Anno</th><th>Fisso 24 mesi (0,14 €/kWh)</th><th>Indicizzato (2 anni)</th><th>Differenza</th></tr></thead>
<tbody>
<tr><td><strong>2023</strong></td><td>504 €</td><td>444 €</td><td>+60 € (fisso perde)</td></tr>
<tr><td><strong>2024</strong></td><td>504 €</td><td>409 €</td><td>+95 € (fisso perde)</td></tr>
<tr><td><strong>Totale 2 anni</strong></td><td><strong>1.008 €</strong></td><td><strong>853 €</strong></td><td style="color:#ef4444"><strong>+155 € pagati in più col fisso</strong></td></tr>
</tbody>
</table>
<p>Bloccare il prezzo a 0,14 €/kWh nel 2023 avrebbe significato pagare <strong>155 € in più in 2 anni</strong> rispetto a chi aveva scelto l'indicizzato.</p>
</div>

<div class="card" style="background:rgba(59,130,246,.04);border-color:rgba(59,130,246,.15)">
<h3>Simulazione gas (PSV 2022-2026)</h3>
<p>Scenario: consumo 1.400 Smc/anno, spread 0,10 €/Smc. Offerta fissa a 0,45 €/Smc bloccata a gennaio.</p>
<table>
<thead><tr><th>Anno</th><th>PSV medio (€/Smc)</th><th>Indicizzato (€/anno)</th><th>Fisso (€/anno)</th><th>Risultato</th></tr></thead>
<tbody>
<tr><td><strong>2022</strong></td><td>0,824</td><td style="color:#ef4444">1.294 €</td><td style="color:#10b981">770 €</td><td>✅ Vince il <strong>fisso</strong> (-524 €/anno)</td></tr>
<tr><td><strong>2023</strong></td><td>0,378</td><td style="color:#10b981">669 €</td><td style="color:#ef4444">770 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-101 €/anno)</td></tr>
<tr><td><strong>2024</strong></td><td>0,324</td><td style="color:#10b981">594 €</td><td style="color:#ef4444">770 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-176 €/anno)</td></tr>
<tr><td><strong>2025</strong></td><td>0,298</td><td style="color:#10b981">557 €</td><td style="color:#ef4444">770 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-213 €/anno)</td></tr>
<tr><td><strong>2026*</strong></td><td>0,275</td><td style="color:#10b981">525 €</td><td style="color:#ef4444">770 €</td><td>✅ Vince l'<strong>indicizzato</strong> (-245 €/anno)</td></tr>
</tbody>
</table>
<p style="font-size:13px;color:var(--text-muted)">*2026: stima primo semestre. Spread medio 0,10 €/Smc, quota fissa esclusa dal calcolo.</p>
</div>

<h2 id="scontrino-energetico">7. Cosa guardare nello scontrino energetico</h2>
<p>Per confrontare correttamente un'offerta fissa con una indicizzata, devi concentrarti su un solo indicatore: il <strong>costo annuo vendita</strong>, ovvero tutto ciò che dipende dal fornitore. Noi lo chiamiamo <strong>"scontrino energetico"</strong>.</p>

<div class="card" style="background:rgba(16,185,129,.05);border-color:rgba(16,185,129,.15)">
<h3 style="color:#10b981">🧾 Lo scontrino energetico: cosa confrontare</h3>
<p>Il costo annuo che dipende dal fornitore si calcola così:</p>
<blockquote>
<strong>Costo annuo vendita = (prezzo energia × consumo annuo) + quota fissa annua + (eventuali sconti)</strong>
</blockquote>
<table>
<thead><tr><th>Voce</th><th>Cambia tra fornitori?</th><th>Come confrontarla</th></tr></thead>
<tbody>
<tr><td><strong>Prezzo energia</strong> (€/kWh o €/Smc)</td><td>❌ Sì</td><td>Fisso: lo stesso per 12-24 mesi. Indicizzato: varia ogni mese</td></tr>
<tr><td><strong>Quota fissa annua</strong> (€/anno)</td><td>❌ Sì</td><td>Varia da offerta a offerta. Incide poco sui consumi alti, tanto sui bassi</td></tr>
<tr><td><strong>Trasporto e distribuzione</strong></td><td>✅ No</td><td>Uguale per tutti (tariffa ARERA)</td></tr>
<tr><td><strong>Oneri di sistema</strong></td><td>✅ No</td><td>Uguali per tutti (stabiliti dallo Stato)</td></tr>
<tr><td><strong>Accise e IVA</strong></td><td>✅ No</td><td>Uguali per tutti (fissate per legge)</td></tr>
</tbody>
</table>
<p style="margin-top:12px;margin-bottom:0"><strong>Regola d'oro:</strong> ignora trasporto, oneri, accise e IVA. Confronta solo <strong>prezzo energia + quota fissa</strong>. Per le offerte indicizzate, confronta lo <strong>spread</strong> (più è basso, meglio è).</p>
</div>

<div class="card">
<h3>Esempio pratico di confronto fisso vs indicizzato</h3>
<p>Consumo: 2.700 kWh/anno luce. Devi scegliere tra:</p>
<table>
<thead><tr><th>Offerta</th><th>Tipologia</th><th>Prezzo energia</th><th>Quota fissa</th><th>Costo annuo vendita</th></tr></thead>
<tbody>
<tr><td><strong>Offerta A</strong></td><td><span class="tag guida">Fisso</span></td><td>0,13 €/kWh (bloccato 12 mesi)</td><td>120 €/anno</td><td><strong>471 €/anno</strong></td></tr>
<tr><td><strong>Offerta B</strong></td><td><span class="tag">Indicizzato</span></td><td>PUN + 0,018 €/kWh spread</td><td>100 €/anno</td><td><strong>variabile*</strong></td></tr>
</tbody>
</table>
<p style="font-size:13px;color:var(--text-muted)">*Il costo annuo dell'indicizzato dipende dall'andamento del PUN. Con PUN a 0,09 €/kWh: (0,09 × 1,102 + 0,018) × 2.700 + 100 = <strong>430 €/anno</strong>. Con PUN a 0,15 €/kWh: (0,15 × 1,102 + 0,018) × 2.700 + 100 = <strong>595 €/anno</strong>.</p>
<p>L'indicizzato conviene se il PUN resta sotto <strong>0,108 €/kWh</strong> (punto di pareggio). Sopra quella soglia, il fisso è più economico.</p>
</div>

<h2 id="tabella-riassuntiva">8. Tabella riassuntiva: fisso vs indicizzato</h2>

<div class="card">
<table>
<thead><tr><th>Caratteristica</th><th>✅ Prezzo fisso</th><th>📊 Prezzo indicizzato</th></tr></thead>
<tbody>
<tr><td><strong>Certezza del prezzo</strong></td><td>Bloccato per 12-24 mesi</td><td>Varia ogni mese col PUN/PSV</td></tr>
<tr><td><strong>Protezione dai rialzi</strong></td><td>Completa</td><td>Nessuna</td></tr>
<tr><td><strong>Beneficio dai ribassi</strong></td><td>Nessuno (fino alla scadenza)</td><td>Immediato</td></tr>
<tr><td><strong>Penale di uscita</strong></td><td>Possibile (se prevista da contratto)</td><td>Mai</td></tr>
<tr><td><strong>Costo medio (2023-2026)</strong></td><td>Più alto (~0,02-0,04 €/kWh in più)</td><td>Più basso</td></tr>
<tr><td><strong>Costo medio (2022)</strong></td><td>Molto più basso (-188 €/anno)</td><td>Altissimo</td></tr>
<tr><td><strong>Ideale per</strong></td><td>Chi vuole dormire sonni tranquilli</td><td>Chi vuole risparmiare e segue il mercato</td></tr>
<tr><td><strong>Consigliato quando</strong></td><td>Mercato in salita, crisi geopolitiche</td><td>Mercato in calo, stabilità economica</td></tr>
</tbody>
</table>
</div>

<blockquote>🔍 <strong>Consiglio SwitchAI:</strong> la scelta giusta dipende dal momento. Nel 2026, con un PUN in calo costante da 3 anni (da 0,307 a 0,088 €/kWh) e spread competitivi, <strong>l'indicizzato sembra la scelta più razionale</strong>. Ma tieni d'occhio il mercato: se i prezzi dovessero invertire il trend, valuta il passaggio al fisso.</blockquote>

<hr>

<div class="card" style="border-color:rgba(245,158,11,.2);background:rgba(245,158,11,.04);text-align:center">
<h3>🔌 Confronta oltre 500 offerte luce e gas</h3>
<p style="font-size:16px">Su SwitchAI puoi confrontare offerte fisse e indicizzate di 21 fornitori con dati ufficiali ARERA. Inserisci il tuo consumo annuo e scopri quale offerta ti conviene oggi.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Confronta le offerte — è gratuito</a>
<p style="font-size:13px;color:var(--text-muted);margin-top:12px;margin-bottom:0">🔒 Dati non sensibili · Nessuna registrazione · Calcolo trasparente ARERA v4.0</p>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Prezzo fisso o variabile? Guida alla scelta dell\'offerta luce e gas 2026',
    'description' => $description,
    'url' => 'https://www.switchai.it/risorse/prezzo-fisso-vs-indicizzato',
    'author' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
        'url' => 'https://www.switchai.it',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
        'url' => 'https://www.switchai.it',
    ],
    'datePublished' => '2026-06-23',
    'dateModified' => '2026-06-23',
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => 'https://www.switchai.it/risorse/prezzo-fisso-vs-indicizzato',
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => 'Prezzo fisso vs indicizzato luce e gas',
    ],
    'keywords' => 'prezzo fisso vs indicizzato, offerta fissa o variabile, quale offerta luce conviene, meglio fisso o variabile 2026',
];
require __DIR__ . '/_footer.php';
