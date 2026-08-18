<?php
/**
 * Post-processing: euristica anti-promo sugli sconti.
 * 
 * Se costo_fisso < 24€/anno ma i componenti mostrano un corrispettivo fisso
 * più alto, vuol dire che uno sconto promozionale ha cancellato il PCV.
 * 
 * Uso: php backend/php/inc/fix_sconti.php
 */

$files = [
    __DIR__ . '/../data/offerte/db-offerte-luce.json',
    __DIR__ . '/../data/offerte/db-offerte-gas.json',
];

$totalFixed = 0;

foreach ($files as $path) {
    if (!is_file($path)) { echo "Skip: $path\n"; continue; }

    $data = json_decode(file_get_contents($path), true);
    if (!$data) { echo "Errore $path\n"; continue; }

    $isLuce = str_contains($path, 'luce');
    $changed = 0;

    foreach ($data as &$o) {
        $scontiApplicati = $o['sconti_applicati'] ?? [];
        $componenti = $o['componenti'] ?? [];

        if (empty($scontiApplicati) || empty($componenti)) continue;

        $cf = (float)str_replace(',', '.', $o['costo_fisso'] ?? '0');
        if ($cf >= 24) continue; // già ragionevole

        // Cerca componenti fissi (€/anno, €/kW, €/mese) nei componenti
        $baseCF = null;
        foreach ($componenti as $c) {
            $unita = $c['unita'] ?? '';
            $nome = $c['nome'] ?? '';
            $prezzo = (float)($c['prezzo'] ?? 0);

            if (str_contains($unita, '€/anno')) {
                // Componente annuale fissa
                if ($baseCF === null) $baseCF = 0;
                $baseCF += $prezzo;
            } elseif (str_contains($unita, '€/mese') && stripos($nome, 'POTENZA') === false && stripos($nome, 'potenza') === false) {
                if ($baseCF === null) $baseCF = 0;
                $baseCF += $prezzo * 12;
            }
        }

        if ($baseCF === null || $baseCF <= 20) continue; // nessun base fisso significativo

        // Verifica: lo sconto ha ridotto il costo_fisso sotto 24?
        // Calcola quanto sconto fisso è stato applicato
        $appliedFixed = 0;
        $fixedSconti = [];
        foreach ($scontiApplicati as $s) {
            $um = $s['unita'] ?? '';
            $prezzo = (float)str_replace(',', '.', $s['prezzo'] ?? '0');
            if (str_contains($um, '€/mese') || str_contains($um, '€/anno') || str_contains($um, '€/kW')) {
                $annuale = str_contains($um, '€/mese') ? $prezzo * 12 : $prezzo;
                $appliedFixed += $annuale;
                $fixedSconti[] = $s;
            }
        }

        if ($appliedFixed <= 0) continue;

        // Ricostruisci il CF SENZA sconti promozionali
        $newCF = max(0, $baseCF - $appliedFixed);

        // Se il nuovo CF è ancora < 24, lo sconto è tot. promozionale
        // Sposta TUTTI i fixed sconti in non_applicati
        if ($newCF < 24) {
            $newScontiApplicati = [];
            $newScontiNonApplicati = $o['sconti_non_applicati'] ?? [];
            $fixedAnnuale = 0;

            foreach ($scontiApplicati as $s) {
                $um = $s['unita'] ?? '';
                $prezzo = (float)str_replace(',', '.', $s['prezzo'] ?? '0');
                if (str_contains($um, '€/mese') || str_contains($um, '€/anno') || str_contains($um, '€/kW')) {
                    // Sconto promo → sposta in non_applicati
                    $newScontiNonApplicati[] = $s;
                } else {
                    // Sconto energia → tieni
                    $newScontiApplicati[] = $s;
                }
            }

            $o['sconti_applicati'] = $newScontiApplicati;
            $o['sconti_non_applicati'] = $newScontiNonApplicati;
            $o['costo_fisso'] = number_format($baseCF, 2, ',', '');
            $o['has_sconti_condizionali'] = !empty($newScontiNonApplicati);
            if ($o['has_sconti_condizionali'] && empty($o['sconto_note'])) {
                $o['sconto_note'] = 'Prezzo base — sconti condizionali (es. SDD, dual fuel) potrebbero ridurre ulteriormente il costo.';
            }

            // Ricalcola profili
            $prezzoKey = $isLuce ? 'prezzo tot kwh' : 'prezzo tot smc';
            $prezzo = (float)str_replace(',', '.', $o[$prezzoKey] ?? '0');
            if ($prezzo > 0) {
                $consumi = $isLuce
                    ? ['basso' => 1500, 'medio' => 2700, 'alto' => 4000]
                    : ['basso' => 400, 'medio' => 1000, 'alto' => 1800];
                foreach ($consumi as $profilo => $kwh) {
                    $val = $kwh * $prezzo + $baseCF;
                    $o["costo_profilo_{$profilo}"] = number_format($val, 2, ',', '');
                }
            }

            $changed++;
            $totalFixed++;
            echo "  FIX: {$o['brand']} - {$o['offerta']}: CF {$cf}→{$baseCF} (reverted {$appliedFixed}€ sconto promo)\n";
        }
    }

    unset($o);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "{$path}: {$changed} offerte corrette (anti-promo)\n\n";
}

