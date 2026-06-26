<?php
$title = 'Come funziona la bolletta della luce: guida completa alle voci e al calcolo';
$description = 'Guida completa alla bolletta luce: Materia Energia, Trasporto e gestione contatore, Oneri di sistema, Accise e IVA. Con esempio di calcolo dettagliato per 2700 kWh. Dati ufficiali ARERA v4.0.';
require __DIR__ . '/_header.php';
?>
<h1>Come funziona la bolletta della luce: guida completa</h1>
<p>La <strong>bolletta della luce</strong> in Italia è composta da voci regolate dall'ARERA (Autorità di Regolazione per Energia Reti e Ambiente). Ogni voce ha uno scopo preciso e insieme determinano il <strong>costo totale dell'energia elettrica</strong> che paghi ogni anno. Capire <strong>come funziona la bolletta luce</strong> è il primo passo per confrontare le offerte e risparmiare.</p>

<p>In questa guida analizziamo ogni componente del <strong>costo energia elettrica kWh</strong>, con le costanti ufficiali ARERA aggiornate al 2026 (Delibera 575/2025 e Comunicato Q2 2026). Alla fine trovi un <strong>esempio di calcolo completo</strong> per un utente residente con consumo di 2.700 kWh e potenza 3 kW.</p>

<hr>

<h2>Le voci della bolletta luce</h2>

<p>La <strong>bolletta luce</strong> si compone di <strong>cinque macro-voci</strong>, che trovi elencate in ogni documento di spesa emesso dal fornitore:</p>

<ol>
<li><strong>Materia Energia</strong> — il costo dell'energia elettrica consumata</li>
<li><strong>Trasporto e gestione contatore</strong> — le spese di rete e noleggio contatore</li>
<li><strong>Oneri di sistema</strong> — contributi per incentivi e servizi di sistema</li>
<li><strong>Accise</strong> — imposta erariale sulla produzione di energia elettrica</li>
<li><strong>IVA</strong> — imposta sul valore aggiunto</li>
</ol>

<p>Vediamo ciascuna voce nel dettaglio.</p>

<h2>1. Materia Energia</h2>

<p>La <strong>Materia Energia</strong> è la componente principale della <strong>bolletta luce</strong> e rappresenta il costo effettivo dell'energia elettrica che consumi. Si suddivide in <strong>quota fissa</strong> e <strong>quota variabile</strong>.</p>

<h3>Quota fissa (commercializzazione)</h3>
<p>Ogni fornitore applica un <strong>costo annuo fisso</strong> per la gestione del contratto, chiamato <strong>PCV</strong> (Prezzo Componente Variabile, in realtà è il costo di commercializzazione) o <strong>CMC</strong> (Componente di Commercializzazione). Copre le spese amministrative, l'assistenza clienti e la gestione del rapporto contrattuale. Varia da offerta a offerta, tipicamente tra <strong>60 e 180 €/anno</strong>.</p>

<h3>Quota variabile (€/kWh)</h3>
<p>La <strong>quota variabile</strong> si applica a ogni kWh consumato. Il prezzo unitario può essere:</p>

<div class="card">
<h3>Monoraria vs Bioraria</h3>
<table>
<thead>
<tr><th>Tipo</th><th>Come funziona</th><th>Quando conviene</th></tr>
</thead>
<tbody>
<tr><td><strong>Monoraria</strong> (F0)</td><td>Stesso prezzo 24 ore su 24, tutti i giorni</td><td>Consumo uniforme durante la giornata</td></tr>
<tr><td><strong>Bioraria</strong> (F1/F23)</td><td>Prezzo diverso tra fascia di punta (F1, 8-19 feriali) e fascia fuori punta (F23, notte e weekend)</td><td>Chi consuma principalmente la sera e nei weekend</td></tr>
</tbody>
</table>
</div>

<div class="card">
<h3>Prezzo fisso vs indicizzato</h3>
<table>
<thead>
<tr><th>Tipo</th><th>Come funziona</th><th>Vantaggi</th><th>Svantaggi</th></tr>
</thead>
<tbody>
<tr>
<td><strong>Prezzo fisso</strong></td>
<td>Il prezzo €/kWh è bloccato per 12-24 mesi</td>
<td>Protezione dai rialzi del mercato, spesa prevedibile</td>
<td>Non benefici dei ribassi del mercato</td>
</tr>
<tr>
<td><strong>Prezzo indicizzato (PUN)</strong></td>
<td>Il prezzo segue l'andamento del PUN (Prezzo Unico Nazionale) maggiorato di uno spread</td>
<td>Prezzo sempre allineato al mercato, potenzialmente più basso</td>
<td>Variabilità imprevedibile, rischio in caso di rialzi</td>
</tr>
</tbody>
</table>
<blockquote>
<strong>Attenzione:</strong> Nelle offerte indicizzate, il coefficiente di <strong>perdite di rete</strong> (λ = 1,102) si applica solo al PUN e non allo spread, secondo le regole ARERA v4.0. Quindi il prezzo effettivo è: <code>PUN × 1,102 + spread</code>.
</blockquote>
</div>

