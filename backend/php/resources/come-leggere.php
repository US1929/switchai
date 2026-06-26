<?php
$title = 'Come leggere la bolletta della luce e del gas: guida visiva completa';
$description = 'Guida visiva con immagini testuali su come leggere la bolletta luce e gas. Dove trovare POD, PDR, consumo annuo, spesa, prezzo energia e tipologia offerta.';
$canonical = 'https://www.switchai.it/risorse/come-leggere-bolletta';
require __DIR__ . '/_header.php';
?>
<h1>Come leggere la bolletta della luce e del gas</h1>
<p style="font-size:17px;color:var(--text-secondary);margin-bottom:24px">Guida visiva completa per capire ogni voce della bolletta, trovare i dati giusti e confrontare le offerte senza errori. Scritta con le regole ufficiali ARERA v4.0.</p>

<div class="card" style="background:rgba(167,139,250,.06);border-color:rgba(167,139,250,.15)">
<p style="margin-bottom:0">📌 <strong>In breve:</strong> per confrontare le offerte ti servono solo <strong>3 dati</strong>: consumo annuo, indirizzo (o zona) e tipologia offerta attuale. Il prezzo dell'energia è l'unica voce che cambia tra fornitori.</p>
</div>

<h2 id="dati-contratto">1. Dati del contratto</h2>
<p>I dati identificativi della fornitura si trovano quasi sempre nella <strong>prima pagina della bolletta</strong>, nell'intestazione o in un riquadro "Dati del contratto" o "Dati della fornitura".</p>

<div class="card">
<h3>Luce — cosa cercare</h3>
<table>
<thead><tr><th>Dato</th><th>Dove trovarlo</th><th>Esempio</th></tr></thead>
<tbody>
<tr><td><strong>POD</strong> (codice punto)</td><td>In alto a destra o in "Dati fornitura"<br>Formato: IT001E + 11 cifre</td><td><code>IT001E12345678901</code></td></tr>
<tr><td><strong>Fornitore attuale</strong></td><td>Logo e ragione sociale in testata</td><td>Enel Energia, Edison, A2A…</td></tr>
<tr><td><strong>Potenza impegnata</strong></td><td>Vicino a "Potenza disponibile" o "kW"</td><td><code>3,0 kW</code> (standard domestico)</td></tr>
<tr><td><strong>Indirizzo fornitura</strong></td><td>Subito sotto i dati anagrafici</td><td>Via Roma 1, 20100 Milano</td></tr>
</tbody>
</table>
</div>

<div class="card">
<h3>Gas — cosa cercare</h3>
<table>
<thead><tr><th>Dato</th><th>Dove trovarlo</th><th>Esempio</th></tr></thead>
<tbody>
<tr><td><strong>PDR</strong> (codice punto gas)</td><td>In alto a destra, codice di 14 cifre</td><td><code>12345678901234</code></td></tr>
<tr><td><strong>Fornitore attuale</strong></td><td>Logo e ragione sociale in testata</td><td>Eni, Estra, Hera…</td></tr>
<tr><td><strong>Categoria d'uso</strong></td><td>Vicino a "Categoria" o "Tipo fornitura"</td><td><code>Usi domestici</code> / <code>Riscaldamento</code></td></tr>
<tr><td><strong>Indirizzo fornitura</strong></td><td>Subito sotto i dati anagrafici</td><td>Via Garibaldi 5, 10100 Torino</td></tr>
</tbody>
</table>
</div>

<blockquote>💡 <strong>Consiglio:</strong> il POD (luce) e il PDR (gas) sono i codici identificativi del tuo punto di fornitura. Cambiano solo se ti trasferisci. Sono utili per verificare la compatibilità delle offerte, ma <strong>non servono per il confronto prezzi</strong>.</blockquote>

<h2 id="consumo-annuo">2. Consumo annuo</h2>
<p>Il consumo annuo è il dato <strong>più importante</strong> per calcolare la spesa e confrontare le offerte. Lo trovi in due modi.</p>

<div class="card">
<h3>Consumo annuo stimato</h3>
<p>In quasi tutte le bollette, di solito nella sezione "Consumi" o "Dettaglio consumi", trovi una riga chiamata <strong>"Consumo annuo stimato"</strong> o <strong>"Consumo annuo di riferimento"</strong>. È il valore che il tuo fornitore stima consumerai in un anno sulla base dei tuoi storici.</p>
<table>
<thead><tr><th>Tipologia</th><th>Unità</th><th>Tipico domestico</th></tr></thead>
<tbody>
<tr><td>Luce</td><td>kWh</td><td>1.200 – 3.500 kWh/anno</td></tr>
<tr><td>Gas</td><td>Smc</td><td>700 – 1.800 Smc/anno</td></tr>
</tbody>
</table>
</div>

