<?php
/**
 * bill_parser.php — Parser Bolletta 2.0 ARERA (Delibera 501/2014/R/com)
 *
 * Tutte le bollette italiane seguono sezioni e terminologia standard ARERA:
 *   Frontespizio → Scontrino dell'energia → Dettaglio fornitura → Box offerta
 *
 * Sistema di auto-apprendimento:
 *   - Pattern testati su 8 bollette reali (Enel, Octopus, A2A, NeN)
 *   - Nuove bollette vengono salvate come template per migliorare il parsing
 *   - Confidence score per ogni campo (0-1); l'LLM può colmare i gap
 */

// ── ARERA STANDARD DETECTION ────────────────────────────────────────

/** Rileva commodity basandosi sui termini standard ARERA */
function detectCommodity(string $text): string {
    $low = strtolower($text);

    // POD è specifico per LUCE (ARERA standard)
    if (preg_match('/\bIT\d{3}[ER]\d{8,9}\b/', $text)) return 'LUCE';
    // PDR è specifico per GAS
    if (preg_match('/\bPDR\b|\bpunto\s+di\s+riconsegna\b/', $low)) return 'GAS';

    // Terminologia standard ARERA
    $luceKw = ['energia elettrica', 'potenza impegnata', 'kw', 'kwh', 'luce', 'fasce f1 f2 f3', 'monoraria', 'bioraria'];
    $gasKw  = ['gas naturale', 'metano', 'smc', 'coefficiente c', 'pcs', 'gas metano'];

    $luceScore = 0; $gasScore = 0;
    foreach ($luceKw as $w) { if (str_contains($low, $w)) $luceScore++; }
    foreach ($gasKw as $w)  { if (str_contains($low, $w)) $gasScore++; }

    return $gasScore > $luceScore ? 'GAS' : 'LUCE';
}

/** Rileva fornitore dalla bolletta (brand comuni italiani) */
function detectSupplier(string $text): array {
    $low = strtolower($text);
    $supplier = 'Fornitore non rilevato';
    $confidence = 0;

    $brands = [
        'enel energia'          => 'Enel Energia',
        'servizio elettrico nazionale' => 'Servizio Elettrico Nazionale',
        'eni plenitude'         => 'Eni Plenitude',
        'plenitude'             => 'Eni Plenitude',
        'a2a energia'           => 'A2A Energia',
        'a2a'                   => 'A2A Energia',
        'nen'                   => 'NeN Energia',
        'iren luce'             => 'Iren Luce Gas e Servizi',
        'iren gas'              => 'Iren Luce Gas e Servizi',
        'iren'                  => 'Iren Luce Gas e Servizi',
        'edison'                => 'Edison',
        'sorgenia'              => 'Sorgenia',
        'hera comm'             => 'Hera Comm',
        'hera'                  => 'Hera Comm',
        'engie'                 => 'Engie',
        'illumia'               => 'Illumia',
        'acea'                  => 'ACEA Energia',
        'fastweb'               => 'Fastweb Energia',
        'octopus energy'        => 'Octopus Energy',
        'octopus'               => 'Octopus Energy',
        'nen'                   => 'NeN Energia',
    ];

    // Priorità: match più lungo, a parità conteggio occorrenze più alto
    $bestMatch = '';
    $bestName = '';
    $bestScore = 0;
    foreach ($brands as $kw => $name) {
        if (str_contains($low, $kw)) {
            $score = strlen($kw) * 100 + substr_count($low, $kw);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $kw;
                $bestName = $name;
            }
        }
    }
    if ($bestName) {
        $supplier = $bestName;
        $confidence = 0.9;
    }

    return ['name' => $supplier, 'confidence' => $confidence];
}

/** Estrae POD (IT001E...) o PDR (14 cifre) — formato ARERA standard */
function extractPOD_PDR(string $text, string $commodity): array {
    // POD: IT + 3 cifre + E/R + 8-9 cifre
    if (preg_match('/\b(IT\d{3}[ER]\d{8,9})\b/i', $text, $m)) {
        return ['value' => strtoupper($m[1]), 'confidence' => 1.0, 'type' => 'POD'];
    }
    // POD etichettato esplicitamente
    if (preg_match('/\bPOD[:\s]*(\w{14,16})\b/i', $text, $m)) {
        return ['value' => $m[1], 'confidence' => 0.9, 'type' => 'POD'];
    }
    // PDR: 14 cifre
    if (preg_match('/\bPDR[:\s]*(\d{14})\b/i', $text, $m)) {
        return ['value' => $m[1], 'confidence' => 1.0, 'type' => 'PDR'];
    }
    if (preg_match('/\b(\d{14})\b/', $text, $m)) {
        return ['value' => $m[1], 'confidence' => 0.7, 'type' => 'PDR_num'];
    }
    return ['value' => null, 'confidence' => 0, 'type' => null];
}

/**
 * Estrae il consumo annuo usando la terminologia standard ARERA.
 *
 * Bolletta 2.0 ha sezioni precise:
 *   "Consumo annuo" / "Consumo totale" / "Totale consumo"
 *   "dal GG/MM/AAAA al GG/MM/AAAA"  (periodo di riferimento)
 */
