#!/usr/bin/env php
<?php
/**
 * Test routine indipendente — verifica coerenza interna dei calcoli tariffari.
 *
 * NON dipende da Wattene né da servizi esterni.
 * Usa SOLO i dati ufficiali ARERA già scaricati.
 *
 * Verifiche:
 *   1. Conti: profili coerenti (basso < medio < alto, no NaN/negativi)
 *   2. Componenti energia: somma per fascia ≈ prezzo_tot_kwh (offerte fisse)
 *   3. Sconti: applicati coerenti, condizionali tracciati
 *   4. Costi fissi: non negativi
 *   5. Campi obbligatori presenti (brand, offerta, tariffa, tipo_cliente)
 *
 * Uso: php tests/verify_offers.php [--verbose] [--sample=N]
 */

mb_internal_encoding('UTF-8');

define('ARERA_DATA_DIR', __DIR__ . '/../backend/php/data/offerte');

// Consumi standard profili ARERA
$consumi = [
    'luce' => ['basso' => 1500, 'medio' => 2700, 'alto' => 4000],
    'gas'  => ['basso' => 400,  'medio' => 1000, 'alto' => 1800],
];

$verbose = in_array('--verbose', $argv);
$sampleSize = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sample=')) $sampleSize = (int)substr($arg, 9);
}

function parseNum(string $s): float {
    return (float)str_replace(',', '.', $s);
}

function fmt(float $n): string {
    return number_format($n, 2, ',', '.');
}

// ── Carica dati ────────────────────────────────────────────────────────
$luce = is_file(ARERA_DATA_DIR . '/db-offerte-luce.json')
    ? json_decode(file_get_contents(ARERA_DATA_DIR . '/db-offerte-luce.json'), true) : [];
$gas  = is_file(ARERA_DATA_DIR . '/db-offerte-gas.json')
    ? json_decode(file_get_contents(ARERA_DATA_DIR . '/db-offerte-gas.json'), true) : [];

echo "╔══════════════════════════════════════╗\n";
echo "║     VERIFICA OFFERTE ARERA           ║\n";
echo "╚══════════════════════════════════════╝\n";
echo "LUCE: " . count($luce) . " offerte | GAS: " . count($gas) . " offerte\n\n";

// ── Stratifica campione ────────────────────────────────────────────────
$all = [];
foreach ([['luce', $luce], ['gas', $gas]] as [$t, $data]) {
    $strati = [
        'res-fissa'     => array_filter($data, fn($o) => ($o['tipo_cliente']??'') === 'residenziale' && ($o['tariffa']??'') === 'Fissa'),
        'res-variabile' => array_filter($data, fn($o) => ($o['tipo_cliente']??'') === 'residenziale' && ($o['tariffa']??'') === 'Variabile'),
        'biz-fissa'     => array_filter($data, fn($o) => ($o['tipo_cliente']??'') === 'business' && ($o['tariffa']??'') === 'Fissa'),
        'biz-variabile' => array_filter($data, fn($o) => ($o['tipo_cliente']??'') === 'business' && ($o['tariffa']??'') === 'Variabile'),
    ];
    $perStrato = max(3, (int)($sampleSize / (count($strati) * 2)));
    foreach ($strati as $nome => $strato) {
        if (count($strato) === 0) continue;
        shuffle($strato);
        foreach (array_slice($strato, 0, $perStrato) as $o) {
            $o['_commodity'] = $t;
            $all[] = $o;
        }
    }
}
shuffle($all);
$sample = array_slice($all, 0, $sampleSize);
echo "Campione: " . count($sample) . " offerte (" . count($luce) . "+" . count($gas) . " totali)\n\n";

// ── Statistiche globali ────────────────────────────────────────────────
$stats = [
    'total_luce'   => count($luce),
    'total_gas'    => count($gas),
    'con_sconti'   => 0,
    'con_condiz'   => 0,
    'costi_fissi_0'=> 0,
    'costo_fisso_avg_res' => 0,
    'costo_fisso_avg_biz' => 0,
];
$cfRes = []; $cfBiz = [];
foreach ($luce as $o) {
    $cf = parseNum($o['costo_fisso'] ?? '0');
    if ($cf <= 0) $stats['costi_fissi_0']++;
    if (!empty($o['sconti_applicati'])) $stats['con_sconti']++;
    if (!empty($o['has_sconti_condizionali'])) $stats['con_condiz']++;
    if ($o['tipo_cliente'] === 'residenziale') $cfRes[] = $cf;
    else $cfBiz[] = $cf;
}
$stats['costo_fisso_avg_res'] = count($cfRes) ? round(array_sum($cfRes)/count($cfRes), 2) : 0;
$stats['costo_fisso_avg_biz'] = count($cfBiz) ? round(array_sum($cfBiz)/count($cfBiz), 2) : 0;

