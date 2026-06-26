<?php
$title = 'Come si calcola la spesa annua di luce e gas: formula ARERA v4.0';
$description = 'La formula ufficiale ARERA per il calcolo della spesa annua stimata. Costanti regolatorie, perdite di rete, accise, IVA spiegate passo passo con esempi.';
require __DIR__ . '/_header.php';
?>

<h1>Come si calcola la spesa annua di luce e gas</h1>

<p class="badge" style="margin-bottom:16px"><span style="font-size:14px">&#8205;</span> Aggiornato al Q2 2026 — ARERA v4.0</p>

<p>La <strong>spesa annua stimata</strong> è il parametro fondamentale per confrontare le offerte luce e gas nel mercato libero italiano. Segue le regole definite dall'ARERA (Autorità di Regolazione per Energia Reti e Ambiente) nel documento <em>"Regole per il calcolo della Spesa Annua Stimata"</em> versione 4.0 (Febbraio 2026).</p>

<p>In questa guida ti spieghiamo <strong>come calcolare la bolletta della luce e del gas</strong> usando la formula ufficiale ARERA, con tutte le costanti regolatorie aggiornate e due esempi numerici completi.</p>

<hr>

<h2>Formula generale ARERA</h2>

<p>La <strong>spesa annua</strong> è la somma di cinque macro-componenti:</p>

<div class="card" style="text-align:center;font-size:18px;padding:20px">
<strong>Spesa Annua = Materia Energia + Trasporto + Oneri di Sistema + Accise + IVA</strong>
</div>

<p>Ogni componente ha una formula specifica e costanti regolatorie aggiornate periodicamente dall'ARERA. SwitchAI utilizza esattamente queste formule per calcolare il costo di ogni offerta presente nel nostro comparatore.</p>

<hr>

<h2>Materia Energia (Luce)</h2>

<p>La componente <strong>materia energia</strong> copre il costo dell'energia elettrica effettivamente consumata, inclusa la quota fissa di commercializzazione (PCV).</p>

<h3>Offerte a prezzo variabile (indicizzate al PUN)</h3>

<div class="card" style="text-align:center;padding:20px">
<strong>Materia energia = (PUN × λ + spread) × consumo + PCV</strong>
</div>

<ul>
<li><strong>PUN</strong> — Prezzo Unico Nazionale (€/kWh), il prezzo all'ingrosso dell'energia elettrica in Italia. Aggiornato mensilmente dal GME.</li>
<li><strong>λ</strong> (perdite di rete) — Coefficiente pari a <strong>1,102</strong> (10,2%), che tiene conto delle perdite sulla rete di Bassa Tensione.</li>
<li><strong>Spread</strong> — Il margine del fornitore (€/kWh), comprensivo di commercializzazione e approvvigionamento.</li>
<li><strong>PCV</strong> — Quota fissa annua di commercializzazione (€/anno), tipicamente 12–120 €/anno.</li>
</ul>

<div class="card" style="border-left:3px solid var(--gas);border-radius:0 var(--radius-lg) var(--radius-lg) 0">
<p style="margin:0;font-size:13px;color:var(--text-primary)"><strong>⚠ Novità v4.0 — Perdite rete solo sul PUN</strong></p>
<p style="margin:4px 0 0;font-size:13px">Nella versione 4.0, il coefficiente λ (1,102) si applica <strong>esclusivamente al PUN</strong>, non allo spread. Nelle versioni precedenti si applicava a (PUN + spread), generando una sovrastima di circa 7,50 €/anno su 2700 kWh.</p>
</div>

<h3>Offerte a prezzo fisso</h3>

<div class="card" style="text-align:center;padding:20px">
<strong>Materia energia = prezzo_bloccato × consumo + PCV</strong>
</div>

<p>Nelle offerte a prezzo fisso il fornitore applica un prezzo bloccato per un periodo determinato (12–24 mesi). Il prezzo è già comprensivo di perdite di rete e spread.</p>

<hr>

<h2>Trasporto e gestione contatore (Luce)</h2>