function extractConsumption(string $text, string $commodity): array {
    $unit = $commodity === 'LUCE' ? 'kwh' : 'smc';
    $low = mb_strtolower($text);

    $maxRealistic = $commodity === 'LUCE' ? 20000 : 5000;

    // PRIORITÀ 1: "Consumo annuo" esplicito (ARERA standard) — massima affidabilità
    $priorityPatterns = [
        // "Consumo annuo: X.XXX kWh"
        '#consumo\s+annuo[^0-9]*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '#i',
        '#consumo\s+anno[^0-9]*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '#i',
        // "Consumo annuo dal ... al ..."
        '#consumo\s+annuo\s+dal.*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '#is',
        // Formato bolletta combinata: "X.XXX kWh (dal GG/MM/AAAA al GG/MM/AAAA)"
        '#([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '\s*\(?dal\s+\d{2}/\d{2}/\d{4}#i',
    ];
    // Cerca TUTTI i match prioritari, prendi il migliore
    $bestPriVal = 0;
    $bestPriConf = 0;
    foreach ($priorityPatterns as $pat) {
        if (preg_match_all($pat, $low, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $val = parseNumberForConsumption($m[1]);
                if ($val >= 50 && $val <= $maxRealistic) {
                    $conf = 1.0;
                    // Penalizza valori sospetti (es. < 500 kWh per LUCE = probabilmente bimestrale)
                    if ($commodity === 'LUCE' && $val < 500) $conf = 0.4;
                    // Preferisci valori con confidenza alta, a parità il più alto
                    $score = ($conf * 10000) + $val;
                    if ($score > ($bestPriConf * 10000) + $bestPriVal) {
                        $bestPriVal = $val;
                        $bestPriConf = $conf;
                    }
                }
            }
        }
    }
    if ($bestPriVal > 0) {
        return $commodity === 'LUCE'
            ? ['kwh' => $bestPriVal, 'smc' => 0, 'source' => 'consumo_annuo_ARERA', 'confidence' => $bestPriConf]
            : ['kwh' => 0, 'smc' => $bestPriVal, 'source' => 'consumo_annuo_ARERA', 'confidence' => $bestPriConf];
    }

    // PRIORITÀ 2: Altri pattern (totale consumo, unità vicino a numero, etc.)
    $fallbackPatterns = [
        '#totale\s+consumo[^0-9]*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '#i',
        '#' . $unit . '\s*(?:annu[oi]|anno|totali)\s*:?\s*([\d]+(?:\.[\d]{3})*(?:,\d+)?)#i',
        '#([\d]+(?:\.[\d]{3})*(?:,\d+)?)\s*' . $unit . '#i',
    ];

    $bestVal = 0;
    $bestConfidence = 0;
    foreach ($fallbackPatterns as $idx => $pat) {
        if (preg_match_all($pat, $low, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $val = parseNumberForConsumption($m[1]);
                if ($val >= 50 && $val <= $maxRealistic && $val > $bestVal) {
                    $bestVal = $val;
                    $bestConfidence = max(0.4, 0.8 - ($idx * 0.1));
                }
            }
        }
    }

    // Rilevamento automatico: se nessun valore realistico trovato
    $source = $bestVal > 0 ? 'bolletta' : 'default_ARERA';
    $confidence = $bestVal > 0 ? $bestConfidence : 0.2;

    if ($bestVal == 0) {
        $bestVal = $commodity === 'LUCE' ? 2700.0 : 1000.0;
    }

    // Estrai F1, F2, F3 se Luce
    $f1 = 0; $f2 = 0; $f3 = 0;
    if ($commodity === 'LUCE') {
        if (preg_match('/\bF1\b.*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)/i', $low, $m)) $f1 = parseNumberForConsumption($m[1]);
        if (preg_match('/\bF2\b.*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)/i', $low, $m)) $f2 = parseNumberForConsumption($m[1]);
        if (preg_match('/\bF3\b.*?([\d]+(?:\.[\d]{3})*(?:,\d+)?)/i', $low, $m)) $f3 = parseNumberForConsumption($m[1]);
        
        // Se la somma è simile al totale, siamo fiduciosi
        $sum = $f1 + $f2 + $f3;
        if ($sum > 0 && abs($sum - $bestVal) < 10) {
            $bestVal = $sum;
        }
    }

    // Estrai Potenza Impegnata
    $potenza = 0;
    if (preg_match('/potenza\s+impegnata.*?([\d]+(?:[.,][\d]+)?)\s*kw/i', $low, $m)) {
        $potenza = (float)str_replace(',', '.', $m[1]);
    } else if (preg_match('/([\d]+(?:[.,][\d]+)?)\s*kw/i', $low, $m)) {
        $potenza_tmp = (float)str_replace(',', '.', $m[1]);
        if ($potenza_tmp >= 1.5 && $potenza_tmp <= 10) {
            $potenza = $potenza_tmp;
        }
    }
    if ($potenza == 0 && $commodity === 'LUCE') $potenza = 3.0; // Default

    return [
        'kwh' => $commodity === 'LUCE' ? $bestVal : 0,
        'smc' => $commodity === 'GAS' ? $bestVal : 0,
        'f1' => $f1,
        'f2' => $f2,
        'f3' => $f3,
        'potenza' => $potenza,
        'source' => $source,
        'confidence' => $confidence
    ];
}

/**
 * Estrae l'importo totale — ARERA: "Totale da pagare" nel frontespizio
 */
function extractTotalAmount(string $text): array {
    $low = mb_strtolower($text);

    // ARERA standard: "Totale da pagare" seguito da € importo
    $patterns = [
        '/totale\s+da\s+pagare[^0-9€]*[€]?\s*([\d.,]+)/i',
        '/totale\s+bolletta[^0-9€]*[€]?\s*([\d.,]+)/i',
        '/importo\s+totale[^0-9€]*[€]?\s*([\d.,]+)/i',
        '/([\d.,]+)\s*€\s*$.*(?:da\s+pagare|entro)/im',
        '/([\d.,]+)\s*€\s*$/m',
    ];

    foreach ($patterns as $pat) {
        if (preg_match($pat, $low, $m)) {
            $v = parseItalianNumber($m[1]);
            if ($v && $v > 5 && $v < 100000) {
                return ['value' => $v, 'confidence' => 0.9];
            }
        }
    }

    // Cattura l'importo più grande vicino a €
    if (preg_match_all('/([\d.,]+)\s*(?:€|eur)/i', $low, $amounts)) {
        $max = 0;
        foreach ($amounts[1] as $a) {
            $v = parseItalianNumber($a);
            if ($v && $v > $max && $v < 100000) $max = $v;
        }
        if ($max > 0) return ['value' => $max, 'confidence' => 0.6];
    }

    return ['value' => 0.0, 'confidence' => 0];
}

/** Rileva zona tariffaria da indirizzo/provincia */
function detectZone(string $text): array {
    $low = strtolower($text);
    $zoneMap = [
        'NORD'   => ['nord', 'piemonte', 'lombardia', 'veneto', 'friuli', 'liguria', 'trentino', 'emilia',
                     'trento', 'bolzano', 'trieste', 'milano', 'torino', 'genova', 'bologna', 'verona', 'padova', 'brescia', 'bergamo', 'monza'],
        'CENTRO' => ['centro', 'toscana', 'umbria', 'marche', 'lazio', 'firenze', 'roma', 'perugia', 'ancona'],
        'SUD'    => ['sud', 'campania', 'puglia', 'calabria', 'sicilia', 'sardegna', 'basilicata', 'molise', 'abruzzo',
                     'napoli', 'bari', 'palermo', 'cagliari', 'catanzaro', 'potenza'],
    ];

    foreach ($zoneMap as $zona => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($low, $kw)) return ['value' => $zona, 'confidence' => 0.8];
        }
    }

    return ['value' => 'NORD', 'confidence' => 0.3];
}