<h2>2. Trasporto e gestione contatore</h2>

<p>Questa voce finanzia la <strong>rete di distribuzione elettrica</strong> (cavi, trasformatori, contatori) gestita dal distributore locale (e-distribuzione, Areti, Unareti, ecc.). È composta da tre parti:</p>

<table>
<thead>
<tr><th>Componente</th><th>Valore ARERA (2026)</th><th>Descrizione</th></tr>
</thead>
<tbody>
<tr>
<td><strong>Quota fissa</strong></td>
<td><strong>23,04 €/anno</strong></td>
<td>Noleggio e manutenzione del contatore elettronico</td>
</tr>
<tr>
<td><strong>Quota potenza</strong></td>
<td><strong>23,52 €/kW/anno</strong></td>
<td>Costo proporzionale alla potenza impegnata (tipicamente 3 o 4,5 kW)</td>
</tr>
<tr>
<td><strong>Quota variabile</strong></td>
<td><strong>0,01204 €/kWh</strong></td>
<td>Costo di trasporto per ogni kWh effettivamente prelevato dalla rete</td>
</tr>
</tbody>
</table>

<p>Il costo totale di trasporto si calcola come: <code>23,04 + (potenza × 23,52) + (consumo × 0,01204)</code>.</p>

<blockquote>
<strong>Lo sapevi?</strong> La quota potenza è il motivo per cui <strong>aumentare la potenza</strong> del contatore (es. da 3 a 4,5 kW) costa circa 35 €/anno in più in bolletta. Valuta se ti serve davvero prima di richiedere l'aumento.
</blockquote>

<h2>3. Oneri di sistema</h2>

<p>Gli <strong>oneri di sistema</strong> sono voci di costo stabilite dall'ARERA per finanziare attività di interesse generale. Si pagano in proporzione ai kWh consumati e sono <strong>identici per tutti i fornitori</strong> nella stessa zona.</p>

<table>
<thead>
<tr><th>Componente</th><th>Valore (€/kWh)</th><th>Cosa finanzia</th></tr>
</thead>
<tbody>
<tr>
<td><strong>ASOS</strong></td>
<td>0,02866</td>
<td>Incentivi alle fonti rinnovabili (FER) — fotovoltaico, eolico, idroelettrico</td>
</tr>
<tr>
<td><strong>ARIM</strong></td>
<td>0,00164</td>
<td>Ricerca di sistema, copertura di squilibri e servizi di dispacciamento</td>
</tr>
<tr>
<td><strong>Totale</strong></td>
<td><strong>0,03030</strong></td>
<td>&nbsp;</td>
</tr>
</tbody>
</table>

<blockquote>
<strong>Curiosità:</strong> La componente ASOS rappresenta circa il 15-20% del costo totale di una <strong>bolletta luce</strong> media. Finanzia gli incentivi per impianti rinnovabili (Conto Energia, DM FER). Negli ultimi anni è gradualmente scesa grazie alla riduzione dei nuovi incentivi.
</blockquote>

<h2>4. Accise</h2>

<p>L'<strong>accisa sull'energia elettrica</strong> è un'imposta erariale stabilita dal Testo Unico Accise (DL 504/1995). Si applica ai kWh consumati oltre una <strong>soglia di esenzione</strong> per le utenze domestiche residenziali.</p>

<table>
<thead>
<tr><th>Parametro</th><th>Valore</th><th>Descrizione</th></tr>
</thead>
<tbody>
<tr><td><strong>Aliquota accise</strong></td><td>0,0227 €/kWh</td><td>Imposta per ogni kWh eccedente la franchigia</td></tr>
<tr><td><strong>Soglia esente</strong></td><td>1.800 kWh/anno</td><td>Primi kWh esenti per utenze domestiche residenti</td></tr>
<tr><td><strong>Soglia compensata</strong></td><td>2.640 kWh/anno</td><td>Oltre questa soglia l'esenzione si riduce gradualmente</td></tr>
</tbody>
</table>

<h3>Come si calcolano le accise</h3>

<p>Per un'utenza domestica <strong>residente</strong> con potenza ≤ 3 kW, il calcolo segue queste regole:</p>

<ol>
<li>I primi <strong>1.800 kWh</strong> sono esenti da accise</li>
<li>Se il consumo supera <strong>2.640 kWh</strong>, l'esenzione diminuisce di <code>(consumo - 2.640)</code> kWh</li>
<li>L'accisa si paga sui kWh residui: <code>(consumo - esenzione_netta) × 0,0227</code></li>
</ol>

