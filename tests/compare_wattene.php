#!/usr/bin/env php
<?php
/**
 * Confronto con Wattene — verifica accuratezza costi.
 */

require_once __DIR__ . '/../backend/php/inc/bill_parser.php';

$data = json_decode(file_get_contents(__DIR__ . '/../backend/php/data/offerte/db-offerte-luce.json'), true);

// ── TABELLA DI CONFRONTO ─────────────────────────────────────────────
// Fonte: Wattene JSON raw (scaricato 29/06/2026)
// Tutti i prezzi Wattene: €/kWh (incl. dispacciamento), PCV €/anno
// Profilo: 3200 kWh, 3 kW, NORD, residenziale
$confronti = [
    [
        'brand' => 'E.ON ENERGIA',
        'offerta' => 'E.ON LuceClick - Amico new',
        'wat_prezzo' => 0.135488,
        'wat_pcv' => 109.23,
        'wat_totale_mensile' => 82,     // 984 €/anno
        'wat_consumo' => 3200,
    ],
    [
        'brand' => 'EDISON ENERGIA',
        'offerta' => 'Edison Web Luce',
        'wat_prezzo' => 0.133988,
        'wat_pcv' => 90.00,
        'wat_totale_mensile' => 82,     // stesso range famiglie
        'wat_consumo' => 3200,
    ],
    [
        'brand' => 'OCTOPUS ENERGY',
        'offerta' => 'Octopus Fissa 12M',
        'wat_prezzo' => 0.125888,
        'wat_pcv' => 72.00,
        'wat_totale_mensile' => null,
        'wat_consumo' => 3200,
    ],
    [
        'brand' => 'A2A ENERGIA',
        'offerta' => 'A2A Full Luce',
        'wat_prezzo' => 0.155988,
        'wat_pcv' => 135.00,
        'wat_totale_mensile' => null,
        'wat_consumo' => 3200,
    ],
    [
        'brand' => 'SORGENIA',
        'offerta' => 'Next Energy Hybrid',
        'wat_prezzo' => 0.137988,
        'wat_pcv' => 108.00,
        'wat_totale_mensile' => null,
        'wat_consumo' => 3200,
    ],
];

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║           CONFRONTO ARERA vs WATTENE (3200 kWh, 3 kW)             ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\nCostanti regolate Q3 2026:\n";
echo "  Dispacciamento: " . number_format(LUCE_DISPACCIAMENTO, 6, ',', '.') . " €/kWh\n";
echo "  Trasporto:      " . number_format(LUCE_TRASPORTO_VAR, 6, ',', '.') . " €/kWh\n";
echo "  Oneri:          " . number_format(ONERI_SISTEMA_LUCE, 6, ',', '.') . " €/kWh\n";
echo "  Accise:         " . number_format(LUCE_ACCISE, 4, ',', '.') . " €/kWh (soglia 1800-2640)\n";
echo "  Potenza:        " . number_format(LUCE_COSTO_POTENZA_KW, 2, ',', '.') . " €/kW/anno\n";
echo "  Quota fissa reti: " . number_format(QUOTA_FISSA_RETI_LUCE, 2, ',', '.') . " €/anno\n";
echo "  IVA:            " . (LUCE_IVA*100) . "%\n\n";

$header = sprintf("%-20s %-35s %12s %12s %12s %12s",
    'Brand', 'Offerta', '€/kWh Wat', '€/kWh NOI', 'PCV Wat', 'PCV NOI');
echo $header . "\n";
echo str_repeat('-', 105) . "\n";

$raccolta = [];