<p>Il trasporto copre i costi di distribuzione, misura e trasmissione dell'energia. È uguale per tutti i fornitori perché regolato dall'ARERA.</p>

<table>
<thead>
<tr><th>Componente</th><th>Valore</th><th>Descrizione</th></tr>
</thead>
<tbody>
<tr><td><strong>Quota fissa</strong></td><td>23,04 €/anno</td><td>Trasporto + dispacciamento + gestione contatore</td></tr>
<tr><td><strong>Quota potenza</strong></td><td>23,52 €/kW/anno</td><td>Per ogni kW di potenza impegnata (tipico: 3 kW)</td></tr>
<tr><td><strong>Quota variabile</strong></td><td>0,01204 €/kWh</td><td>TRAS + DIS (0,01190) + UC3 (0,00007) + UC6 (0,00007)</td></tr>
</tbody>
</table>

<div class="card" style="border-left:3px solid var(--electric);border-radius:0 var(--radius-lg) var(--radius-lg) 0">
<p style="margin:0;font-size:13px"><strong>Fonte:</strong> ARERA Delibera 575/2025/R/eel e Comunicato Q2 2026.</p>
</div>

<hr>

<h2>Oneri di sistema</h2>

<p>Gli oneri di sistema finanziano incentivi per le rinnovabili (ASOS), efficienza energetica e bonus sociali (ARIM). Anche questi sono fissi e uguali per tutti.</p>

<table>
<thead>
<tr><th>Componente</th><th>Valore</th><th>Voce ARERA</th></tr>
</thead>
<tbody>
<tr><td><strong>Oneri sistema luce</strong></td><td>0,0303 €/kWh</td><td>ASOS (0,02866) + ARIM (0,00164)</td></tr>
<tr><td><strong>Oneri sistema gas</strong></td><td>0,03 €/Smc</td><td>Oneri generali sistema gas</td></tr>
</tbody>
</table>

<hr>

<h2>Accise sulla luce</h2>

<p>Le accise sono imposte sulla produzione e consumo di energia elettrica, regolate dal Testo Unico Accise (DL 504/1995). Per i clienti <strong>residenti con potenza ≤ 3 kW</strong> si applicano i seguenti scaglioni:</p>

<table>
<thead>
<tr><th>Consumo annuo</th><th>Accise</th><th>Formula</th></tr>
</thead>
<tbody>
<tr><td>≤ 1.800 kWh</td><td><span style="color:var(--save)"><strong>0 €</strong></span></td><td>Esenzione completa</td></tr>
<tr><td>1.801 – 2.640 kWh</td><td>0,0227 €/kWh</td><td>(consumo – 1.800) × 0,0227</td></tr>
<tr><td>&gt; 2.640 kWh</td><td>0,0227 €/kWh</td><td>Compensazione: esenzione si riduce di (consumo – 2.640)</td></tr>
</tbody>
</table>

<div class="card" style="border-left:3px solid var(--gas);border-radius:0 var(--radius-lg) var(--radius-lg) 0">
<p style="margin:0;font-size:13px;color:var(--text-primary)"><strong>⚠ Novità v4.0 — Compensazione oltre 2640 kWh</strong></p>
<p style="margin:4px 0 0;font-size:13px">Introdotta la <strong>soglia di compensazione a 2640 kWh</strong>: oltre questo consumo, l'esenzione fiscale si riduce progressivamente. Per un consumo di 2700 kWh, l'esenzione scende da 1800 a 1740 kWh, con un'impatto di circa +1,36 €/anno rispetto alla versione precedente.</p>
</div>

<hr>

<h2>IVA</h2>

<table>
<thead>
<tr><th>Prodotto</th><th>Aliquota</th></tr>
</thead>
<tbody>
<tr><td><strong>Luce</strong> (usi domestici)</td><td>10% flat sul totale</td></tr>
<tr><td><strong>Gas</strong> — primi 480 Smc/anno</td><td>10%</td></tr>
<tr><td><strong>Gas</strong> — oltre 480 Smc/anno</td><td>22%</td></tr>
</tbody>
</table>

