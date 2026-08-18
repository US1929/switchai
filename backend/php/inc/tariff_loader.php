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
        'A2A ENERGIA'     => 'A2A Energia',
        'ENEL ENERGIA'    => 'Enel Energia',
        'ENI PLENITUDE'   => 'Eni Plenitude',
        'IREN MERCATO'    => 'Iren Mercato',
        'EDISON ENERGIA'  => 'Edison Energia',
        'HERA COMM'       => 'Hera Comm',
        'OCTOPUS ENERGY'  => 'Octopus Energy',
        'POSTE ENERGIA'   => 'Poste Energia',
        'PULSEE'          => 'Pulsee',
        'FASTWEB ENERGIA' => 'Fastweb Energia',
        'NEN'             => 'NeN',
        'CALABRIA ENERGIA'=> 'Calabria Energia',
        'ASM VENDITA E SERVIZI' => 'ASM Vendita e Servizi',
        'AMG ENERGIA'     => 'AMG Energia',
        'BARI ENERGIA'    => 'Bari Energia',
        'E.ON ENERGIA'    => 'E.ON Energia',
        'ENGIE'           => 'Engie',
        'ESTRA'           => 'Estra',
        'ILLUMIA'         => 'Illumia',
        'ITALY GREEN POWER' => 'Italy Green Power',
        'VOLTY'           => 'Volty',
    ];
}