echo "Totale (anti-promo): {$totalFixed} offerte fixate\n\n";

// ── SECONDO PASSO: Sconti con soglia (es. "40% primi 840 kWh") ─────────
// Questi sconti sono in sconti_non_applicati ma dovrebbero essere applicati
// perché la soglia è una limitazione di quantità, non una condizione.
echo "=== PASSO 2: Sconti con soglia ===\n\n";
$totalSoglia = 0;

foreach ($files as $path) {
    if (!is_file($path)) continue;
    $data = json_decode(file_get_contents($path), true);
    if (!$data) continue;

    $isLuce = str_contains($path, 'luce');
    $changed = 0;

    foreach ($data as &$o) {
        $sna = $o['sconti_non_applicati'] ?? [];
        if (empty($sna)) continue;

        $hasChange = false;
        $newScontiNonApplicati = [];
        $sogliaDiscount = 0.0;
        $isUnconditional = false;
        $sogliaDetail = '';

        foreach ($sna as $s) {
            $soglia = null;
            $nome = $s['nome'] ?? '';

            // Estrai soglia dal nome (es. "Sconto 40% sui primi 840 kWh/a")
            if (preg_match('/primi\s+(\d+)\s*(kWh|Smc)/i', $nome, $m)) {
                $soglia = (float)$m[1];
            }

            $prezzo = (float)str_replace(',', '.', $s['prezzo'] ?? '0');
            $um = $s['unita'] ?? '';
            $motivo = $s['motivo'] ?? '';

            // Applica solo sconti con soglia quantitativa E senza motivo condizionale
            if ($soglia > 0 && $prezzo > 0 && stripos($motivo, 'condizionale') === false && stripos($motivo, 'SDD') === false) {
                if (str_contains($um, '€/kWh') || str_contains($um, '€/Smc')) {
                    // Sconto energia con soglia → converti in sconto fisso
                    $sogliaVal = $soglia * $prezzo;
                    $sogliaDiscount += $sogliaVal;
                    $hasChange = true;
                    $sogliaDetail = $sogliaDetail ?: "{$nome}: {$soglia}×{$prezzo} = {$sogliaVal}€";
                    continue; // skip → applied
                } elseif (str_contains($um, '€/anno') || str_contains($um, '€/mese') || str_contains($um, '€/kW')) {
                    // Sconto fisso con soglia: applica direttamente
                    $annuale = str_contains($um, '€/mese') ? $prezzo * 12 : $prezzo;
                    $sogliaDiscount += $annuale;
                    $hasChange = true;
                    $sogliaDetail = $sogliaDetail ?: "{$nome}: {$annuale}€/anno";
                    continue;
                }
            }
            // Keep in non_applicati
            $newScontiNonApplicati[] = $s;
        }

        if ($hasChange) {
            $cf = (float)str_replace(',', '.', $o['costo_fisso'] ?? '0');
            $newCF = $cf - $sogliaDiscount;
            if ($newCF < 0) $newCF = 0.0;

            $o['costo_fisso'] = number_format($newCF, 2, ',', '');
            $o['sconti_non_applicati'] = $newScontiNonApplicati;
            $o['has_sconti_condizionali'] = !empty($newScontiNonApplicati);

            // Ricalcola profili
            $prezzoKey = $isLuce ? 'prezzo tot kwh' : 'prezzo tot smc';
            $prezzo = (float)str_replace(',', '.', $o[$prezzoKey] ?? '0');
            if ($prezzo > 0) {
                $consumi = $isLuce
                    ? ['basso' => 1500, 'medio' => 2700, 'alto' => 4000]
                    : ['basso' => 400, 'medio' => 1000, 'alto' => 1800];
                foreach ($consumi as $profilo => $kwh) {
                    $val = $kwh * $prezzo + $newCF;
                    $o["costo_profilo_{$profilo}"] = number_format($val, 2, ',', '');
                }
            }

            $changed++;
            $totalSoglia++;
            echo "  SOGLIA: {$o['brand']} - {$o['offerta']}: CF {$cf}→{$newCF} ({$sogliaDetail})\n";
        }
    }

    unset($o);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "{$path}: {$changed} offerte con soglia applicate\n\n";
}

echo "Totale (soglia): {$totalSoglia} offerte\n";