<hr>

<h2>Gas: componenti aggiuntive</h2>

<p>Il calcolo del gas segue una struttura simile ma con alcune differenze importanti:</p>

<div class="card" style="text-align:center;padding:20px">
<strong>Spesa Gas = Materia prima + Trasporto + Oneri + Accise + Addizionale regionale + IVA mista</strong>
</div>

<table>
<thead>
<tr><th>Componente</th><th>Valore</th><th>Note</th></tr>
</thead>
<tbody>
<tr><td><strong>Materia prima</strong></td><td>(PSV + spread) × Smc o prezzo fisso × Smc</td><td>Il PSV è l'indice all'ingrosso del gas</td></tr>
<tr><td><strong>Quota fissa reti</strong></td><td>23,00 €/anno</td><td>Trasporto, dispacciamento, stoccaggio</td></tr>
<tr><td><strong>Trasporto variabile</strong></td><td>0,15 €/Smc</td><td>Distribuzione</td></tr>
<tr><td><strong>Oneri sistema</strong></td><td>0,03 €/Smc</td><td>Oneri generali</td></tr>
<tr><td><strong>Accise</strong></td><td>0,149959 €/Smc</td><td>DL 504/1995 (valore aggiornato v4.0)</td></tr>
<tr><td><strong>Addizionale regionale</strong></td><td>0,0093 €/Smc</td><td>Nuovo in v4.0 — valore medio Italia</td></tr>
<tr><td><strong>IVA</strong></td><td>10% / 22%</td><td>10% fino a 480 Smc, 22% sulla quota eccedente</td></tr>
</tbody>
</table>

<div class="card" style="border-left:3px solid var(--gas);border-radius:0 var(--radius-lg) var(--radius-lg) 0">
<p style="margin:0;font-size:13px;color:var(--text-primary)"><strong>⚠ Novità v4.0</strong></p>
<p style="margin:4px 0 0;font-size:13px">L'accisa gas è stata aggiornata da 0,15 a <strong>0,149959 €/Smc</strong> per maggiore precisione. È stata inoltre introdotta l'<strong>addizionale regionale</strong> di 0,0093 €/Smc (media Italia).</p>
</div>

<hr>

<h2>Esempio 1: Calcolo spesa annua luce</h2>

<p><span class="tag luce">Luce</span> Scenario: famiglia residente, 3 kW potenza, consumo 2.700 kWh/anno, offerta variabile indicizzata PUN (spread 0,03 €/kWh), PUN a 0,1287 €/kWh.</p>

<h3>Passo 1 — Calcolo materia energia</h3>

<table>
<thead>
<tr><th>Passaggio</th><th>Formula</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>PUN × λ</td><td>0,1287 × 1,102</td><td>0,14183 €/kWh</td></tr>
<tr><td>+ Spread</td><td>0,14183 + 0,03</td><td>0,17183 €/kWh</td></tr>
<tr><td>Materia energia</td><td>0,17183 × 2.700</td><td><strong>463,93 €</strong></td></tr>
<tr><td>PCV (quota fissa commerciale)</td><td>1 €/mese × 12</td><td><strong>12,00 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 2 — Trasporto</h3>

<table>
<thead>
<tr><th>Componente</th><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>Quota fissa reti</td><td>23,04</td><td><strong>23,04 €</strong></td></tr>
<tr><td>Quota potenza</td><td>23,52 × 3 kW</td><td><strong>70,56 €</strong></td></tr>
<tr><td>Quota variabile</td><td>0,01204 × 2.700</td><td><strong>32,51 €</strong></td></tr>
<tr><td><strong>Totale trasporto</strong></td><td></td><td><strong>126,11 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 3 — Oneri di sistema</h3>

<table>
<thead>
<tr><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>0,0303 × 2.700</td><td><strong>81,81 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 4 — Accise (residente ≤3 kW)</h3>

<p>Consumo 2.700 kWh > 2.640 kWh → si applica la compensazione.</p>