<div class="card">
<h3>Esempi pratici accise</h3>
<table>
<thead>
<tr><th>Consumo annuo</th><th>Esenzione netta</th><th>kWh tassati</th><th>Accisa annua</th></tr>
</thead>
<tbody>
<tr><td>1.500 kWh</td><td>1.500 kWh</td><td>0 kWh</td><td><strong>0,00 €</strong></td></tr>
<tr><td>2.200 kWh</td><td>1.800 kWh</td><td>400 kWh</td><td><strong>9,08 €</strong></td></tr>
<tr><td>2.700 kWh</td><td>1.740 kWh</td><td>960 kWh</td><td><strong>21,79 €</strong></td></tr>
<tr><td>3.500 kWh</td><td>1.800 - (3.500-2.640) = 940 kWh</td><td>2.560 kWh</td><td><strong>58,11 €</strong></td></tr>
</tbody>
</table>
</div>

<h2>5. IVA</h2>

<p>L'<strong>IVA</strong> (Imposta sul Valore Aggiunto) si applica al totale imponibile, ovvero la somma di tutte le voci precedenti (Materia Energia + Trasporto + Oneri + Accise).</p>

<table>
<thead>
<tr><th>Tipologia utenza</th><th>Aliquota IVA</th></tr>
</thead>
<tbody>
<tr><td><strong>Uso domestico residente</strong> (consumo ≤ 1.800 kWh/anno)</td><td>10%</td></tr>
<tr><td><strong>Uso domestico residente</strong> (consumo > 1.800 kWh/anno)</td><td>10%*</td></tr>
<tr><td><strong>Uso domestico non residente</strong></td><td>22%</td></tr>
<tr><td><strong>Uso commerciale/industriale</strong></td><td>22%</td></tr>
</tbody>
</table>
<p><em>* Per le utenze domestiche <strong>residenti</strong> l'IVA agevolata al 10% si applica sull'intero importo, indipendentemente dal consumo. Le soglie IVA sono gestite diversamente per il gas.</em></p>

<h2>Calcolo completo esempio: 2.700 kWh / 3 kW residente</h2>

<p>Mettiamo insieme tutte le voci per calcolare la <strong>spesa annua</strong> di un'utenza domestica <strong>residente</strong> con consumo 2.700 kWh e potenza 3 kW, utilizzando un'offerta a <strong>prezzo fisso 0,12 €/kWh</strong> e quota fissa commercializzazione <strong>120 €/anno</strong>.</p>

<div class="card">
<h3>Dati dell'esempio</h3>
<table>
<thead><tr><th>Parametro</th><th>Valore</th></tr></thead>
<tbody>
<tr><td>Consumo annuo</td><td>2.700 kWh</td></tr>
<tr><td>Potenza impegnata</td><td>3 kW</td></tr>
<tr><td>Prezzo energia (fisso monorario)</td><td>0,12 €/kWh</td></tr>
<tr><td>Quota fissa commercializzazione</td><td>120 €/anno</td></tr>
<tr><td>Residente ≤ 3 kW</td><td>Sì</td></tr>
</tbody>
</table>
</div>

<h3>Step 1 — Materia Energia</h3>
<table>
<thead><tr><th>Sotto-voce</th><th>Calcolo</th><th>Importo</th></tr></thead>
<tbody>
<tr><td>Quota fissa (commercializzazione)</td><td>—</td><td>120,00 €</td></tr>
<tr><td>Quota variabile</td><td>2.700 kWh × 0,12 €/kWh</td><td>324,00 €</td></tr>
<tr><td><strong>Totale Materia Energia</strong></td><td></td><td><strong>444,00 €</strong></td></tr>
</tbody>
</table>

<h3>Step 2 — Trasporto e gestione contatore</h3>
<table>
<thead><tr><th>Sotto-voce</th><th>Calcolo</th><th>Importo</th></tr></thead>
<tbody>
<tr><td>Quota fissa</td><td>23,04 €/anno</td><td>23,04 €</td></tr>
<tr><td>Quota potenza</td><td>3 kW × 23,52 €/kW/anno</td><td>70,56 €</td></tr>
<tr><td>Quota variabile</td><td>2.700 × 0,01204 €/kWh</td><td>32,51 €</td></tr>
<tr><td><strong>Totale Trasporto</strong></td><td></td><td><strong>126,11 €</strong></td></tr>
</tbody>
</table>

<h3>Step 3 — Oneri di sistema</h3>
<table>
<thead><tr><th>Sotto-voce</th><th>Calcolo</th><th>Importo</th></tr></thead>
<tbody>
<tr><td>ASOS + ARIM</td><td>2.700 × 0,03030 €/kWh</td><td><strong>81,81 €</strong></td></tr>
</tbody>
</table>