foreach ($confronti as $c) {
    // Trova l'offerta corrispondente nei nostri dati
    $found = null;
    foreach ($data as $o) {
        if (stripos($o['brand']??'', $c['brand']) === false) continue;
        if (stripos($o['offerta']??'', $c['offerta']) === false) continue;
        if (($o['tipo_cliente']??'') !== 'residenziale') continue;
        $found = $o;
        break;
    }

    if (!$found) {
        echo sprintf("%-20s %-35s %12s %12s %12s %12s",
            $c['brand'], substr($c['offerta'],0,33), 
            number_format($c['wat_prezzo'],6,',','.'),
            'NON TROVATO', '', '') . "\n";
        continue;
    }

    $ourPrezzo = (float)str_replace(',', '.', $found['prezzo tot kwh']);
    $ourPcv = (float)str_replace(',', '.', $found['costo_fisso']);

    // Corregge: il nostro prezzo non include dispacciamento (lo aggiungiamo come voce separata)
    // Wattene: prezzo include dispacciamento
    $ourPrezzoCorretto = $ourPrezzo + LUCE_DISPACCIAMENTO;

    echo sprintf("%-20s %-35s %12s %12s %12s %12s",
        $c['brand'],
        substr($c['offerta'],0,33),
        number_format($c['wat_prezzo'],6,',','.'),
        number_format($ourPrezzoCorretto,6,',','.'),
        number_format($c['wat_pcv'],2,',','.'),
        number_format($ourPcv,2,',','.')) . "\n";

    $diffPrezzo = ($c['wat_prezzo'] - $ourPrezzoCorretto) * 1000;
    $diffPcv = $c['wat_pcv'] - $ourPcv;

    $raccolta[] = [
        'brand' => $c['brand'],
        'offerta' => $c['offerta'],
        'prezzo_nostro' => $ourPrezzoCorretto,
        'prezzo_wat' => $c['wat_prezzo'],
        'diff_prezzo_mill' => $diffPrezzo,
        'pcv_nostro' => $ourPcv,
        'pcv_wat' => $c['wat_pcv'],
        'diff_pcv' => $diffPcv,
        'nostri_sconti' => $found['sconti_applicati'] ?? [],
        'wat_totale_mensile' => $c['wat_totale_mensile'] ?? null,
    ];
}

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                    ANALISI DIFFERENZE                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

