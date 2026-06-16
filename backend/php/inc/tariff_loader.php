<?php
/**
 * tariff_loader.php — Carica le tariffe da fonti dati proprietarie.
 *
 * URL configurati come costanti private (non esposti a frontend/API).
 */

define('LUCE_JSON_URL', getenv('LUCE_JSON_URL') ?: '');
define('GAS_JSON_URL',  getenv('GAS_JSON_URL') ?: '');

// Brand → nome fornitore canonico
function getBrandMap(): array {
    return [
        'ENEL'     => 'Enel Energia',
        'ENI'      => 'Eni Plenitude',
        'EDISON'   => 'Edison',
        'A2A'      => 'A2A Energia',
        'IREN'     => 'Iren Luce Gas e Servizi',
        'HERA'     => 'Hera Comm',
        'SORGENIA' => 'Sorgenia',
        'ENGIE'    => 'Engie',
        'FASTWEB'  => 'Fastweb Energia',
        'ILLUMIA'  => 'Illumia',
        'ACEA'     => 'ACEA Energia',
        'OCTOPUS'  => 'Octopus Energy',
        'VOLTY'    => 'Volty',
    ];
}

function getBrandSlug(): array {
    return [
        'ENEL'     => 'enel-energia',
        'ENI'      => 'eni-plenitude',
        'EDISON'   => 'edison',
        'A2A'      => 'a2a-energia',
        'IREN'     => 'iren-luce-gas',
        'HERA'     => 'hera-comm',
        'SORGENIA' => 'sorgenia',
        'ENGIE'    => 'engie',
        'FASTWEB'  => 'fastweb-energia',
        'ILLUMIA'  => 'illumia',
        'ACEA'     => 'acea-energia',
        'OCTOPUS'  => 'octopus-energy',
        'VOLTY'    => 'volty',
    ];
}

function parseItalianNumber(?string $s): ?float {
    if ($s === null || trim($s) === '') return null;
    $s = trim(str_replace([' ', "\xc2\xa0", '€', "\xe2\x82\xac"], '', $s));
    if ($s === '') return null;
    
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $lc = strrpos($s, ',');
        $ld = strrpos($s, '.');
        $s = $lc > $ld ? str_replace('.', '', $s) : str_replace(',', '', $s);
        if ($lc > $ld) $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    }
    
    return is_numeric($s) ? (float)$s : null;
}

function deterministicUuid(string $seed): string {
    $h = sha1($seed);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

function fetchJson(string $url): array {
    // Metodo 1: file_get_contents (se allow_url_fopen è attivo)
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (compatible; SwitchAIBot/1.0)\r\nAccept: application/json\r\n",
        'timeout' => 15,
    ]]);

    $response = @file_get_contents($url, false, $ctx);

    // Metodo 2: fallback cURL (più compatibile con hosting OVH)
    if ($response === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SwitchAIBot/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new RuntimeException("Impossibile scaricare $url (HTTP $httpCode)");
        }
    }

    if ($response === false || empty($response)) {
        throw new RuntimeException("Impossibile scaricare $url");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) throw new RuntimeException("JSON non valido da $url");

    return $data;
}