/** Rileva Canone RAI / Canone TV nella bolletta LUCE */
function extractCanoneRai(string $text): array {
    $low = mb_strtolower($text);

    // Pattern per Canone RAI con importo
    $patterns = [
        '#canone\s+(?:rai|tv|televisivo|abbonamento\s+(?:tv|televisione))[^0-9€]*[€]?\s*([\d.,]+)#i',
        '#canone\s+(?:rai|tv)[^0-9]*?([\d.,]+)\s*(?:€|eur)#i',
        '#importo\s+canone[^0-9]*?([\d.,]+)#i',
        // Formato bolletta: "Canone RAI € 90,00" o "Canone TV € 90.00"
        '#\bcanone\s+(?:rai|tv)\b[^0-9]*?(\d{1,3}[.,]\d{2})#i',
    ];

    foreach ($patterns as $pat) {
        if (preg_match($pat, $low, $m)) {
            $val = parseItalianNumber($m[1]);
            if ($val > 5 && $val < 500) {
                // Il canone RAI è €90/anno (addebitato in rate mensili da ~€9 o bimestrali da ~€18)
                // Se troviamo un importo mensile (~7-20€), moltiplichiamo × 12
                // Se troviamo un importo bimestrale (~14-40€), moltiplichiamo × 6
                // Se troviamo un importo annuale (70-120€), è già annuale
                if ($val < 20) {
                    return ['value' => round($val * 12, 2), 'confidence' => 0.8, 'period' => 'mensile'];
                } elseif ($val < 40) {
                    return ['value' => round($val * 6, 2), 'confidence' => 0.8, 'period' => 'bimestrale'];
                } elseif ($val >= 70 && $val <= 120) {
                    return ['value' => $val, 'confidence' => 0.9, 'period' => 'annuale'];
                }
                return ['value' => $val, 'confidence' => 0.6, 'period' => 'sconosciuto'];
            }
        }
    }

    // Rilevamento presenza senza importo: cerca solo la dicitura
    if (preg_match('#\bcanone\s+(?:rai|tv|televisivo)\b#i', $low)) {
        return ['value' => CANONE_RAI_ANNUO, 'confidence' => 0.7, 'period' => 'presunto_annuale'];
    }

    // Se è bolletta GAS, il canone RAI non c'è
    if (detectCommodity($text) === 'GAS') {
        return ['value' => 0, 'confidence' => 1.0, 'period' => null];
    }

    return ['value' => 0, 'confidence' => 0.5, 'period' => null];
}
function parseBillText(string $text): array {
    $text = preg_replace('/\s+/', ' ', trim($text));

    $commodity = detectCommodity($text);
    $supplier = detectSupplier($text);
    $podResult = extractPOD_PDR($text, $commodity);
    $consumption = extractConsumption($text, $commodity);
    $totalResult = extractTotalAmount($text);
    $zoneResult = detectZone($text);
    $canoneRaiResult = extractCanoneRai($text);

    // Stima spesa annuale (ARERA: bolletta bimestrale × 6)
    $annualSpend = 0.0;
    $spendConfidence = 0;
    if ($totalResult['value'] > 0) {
        $annualSpend = round($totalResult['value'] * 6, 2);
        $spendConfidence = 0.7;
    } elseif ($commodity === 'LUCE') {
        $annualSpend = round($consumption['kwh'] * 0.18 + 144, 2);
        $spendConfidence = 0.4;
    } else {
        $annualSpend = round($consumption['smc'] * 0.65 + 144, 2);
        $spendConfidence = 0.4;
    }

    $unit = $commodity === 'LUCE' ? 'kWh' : 'Smc';
    $consumoVal = $commodity === 'LUCE' ? $consumption['kwh'] : $consumption['smc'];

    // Learning: salva questa bolletta come template se mai vista prima
    saveBillTemplate($supplier['name'], $commodity, $text);

    return [
        'commodity'              => $commodity,
        'current_supplier'       => $supplier['name'],
        'pod_pdr'                => $podResult['value'],
        'yearly_consumption_kwh' => round($consumption['kwh'], 1),
        'yearly_consumption_smc' => round($consumption['smc'], 1),
        'yearly_consumption_f1'  => round($consumption['f1'] ?? 0, 1),
        'yearly_consumption_f2'  => round($consumption['f2'] ?? 0, 1),
        'yearly_consumption_f3'  => round($consumption['f3'] ?? 0, 1),
        'potenza_impegnata'      => round($consumption['potenza'] ?? 0, 1),
        'current_annual_spend'   => $annualSpend,
        'canone_rai'             => $canoneRaiResult['value'],
        'zone'                   => $zoneResult['value'],

        // Confidence scores (0-1) — l'LLM può usarli per decidere se intervenire
        'confidence' => [
            'commodity'       => 0.9,
            'supplier'        => $supplier['confidence'],
            'pod_pdr'         => $podResult['confidence'],
            'consumption'     => $consumption['confidence'],
            'annual_spend'    => $spendConfidence,
            'canone_rai'      => $canoneRaiResult['confidence'],
            'zone'            => $zoneResult['confidence'],
        ],

        // Metadati per LLM
        '_meta' => [
            'parser_version'  => '3.0-ARERA',
            'consumo_unit'    => $consumoVal . ' ' . $unit,
            'consumo_source'  => $consumption['source'],
            'advice'          => generateLLMAdvice($supplier['confidence'], $podResult['confidence'], $consumption['confidence'], $commodity, $consumption),
        ],
    ];
}

/**
 * Genera consigli per l'LLM su quali campi potrebbero aver bisogno di verifica umana.
 */