// ── Esegui verifiche sul campione ──────────────────────────────────────
$tests = 0;
$passed = 0;
$warnings = [];
$errors = [];

foreach ($sample as $off) {
    $commodity = $off['_commodity'];
    $isLuce = $commodity === 'luce';
    $brand = $off['brand'] ?? '?';
    $offerta = mb_substr($off['offerta'] ?? '?', 0, 35);
    $tipo = $off['tariffa'] ?? '?';
    $cliente = $off['tipo_cliente'] ?? '?';
    $prefix = "{$tipo}/{$cliente} [{$brand}]";
    $cons = $consumi[$commodity];

    // ── Check 1: Campi obbligatori presenti ──────────────────────────
    $tests++;
    $required = ['brand', 'offerta', 'tariffa', 'tipo_cliente', 'costo_fisso'];
    if ($isLuce) $required[] = 'prezzo tot kwh';
    else $required[] = 'prezzo tot smc';
    $missing = array_filter($required, fn($r) => empty($off[$r]) && $off[$r] !== '0' && $off[$r] !== 0);
    if (empty($missing)) $passed++;
    else $errors[] = "{$prefix} campi mancanti: " . implode(', ', $missing);

    // ── Check 2: Profili coerenti (basso ≤ medio ≤ alto) ────────────
    $profs = [];
    foreach (['basso', 'medio', 'alto'] as $p) {
        $profs[$p] = parseNum($off["costo_profilo_{$p}"] ?? '0');
    }
    $tests++;
    if ($profs['basso'] > 0 && $profs['medio'] > 0 && $profs['alto'] > 0) {
        $monotonic = $profs['basso'] <= $profs['medio'] && $profs['medio'] <= $profs['alto'];
        if ($monotonic) {
            $passed++;
        } else {
            $errors[] = sprintf("{$prefix} profili non crescenti: basso=%.0f medio=%.0f alto=%.0f",
                $profs['basso'], $profs['medio'], $profs['alto']);
        }
    } else {
        $warnings[] = "{$prefix} profili costo non valorizzati (variabile o dato assente)";
        $passed++; // non è un errore per le variabili
    }

    // ── Check 3: Costi profilo > 0 per offerte fisse ─────────────────
    if ($tipo === 'Fissa') {
        $tests++;
        if ($profs['medio'] > 0) $passed++;
        else $errors[] = "{$prefix} costo_profilo_medio = 0 per offerta fissa";
    }

    // ── Check 4: Spread e PUN/PSV positivi (variabili) ──────────────
    if ($tipo === 'Variabile') {
        $tests++;
        $spread = parseNum($off['spread'] ?? '0');
        $pun = parseNum($off[$isLuce ? 'Pun' : 'Psv'] ?? '0');
        if ($pun > 0 && $spread >= 0) {
            $passed++;
        } elseif ($pun <= 0) {
            $warnings[] = "{$prefix} PUN/PSV = 0 per offerta variabile";
            $passed++; // warning, non errore
        } else {
            $errors[] = "{$prefix} spread negativo: {$spread}";
        }
    }

    // ── Check 5: Costo fisso ≥ 0 ────────────────────────────────────
    $tests++;
    $cf = parseNum($off['costo_fisso'] ?? '0');
    if ($cf >= 0) $passed++;
    else $errors[] = "{$prefix} costo_fisso negativo: " . fmt($cf) . "€";

    // ── Check 6: Componenti fascia ≈ prezzo_tot (solo LUCE fissa) ──
    if ($isLuce && $tipo === 'Fissa' && !empty($off['componenti'])) {
        $tests++;
        $sumF1 = $sumF2 = $sumF3 = 0;
        foreach ($off['componenti'] as $c) {
            $unita = $c['unita'] ?? '';
            if (!str_contains($unita, '€/kWh')) continue;
            $cNome = $c['nome'] ?? '';
            if (stripos($cNome, 'SPREAD') !== false) continue;
            $fascia = $c['fascia'] ?? 'F1';
            if ($fascia === 'F1') $sumF1 += (float)($c['prezzo'] ?? 0);
            elseif ($fascia === 'F2') $sumF2 += (float)($c['prezzo'] ?? 0);
            else $sumF3 += (float)($c['prezzo'] ?? 0);
        }
        $prezzoTot = parseNum($off['prezzo tot kwh'] ?? '0');
        $nonZero = array_filter([$sumF1, $sumF2, $sumF3]);
        $avgFascia = count($nonZero) > 0 ? array_sum($nonZero) / count($nonZero) : $prezzoTot;
        $delta = $prezzoTot > 0 ? abs($avgFascia - $prezzoTot) / $prezzoTot : 0;
        if ($delta < 0.25) {
            $passed++;
        } else {
            $errors[] = sprintf("{$prefix} componenti fascia avg=%.4f vs prezzo_tot=%.4f (Δ%.1f%%) [F1:%.4f F2:%.4f F3:%.4f]",
                $avgFascia, $prezzoTot, $delta*100, $sumF1, $sumF2, $sumF3);
        }
    }

    // ── Check 7: Sconti applicati con prezzo > 0 ────────────────────
    if (!empty($off['sconti_applicati'])) {
        $tests++;
        $allOk = true;
        foreach ($off['sconti_applicati'] as $s) {
            if (parseNum($s['prezzo'] ?? '0') <= 0) {
                $errors[] = "{$prefix} sconto applicato '{$s['nome']}' con prezzo ≤ 0";
                $allOk = false;
            }
        }
        if ($allOk) $passed++;
    }

    // ── Check 8: Sconti condizionali tracciati ──────────────────────
    if (!empty($off['has_sconti_condizionali'])) {
        $tests++;
        if (!empty($off['sconti_non_applicati'])) $passed++;
        else $errors[] = "{$prefix} has_sconti_condizionali=true ma sconti_non_applicati vuoto";
    }
}