function normalizeLuceOffer(array $offer): ?array {
    $brand = $offer['brand'] ?? 'Sconosciuto';
    $bm = getBrandMap();
    $supplierName = $bm[$brand] ?? ucfirst(strtolower($brand));

    $tipo = (isset($offer['tariffa']) && strtolower($offer['tariffa']) === 'fissa') ? 'FISSO' : 'VARIABILE';

    // Dettagli prezzo (preservati per mostrarli all'utente)
    $pun = parseItalianNumber($offer['Pun'] ?? null);
    $spread = parseItalianNumber($offer['spread'] ?? null);

    $prezzo = parseItalianNumber($offer['prezzo tot kwh'] ?? null);
    if ($prezzo === null) {
        if ($pun !== null) $prezzo = $pun + ($spread ?? 0.0);
    }

    if ($prezzo === null || $prezzo <= 0) return null;

    $costoFissoAnnuale = parseItalianNumber($offer['costo_fisso'] ?? null) ?? 0.0;

    // Campi aggiuntivi (solo se valorizzati)
    $extra = [];
    if (!empty($offer['prezzo_bloccato'])) $extra['prezzo_bloccato_mesi'] = $offer['prezzo_bloccato'];
    if (!empty($offer['pagamento'])) $extra['modalita_pagamento'] = $offer['pagamento'];
    if (!empty($offer['vantaggi'])) $extra['vantaggi'] = $offer['vantaggi'];
    if (!empty($offer['note_costi'])) $extra['note'] = $offer['note_costi'];
    if (!empty($offer['validità offerta'])) $extra['validita_offerta'] = $offer['validità offerta'];
    if (!empty($offer['penale_recesso'])) $extra['penale_recesso'] = $offer['penale_recesso'];
    if (!empty($offer['penale'])) $extra['penale_recesso'] = $offer['penale'];

    // Profili di costo (se valorizzati)
    $profili = [];
    foreach (['basso', 'medio', 'alto'] as $p) {
        $val = parseItalianNumber($offer["costo_profilo_$p"] ?? null);
        if ($val !== null) $profili[$p] = $val;
    }
    if (!empty($profili)) $extra['costo_profili'] = $profili;

    $prezzoF1 = parseItalianNumber($offer['prezzo f1'] ?? null);
    $prezzoF2 = parseItalianNumber($offer['prezzo f2'] ?? null);
    $prezzoF3 = parseItalianNumber($offer['prezzo f3'] ?? null);

    return [
        'id'                => deterministicUuid("tariff-{$brand}-{$offer['offerta']}-LUCE"),
        'supplier_id'       => deterministicUuid("supplier-{$brand}"),
        'supplier_name'     => $supplierName,
        'commodity'         => 'LUCE',
        'name'              => $offer['offerta'] ?? 'Offerta',
        'type'              => $tipo,
        'price_mono_kwh'    => $prezzo,
        'price_f1_kwh'      => $prezzoF1,
        'price_f2_kwh'      => $prezzoF2,
        'price_f3_kwh'      => $prezzoF3,
        'price_smc'         => null,
        'fixed_fee_monthly' => round($costoFissoAnnuale / 12, 2),
        'fixed_fee_annual'  => $costoFissoAnnuale,
        'transport_fee_kwh' => 0.0089,
        'spread'            => $spread,
        'pun'               => $pun,
        'promo_active'      => false,
        'active'            => true,
        'brand'             => $brand,
        'logo'              => $offer['logo'] ?? null,
        'extra'             => $extra,
    ];
}

