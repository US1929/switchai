<?php
$title = 'Glossario Energia: tutti i termini della bolletta';
$description = 'Significato di PUN, PSV, Smc, kWh, spread, dispacciamento, oneri di sistema e tutte le voci della bolletta luce e gas. Glossario completo.';
require __DIR__ . '/_header.php';
?>
<h1>Glossario dell'Energia</h1>
<p style="font-size:17px;color:var(--text-secondary);margin-bottom:32px">
Tutti i termini tecnici della bolletta della luce e del gas spiegati in modo semplice e chiaro. Dalla <strong>materia prima</strong> alle <strong>accise</strong>, dal <strong>PUN</strong> al <strong>PSV</strong>: ogni voce del mercato energetico italiano con definizione, contesto ed esempi.
</p>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="pun">PUN — Prezzo Unico Nazionale</h3>
<p>Il <strong>PUN</strong> (Prezzo Unico Nazionale) è il prezzo all'ingrosso dell'energia elettrica in Italia, determinato ogni ora dal GME (Gestore dei Mercati Energetici) attraverso la borsa elettrica. Rappresenta il costo della materia prima energia prima dei margini del fornitore. Viene espresso in <strong>€/MWh</strong> (al grossista) o convertito in <strong>€/kWh</strong> (al dettaglio, dividendo per 1000). Nelle offerte indicizzate, il prezzo finale è <code>PUN × 1,102 + spread</code>, dove 1,102 è il coefficiente delle perdite di rete.</p>
</div>

<div class="card">
<span class="tag gas">Gas</span>
<h3 id="psv">PSV — Punto di Scambio Virtuale</h3>
<p>Il <strong>PSV</strong> (Punto di Scambio Virtuale) è il prezzo all'ingrosso del gas naturale in Italia, determinato dalla piattaforma Snam Gas. Corrisponde all'equivalente del PUN per il gas. Viene espresso in <strong>€/MWh</strong> e convertito in <strong>€/Smc</strong> (1 Smc ≈ 0,010695 MWh). Nelle offerte gas indicizzate, il prezzo è calcolato come <code>PSV × 1 + spread</code> (il coefficiente perdite non si applica al gas).</p>
</div>

<div class="card">
<span class="tag gas">Gas</span>
<h3 id="cmem">CMEM — Commodity Market Energy Monthly</h3>
<p>Il <strong>CMEM</strong> è un indice di riferimento alternativo al PSV per le offerte gas indicizzate. Pubblicato mensilmente dall'ARERA, rappresenta la media dei prezzi del gas rilevati sul mercato all'ingrosso italiano. Alcuni fornitori utilizzano il CMEM anziché il PSV come base per calcolare il prezzo finale della materia prima gas.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="kwh">kWh — chilowattora</h3>
<p>Il <strong>chilowattora</strong> (simbolo <strong>kWh</strong>) è l'unità di misura dell'energia elettrica. Corrisponde all'energia consumata da un dispositivo da 1000 watt (1 kW) acceso per un'ora. Un consumatore tipo consuma circa <strong>2700 kWh/anno</strong> (luce) e, per confronto, un asciugacapelli da 2000 W acceso 30 minuti consuma 1 kWh. In bolletta, il prezzo dell'energia è tipicamente espresso in <strong>€/kWh</strong>.</p>
</div>

<hr>