function getBrandSlug(): array {
    return [
        'A2A ENERGIA'     => 'a2a-energia',
        'ENEL ENERGIA'    => 'enel-energia',
        'ENI PLENITUDE'   => 'eni-plenitude',
        'IREN MERCATO'    => 'iren-mercato',
        'EDISON ENERGIA'  => 'edison-energia',
        'HERA COMM'       => 'hera-comm',
        'OCTOPUS ENERGY'  => 'octopus-energy',
        'POSTE ENERGIA'   => 'poste-energia',
        'PULSEE'          => 'pulsee',
        'FASTWEB ENERGIA' => 'fastweb-energia',
        'NEN'             => 'nen',
        'CALABRIA ENERGIA'=> 'calabria-energia',
        'ASM VENDITA E SERVIZI' => 'asm-vendita-servizi',
        'AMG ENERGIA'     => 'amg-energia',
        'BARI ENERGIA'    => 'bari-energia',
        'E.ON ENERGIA'    => 'eon-energia',
        'ENGIE'           => 'engie',
        'ESTRA'           => 'estra',
        'ILLUMIA'         => 'illumia',
        'ITALY GREEN POWER' => 'italy-green-power',
        'VOLTY'           => 'volty',
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

function hasPenaleFromTempistica(array $offer): bool {
    $text = ($offer['tempistica_info'] ?? '') . ' ' . ($offer['note_costi'] ?? '');
    $text = mb_strtolower($text);
    if (preg_match('/nessuna\s*penale|no\s*penal[il]|senza\s*penal[il]/iu', $text)) return false;
    if (preg_match('/penale|penali|penalità|recesso\s*anticipato/iu', $text)) return true;
    return false;
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
    if (!empty($offer['url_offerta']) && filter_var($offer['url_offerta'], FILTER_VALIDATE_URL)) {
        $extra['url_offerta'] = $offer['url_offerta'];
    }
    if (!empty($offer['url_sito_venditore']) && filter_var($offer['url_sito_venditore'], FILTER_VALIDATE_URL)) {
        $extra['url_sito_venditore'] = $offer['url_sito_venditore'];
    }
    if (!empty($offer['codice_offerta'])) $extra['codice_offerta'] = $offer['codice_offerta'];
    if (!empty($offer['componenti'])) $extra['componenti'] = $offer['componenti'];
    if (!empty($offer['sconti_applicati'])) $extra['sconti_applicati'] = $offer['sconti_applicati'];
    if (!empty($offer['sconti_non_applicati'])) $extra['sconti_non_applicati'] = $offer['sconti_non_applicati'];
    if (!empty($offer['sconto_note'])) $extra['sconto_note'] = $offer['sconto_note'];

    // Profili di costo (se valorizzati)
    $profili = [];
    foreach (['basso', 'medio', 'alto'] as $p) {
        $val = parseItalianNumber($offer["costo_profilo_$p"] ?? null);
        if ($val !== null) $profili[$p] = $val;
    }
    if (!empty($profili)) $extra['costo_profili'] = $profili;

    // Sconti: semplificati per il frontend
    $hasScontiCondizionali = !empty($offer['has_sconti_condizionali']);
    $scontoNote = $offer['sconto_note'] ?? '';

    // Estrai prezzi per fascia dai componenti ARERA
    $prezzoF1 = null; $prezzoF2 = null; $prezzoF3 = null;
    $componenti = $offer['componenti'] ?? [];

    if ($tipo === 'FISSO' && !empty($componenti) && is_array($componenti)) {
        // Per offerte fisse: somma tutti i componenti energia per fascia
        $sums = ['F1' => 0.0, 'F2' => 0.0, 'F3' => 0.0];
        foreach ($componenti as $c) {
            $unita = $c['unita'] ?? '';
            if (!str_contains($unita, '€/kWh')) continue;
            $cNome = $c['nome'] ?? '';
            if (stripos($cNome, 'SPREAD') !== false) continue;
            $fascia = $c['fascia'] ?? 'F1';
            if (!isset($sums[$fascia])) $fascia = 'F1';
            $sums[$fascia] += (float)($c['prezzo'] ?? 0);
        }
        if ($sums['F1'] > 0) $prezzoF1 = $sums['F1'];
        if ($sums['F2'] > 0) $prezzoF2 = $sums['F2'];
        if ($sums['F3'] > 0) $prezzoF3 = $sums['F3'];
    }
    // Per variabili o se non trovato: usa il prezzo mono (PUN+spread per variabili, prezzo fisso per fisse)
    if ($prezzoF1 === null) $prezzoF1 = $prezzo;
    if ($prezzoF2 === null) $prezzoF2 = $prezzo;
    if ($prezzoF3 === null) $prezzoF3 = $prezzo;

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
        'spread'            => $spread,
        'pun'               => $pun,
        'promo_active'      => false,
        'active'            => true,
        'brand'             => $brand,
        'logo'              => $offer['logo'] ?? null,
        'extra'             => $extra,
        'tipo_cliente'      => $offer['tipo_cliente'] ?? 'residenziale',
        'tipo_fasce'        => $offer['tipo_fasce'] ?? null,
        'regioni'           => $offer['regioni'] ?? [],
        'province'          => $offer['province'] ?? [],
        'nazionale'         => (bool)($offer['nazionale'] ?? true),
        'has_sconti_condizionali' => $hasScontiCondizionali,
        'sconto_note'       => $scontoNote,
        'is_main_supplier'  => isset($bm[$brand]),
        'has_penale_recesso'=> hasPenaleFromTempistica($offer),
        'attivabile_online' => !empty($offer['url_offerta']) && filter_var($offer['url_offerta'], FILTER_VALIDATE_URL),
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
    if (!empty($offer['url_offerta']) && filter_var($offer['url_offerta'], FILTER_VALIDATE_URL)) {
        $extra['url_offerta'] = $offer['url_offerta'];
    }
    if (!empty($offer['url_sito_venditore']) && filter_var($offer['url_sito_venditore'], FILTER_VALIDATE_URL)) {
        $extra['url_sito_venditore'] = $offer['url_sito_venditore'];
    }
    if (!empty($offer['codice_offerta'])) $extra['codice_offerta'] = $offer['codice_offerta'];
    if (!empty($offer['componenti'])) $extra['componenti'] = $offer['componenti'];
    if (!empty($offer['sconti_applicati'])) $extra['sconti_applicati'] = $offer['sconti_applicati'];
    if (!empty($offer['sconti_non_applicati'])) $extra['sconti_non_applicati'] = $offer['sconti_non_applicati'];
    if (!empty($offer['sconto_note'])) $extra['sconto_note'] = $offer['sconto_note'];

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
        'spread'            => $spread,
        'psv'               => $psv,
        'promo_active'      => false,
        'active'            => true,
        'brand'             => $brand,
        'logo'              => $offer['logo'] ?? null,
        'extra'             => $extra,
        'tipo_cliente'      => $offer['tipo_cliente'] ?? 'residenziale',
        'tipo_fasce'        => $offer['tipo_fasce'] ?? null,
        'regioni'           => $offer['regioni'] ?? [],
        'province'          => $offer['province'] ?? [],
        'nazionale'         => (bool)($offer['nazionale'] ?? true),
        'has_sconti_condizionali' => !empty($offer['has_sconti_condizionali']),
        'sconto_note'       => $offer['sconto_note'] ?? '',
        'is_main_supplier'  => isset($bm[$brand]),
        'has_penale_recesso'=> hasPenaleFromTempistica($offer),
        'attivabile_online' => !empty($offer['url_offerta']) && filter_var($offer['url_offerta'], FILTER_VALIDATE_URL),
    ];
}

function loadAreraData(): array {
    $dataDir = __DIR__ . '/../data/offerte';
    $luceFile = $dataDir . '/db-offerte-luce.json';
    $gasFile  = $dataDir . '/db-offerte-gas.json';

    $luce = is_file($luceFile) ? json_decode(file_get_contents($luceFile), true) : [];
    $gas  = is_file($gasFile)  ? json_decode(file_get_contents($gasFile), true) : [];

    return [
        'luce' => is_array($luce) ? $luce : [],
        'gas'  => is_array($gas)  ? $gas  : [],
        'updated' => is_file($luceFile) ? filemtime($luceFile) : 0,
    ];
}

function loadTariffs(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    // Primary: load from local ARERA JSON files
    $arera = loadAreraData();
    if (!empty($arera['luce']) || !empty($arera['gas'])) {
        $tariffs = [];
        foreach ($arera['luce'] as $o) { $t = normalizeLuceOffer($o); if ($t) $tariffs[] = $t; }
        foreach ($arera['gas'] as $o)  { $t = normalizeGasOffer($o);  if ($t) $tariffs[] = $t; }
        if (!empty($tariffs)) {
            $cache = $tariffs;
            return $tariffs;
        }
    }

    // Fallback: remote JSON URLs (legacy)
    if (!LUCE_JSON_URL && !GAS_JSON_URL) {
        error_log("tariff_loader: ARERA data not found and no remote URLs configured");
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

function tariffIsExpired(array $tariff): bool {
    $validUntil = $tariff['extra']['validita_offerta'] ?? null;
    if (!$validUntil) return false;
    $dt = DateTime::createFromFormat('d/m/Y', $validUntil);
    if (!$dt) return false;
    return $dt < new DateTime('today');
}

function getTariffsByCommodity(string $commodity): array {
    $raw = array_values(array_filter(loadTariffs(), fn($t) => $t['commodity'] === $commodity && $t['active'] && !tariffIsExpired($t)));
    $seen = [];
    $clean = [];
    foreach ($raw as $t) {
        if (isset($seen[$t['id']])) continue;
        $seen[$t['id']] = true;
        // Sanity check: scarta spread > 2 o prezzo > 5 (dati import corrotti)
        $spread = $t['spread'] ?? null;
        if ($spread !== null && $spread > 2.0) continue;
        $price = $commodity === 'LUCE' ? ($t['price_mono_kwh'] ?? null) : ($t['price_smc'] ?? null);
        if ($price !== null && $price > 5.0) continue;
        $clean[] = $t;
    }
    return $clean;
}

function getZoneRegions(string $zone): array {
    $map = [
        'NORD'   => ['01','02','03','04','05','06','07','08'],
        'CENTRO' => ['09','10','11','12','13'],
        'SUD'    => ['14','15','16','17','18','19','20'],
    ];
    return $map[$zone] ?? $map['NORD'];
}

function tariffIsAvailableInZone(array $tariff, string $zone, ?string $tipoCliente = null): bool {
    // Client type filter
    if ($tipoCliente && $tariff['tipo_cliente'] !== $tipoCliente) {
        return false;
    }

    // National offers are always available
    if ($tariff['nazionale']) {
        return true;
    }

    // Geographic check: if offer specifies regions, at least one must match the zone
    $offerRegions = $tariff['regioni'] ?? [];
    if (empty($offerRegions)) {
        return $tariff['nazionale']; // if no regions list, rely on nazionale flag
    }

    $zoneRegions = getZoneRegions($zone);
    foreach ($offerRegions as $r) {
        if (in_array($r, $zoneRegions, true)) {
            return true;
        }
    }

    return false;
}

function getTariffsForCalculation(string $commodity, string $zone = 'NORD', ?string $tipoCliente = null, array $filters = []): array {
    $all = getTariffsByCommodity($commodity);
    $all = array_values(array_filter($all, fn($t) => tariffIsAvailableInZone($t, $zone, $tipoCliente)));

    if (!empty($filters['main_suppliers'])) {
        $all = array_values(array_filter($all, fn($t) => $t['is_main_supplier'] ?? false));
    }
    if (!empty($filters['no_penali'])) {
        $all = array_values(array_filter($all, fn($t) => !($t['has_penale_recesso'] ?? false)));
    }
    if (!empty($filters['online_only'])) {
        $all = array_values(array_filter($all, fn($t) => $t['attivabile_online'] ?? false));
    }

    return $all;
}