<h3>Step 4 — Accise</h3>
<table>
<thead><tr><th>Passaggio</th><th>Calcolo</th><th>Valore</th></tr></thead>
<tbody>
<tr><td>Esenzione base</td><td>1.800 kWh</td><td>1.800 kWh</td></tr>
<tr><td>Compensazione oltre 2.640</td><td>2.700 - 2.640 = 60 kWh</td><td>-60 kWh</td></tr>
<tr><td>Esenzione netta</td><td>1.800 - 60</td><td>1.740 kWh</td></tr>
<tr><td>Base imponibile accise</td><td>2.700 - 1.740</td><td>960 kWh</td></tr>
<tr><td>Accisa totale</td><td>960 × 0,0227</td><td><strong>21,79 €</strong></td></tr>
</tbody>
</table>

<h3>Step 5 — IVA</h3>
<table>
<thead><tr><th>Voce</th><th>Importo</th></tr></thead>
<tbody>
<tr><td>Materia Energia</td><td>444,00 €</td></tr>
<tr><td>Trasporto e gestione contatore</td><td>126,11 €</td></tr>
<tr><td>Oneri di sistema</td><td>81,81 €</td></tr>
<tr><td>Accise</td><td>21,79 €</td></tr>
<tr><td><strong>Imponibile totale</strong></td><td><strong>673,71 €</strong></td></tr>
<tr><td>IVA 10%</td><td><strong>67,37 €</strong></td></tr>
</tbody>
</table>

<h3>Riepilogo finale</h3>
<table>
<thead><tr><th>Voce</th><th>Importo annuo</th><th>% sul totale</th></tr></thead>
<tbody>
<tr><td>Materia Energia</td><td>444,00 €</td><td>59,9%</td></tr>
<tr><td>Trasporto e gestione contatore</td><td>126,11 €</td><td>17,0%</td></tr>
<tr><td>Oneri di sistema</td><td>81,81 €</td><td>11,0%</td></tr>
<tr><td>Accise</td><td>21,79 €</td><td>2,9%</td></tr>
<tr><td>IVA 10%</td><td>67,37 €</td><td>9,1%</td></tr>
<tr><td><strong>Totale spesa annua</strong></td><td><strong>741,08 €</strong></td><td><strong>100%</strong></td></tr>
</tbody>
</table>

<blockquote>
<strong>Nota:</strong> Il costo medio per kWh in questo esempio è di <strong>0,274 €/kWh</strong> (741,08 € ÷ 2.700 kWh). Di questi, solo 0,12 €/kWh (44%) vanno al fornitore per l'energia: il resto sono costi di rete, imposte e oneri fissi.
</blockquote>

<h2>Come risparmiare sulla bolletta della luce</h2>

<p>Ora che sai <strong>come funziona la bolletta luce</strong> e conosci tutte le <strong>voci della bolletta luce</strong>, ecco tre strategie per ridurre la spesa:</p>

<ol>
<li><strong>Confronta le offerte</strong> — Il <strong>costo energia elettrica kWh</strong> varia molto tra i fornitori. Con i dati ufficiali ARERA, puoi confrontare oltre 500 offerte luce in pochi secondi.</li>
<li><strong>Scegli la potenza giusta</strong> — Se hai un contatore da 4,5 o 6 kW ma non ne hai bisogno, chiedere la riduzione a 3 kW ti fa risparmiare 35-70 €/anno di quota potenza.</li>
<li><strong>Riduci i consumi</strong> — Elettrodomestici in classe A, illuminazione a LED e stand-by ridotto abbassano il consumo e quindi tutte le voci variabili della bolletta.</li>
</ol>

<div class="card" style="border-color:rgba(16,185,129,.2);background:rgba(16,185,129,.03)">
<h3>🔌 Confronta le offerte luce con dati ARERA reali</h3>
<p>SwitchAI analizza oltre <strong>500 offerte luce</strong> di 21 fornitori con i dati ufficiali ARERA. Scopri quanto puoi risparmiare rispetto alla tua bolletta attuale.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Confronta le offerte luce</a>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Come funziona la bolletta della luce: guida completa alle voci e al calcolo',
    'description' => 'Guida completa alla bolletta luce: Materia Energia, Trasporto e gestione contatore, Oneri di sistema, Accise e IVA. Con esempio di calcolo dettagliato per 2700 kWh. Dati ufficiali ARERA v4.0.',
    'author' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
    ],
    'datePublished' => '2026-06-23',
    'dateModified' => '2026-06-23',
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => 'Bolletta luce Italia',
        'description' => 'Voci della bolletta elettrica secondo la regolazione ARERA',
    ],
];
require __DIR__ . '/_footer.php';