<table>
<thead>
<tr><th>Passaggio</th><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>Esenzione residua</td><td>max(0, 1.800 – (2.700 – 2.640))</td><td>1.740 kWh</td></tr>
<tr><td>Base imponibile</td><td>2.700 – 1.740</td><td>960 kWh</td></tr>
<tr><td><strong>Accise</strong></td><td>960 × 0,0227</td><td><strong>21,79 €</strong></td></tr>
</tbody>
</table>

<h3>Riepilogo — Spesa annua luce</h3>

<table>
<thead>
<tr><th>Voce</th><th>Importo</th></tr>
</thead>
<tbody>
<tr><td>Materia energia</td><td>463,93 €</td></tr>
<tr><td>PCV (commercializzazione)</td><td>12,00 €</td></tr>
<tr><td>Trasporto (fissa + potenza + variabile)</td><td>126,11 €</td></tr>
<tr><td>Oneri di sistema</td><td>81,81 €</td></tr>
<tr><td>Accise</td><td>21,79 €</td></tr>
<tr><td style="font-weight:700;border-top:1px solid var(--border-light)">Subtotale</td><td style="font-weight:700;border-top:1px solid var(--border-light)">705,64 €</td></tr>
<tr><td>IVA 10%</td><td>70,56 €</td></tr>
<tr style="background:rgba(245,158,11,.04)"><td style="font-weight:800;color:var(--electric)"><strong>Totale spesa annua</strong></td><td style="font-weight:800;color:var(--electric)"><strong>776,20 €</strong></td></tr>
</tbody>
</table>

<h3>Breakdown ARERA</h3>

<table>
<thead>
<tr><th>Componente ARERA</th><th>Importo</th></tr>
</thead>
<tbody>
<tr><td>Materia prima</td><td>463,93 €</td></tr>
<tr><td>Commercializzazione</td><td>12,00 €</td></tr>
<tr><td>Tariffa rete</td><td>126,11 €</td></tr>
<tr><td>Oneri sistema</td><td>81,81 €</td></tr>
<tr><td>Accise</td><td>21,79 €</td></tr>
<tr><td style="font-weight:700;border-top:1px solid var(--border-light)">Subtotale</td><td style="font-weight:700;border-top:1px solid var(--border-light)">705,64 €</td></tr>
<tr><td>IVA</td><td>70,56 €</td></tr>
<tr style="background:rgba(245,158,11,.04)"><td style="font-weight:800;color:var(--electric)"><strong>Totale</strong></td><td style="font-weight:800;color:var(--electric)"><strong>776,20 €</strong></td></tr>
</tbody>
</table>

<hr>

<h2>Esempio 2: Calcolo spesa annua gas</h2>

<p><span class="tag gas">Gas</span> Scenario: famiglia in casa singola, consumo 1.000 Smc/anno, offerta a prezzo fisso 0,55 €/Smc.</p>

<h3>Passo 1 — Calcolo materia prima</h3>

<table>
<thead>
<tr><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>0,55 × 1.000</td><td><strong>550,00 €</strong></td></tr>
<tr><td>PCV (quota fissa commerciale)</td><td><strong>12,00 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 2 — Trasporto</h3>

<table>
<thead>
<tr><th>Componente</th><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>Quota fissa reti</td><td>23,00</td><td><strong>23,00 €</strong></td></tr>
<tr><td>Quota variabile</td><td>0,15 × 1.000</td><td><strong>150,00 €</strong></td></tr>
<tr><td><strong>Totale trasporto</strong></td><td></td><td><strong>173,00 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 3 — Oneri sistema</h3>

<table>
<thead>
<tr><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>0,03 × 1.000</td><td><strong>30,00 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 4 — Accise e addizionale regionale</h3>

<table>
<thead>
<tr><th>Componente</th><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>Accise</td><td>0,149959 × 1.000</td><td><strong>149,96 €</strong></td></tr>
<tr><td>Addizionale regionale</td><td>0,0093 × 1.000</td><td><strong>9,30 €</strong></td></tr>
<tr><td><strong>Totale imposte</strong></td><td></td><td><strong>159,26 €</strong></td></tr>
</tbody>
</table>