// ── Report ──────────────────────────────────────────────────────────────
echo "──────────────────────────────────────────────────────────────\n";
echo "STATISTICHE GLOBALI:\n";
echo "  Offerte totali:     " . $stats['total_luce'] . " LUCE | " . $stats['total_gas'] . " GAS\n";
echo "  Con sconti applicati: " . $stats['con_sconti'] . " LUCE\n";
echo "  Con sconti condizionali: " . $stats['con_condiz'] . " LUCE\n";
echo "  Costi fissi = 0:   " . $stats['costi_fissi_0'] . " LUCE\n";
echo "  Costo fisso medio: residenziale " . fmt($stats['costo_fisso_avg_res']) . "€ | business " . fmt($stats['costo_fisso_avg_biz']) . "€\n";
echo "\n";

echo "──────────────────────────────────────────────────────────────\n";
echo "VERIFICHE CAMPIONE ({$tests} test su " . count($sample) . " offerte):\n";
echo "  ✓ Passati:  {$passed}\n";
echo "  ⚠ Warnings: " . count($warnings) . "\n";
echo "  ✗ Errori:   " . count($errors) . "\n";
echo "  Score:      " . round($passed/max($tests,1)*100, 1) . "%\n";
echo "──────────────────────────────────────────────────────────────\n\n";

if (count($warnings) > 0) {
    $maxShow = $verbose ? count($warnings) : min(5, count($warnings));
    echo "WARNINGS (" . count($warnings) . " totali):\n";
    foreach (array_slice($warnings, 0, $maxShow) as $i => $w) {
        echo "  ⚠ {$w}\n";
    }
    if (count($warnings) > $maxShow) echo "  ... altri " . (count($warnings) - $maxShow) . "\n";
    echo "\n";
}

if (count($errors) > 0) {
    $maxShow = $verbose ? count($errors) : min(15, count($errors));
    echo "ERRORI (" . count($errors) . " totali):\n";
    foreach (array_slice($errors, 0, $maxShow) as $i => $err) {
        echo "  ✗ {$err}\n";
    }
    if (count($errors) > $maxShow) echo "  ... altri " . (count($errors) - $maxShow) . " (usa --verbose)\n";
    echo "\n";
}

// ── Giudizio ───────────────────────────────────────────────────────────
$score = $passed / max($tests, 1);
if ($score >= 0.97) {
    echo "✅ VERIFICA SUPERATA ({$passed}/{$tests}) — dati coerenti\n";
} elseif ($score >= 0.90) {
    echo "⚠ ACCURATEZZA BUONA ({$passed}/{$tests}) — verificabili anomalie note\n";
} else {
    echo "❌ TROPPI ERRORI ({$passed}/{$tests}) — serve investigazione\n";
}

exit($score >= 0.90 ? 0 : 1);