function generateLLMAdvice(float $supplierConf, float $podConf, float $consumoConf, string $commodity = 'LUCE', array $consumption = []): string {
    $unit = $commodity === 'LUCE' ? 'kWh' : 'Smc';
    $advice = [];
    if ($supplierConf < 0.7) $advice[] = "Fornitore non rilevato con certezza. Chiedi all'utente: 'Chi è il tuo attuale fornitore di energia?'";
    if ($podConf < 0.7) $advice[] = "POD/PDR non rilevato. Chiedi all'utente: 'Qual è il tuo codice POD (luce) o PDR (gas)? Si trova in bolletta, di solito in alto.'";
    if ($consumoConf < 0.5) {
        $val = $commodity === 'LUCE' ? ($consumption['kwh'] ?? 0) : ($consumption['smc'] ?? 0);
        $advice[] = "Consumo annuale trovato ({$val} {$unit}) ma potrebbe essere il consumo di periodo (bolletta combinata LUCE+GAS). "
                   . "Chiedi all'utente: 'Quanti {$unit} consumi all'anno? Il consumo annuo si trova nello Scontrino dell\'energia.'";
    }
    return empty($advice) ? 'Tutti i dati sono stati estratti con alta confidenza.' : implode(' ', $advice);
}

// ── LEARNING SYSTEM ─────────────────────────────────────────────────

/**
 * Salva un template di bolletta per migliorare il parsing futuro.
 * Ogni fornitore ha formattazioni diverse; salviamo esempi per pattern matching.
 */