<div class="card">
<h3>Storico consumi (bolletta singola)</h3>
<p>Se non trovi il consumo annuo stimato, puoi calcolarlo da una bolletta singola, ma attenzione: una bolletta luce copre in genere <strong>1-2 mesi</strong> (consumo parziale), mentre quella del gas è fortemente stagionale. Per la luce puoi moltiplicare il consumo mensile per 8-10 (non 12: i mesi estivi consumano meno). Per il gas, il consumo invernale è 3-5 volte quello estivo.</p>
<blockquote>⚠️ <strong>Attenzione:</strong> usare il consumo di una singola bolletta per stimare l'annualità porta a errori. Cerca sempre il "consumo annuo stimato" in bolletta, o usa lo storico di 12 mesi se hai tutte le bollette. Su <a href="/">SwitchAI</a> puoi inserire il consumo che trovi in bolletta.</blockquote>
</div>

<h2 id="spesa-annua">3. Spesa annua</h2>
<p>La spesa annua è il totale che paghi in un anno. Alcune bollette lo mostrano già come <strong>"Spesa annua"</strong> o <strong>"Costo annuo"</strong> nella sezione riepilogativa. Altre bollette mostrano solo la spesa del periodo.</p>

<div class="card">
<h3>Da spesa del periodo a spesa annua</h3>
<p>Se hai solo la spesa di una bolletta singola, puoi stimare la spesa annua cosi:</p>
<table>
<thead><tr><th>Se hai</th><th>Calcolo approssimativo</th></tr></thead>
<tbody>
<tr><td>Bolletta luce bimestrale</td><td>Spesa × 6 (ma consumerai meno d'estate)</td></tr>
<tr><td>Bolletta gas mensile (inverno)</td><td>Spesa × 3 o 4 (il resto dell'anno è molto meno)</td></tr>
<tr><td>Bolletta gas mensile (estate)</td><td>Spesa × 12 (sottostimerai il costo invernale)</td></tr>
</tbody>
</table>
<blockquote>💡 <strong>Consiglio:</strong> il metodo più preciso è usare il <strong>consumo annuo stimato</strong> e applicare il tuo prezzo per kWh/Smc. Su SwitchAI puoi inserire consumo annuo e prezzo attuale e il calcolo è automatico.</blockquote>
</div>

<h2 id="prezzo-energia">4. Prezzo energia</h2>
<p>Il prezzo dell'energia è la voce che cambia tra un fornitore e l'altro. Per la luce si misura in <strong>€/kWh</strong>, per il gas in <strong>€/Smc</strong>.</p>

<div class="card">
<h3>Luce: scomporre il prezzo</h3>
<p>Cerca la sezione <strong>"Spesa per la materia energia"</strong> o <strong>"Spesa per il consumo"</strong>. Il prezzo è composto da:</p>
<table>
<thead><tr><th>Voce</th><th>Descrizione</th></tr></thead>
<tbody>
<tr><td><strong>Prezzo PUN</strong></td><td>Se l'offerta è indicizzata, trovi il PUN del mese (€/kWh)</td></tr>
<tr><td><strong>Spread</strong> o <strong>Componente fissa</strong></td><td>Il margine del fornitore in €/kWh (es. 0,020 €/kWh)</td></tr>
<tr><td><strong>Quota fissa</strong></td><td>Costo annuo in €/anno (es. 120 €/anno), indipendentemente dai consumi</td></tr>
<tr><td><strong>Prezzo finale</strong></td><td>PUN × 1,102 (perdite rete) + spread + quota fissa/consumo</td></tr>
</tbody>
</table>
</div>

<div class="card">
<h3>Gas: scomporre il prezzo</h3>
<p>Per il gas il meccanismo è analogo. Cerca la <strong>"Spesa per la materia prima"</strong>:</p>
<table>
<thead><tr><th>Voce</th><th>Descrizione</th></tr></thead>
<tbody>
<tr><td><strong>Prezzo PSV</strong></td><td>Se indicizzato, trovi il PSV del mese (€/Smc)</td></tr>
<tr><td><strong>Spread</strong></td><td>Il margine del fornitore in €/Smc (es. 0,12 €/Smc)</td></tr>
<tr><td><strong>Quota fissa</strong></td><td>Costo annuo in €/anno</td></tr>
</tbody>
</table>
</div>

<blockquote>📌 <strong>Ricorda:</strong> il prezzo della materia energia (€/kWh luce o €/Smc gas) è l'unica voce che puoi confrontare tra le offerte. Trasporto, oneri, accise e IVA sono uguali per tutti.</blockquote>

<h2 id="tipologia-offerta">5. Tipologia offerta</h2>
<p>La tipologia dell'offerta attuale è fondamentale per scegliere la nuova offerta: se hai un'offerta a prezzo fisso e confronti con una indicizzata, il confronto non è corretto.</p>

<div class="card">
<h3>Prezzo fisso vs indicizzato</h3>
<table>
<thead><tr><th>Tipologia</th><th>Come riconoscerla in bolletta</th></tr></thead>
<tbody>
<tr><td><strong>Prezzo fisso</strong></td><td>Trovi una riga "Prezzo: 0,12 €/kWh" o "Prezzo bloccato per 12/24 mesi". Il prezzo non cambia ogni mese.</td></tr>
<tr><td><strong>Prezzo indicizzato</strong></td><td>Trovi scritto "PUN mese precedente" o "Indice del mese" o "Prezzo variabile". Il prezzo cambia ogni mese in base al mercato.</td></tr>
</tbody>
</table>
</div>

<div class="card">
<h3>Monoraria vs bioraria</h3>
<table>
<thead><tr><th>Tipologia</th><th>Come riconoscerla</th></tr></thead>
<tbody>
<tr><td><strong>Monoraria (F0)</strong></td><td>Stesso prezzo tutte le ore. In bolletta vedi un unico prezzo per kWh.</td></tr>
<tr><td><strong>Bioraria (F1/F2/F3)</strong></td><td>Prezzi diversi a seconda dell'ora: F1 (lun-ven 8-19) più caro, F2/F3 più economici. In bolletta vedi consumi divisi per fascia.</td></tr>
</tbody>
</table>
<blockquote>💡 <strong>Consiglio:</strong> la maggior parte delle offerte oggi è monoraria. Se hai un'offerta bioraria e consumi molto in fascia F1, potresti risparmiare passando a monoraria. Se consumi poco in F1, la bioraria può essere più conveniente.</blockquote>
</div>

<h2 id="struttura-bolletta">6. La struttura della bolletta ARERA 2.0</h2>
<p>Dal 2024, le bollette luce e gas seguono uno schema standard imposto da ARERA. Le voci sono organizzate in <strong>tre grandi aree</strong>:</p>

<div class="card">
<table>
<thead><tr><th>Sezione</th><th>Cosa include</th><th>% tipica sulla spesa</th></tr></thead>
<tbody>
<tr><td><strong>Spesa per la materia energia</strong></td><td>Prezzo energia + dispacciamento + perdite di rete (solo luce)</td><td>40-55%</td></tr>
<tr><td><strong>Spesa per il trasporto e la gestione del contatore</strong></td><td>Trasporto, distribuzione, gestione contatore</td><td>15-25%</td></tr>
<tr><td><strong>Spesa per oneri di sistema</strong></td><td>Oneri generali (ASIM, UC4, MCT, contributi rinnovabili)</td><td>5-10%</td></tr>
<tr><td><strong>Accise e IVA</strong></td><td>Imposte: accise luce/gas, addizionale regionale gas, IVA</td><td>15-25%</td></tr>
</tbody>
</table>
</div>

<p>La prima sezione (<strong>materia energia</strong>) è l'unica che cambi quando cambi fornitore. Le altre voci sono regolate da ARERA e sono identiche per tutti i clienti dello stesso profilo (stessa zona, stessa potenza, stessi consumi).</p>

<h3>Spesa per la materia energia — nel dettaglio</h3>
<p>Nella bolletta, questa sezione è di solito la prima voce del dettaglio spesa. Cerca "Materia energia" o "Spesa per la materia energia". Qui dentro trovi:</p>
<ul>
<li><strong>Quota fissa</strong> (€/anno) — decisa dal fornitore</li>
<li><strong>Quota energia</strong> (€/kWh o €/Smc) — il prezzo puro dell'energia</li>
<li><strong>Dispacciamento</strong> (solo luce) — costo per bilanciamento rete, incluso nel prezzo finale</li>
<li><strong>Perdite di rete</strong> (solo luce) — maggiorazione del 10,2% sul PUN, obbligatoria per legge</li>
</ul>

<h2 id="cosa-non-serve">7. Cosa NON serve confrontare</h2>
<p>Questa è la parte più importante: <strong>non tutte le voci della bolletta cambiano tra fornitori</strong>. Confrontarle è tempo perso.</p>

<div class="card">
<table>
<thead><tr><th>Voce</th><th>È uguale per tutti?</th><th>Perché</th></tr></thead>
<tbody>
<tr><td><strong>Trasporto e distribuzione</strong></td><td>✅ Sì</td><td>Tariffa unica ARERA per zona</td></tr>
<tr><td><strong>Gestione contatore</strong></td><td>✅ Sì</td><td>Tariffa unica ARERA</td></tr>
<tr><td><strong>Oneri di sistema</strong></td><td>✅ Sì</td><td>Decisi dallo Stato, uguali per tutti</td></tr>
<tr><td><strong>Accise</strong></td><td>✅ Sì</td><td>Fissate per legge (€/kWh o €/Smc)</td></tr>
<tr><td><strong>Addizionale regionale (gas)</strong></td><td>✅ Sì</td><td>Fissata per regione</td></tr>
<tr><td><strong>IVA</strong></td><td>✅ Sì</td><td>10% (luce e gas usi domestici)</td></tr>
<tr><td><strong>Materia energia (prezzo)</strong></td><td>❌ <strong>No</strong></td><td>Deciso da ogni fornitore — <strong>unico vero differenziale</strong></td></tr>
</tbody>
</table>
</div>

<blockquote>✅ <strong>Regola d'oro:</strong> quando confronti due offerte, ignora trasporto, oneri, accise e IVA. Concentrati solo sul <strong>prezzo della materia energia</strong> (€/kWh o €/Smc) e sulla <strong>quota fissa annua</strong>.</blockquote>

<h2 id="scontrino-energetico">8. Lo "scontrino energetico"</h2>
<p>Noi di SwitchAI chiamiamo <strong>"scontrino energetico"</strong> l'insieme delle voci che cambiano tra fornitori: materia energia (prezzo unitario) + quota fissa annua. È l'unica cosa che devi confrontare, esattamente come quando confronti il prezzo al chilo tra due supermercati.</p>

<div class="card" style="background:rgba(16,185,129,.05);border-color:rgba(16,185,129,.15)">
<h3 style="color:#10b981">🧾 Lo scontrino energetico</h3>
<table>
<thead><tr><th>Voce confrontabile</th><th>Dove trovarla in bolletta</th></tr></thead>
<tbody>
<tr><td><strong>Prezzo materia energia luce</strong> (€/kWh)</td><td>Sezione "Materia energia" → voce "Quota energia"</td></tr>
<tr><td><strong>Prezzo materia prima gas</strong> (€/Smc)</td><td>Sezione "Materia prima" → voce "Quota energia"</td></tr>
<tr><td><strong>Quota fissa annua</strong> (€/anno)</td><td>Sempre in "Materia energia", voce "Quota fissa"</td></tr>
<tr><td><strong>Tipologia offerta</strong> (fisso/variabile)</td><td>Verificare se il prezzo cambia ogni mese</td></tr>
</tbody>
</table>
<p style="margin-top:12px;margin-bottom:0">Tutto il resto (trasporto, oneri, accise, IVA) è <strong>identico per tutti i fornitori</strong> sullo stesso profilo di consumo. Puoi ignorarlo completamente.</p>
</div>

<h3>Esempio pratico</h3>
<p>Il tuo fornitore attuale ti fa pagare <strong>0,14 €/kWh</strong> + 120 €/anno di quota fissa. Un altro fornitore propone <strong>0,11 €/kWh</strong> + 150 €/anno di quota fissa. Quale conviene?</p>
<p>Se consumi 2.700 kWh/anno:</p>
<ul>
<li><strong>Fornitore A:</strong> (2.700 × 0,14) + 120 = 378 + 120 = <strong>498 €/anno</strong></li>
<li><strong>Fornitore B:</strong> (2.700 × 0,11) + 150 = 297 + 150 = <strong>447 €/anno</strong></li>
</ul>
<p>Risultato: il fornitore B ha un prezzo unitario più basso e, nonostante la quota fissa più alta, ti fa risparmiare <strong>51 €/anno</strong>.</p>

<hr>

<div class="card" style="border-color:rgba(245,158,11,.2);background:rgba(245,158,11,.04);text-align:center">
<h3>🔌 Confronta le offerte con dati ARERA reali</h3>
<p style="font-size:16px">Su SwitchAI puoi confrontare oltre 500 offerte luce e gas aggiornate con i dati ufficiali ARERA. Inserisci il consumo annuo e scopri quanto puoi risparmiare.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Confronta ora — è gratuito</a>
<p style="font-size:13px;color:var(--text-muted);margin-top:12px;margin-bottom:0">🔒 Dati non sensibili · Nessuna registrazione · Calcolo trasparente</p>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Come leggere la bolletta della luce e del gas: guida visiva completa',
    'description' => $description,
    'url' => 'https://www.switchai.it/risorse/come-leggere-bolletta',
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
    'datePublished' => '2026-02-15',
    'dateModified' => '2026-06-23',
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => 'https://www.switchai.it/risorse/come-leggere-bolletta',
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => 'Bolletta luce e gas',
    ],
    'keywords' => 'leggere bolletta luce, capire bolletta energia, dove trovare POD bolletta, come leggere bolletta gas',
];
require __DIR__ . '/_footer.php';
