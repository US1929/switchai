<?php
$title = 'Risorse e Guide su Bollette Luce e Gas';
$description = 'Guide complete, analisi e approfondimenti per capire la bolletta della luce e del gas, confrontare le offerte e risparmiare.';
require __DIR__ . '/_header.php';
?>
<h1 style="font-size:clamp(32px,5vw,48px);margin-bottom:8px">Risorse</h1>
<p style="font-size:17px;color:var(--text-secondary);margin-bottom:32px">Guide, articoli e approfondimenti per capire la bolletta, confrontare le offerte e risparmiare energia.</p>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/come-funziona-bolletta-luce">Come funziona la bolletta della luce: guida completa</a></h3>
<p>Materia energia, trasporto, oneri di sistema, accise e IVA: tutto quello che devi sapere sulla bolletta luce. Con esempi di calcolo e consigli pratici.</p>
</div>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/come-funziona-bolletta-gas">Come funziona la bolletta del gas: guida completa</a></h3>
<p>Materia prima, trasporto, oneri, accise e IVA mista: tutte le voci della bolletta gas spiegate nel dettaglio, con esempi numerici.</p>
</div>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/glossario-energia">Glossario energia: tutti i termini della bolletta</a></h3>
<p>PUN, PSV, Smc, kWh, spread, dispacciamento, oneri di sistema: significato di ogni voce che trovi in bolletta e nelle offerte luce e gas.</p>
</div>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/prezzo-fisso-vs-indicizzato">Prezzo fisso vs indicizzato: quale conviene?</a></h3>
<p>Confronto tra offerte a prezzo fisso e indicizzato: come funzionano, quando convengono, rischi e vantaggi con dati di mercato reali.</p>
</div>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/calcolo-spesa-annua">Come si calcola la spesa annua di luce e gas</a></h3>
<p>La formula ufficiale ARERA per il calcolo della spesa annua stimata. Costanti regolatorie, perdite di rete, accise e IVA spiegate passo passo.</p>
</div>

<div class="card">
<div class="tag guida">Guida</div>
<h3><a href="/risorse/come-leggere-bolletta">Come leggere la bolletta della luce e del gas</a></h3>
<p>Guida visiva alla lettura della bolletta: dove trovare consumo, spesa, POD/PDR, offerta attuale e dati necessari per il confronto.</p>
</div>

<div class="card" style="border-color:rgba(16,185,129,.2);background:rgba(16,185,129,.03)">
<h3>🔌 Pronto a risparmiare?</h3>
<p>Confronta le offerte luce e gas con i dati ufficiali ARERA in tempo reale.</p>
<a href="/" class="cta cta-primary" style="margin-top:8px">Confronta ora</a>
</div>

<?php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Risorse SwitchAI',
    'description' => $description,
    'url' => 'https://www.switchai.it/risorse/',
];
require __DIR__ . '/_footer.php';