<h3>Passo 5 — IVA mista</h3>

<table>
<thead>
<tr><th>Scaglione</th><th>Calcolo</th><th>Valore</th></tr>
</thead>
<tbody>
<tr><td>IVA 10% su 480 Smc</td><td>480 ÷ 1.000 × 924,26 × 10%</td><td><strong>44,36 €</strong></td></tr>
<tr><td>IVA 22% su 520 Smc</td><td>520 ÷ 1.000 × 924,26 × 22%</td><td><strong>105,69 €</strong></td></tr>
<tr><td><strong>IVA totale</strong></td><td></td><td><strong>150,05 €</strong></td></tr>
</tbody>
</table>

<h3>Riepilogo — Spesa annua gas</h3>

<table>
<thead>
<tr><th>Voce</th><th>Importo</th></tr>
</thead>
<tbody>
<tr><td>Materia prima</td><td>550,00 €</td></tr>
<tr><td>PCV (commercializzazione)</td><td>12,00 €</td></tr>
<tr><td>Trasporto (fissa + variabile)</td><td>173,00 €</td></tr>
<tr><td>Oneri di sistema</td><td>30,00 €</td></tr>
<tr><td>Accise</td><td>149,96 €</td></tr>
<tr><td>Addizionale regionale</td><td>9,30 €</td></tr>
<tr><td style="font-weight:700;border-top:1px solid var(--border-light)">Subtotale</td><td style="font-weight:700;border-top:1px solid var(--border-light)">924,26 €</td></tr>
<tr><td>IVA mista</td><td>150,05 €</td></tr>
<tr style="background:rgba(59,130,246,.04)"><td style="font-weight:800;color:var(--gas)"><strong>Totale spesa annua</strong></td><td style="font-weight:800;color:var(--gas)"><strong>1.074,31 €</strong></td></tr>
</tbody>
</table>

<h3>Breakdown ARERA</h3>

<table>
<thead>
<tr><th>Componente ARERA</th><th>Importo</th></tr>
</thead>
<tbody>
<tr><td>Materia prima</td><td>550,00 €</td></tr>
<tr><td>Commercializzazione</td><td>12,00 €</td></tr>
<tr><td>Tariffa rete</td><td>173,00 €</td></tr>
<tr><td>Oneri sistema</td><td>30,00 €</td></tr>
<tr><td>Accise + addizionale regionale</td><td>159,26 €</td></tr>
<tr><td style="font-weight:700;border-top:1px solid var(--border-light)">Subtotale</td><td style="font-weight:700;border-top:1px solid var(--border-light)">924,26 €</td></tr>
<tr><td>IVA</td><td>150,05 €</td></tr>
<tr style="background:rgba(59,130,246,.04)"><td style="font-weight:800;color:var(--gas)"><strong>Totale</strong></td><td style="font-weight:800;color:var(--gas)"><strong>1.074,31 €</strong></td></tr>
</tbody>
</table>

<hr>

<h2>Costanti regolatorie ARERA (Q2 2026)</h2>

<p>Tabella riepilogativa di tutti i parametri utilizzati nei calcoli, corrispondenti a quelli implementati in SwitchAI:</p>