<div class="card">
<span class="tag gas">Gas</span>
<h3 id="smc">Smc — Standard Metro Cubo</h3>
<p>Lo <strong>Standard Metro Cubo</strong> (<strong>Smc</strong>) è l'unità di misura del gas naturale, corretta per tenere conto di pressione e temperatura (condizioni standard a 15 °C e 1013,25 mbar). Un Smc corrisponde a circa <strong>0,010695 MWh</strong> (10,695 kWh) di potere calorifico. Il consumo medio di una famiglia è di circa <strong>1400 Smc/anno</strong>. In bolletta, il prezzo del gas è spesso indicato sia in €/Smc che in €/MWh.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="spread">Spread — margine del fornitore</h3>
<p>Lo <strong>spread</strong> (o margine) è la componente fissa aggiunta dal fornitore al prezzo all'ingrosso (PUN o PSV) nelle offerte indicizzate. È espresso in <strong>€/kWh</strong> (luce) o <strong>€/Smc</strong> (gas) e rappresenta il margine del fornitore. Ad esempio, in un'offerta con formula <code>PUN + 0,05 €/kWh</code>, lo spread è 0,05 €/kWh. Più lo spread è basso, più l'offerta è competitiva. Lo spread <strong>non</strong> viene maggiorato del coefficiente perdite di rete (λ).</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="pcv">PCV / CMC — Corrispettivo di Commercializzazione</h3>
<p>Il <strong>PCV</strong> (luce) e il <strong>CMC</strong> (gas) sono i corrispettivi di commercializzazione della vendita, ovvero la quota fissa annua che copre i costi gestionali e amministrativi del fornitore (emissione bollette, servizio clienti, gestione contratti). È una componente <strong>fissa</strong> pagata indipendentemente dal consumo, tipicamente compresa tra 30 e 120 €/anno nella maggior tutela o regolata ARERA. Nel mercato libero ogni fornitore la definisce autonomamente.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="lambda">λ — Perdite di rete (coefficiente 10,2%)</h3>
<p>Il coefficiente <strong>λ</strong> (lettera greca lambda) rappresenta le <strong>perdite di rete</strong>, ovvero l'energia dispersa durante il trasporto sulla rete elettrica. Secondo la delibera ARERA v4.0 (Febbraio 2026), λ è pari a <strong>10,2%</strong>, equivalente a un fattore moltiplicativo di <strong>1,102</strong>. Il coefficiente si applica <strong>solo al PUN</strong>, non allo spread. Formula corretta: <code>PUN × 1,102 + spread</code> (e non <code>(PUN + spread) × 1,102</code> come calcolato in precedenza).</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="oneri-sistema">Oneri di sistema — ASOS e ARIM</h3>
<p>Gli <strong>oneri di sistema</strong> sono voci della bolletta che finanziano attività di interesse generale per il sistema elettrico e del gas. Si compongono di:
<strong>ASOS</strong> (Aggiuntivo per gli oneri di sistema) — finanzia lo sviluppo delle energie rinnovabili, gli incentivi alle fonti pulite e la ricerca nel settore energetico;
<strong>ARIM</strong> (Aggiuntivo per la remunerazione degli investimenti) — copre i costi di infrastrutture e sistemi di misura. Sono determinati da ARERA e sono uguali per tutti i fornitori: la differenza tra le offerte sta solo nello "scontrino energetico" (quota fissa + consumo).</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="accise">Accise — imposta sul consumo energetico</h3>
<p>Le <strong>accise</strong> sono imposte indirette applicate al consumo di energia elettrica e gas. Per la <strong>luce</strong> (utenze domestiche residenti ≤ 3 kW): si paga 0,0227 €/kWh per i kWh eccedenti la franchigia, con compensazione oltre i 2640 kWh (l'esenzione si riduce di <code>consumo - 2640</code>). Per il <strong>gas</strong>: l'accisa è <strong>0,149959 €/Smc</strong> (valore aggiornato v4.0), applicata sull'intero consumo per usi domestici.</p>
</div>

<div class="card">
<span class="tag gas">Gas</span>
<h3 id="addizionale-regionale">Addizionale regionale gas</h3>
<p>L'<strong>addizionale regionale</strong> è un'imposta variabile applicata al consumo di gas, stabilita autonomamente da ogni regione italiana. Il valore di riferimento ARERA v4.0 è <strong>0,0093 €/Smc</strong>, ma ogni regione può applicare aliquote diverse. Viene aggiunta in bolletta dopo l'accisa e prima dell'IVA, e varia in base alla zona geografica di residenza.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="dispacciamento">Dispacciamento</h3>
<p>Il <strong>dispacciamento</strong> è il servizio che mantiene in equilibrio la rete elettrica, garantendo che in ogni istante la quantità di elettricità immessa in rete sia uguale a quella prelevata dai consumatori. Include i costi di bilanciamento, riserva e regolazione di frequenza/tensione. Dal 2026, il dispacciamento è incluso nella <strong>tariffa di rete</strong> e non compare più come voce separata in bolletta, ma la sua incidenza è ancora significativa (circa 5-8% del totale).</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="fasce-orarie">Fasce orarie — F1, F2, F3, F23</h3>
<p>Le <strong>fasce orarie</strong> sono intervalli della giornata in cui il prezzo dell'energia elettrica cambia in base al carico sulla rete:
<strong>F1</strong> (ore di punta, 8-19, lun-ven) — fascia più cara, maggior domanda;
<strong>F2</strong> (ore intermedie, 7-8 e 19-23, lun-ven; 7-23 sab) — fascia intermedia;
<strong>F3</strong> (ore fuori punta, 23-7, lun-sab; tutte le 24 ore dom e festivi) — fascia più economica;
<strong>F23</strong> (unione di F2+F3 nella bioraria). La scelta tra monoraria, bioraria o trioraria dipende dal proprio profilo di consumo.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="pod">POD — Point of Delivery (luce)</h3>
<p>Il <strong>POD</strong> (Point of Delivery) è il codice identificativo univoco del punto di consegna dell'energia elettrica, composto da 14-15 caratteri alfanumerici (formato: IT001EXXXXXXXXX). È associato al contatore e all'indirizzo di fornitura, non alla persona. Serve per attivare, cambiare o disattivare una fornitura. Si trova in bolletta, di solito nella prima pagina. Ogni POD è unico in tutta Italia.</p>
</div>

<div class="card">
<span class="tag gas">Gas</span>
<h3 id="pdr">PDR — Punto di Riconsegna (gas)</h3>
<p>Il <strong>PDR</strong> (Punto di Riconsegna) è il codice identificativo univoco del punto di riconsegna del gas, composto da 14 cifre numeriche. Identifica il contatore e l'impianto di allaccio alla rete del gas. Come il POD per la luce, il PDR è necessario per attivare, cambiare o cessare una fornitura gas. Si trova in bolletta tra i dati del contratto.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span>
<h3 id="bioraria-trioraria">Bioraria / Trioraria</h3>
<p>Sono le modalità di tariffazione dell'energia elettrica in base alle fasce orarie:
<strong>Bioraria</strong> (o F23) — prezzo diverso tra fascia F1 (più cara) e fascia F23 (più economica, unione di F2+F3) — la formula più diffusa per le offerte del mercato libero;
<strong>Trioraria</strong> — tre fasce distinte (F1, F2, F3) con prezzi differenti. La bioraria è generalmente più conveniente per chi consuma soprattutto nei weekend e nelle ore serali; la trioraria premia chi sposta i consumi nelle ore notturne.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="prezzo-fisso">Prezzo fisso</h3>
<p>Nel <strong>prezzo fisso</strong>, il costo dell'energia (€/kWh per la luce, €/Smc per il gas) rimane <strong>bloccato</strong> per tutta la durata contrattuale, indipendentemente dall'andamento del mercato all'ingrosso (PUN o PSV). Vantaggio: stabilità e prevedibilità della spesa. Svantaggio: se il mercato scende, non si beneficia del calo. Ideale per chi cerca certezza e vuole proteggersi dai rialzi del mercato energetico.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="prezzo-indicizzato">Prezzo indicizzato</h3>
<p>Nel <strong>prezzo indicizzato</strong>, il costo della materia prima è legato all'andamento del PUN (luce) o del PSV (gas) tramite la formula <code>indice + spread</code>. Il prezzo varia ogni mese (o trimestre) in base al valore dell'indice di riferimento. Vantaggio: se il mercato scende, si paga meno; storica convenienza rispetto al fisso. Svantaggio: volatilità e possibili rincari improvvisi in caso di crisi energetiche. Per calcolare il costo finale luce: <code>PUN × 1,102 + spread</code>.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="arera">ARERA — Autorità di Regolazione per Energia Reti e Ambiente</h3>
<p>L'<strong>ARERA</strong> (Autorità di Regolazione per Energia Reti e Ambiente) è l'ente pubblico indipendente che regola e controlla il settore dell'energia elettrica, del gas e del servizio idrico in Italia. Definisce le regole per il calcolo della spesa annua stimata, stabilisce le tariffe di rete e gli oneri di sistema, e pubblica i dati ufficiali su offerte e prezzi (Portale Offerte, aggiornamenti trimestrali). La delibera <strong>v4.0</strong> (Febbraio 2026) contiene le regole attuali per il confronto delle offerte.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="mercato-tutelato">Mercato tutelato / Maggior tutela</h3>
<p>Il <strong>mercato tutelato</strong> (o <strong>maggior tutela</strong> per la luce, <strong>tutela gas</strong>) è il servizio di fornitura con condizioni economiche e contrattuali stabilite dall'ARERA, in cui il prezzo è aggiornato trimestralmente e non c'è margine discrezionale del fornitore. È attualmente in fase di superamento: dal <strong>1° luglio 2024</strong> la maggior tutela luce è terminata per i clienti non vulnerabili (prorogata al <strong>2027</strong> per i vulnerabili). Per il gas, il servizio di tutela è stato gradualmente sostituito dal Servizio a Tutele Graduali (STG).</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="mercato-libero">Mercato libero</h3>
<p>Il <strong>mercato libero</strong> è il segmento del mercato energetico in cui i fornitori (Enel, Eni, Edison, A2A, Acea, Sorgenia, Iberdrola, Nordpool, NeN, Dolomiti, E.ON, Octopus, ecc.) offrono contratti con prezzi e condizioni stabiliti autonomamente. Le offerte si distinguono per prezzo fisso o indicizzato, spread, quota fissa e servizi inclusi. Dal 2024 è diventato il regime prevalente per la luce, con oltre l'80% dei clienti già passati al mercato libero.</p>
</div>

<div class="card">
<span class="tag luce">Luce</span> <span class="tag gas">Gas</span>
<h3 id="scontrino-energetico">Scontrino energetico</h3>
<p>Lo <strong>scontrino energetico</strong> (o "vendita energia") è la componente della bolletta che include la <strong>quota fissa</strong> (PCV/CMC) e la <strong>quota consumo</strong> (prezzo energia × kWh o Smc consumati). È l'unica voce che cambia tra le diverse offerte del mercato libero: le tariffe di rete, gli oneri di sistema e le imposte sono uguali per tutti i fornitori. Per confrontare le offerte, basta confrontare lo scontrino energetico, perché tutto il resto è identico.</p>
</div>

<div class="card" style="border-color:rgba(16,185,129,.2);background:rgba(16,185,129,.03)">
<h3>🔌 Pronto a risparmiare?</h3>
<p>Confronta le offerte luce e gas con i dati ufficiali ARERA in tempo reale. Scopri quanto puoi risparmiare rispetto alla tua bolletta attuale.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Confronta ora</a>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Glossario Energia: tutti i termini della bolletta',
    'description' => $description,
    'url' => 'https://www.switchai.it/risorse/glossario-energia',
    'author' => ['@type' => 'Organization', 'name' => 'SwitchAI'],
    'publisher' => ['@type' => 'Organization', 'name' => 'SwitchAI'],
    'datePublished' => '2026-02-15',
    'dateModified' => '2026-06-23',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => 'https://www.switchai.it/risorse/glossario-energia'],
];
require __DIR__ . '/_footer.php';