function normalizeGasOffer(array $offer): ?array {
    $brand = $offer['brand'] ?? 'Sconosciuto';
    $bm = getBrandMap();
    $supplierName = $bm[$brand] ?? ucfirst(strtolower($brand));

    $t = strtolower($offer['tariffa'] ?? '');
    $tipo = ($t === 'fissa' || $t === 'fisso') ? 'FISSO' : 'VARIABILE';

    // Dettagli prezzo (preservati per mostrarli all'utente)
    $psv = parseItalianNumber($offer['psv Aprile 2025/'] ?? null);
    $spread = parseItalianNumber($offer['spread'] ?? null);

    $prezzo = parseItalianNumber($offer['prezzo tot smc'] ?? null);
    if ($prezzo === null) {
        if ($psv !== null) $prezzo = $psv + ($spread ?? 0.0);
    }

    if ($prezzo === null || $prezzo <= 0) return null;

    $costoFissoAnnuale = parseItalianNumber($offer['costo_fisso'] ?? null) ?? 0.0;

    // Campi aggiuntivi (solo se valorizzati)
    $extra = [];
    if (!empty($offer['prezzo_bloccato'])) $extra['prezzo_bloccato_mesi'] = $offer['prezzo_bloccato'];
    if (!empty($offer['pagamento'])) $extra['modalita_pagamento'] = $offer['pagamento'];
    if (!empty($offer['vantaggi'])) $extra['vantaggi'] = $offer['vantaggi'];
    if (!empty($offer['note_costi'])) $extra['note'] = $offer['note_costi'];
    if (!empty($offer['validità offerta'])) $extra['validita_offerta'] = $offer['validità offerta'];
    if (!empty($offer['penale_recesso'])) $extra['penale_recesso'] = $offer['penale_recesso'];
    if (!empty($offer['penale'])) $extra['penale_recesso'] = $offer['penale'];

    // Profili di costo (se valorizzati)
    $profili = [];
    foreach (['basso', 'medio', 'alto'] as $p) {
        $val = parseItalianNumber($offer["costo_profilo_$p"] ?? null);
        if ($val !== null) $profili[$p] = $val;
    }
    if (!empty($profili)) $extra['costo_profili'] = $profili;

    return [
        'id'                => deterministicUuid("tariff-{$brand}-{$offer['offerta']}-GAS"),
        'supplier_id'       => deterministicUuid("supplier-{$brand}"),
        'supplier_name'     => $supplierName,
        'commodity'         => 'GAS',
        'name'              => $offer['offerta'] ?? 'Offerta',
        'type'              => $tipo,
        'price_mono_kwh'    => null,
        'price_smc'         => $prezzo,
        'fixed_fee_monthly' => round($costoFissoAnnuale / 12, 2),
        'fixed_fee_annual'  => $costoFissoAnnuale,
        'transport_fee_kwh' => 0.0,
        'spread'            => $spread,
        'psv'               => $psv,
        'promo_active'      => false,
        'active'            => true,
        'brand'             => $brand,
        'logo'              => $offer['logo'] ?? null,
        'extra'             => $extra,
    ];
}

function loadTariffs(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    if (!LUCE_JSON_URL && !GAS_JSON_URL) {
        error_log("tariff_loader: LUCE_JSON_URL e GAS_JSON_URL non configurati nel .env");
        return [];
    }

    try {
        $luceRaw = LUCE_JSON_URL ? fetchJson(LUCE_JSON_URL) : [];
        $gasRaw  = GAS_JSON_URL ? fetchJson(GAS_JSON_URL) : [];
    } catch (RuntimeException $e) {
        error_log("tariff_loader: " . $e->getMessage());
        return [];
    }
    
    $tariffs = [];
    foreach ($luceRaw as $o) { $t = normalizeLuceOffer($o); if ($t) $tariffs[] = $t; }
    foreach ($gasRaw as $o)  { $t = normalizeGasOffer($o);  if ($t) $tariffs[] = $t; }
    
    $cache = $tariffs;
    return $tariffs;
}

function loadSuppliers(): array {
    $tariffs = loadTariffs();
    $seen = [];
    $slugMap = getBrandSlug();
    foreach ($tariffs as $t) {
        $sid = $t['supplier_id'];
        if (!isset($seen[$sid])) {
            $brand = $t['brand'] ?? '';
            $firstLogo = null;
            foreach ($tariffs as $tt) {
                if (($tt['brand'] ?? '') === $brand && !empty($tt['logo'])) {
                    $firstLogo = $tt['logo'];
                    break;
                }
            }
            $seen[$sid] = [
                'id'   => $sid,
                'name' => $t['supplier_name'],
                'slug' => $slugMap[$brand] ?? strtolower(str_replace(' ', '-', $brand)),
                'logo' => $firstLogo,
            ];
        }
    }
    return array_values($seen);
}

function getTariffsByCommodity(string $commodity): array {
    return array_values(array_filter(loadTariffs(), fn($t) => $t['commodity'] === $commodity && $t['active']));
}

function getTariffsForCalculation(string $commodity, string $zone = 'NORD'): array {
    return getTariffsByCommodity($commodity);
}