<table>
<thead>
<tr><th>Parametro</th><th>Valore</th><th>Fonte</th></tr>
</thead>
<tbody>
<tr><td>Perdite rete BT (λ)</td><td>1,102</td><td>ARERA Del. 449/2020</td></tr>
<tr><td>Quota fissa reti luce</td><td>23,04 €/anno</td><td>ARERA Del. 575/2025</td></tr>
<tr><td>Costo potenza</td><td>23,52 €/kW/anno</td><td>ARERA Del. 575/2025</td></tr>
<tr><td>Trasporto variabile luce</td><td>0,01204 €/kWh</td><td>ARERA Del. 575/2025 + Q2 2026</td></tr>
<tr><td>Oneri sistema luce</td><td>0,0303 €/kWh</td><td>ARERA Comunicato Q2 2026</td></tr>
<tr><td>Accisa luce (oltre esenzione)</td><td>0,0227 €/kWh</td><td>DL 504/1995</td></tr>
<tr><td>Soglia esenzione accise luce</td><td>1.800 kWh/anno</td><td>DL 504/1995</td></tr>
<tr><td>Soglia compensazione accise luce</td><td>2.640 kWh/anno</td><td>DL 504/1995 (v4.0)</td></tr>
<tr><td>IVA luce</td><td>10%</td><td>DPR 633/1972</td></tr>
<tr><td>Quota fissa reti gas</td><td>23,00 €/anno</td><td>ARERA</td></tr>
<tr><td>Trasporto variabile gas</td><td>0,15 €/Smc</td><td>ARERA</td></tr>
<tr><td>Oneri sistema gas</td><td>0,03 €/Smc</td><td>ARERA</td></tr>
<tr><td>Accisa gas</td><td>0,149959 €/Smc</td><td>DL 504/1995 (v4.0)</td></tr>
<tr><td>Addizionale regionale gas</td><td>0,0093 €/Smc</td><td>v4.0</td></tr>
<tr><td>Soglia IVA 10% gas</td><td>480 Smc/anno</td><td>DPR 633/1972</td></tr>
</tbody>
</table>

<hr>

<h2>Riferimento ufficiale ARERA</h2>

<p>La metodologia descritta in questa guida segue fedelmente il documento ARERA <strong>"Regole per il calcolo della Spesa Annua Stimata" versione 4.0</strong> (Febbraio 2026, 46 pagine). Il PDF ufficiale è disponibile sul sito <a href="https://www.arera.it" target="_blank" rel="noopener">ARERA</a> e sul <a href="https://www.portaleofferte.it" target="_blank" rel="noopener">Portale Offerte</a>.</p>

<p>SwitchAI utilizza quotidianamente queste regole per calcolare la spesa annua di oltre 3.000 offerte luce e 2.300 offerte gas di 21 fornitori attivi nel mercato libero italiano.</p>

<hr>

<h2>Perché usare SwitchAI per confrontare le offerte</h2>

<p>Calcolare manualmente la spesa annua per ogni offerta richiederebbe decine di passaggi. SwitchAI lo fa automaticamente per te:</p>

<ul>
<li>Usa le <strong>stesse identiche formule ARERA v4.0</strong> descritte in questa guida</li>
<li>Importa i dati ufficiali da <strong>ARERA</strong> ogni 7 giorni (oltre 5.400 offerte)</li>
<li>Mostra il <strong>breakdown dettagliato</strong> (materia prima, commercializzazione, tariffa rete, oneri, accise, IVA)</li>
<li>Calcola il <strong>risparmio reale</strong> rispetto alla tua bolletta attuale</li>
<li>Funziona con <strong>Claude, ChatGPT e Gemini</strong></li>
</ul>

<div class="card" style="border-color:rgba(16,185,129,.2);background:rgba(16,185,129,.03)">
<h3>🔌 Confronta le offerte ora</h3>
<p>Inserisci la tua bolletta e scopri quanto puoi risparmiare con i dati ARERA in tempo reale.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Calcola il risparmio →</a>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Come si calcola la spesa annua di luce e gas: formula ARERA v4.0',
    'description' => $description,
    'url' => 'https://www.switchai.it/risorse/calcolo-spesa-annua',
    'datePublished' => '2026-06-23',
    'dateModified' => '2026-06-23',
    'author' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
        'url' => 'https://www.switchai.it',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'SwitchAI',
    ],
    'inLanguage' => 'it-IT',
    'isAccessibleForFree' => true,
    'about' => [
        '@type' => 'Thing',
        'name' => 'Calcolo spesa annua luce gas ARERA',
    ],
    'keywords' => 'calcolo spesa annua luce, formula ARERA spesa annua, calcolo bolletta luce, stima annua energia, accise luce, perdite rete, costo energia elettrica',
];
require __DIR__ . '/_footer.php';