foreach ($raccolta as $r) {
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%s — %s\n", $r['brand'], $r['offerta']);
    echo str_repeat('-', 80) . "\n";

    // Prezzo energia
    $diffP = $r['diff_prezzo_mill'];
    $prezzoOk = abs($diffP) < 2; // tolleranza 2 millesimi
    echo sprintf("  Prezzo energia:  NOSTRO=%s WATTENE=%s  [%s]\n",
        number_format($r['prezzo_nostro'],6,',','.'),
        number_format($r['prezzo_wat'],6,',','.'),
        $prezzoOk ? 'OK' : sprintf('DIFF %+.3f mill', $diffP));

    // PCV
    $pcvOk = abs($r['diff_pcv']) < 2;
    echo sprintf("  PCV:             NOSTRO=%s WATTENE=%s  [%s]\n",
        number_format($r['pcv_nostro'],2,',','.'),
        number_format($r['pcv_wat'],2,',','.'),
        $pcvOk ? 'OK' : sprintf('DIFF %+.2f€', $r['diff_pcv']));

    // Sconti
    if (!empty($r['nostri_sconti'])) {
        echo "  ⚠ SCONTI APPLICATI (erronei se condizionali):\n";
        foreach ($r['nostri_sconti'] as $s) {
            $nome = $s['nome'] ?? '?';
            $val = $s['prezzo'] ?? '?';
            $um = $s['unita'] ?? '?';
            echo "    - {$nome}: {$val} {$um}\n";
        }
    }

    // Calcolo costo completo per 3200 kWh
    $consumo = $r['prezzo_nostro'] > 0 ? 3200 : 0;
    if ($consumo > 0) {
        $spesaEnergia = $consumo * $r['prezzo_nostro'];
        $oneri = $consumo * ONERI_SISTEMA_LUCE;
        $trasporto = $consumo * LUCE_TRASPORTO_VAR;
        $costoPotenza = LUCE_COSTO_POTENZA_KW * 3;
        $quotaFissaReti = QUOTA_FISSA_RETI_LUCE;
        $accise = max(0, min($consumo, LUCE_ACCISE_SOGLIA_COMPENSATA) - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
        $pcv = $r['pcv_nostro'];

        $subtotale = $spesaEnergia + $oneri + $trasporto + $costoPotenza + $quotaFissaReti + $accise + $pcv;
        $iva = $subtotale * LUCE_IVA;
        $canoneRai = 90;
        $totale = $subtotale + $iva + $canoneRai;

        echo sprintf("  ├─ Materia energia:          € %8s\n", number_format($spesaEnergia,2,',','.'));
        echo sprintf("  ├─ Oneri sistema:            € %8s\n", number_format($oneri,2,',','.'));
        echo sprintf("  ├─ Trasporto:                € %8s\n", number_format($trasporto,2,',','.'));
        echo sprintf("  ├─ Potenza:                  € %8s\n", number_format($costoPotenza,2,',','.'));
        echo sprintf("  ├─ Quota fissa reti:         € %8s\n", number_format($quotaFissaReti,2,',','.'));
        echo sprintf("  ├─ Accise:                   € %8s\n", number_format($accise,2,',','.'));
        echo sprintf("  ├─ PCV:                      € %8s\n", number_format($pcv,2,',','.'));
        echo sprintf("  ├─ IVA 10%%:                  € %8s\n", number_format($iva,2,',','.'));
        echo sprintf("  └─ Canone RAI:               € %8s\n", number_format($canoneRai,2,',','.'));
        echo sprintf("  === TOTALE ANNUO:           € %8s\n", number_format($totale,2,',','.'));
        echo sprintf("  === TOTALE MENSILE:         € %8s/mese\n", number_format($totale/12,2,',','.'));
        if (!empty($r['wat_totale_mensile'])) {
            $watAnn = $r['wat_totale_mensile'] * 12;
            echo sprintf("  Wattene stima:              € %8s/anno (%d €/mese)\n",
                number_format($watAnn,0,',','.'), $r['wat_totale_mensile']);
            echo sprintf("  DIFFERENZA:                 € %+8s (%+.1f%%)\n",
                number_format($totale - $watAnn,2,',','.'),
                ($totale/$watAnn-1)*100);
        } else {
            echo "  Wattene stima: N/D per questo profilo\n";
        }
    }
}

// ── RIEPILOGO ────────────────────────────────────────────────────
echo "\n\n";
echo str_repeat('=', 80) . "\n";
echo "RIEPILOGO:\n";
echo str_repeat('=', 80) . "\n";
$tuttiOk = true;
foreach ($raccolta as $r) {
    $diffP = $r['diff_prezzo_mill'];
    $prezzoOk = abs($diffP) < 2;
    $pcvOk = abs($r['diff_pcv']) < 2;
    $scontiOk = empty($r['nostri_sconti']);
    $stato = ($prezzoOk && $pcvOk) ? '✅' : '❌';
    if (!$prezzoOk || !$pcvOk) $tuttiOk = false;
    echo sprintf("  %s %-20s %-35s prezzo=%s pcv=%s\n",
        $stato,
        $r['brand'],
        substr($r['offerta'],0,33),
        $prezzoOk ? 'OK' : sprintf('diff %+.3f', $diffP),
        $pcvOk ? 'OK' : sprintf('diff %+.2f€', $r['diff_pcv']));
    if (!empty($r['nostri_sconti'])) {
        $tuttiOk = false;
        echo "     ⚠ Ha sconti applicati (potenzialmente condizionali!)\n";
    }
}

if ($tuttiOk) {
    echo "\n✅ TUTTI I TEST SUPERATI — allineamento con Wattene entro ±2 millesimi\n";
} else {
    $failCount = 0;
    foreach ($raccolta as $r) {
        if (abs($r['diff_prezzo_mill']) >= 2 || abs($r['diff_pcv']) >= 2 || !empty($r['nostri_sconti']))
            $failCount++;
    }
    echo "\n❌ $failCount/" . count($raccolta) . " test con discrepanze\n";
}
echo "\nNota: Il nostro prezzo energia esclude dispacciamento (0.016988 €/kWh\n";
echo "aggiunto come voce separata). Wattene include dispacciamento nel prezzo.\n";
echo "Prezzo corretto = nostro prezzo + dispacciamento per confronto.\n";