function saveBillTemplate(string $supplier, string $commodity, string $text): void {
    $dir = __DIR__ . '/../../data/templates';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Genera un fingerprint del testo (primi 500 caratteri normalizzati)
    $fingerprint = md5(substr(preg_replace('/\s+/', ' ', $text), 0, 500));

    $templateFile = "$dir/{$fingerprint}.json";
    if (is_file($templateFile)) return; // già salvato

    file_put_contents($templateFile, json_encode([
        'supplier'   => $supplier,
        'commodity'  => $commodity,
        'saved_at'   => date('c'),
        'text_sample'=> substr($text, 0, 1000),
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * Carica i template salvati per suggerire pattern specifici per fornitore.
 */
function getSupplierTemplates(string $supplier): array {
    $dir = __DIR__ . '/../../data/templates';
    if (!is_dir($dir)) return [];

    $templates = [];
    foreach (glob("$dir/*.json") as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && ($data['supplier'] ?? '') === $supplier) {
            $templates[] = $data;
        }
    }
    return $templates;
}

// ── NUMBER PARSING ──────────────────────────────────────────────────
// parseItalianNumber è già definita in tariff_loader.php

function parseNumberForConsumption(string $s): float {
    $s = trim($s);
    if (str_contains($s, '.') && str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',') && !str_contains($s, '.')) {
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, '.') && !str_contains($s, ',')) {
        if (preg_match('/\.(\d{3})$/', $s)) {
            $s = str_replace('.', '', $s);
        }
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

// ── SAVINGS CALCULATION ─────────────────────────────────────────────

/**
 * Costanti regolatorie ARERA — FONTE UNICA per backend PHP.
 * Aggiornare QUI per propagare a TUTTI i calcoli.
 *
 * Fonti: ARERA Del. 449/2020 (perdite), Del. 575/2025/R/eel (trasporto, potenza),
 *        Comunicato Q2 2026 (oneri), DL 504/1995 (accise)
 * Data ultimo aggiornamento: 2026-06-26
 */
if (!defined('LUCE_PERDITE_RETE_BT'))       define('LUCE_PERDITE_RETE_BT', 1.102);
if (!defined('QUOTA_FISSA_RETI_LUCE'))       define('QUOTA_FISSA_RETI_LUCE', 23.04);   // Del. 575/2025
if (!defined('LUCE_TRASPORTO_VAR'))          define('LUCE_TRASPORTO_VAR', 0.01204);   // TRAS 0.01190 + UC3 0.00007 + UC6 0.00007
if (!defined('ONERI_SISTEMA_LUCE'))          define('ONERI_SISTEMA_LUCE', 0.0303);    // ASOS 0.02866 + ARIM 0.00164
if (!defined('LUCE_ACCISE'))                define('LUCE_ACCISE', 0.0227);
if (!defined('LUCE_ACCISE_SOGLIA_ESENTE'))   define('LUCE_ACCISE_SOGLIA_ESENTE', 1800);    // DL 504/1995
if (!defined('LUCE_ACCISE_SOGLIA_COMPENSATA')) define('LUCE_ACCISE_SOGLIA_COMPENSATA', 2640);
if (!defined('LUCE_COSTO_POTENZA_KW'))       define('LUCE_COSTO_POTENZA_KW', 23.52);   // Del. 575/2025
if (!defined('LUCE_IVA'))                   define('LUCE_IVA', 0.10);
if (!defined('CANONE_RAI_ANNUO'))           define('CANONE_RAI_ANNUO', 90.00);  // Canone RAI in bolletta LUCE (€/anno)

if (!defined('QUOTA_FISSA_RETI_GAS'))        define('QUOTA_FISSA_RETI_GAS', 23.00);
if (!defined('GAS_TRASPORTO_VAR'))           define('GAS_TRASPORTO_VAR', 0.15);
if (!defined('GAS_ONERI_SISTEMA'))           define('GAS_ONERI_SISTEMA', 0.03);
if (!defined('GAS_ACCISE'))                 define('GAS_ACCISE', 0.149959);
if (!defined('GAS_ADDIZIONALE_REGIONALE'))   define('GAS_ADDIZIONALE_REGIONALE', 0.0093);
if (!defined('GAS_SOGLIA_IVA_10'))           define('GAS_SOGLIA_IVA_10', 480);
if (!defined('GAS_IVA_10'))                 define('GAS_IVA_10', 0.10);
if (!defined('GAS_IVA_22'))                 define('GAS_IVA_22', 0.22);

/**
 * Espone le costanti ARERA come array (per endpoint API).
 */
function getAreraConstants(): array {
    return [
        'luce' => [
            'perdite_rete_bt'    => LUCE_PERDITE_RETE_BT,
            'quota_fissa_reti'   => QUOTA_FISSA_RETI_LUCE,
            'trasporto_var'      => LUCE_TRASPORTO_VAR,
            'oneri_sistema'      => ONERI_SISTEMA_LUCE,
            'accise'             => LUCE_ACCISE,
            'accise_soglia_esente' => LUCE_ACCISE_SOGLIA_ESENTE,
            'accise_soglia_compensata' => LUCE_ACCISE_SOGLIA_COMPENSATA,
            'costo_potenza_kw'   => LUCE_COSTO_POTENZA_KW,
            'iva'                => LUCE_IVA,
            'canone_rai'         => CANONE_RAI_ANNUO,
            'prezzo_riferimento' => 0.16,
            'quota_fissa_riferimento' => 120,
        ],
        'gas' => [
            'quota_fissa_reti'   => QUOTA_FISSA_RETI_GAS,
            'trasporto_var'      => GAS_TRASPORTO_VAR,
            'oneri_sistema'      => GAS_ONERI_SISTEMA,
            'accise'             => GAS_ACCISE,
            'addizionale_regionale' => GAS_ADDIZIONALE_REGIONALE,
            'soglia_iva_10'      => GAS_SOGLIA_IVA_10,
            'iva_10'             => GAS_IVA_10,
            'iva_22'             => GAS_IVA_22,
            'prezzo_riferimento' => 0.55,
            'quota_fissa_riferimento' => 120,
        ],
        'aggiornato_il' => '2026-06-26',
    ];
}

function calculateSavings(array $billData, int $topN = 3): array {
    require_once __DIR__ . '/tariff_loader.php';

    $commodity = $billData['commodity'];
    $kwh = $billData['yearly_consumption_kwh'] ?? 0;
    $smc = $billData['yearly_consumption_smc'] ?? 0;
    $f1 = $billData['yearly_consumption_f1'] ?? 0;
    $f2 = $billData['yearly_consumption_f2'] ?? 0;
    $f3 = $billData['yearly_consumption_f3'] ?? 0;
    $potenza = $billData['potenza_impegnata'] ?? 3.0;
    $currentSpend = $billData['current_annual_spend'] ?? 0;
    $currentSupplier = $billData['current_supplier'] ?? '';

    $offers = getTariffsByCommodity($commodity);
    $results = [];

    foreach ($offers as $offer) {
        if ($commodity === 'LUCE') {
            $priceMono = $offer['price_mono_kwh'] ?? 0;
            if ($f1 > 0 || $f2 > 0 || $f3 > 0) {
                $priceF1 = $offer['price_f1_kwh'] ?? $priceMono;
                $priceF2 = $offer['price_f2_kwh'] ?? $priceMono;
                $priceF3 = $offer['price_f3_kwh'] ?? $priceMono;
                $annualEnergy = ($f1 * $priceF1) + ($f2 * $priceF2) + ($f3 * $priceF3);
            } else {
                $annualEnergy = $kwh * $priceMono;
            }
            $costo_potenza = 21.48 * $potenza;
            $trasporto = $kwh * ($offer['transport_fee_kwh'] ?? 0.0089);
            $oneri = $kwh * ONERI_SISTEMA_LUCE;
            $accise = $kwh * 0.0227;
            $annualFixed = ($offer['fixed_fee_monthly'] ?? 0) * 12 + $costo_potenza + QUOTA_FISSA_RETI_LUCE;
            $subtotal = $annualEnergy + $annualFixed + $trasporto + $oneri + $accise;
            $annualIVA = $subtotal * 0.10;
            $annualTotal = round($subtotal + $annualIVA, 2);
        } else {
            $price = $offer['price_smc'] ?? 0;
            $annualEnergy = $smc * $price;
            $trasporto = $smc * 0.15;
            $oneri = $smc * 0.03;
            $accise = $smc * 0.15;
            $annualFixed = ($offer['fixed_fee_monthly'] ?? 0) * 12 + QUOTA_FISSA_RETI_GAS;
            $subtotal = $annualEnergy + $annualFixed + $trasporto + $oneri + $accise;

            $iva10 = min($smc, 480) / ($smc ?: 1) * $subtotal * 0.10;
            $iva22 = max(0, $smc - 480) / ($smc ?: 1) * $subtotal * 0.22;
            $annualIVA = $smc > 0 ? $iva10 + $iva22 : 0;
            $annualTotal = round($subtotal + $annualIVA, 2);
        }

        $savings = $currentSpend > 0 ? round($currentSpend - $annualTotal, 2) : null;
        $savingsPct = ($currentSpend > 0 && $savings !== null) ? round(($savings / $currentSpend) * 100, 1) : null;

        $results[] = [
            'id'               => $offer['id'],
            'supplier_name'    => $offer['supplier_name'],
            'name'             => $offer['name'],
            'type'             => $offer['type'],
            'price_mono_kwh'   => $offer['price_mono_kwh'] ?? null,
            'price_smc'        => $offer['price_smc'] ?? null,
            'fixed_fee_monthly'=> $offer['fixed_fee_monthly'],
            'annual_cost'      => $annualTotal,
            'annual_savings'   => $savings,
            'savings_pct'      => $savingsPct,
            'monthly_cost'     => round($annualTotal / 12, 2),
            'monthly_savings'  => $savings !== null ? round($savings / 12, 2) : null,
        ];
    }

    usort($results, fn($a, $b) => $a['annual_cost'] <=> $b['annual_cost']);
    return array_slice($results, 0, $topN);
}

function calculateSavingsBreakdown(array $data): array {
    $commodity = $data['commodity'] ?? '';
    $zone = $data['zone'] ?? 'NORD';
    $currentSupplier = $data['current_supplier'] ?? 'Generico';

    if (!in_array($commodity, ['LUCE', 'GAS'])) {
        throw new InvalidArgumentException("Commodity deve essere LUCE o GAS");
    }

    $yearlyKwh = (float)($data['yearly_consumption_kwh'] ?? 0);
    $yearlySmc = (float)($data['yearly_consumption_smc'] ?? 0);
    $currentAnnualSpend = (float)($data['current_annual_spend'] ?? 0);

    $f1 = (float)($data['yearly_consumption_f1'] ?? 0);
    $f2 = (float)($data['yearly_consumption_f2'] ?? 0);
    $f3 = (float)($data['yearly_consumption_f3'] ?? 0);
    $potenza = (float)($data['potenza_impegnata'] ?? 3.0);

    // PUN/PSV live per confronto simmetrico tariffe variabili
    // Fondamentale: usare lo STESSO PUN per entrambi i lati del confronto
    $livePunEurKwh = isset($data['live_pun_eur_kwh']) ? (float)$data['live_pun_eur_kwh'] : null;
    $livePsvEurSmc = isset($data['live_psv_eur_smc']) ? (float)$data['live_psv_eur_smc'] : null;

    // Prezzi di riferimento Tutela (o mercato standard)
    if ($commodity === 'LUCE') {
        $currentPriceKwh = 0.16;
        $currentFixedMonthly = 10.00;
        $currentTransportKwh = LUCE_TRASPORTO_VAR;
    } else {
        $currentPriceSmc = 0.55;
        $currentFixedMonthly = 10.00;
    }

    if ($currentAnnualSpend <= 0) {
        if ($commodity === 'LUCE' && $yearlyKwh > 0) {
            $costo_potenza = LUCE_COSTO_POTENZA_KW * $potenza;
            $oneri = $yearlyKwh * ONERI_SISTEMA_LUCE;
            $trasporto = $yearlyKwh * LUCE_TRASPORTO_VAR;
            // Accise DL 504/1995
            if ($yearlyKwh <= LUCE_ACCISE_SOGLIA_ESENTE) {
                $accise = 0;
            } elseif ($yearlyKwh <= LUCE_ACCISE_SOGLIA_COMPENSATA) {
                $accise = ($yearlyKwh - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
            } else {
                $esenzioneResidua = max(0, LUCE_ACCISE_SOGLIA_ESENTE - ($yearlyKwh - LUCE_ACCISE_SOGLIA_COMPENSATA));
                $accise = ($yearlyKwh - $esenzioneResidua) * LUCE_ACCISE;
            }
            $subtotal = ($yearlyKwh * $currentPriceKwh) + ($currentFixedMonthly * 12) + $costo_potenza + QUOTA_FISSA_RETI_LUCE + $trasporto + $oneri + $accise;
            $currentAnnualSpend = $subtotal * 1.10;
        } elseif ($commodity === 'GAS' && $yearlySmc > 0) {
            $trasporto = $yearlySmc * GAS_TRASPORTO_VAR;
            $oneri = $yearlySmc * GAS_ONERI_SISTEMA;
            $accise = $yearlySmc * GAS_ACCISE;
            $addizionale = $yearlySmc * GAS_ADDIZIONALE_REGIONALE;
            $subtotal = ($yearlySmc * $currentPriceSmc) + ($currentFixedMonthly * 12) + QUOTA_FISSA_RETI_GAS + $trasporto + $oneri + $accise + $addizionale;
            $iva10 = min($yearlySmc, GAS_SOGLIA_IVA_10) / ($yearlySmc ?: 1) * $subtotal * GAS_IVA_10;
            $iva22 = max(0, $yearlySmc - GAS_SOGLIA_IVA_10) / ($yearlySmc ?: 1) * $subtotal * GAS_IVA_22;
            $currentAnnualSpend = $subtotal + ($yearlySmc > 0 ? $iva10 + $iva22 : 0);
        }
    }

    $tariffs = getTariffsForCalculation($commodity, $zone);
    $results = [];
    $comparisonId = deterministicUuid('comparison-' . microtime(true));

    foreach ($tariffs as $tariff) {
        $fixedFee = (float)($tariff['fixed_fee_monthly'] ?? 0);
        $transportFee = (float)($tariff['transport_fee_kwh'] ?? LUCE_TRASPORTO_VAR);
        $annualCost = 0.0;
        $breakdown = [];
        $explanationParts = [];

        if ($commodity === 'LUCE') {
            $isVariable = $tariff['type'] === 'VARIABILE';
            $spread = isset($tariff['spread']) ? (float)$tariff['spread'] : null;
            $tariffPun = isset($tariff['pun']) ? (float)$tariff['pun'] : null;

            // Determina il prezzo energia da usare
            // ARERA v4.0: PERDITE_RETE_BT (10.2%) si applicano SOLO al PUN, non allo spread
            if ($isVariable && $livePunEurKwh !== null && $spread !== null) {
                $effectiveEnergyPrice = $livePunEurKwh * LUCE_PERDITE_RETE_BT + $spread;
            } elseif ($isVariable && $livePunEurKwh !== null && $spread === null && $tariffPun !== null) {
                $priceMono = $tariff['price_mono_kwh'];
                $estimatedSpread = max(0, (float)$priceMono - $tariffPun);
                $effectiveEnergyPrice = $livePunEurKwh * LUCE_PERDITE_RETE_BT + $estimatedSpread;
            } elseif ($isVariable && $livePunEurKwh !== null && $spread === null && $tariffPun === null) {
                $priceMono = $tariff['price_mono_kwh'];
                if ($priceMono === null) continue;
                $effectiveEnergyPrice = (float)$priceMono;
            } else {
                // Tariffa FISSA o PUN live non disponibile: usa prezzo contrattuale
                $priceMono = $tariff['price_mono_kwh'];
                if ($priceMono === null) continue;
                // Sanity check: prezzo fisso < costo regolato minimo è impossibile
                if (!$isVariable && (float)$priceMono < 0.05) continue;
                $effectiveEnergyPrice = (float)$priceMono;
            }

            // Usa effectiveEnergyPrice come prezzo monorario effettivo
            $priceMono = $effectiveEnergyPrice; // per retrocompatibilità con il resto del codice

            if ($f1 > 0 || $f2 > 0 || $f3 > 0) {
                // Multi-fascia: applica proporzioni al prezzo effettivo
                $priceF1 = $tariff['price_f1_kwh'] ?? null;
                $priceF2 = $tariff['price_f2_kwh'] ?? null;
                $priceF3 = $tariff['price_f3_kwh'] ?? null;
                if ($priceF1 !== null && $priceF2 !== null && $priceF3 !== null && $isVariable && $livePunEurKwh !== null) {
                    // Ricalcola prezzi fascia con spread + PUN live (approssimazione: stessi spread, diverso PUN)
                    $oldPun = $tariffPun ?? ($livePunEurKwh);
                    $punDelta = $livePunEurKwh - $oldPun;
                    $priceF1 = max(0, $priceF1 + $punDelta * LUCE_PERDITE_RETE_BT);
                    $priceF2 = max(0, $priceF2 + $punDelta * LUCE_PERDITE_RETE_BT);
                    $priceF3 = max(0, $priceF3 + $punDelta * LUCE_PERDITE_RETE_BT);
                }
                $priceF1 = $priceF1 ?? $effectiveEnergyPrice;
                $priceF2 = $priceF2 ?? $effectiveEnergyPrice;
                $priceF3 = $priceF3 ?? $effectiveEnergyPrice;
                $energyCost = ($f1 * $priceF1) + ($f2 * $priceF2) + ($f3 * $priceF3);
            } else {
                $energyCost = $yearlyKwh * $effectiveEnergyPrice;
            }

            $costo_potenza = LUCE_COSTO_POTENZA_KW * $potenza;
            $oneri = $yearlyKwh * ONERI_SISTEMA_LUCE;
            $trasporto = $yearlyKwh * LUCE_TRASPORTO_VAR;
            // Accise DL 504/1995 con soglie progressive
            if ($yearlyKwh <= LUCE_ACCISE_SOGLIA_ESENTE) {
                $accise = 0;
            } elseif ($yearlyKwh <= LUCE_ACCISE_SOGLIA_COMPENSATA) {
                $accise = ($yearlyKwh - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
            } else {
                $esenzioneResidua = max(0, LUCE_ACCISE_SOGLIA_ESENTE - ($yearlyKwh - LUCE_ACCISE_SOGLIA_COMPENSATA));
                $accise = ($yearlyKwh - $esenzioneResidua) * LUCE_ACCISE;
            }

            $fixedCost = $fixedFee * 12 + $costo_potenza + QUOTA_FISSA_RETI_LUCE;
            $subtotal = $energyCost + $fixedCost + $trasporto + $oneri + $accise;
            $annualCost = $subtotal * 1.10;
            $transportCost = $trasporto; // per retrocompatibilità display

            $currentEnergyCost = $yearlyKwh * $currentPriceKwh;
            $currentFixedCost = $currentFixedMonthly * 12 + $costo_potenza + QUOTA_FISSA_RETI_LUCE;
            $currentTransportCost = $yearlyKwh * $currentTransportKwh;

            $energyDiff = round($currentEnergyCost - $energyCost, 2);
            $fixedDiff = round($currentFixedCost - $fixedCost, 2);
            $transportDiff = round($currentTransportCost - $transportCost, 2);
            $effectivePrice = $yearlyKwh > 0 ? $energyCost / $yearlyKwh : 0;

            $breakdown = [
                'current_energy_cost' => round($currentEnergyCost, 2),
                'new_energy_cost'     => round($energyCost, 2),
                'energy_diff'         => $energyDiff,
                'current_fixed_cost'  => round($currentFixedCost, 2),
                'new_fixed_cost'      => round($fixedCost, 2),
                'fixed_diff'          => $fixedDiff,
                'new_price_kwh'       => round($effectivePrice, 6),
                'current_price_kwh'   => round($currentPriceKwh, 6),
            ];

            if ($energyDiff > 0) {
                $explanationParts[] = sprintf("Risparmi %.2f€ sulla componente energia (%.4f €/kWh vs %.4f €/kWh)", $energyDiff, $effectivePrice, $currentPriceKwh);
            }
            if ($fixedDiff > 0) {
                $explanationParts[] = sprintf("risparmi %.2f€ sulla quota fissa (%.2f€/mese vs %.2f€/mese)", $fixedDiff, $fixedFee, $currentFixedMonthly);
            } elseif ($fixedDiff < 0) {
                $explanationParts[] = sprintf("quota fissa leggermente più alta (+%.2f€/anno) ma compensata", abs($fixedDiff));
            }
        } else {
            $isVariable = $tariff['type'] === 'VARIABILE';
            $spread = isset($tariff['spread']) ? (float)$tariff['spread'] : null;
            $tariffPsv = isset($tariff['psv']) ? (float)$tariff['psv'] : null;

            // Determina il prezzo gas da usare
            if ($isVariable && $livePsvEurSmc !== null && $spread !== null) {
                // Tariffa variabile CON PSV live: usa PSV corrente + spread contrattuale
                $effectiveEnergyPrice = $livePsvEurSmc + $spread;
            } elseif ($isVariable && $livePsvEurSmc !== null && $spread === null && $tariffPsv !== null) {
                // Spread mancante: stima da price_smc - PSV storico
                $priceSmc = $tariff['price_smc'];
                $estimatedSpread = max(0, (float)$priceSmc - $tariffPsv);
                $effectiveEnergyPrice = $livePsvEurSmc + $estimatedSpread;
            } elseif ($isVariable && $livePsvEurSmc !== null && $spread === null && $tariffPsv === null) {
                // Nessun dato spread/PSV: usa price_smc congelato (fallback)
                $priceSmc = $tariff['price_smc'];
                if ($priceSmc === null) continue;
                $effectiveEnergyPrice = (float)$priceSmc;
            } else {
                // Tariffa FISSA o PSV live non disponibile: usa prezzo contrattuale
                $priceSmc = $tariff['price_smc'];
                if ($priceSmc === null) continue;
                // Sanity check: prezzo gas < 0.15 €/Smc è sospetto
                if (!$isVariable && (float)$priceSmc < 0.15) continue;
                $effectiveEnergyPrice = (float)$priceSmc;
            }

            $energyCost = $yearlySmc * $effectiveEnergyPrice;
            $trasporto = $yearlySmc * GAS_TRASPORTO_VAR;
            $oneri = $yearlySmc * GAS_ONERI_SISTEMA;
            $accise = $yearlySmc * GAS_ACCISE;
            $addizionale = $yearlySmc * GAS_ADDIZIONALE_REGIONALE;

            $fixedCost = $fixedFee * 12 + QUOTA_FISSA_RETI_GAS;
            $subtotal = $energyCost + $fixedCost + $trasporto + $oneri + $accise + $addizionale;

            $iva10 = min($yearlySmc, GAS_SOGLIA_IVA_10) / ($yearlySmc ?: 1) * $subtotal * GAS_IVA_10;
            $iva22 = max(0, $yearlySmc - GAS_SOGLIA_IVA_10) / ($yearlySmc ?: 1) * $subtotal * GAS_IVA_22;
            $annualCost = $subtotal + ($yearlySmc > 0 ? $iva10 + $iva22 : 0);

            $currentEnergyCost = $yearlySmc * $currentPriceSmc;
            $currentFixedCost = $currentFixedMonthly * 12 + QUOTA_FISSA_RETI_GAS;

            $energyDiff = round($currentEnergyCost - $energyCost, 2);
            $fixedDiff = round($currentFixedCost - $fixedCost, 2);
            $effectivePrice = $yearlySmc > 0 ? $energyCost / $yearlySmc : 0;

            $breakdown = [
                'current_energy_cost' => round($currentEnergyCost, 2),
                'new_energy_cost'     => round($energyCost, 2),
                'energy_diff'         => $energyDiff,
                'current_fixed_cost'  => round($currentFixedCost, 2),
                'new_fixed_cost'      => round($fixedCost, 2),
                'fixed_diff'          => $fixedDiff,
                'new_price_smc'       => round($effectivePrice, 6),
                'current_price_smc'   => round($currentPriceSmc, 6),
            ];

            if ($energyDiff > 0) {
                $explanationParts[] = sprintf("Risparmi %.2f€ sulla materia prima gas (%.4f €/Smc vs %.4f €/Smc)", $energyDiff, $effectivePrice, $currentPriceSmc);
            }
            if ($fixedDiff > 0) {
                $explanationParts[] = sprintf("risparmi %.2f€ sulla quota fissa (%.2f€/mese vs %.2f€/mese)", $fixedDiff, $fixedFee, $currentFixedMonthly);
            } elseif ($fixedDiff < 0) {
                $explanationParts[] = sprintf("quota fissa leggermente più alta (+%.2f€/anno) ma compensata", abs($fixedDiff));
            }
        }

        if ($tariff['type'] === 'VARIABILE') {
            $punRef = $commodity === 'LUCE' ? ($livePunEurKwh ? round($livePunEurKwh * 1000, 1) . ' €/MWh' : 'PUN di riferimento') : ($livePsvEurSmc ? round($livePsvEurSmc * 1000, 1) . ' €/MWh' : 'PSV di riferimento');
            $explanationParts[] = "prezzo indicizzato al mercato, calcolato con $punRef + spread contrattuale (confronto simmetrico ARERA)";
        } else {
            $explanationParts[] = "prezzo bloccato, protezione da rincari futuri";
        }

        $explanation = implode('. ', $explanationParts) . '.';
        $breakdown['explanation'] = $explanation;

        $savings = $currentAnnualSpend - $annualCost;
        $savingsPct = $currentAnnualSpend > 0 ? ($savings / $currentAnnualSpend) * 100 : 0;

        $extra = $tariff['extra'] ?? [];
        $isFixed = $tariff['type'] === 'FISSO';
        $monthlyCost = round($annualCost / 12, 2);
        $monthlySavings = $savings > 0 ? round($savings / 12, 2) : 0;
        $unitPrice = $commodity === 'LUCE' ? ($tariff['price_mono_kwh'] ?? null) : ($tariff['price_smc'] ?? null);

        // Sanity check: prezzo sospettosamente basso (< 0,05 €/kWh o < 0,20 €/Smc)
        $priceWarning = null;
        $unitPrice = $commodity === 'LUCE' ? ($tariff['price_mono_kwh'] ?? null) : ($tariff['price_smc'] ?? null);
        $threshold = $commodity === 'LUCE' ? 0.05 : 0.20;
        if ($unitPrice !== null && $unitPrice > 0 && $unitPrice < $threshold) {
            $priceWarning = "Prezzo molto inferiore alla media di mercato. Potrebbe essere un'offerta promozionale o contenere condizioni particolari. Verifica sempre il contratto prima di sottoscrivere.";
        }

        $results[] = [
            'tariff_id'           => $tariff['id'],
            'supplier'            => $tariff['supplier_name'],
            'tariff_name'         => $tariff['name'],
            'annual_cost_eur'     => round($annualCost, 2),
            'monthly_cost_eur'    => $monthlyCost,
            'savings_eur'         => round($savings, 2),
            'savings_pct'         => round($savingsPct, 1),
            'monthly_savings_eur' => $monthlySavings,
            'type'                => $tariff['type'],
            'contract_detail'     => $isFixed
                ? ('Prezzo fisso' . (!empty($extra['prezzo_bloccato_mesi']) ? " bloccato per {$extra['prezzo_bloccato_mesi']} mesi" : ' (durata non specificata)'))
                : 'Prezzo variabile indicizzato al ' . ($commodity === 'LUCE' ? 'PUN' : 'PSV'),
            'price_per_unit'      => $unitPrice,
            'unit'                => $commodity === 'LUCE' ? 'kWh' : 'Smc',
            'spread'              => $tariff['spread'] ?? null,
            'fixed_fee_monthly'   => $fixedFee,
            'fixed_fee_annual'    => $tariff['fixed_fee_annual'] ?? null,
            'payment_method'      => $extra['modalita_pagamento'] ?? null,
            'advantages'          => $extra['vantaggi'] ?? null,
            'valid_until'         => $extra['validita_offerta'] ?? null,
            'penale_recesso'      => $extra['penale_recesso'] ?? null,
            'promo_active'        => $tariff['promo_active'],
            'supplier_logo'       => $tariff['logo'] ?? null,
            'price_warning'       => $priceWarning,
            'subscription_url'    => "https://www.switchai.it/sottoscrizione?tariff={$tariff['id']}&supplier=" . urlencode($tariff['supplier_name']) . "&name=" . urlencode($tariff['name']) . "&commodity=" . ($commodity === 'LUCE' ? 'luce' : 'gas') . "&annualCost=" . round($annualCost, 0),
            'breakdown'           => $breakdown,
        ];
    }

    usort($results, fn($a, $b) => $a['annual_cost_eur'] <=> $b['annual_cost_eur']);
    $topResults = array_slice($results, 0, 3);

    return [
        'results'               => $topResults,
        'comparison_id'         => $comparisonId,
        'current_spend_estimated' => round($currentAnnualSpend, 2),
    ];
}
