<?php
/**
 * SwitchAI MCP Server — PHP (HTTP POST JSON-RPC)
 *
 * Protocollo MCP accessibile pubblicamente a:
 *   POST https://www.switchai.it/mcp
 *
 * Compatibile con client MCP che supportano HTTP transport.
 * Registrabile su mcp.so, Smithery, e directory MCP.
 */

// Suppress PHP errors in production (OVH PHP-FPM doesn't read .htaccess php_flag)
error_reporting(0);
ini_set('display_errors', '0');

// ── Carica variabili d'ambiente ─────────────────────────────────────
$envPaths = [__DIR__ . '/../../.env', __DIR__ . '/../.env', $_SERVER['DOCUMENT_ROOT'] . '/.env'];
foreach ($envPaths as $envFile) {
    if (is_file($envFile) && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) putenv($line);
        }
        break;
    }
}

$allowedOrigins = ['https://www.switchai.it', 'https://switchai.it', 'http://localhost:5173', 'http://localhost:8080'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($origin, $allowedOrigins) ? $origin : 'https://www.switchai.it'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

require_once __DIR__ . '/../inc/tariff_loader.php';
require_once __DIR__ . '/../inc/bill_parser.php';
require_once __DIR__ . '/../inc/api_auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $input['method'] ?? '';

// ── Rate Limiting (solo per tools/call, non per initialize/list) ──
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
if (!$isLocal && $method === 'tools/call') {
    $client = getClientTier();
    if (!checkRateLimit($client)) {
        http_response_code(429);
        header('Retry-After: 3600');
        echo json_encode(['error' => 'Rate limit superato.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── MCP: initialize (richiesto dal protocollo) ────────────────────

if ($method === 'initialize') {
    echo json_encode([
        'jsonrpc' => '2.0',
        'id'      => $input['id'] ?? 1,
        'result'  => [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => [
                'name'    => 'SwitchAI',
                'version' => '2.3.0',
            ],
        ],
    ]);
    exit;
}

// ── MCP: tools/list ──────────────────────────────────────────────

if ($method === 'tools/list') {
    echo json_encode([
        'jsonrpc' => '2.0',
        'id'      => $input['id'] ?? 1,
        'result'  => [
            'tools' => [
                [
                    'name' => 'calculate_energy_savings',
                    'description' => "Confronta le tariffe Luce o Gas e calcola il risparmio annuo. Ricevi le 3 migliori offerte con link diretto al sito del fornitore (affiliazione se disponibile). "
                        . "FLUSSO CORRETTO: "
                        . "(0) PRIMA che l'utente condivida la bolletta, avvisalo: "
                        . "'Condividendo la bolletta con me, i tuoi dati personali verranno elaborati da [nome provider LLM, es. Anthropic/OpenAI] secondo la loro privacy policy. "
                        . "SwitchAI riceverà solo dati numerici (consumi, spesa, zona) e non conserverà i tuoi dati personali dopo la sessione.' "
                        . "(1) Estrai i dati numerici dalla bolletta: commodity (LUCE/GAS), consumo annuo, spesa annua, zona (NORD/CENTRO/SUD), "
                        . "spesa_materia_energia, quota_fissa_mensile, tipo_cliente, canone_rai (se LUCE). "
                        . "(2) Passa i dati numerici a questo tool. "
                        . "(3) Il tool restituisce le 3 migliori offerte con link per attivare direttamente sul sito del fornitore. "
                        . "(4) L'utente clicca il link e completa l'attivazione in autonomia sul sito del fornitore.",
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => false],
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'bill_text'         => ['type' => 'string', 'description' => '(Opzionale) Testo della bolletta per il parser PHP. Preferisci estrarre tu i dati ed usare i parametri numerici.'],
                            'commodity'         => ['type' => 'string', 'enum' => ['LUCE', 'GAS'], 'description' => 'Tipo fornitura. Deduci dal testo: kWh/POD = LUCE, Smc/PDR = GAS.'],
                            'yearly_consumption_kwh' => ['type' => 'number', 'description' => 'Consumo annuo kWh (LUCE). Cerca "consumo annuo stimato" nella bolletta ARERA 2.0. Preferisci questo nome (o consumi_annui_kwh come alias).'],
                            'yearly_consumption_smc' => ['type' => 'number', 'description' => 'Consumo annuo Smc (GAS).'],
                            'current_annual_spend'   => ['type' => 'number', 'description' => 'Spesa annua attuale in € (IVA inclusa, TOTALE bolletta × periodo). Moltiplica importo bolletta × 6 (bimestrale) o × 4 (trimestrale). Alias: spesa_annua_eur.'],
                            'consumo_annuo_kwh' => ['type' => 'number', 'description' => '(Alias di yearly_consumption_kwh)'],
                            'consumo_annuo_smc' => ['type' => 'number', 'description' => '(Alias di yearly_consumption_smc)'],
                            'spesa_annua_eur'   => ['type' => 'number', 'description' => '(Alias di current_annual_spend)'],
                            'canone_rai'        => ['type' => 'number', 'description' => 'Canone RAI annuale in € (solo LUCE). Cerca "Canone RAI" o "Canone TV" nel dettaglio costi. Se presente ~90€/anno. 0 se assente o bolletta GAS.'],
                            'spesa_materia_energia' => ['type' => 'number', 'description' => 'Spesa annua MATERIA ENERGIA in € (solo componente energia/gas, ESCLUDI trasporto, oneri, imposte, IVA, Canone RAI). Dal dettaglio costi bolletta.'],
                            'quota_fissa_mensile' => ['type' => 'number', 'description' => 'Quota fissa mensile in €/mese dal Box Offerta o dettaglio costi. Es: "12,00 €/mese".'],
                            'tipo_cliente'      => ['type' => 'string', 'enum' => ['residenziale', 'business'], 'description' => 'Tipo cliente: "residenziale" (uso domestico/residenziale) o "business" (Partita IVA, non domestico, azienda). Default: residenziale.'],
                            'tariff_type'       => ['type' => 'string', 'enum' => ['fisso', 'variabile'], 'description' => '(Opzionale) Tipo tariffa attuale: "fisso" o "variabile". Per tariffe variabili il confronto usa PUN simmetrico.'],
                            'spread_eur_kwh'    => ['type' => 'number', 'description' => '(Opzionale) Spread attuale in €/kWh per tariffe LUCE variabili. Dal Box Offerta.'],
                            'spread_eur_smc'    => ['type' => 'number', 'description' => '(Opzionale) Spread attuale in €/Smc per tariffe GAS variabili. Dal Box Offerta.'],
                            'zona'              => ['type' => 'string', 'enum' => ['NORD', 'CENTRO', 'SUD'], 'description' => 'Zona tariffaria: NORD (Lombardia, Piemonte, Veneto...), CENTRO (Toscana, Lazio, Marche...), SUD (Campania, Sicilia, Calabria...).'],

                        ],
                    ],
                    'outputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'profile'               => ['type' => 'object', 'description' => 'Dati riepilogativi: commodity, consumo_annuo, spesa_annua_eur, zona'],
                            'top3'                  => ['type' => 'array', 'description' => 'Top 3 offerte con supplier, tariff_name, annual_cost_eur, savings_eur, savings_pct, subscription_url'],
                            'agent_summary'         => ['type' => 'string', 'description' => 'Riepilogo e istruzioni per guidare l\'utente all\'attivazione con prefill.'],
                            'prefill_instructions'  => ['type' => 'string', 'description' => 'SwitchAI non raccoglie dati personali. Attivazione sul sito del fornitore tramite link.']
                        ],
                    ],
                ],
                [
                    'name' => 'get_available_offers',
                    'description' => 'Elenca tutte le offerte disponibili per Luce o Gas nel mercato libero italiano. Restituisce tutte le offerte attive con prezzi, tipo contratto, quota fissa e dettagli fornitore.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'required' => ['commodity'],
                        'properties' => [
                            'commodity' => ['type' => 'string', 'enum' => ['LUCE', 'GAS'], 'description' => 'LUCE per offerte energia elettrica, GAS per offerte gas naturale.'],
                        ],
                    ],
                    'outputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'commodity' => ['type' => 'string'],
                            'count'     => ['type' => 'integer', 'description' => 'Numero totale offerte disponibili'],
                            'offers'    => ['type' => 'array', 'description' => 'Lista offerte con supplier_name, name, type, price, fixed_fee_monthly, spread, pun/psv, extra'],
                        ],
                    ],
                ],
                [
                    'name' => 'get_market_indices',
                    'description' => 'Restituisce PUN (Prezzo Unico Nazionale dell\'energia elettrica) e PSV (Punto di Scambio Virtuale del gas) correnti, aggiornati quotidianamente da fonte pubblica.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                    'outputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'pun'          => ['type' => 'number', 'description' => 'PUN corrente in €/kWh'],
                            'psv'          => ['type' => 'number', 'description' => 'PSV corrente in €/Smc'],
                            'pun_display'  => ['type' => 'string', 'description' => 'PUN formattato in €/MWh e €/kWh'],
                            'psv_display'  => ['type' => 'string', 'description' => 'PSV formattato in €/MWh e €/Smc'],
                            'source'       => ['type' => 'string'],
                            'data_date'    => ['type' => 'string', 'description' => 'Data del dato'],
                        ],
                    ],
                ],
                [
                    'name' => 'parse_energy_bill',
                    'description' => 'Estrae e struttura i dati da una bolletta energia italiana (luce o gas) fornita come testo. Riconosce fornitore, POD/PDR, consumi annui, spesa annua, zona tariffaria.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'required' => ['bill_text'],
                        'properties' => [
                            'bill_text' => ['type' => 'string', 'description' => 'Testo completo della bolletta energia italiana.'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    exit;
}

// ── MCP: tools/call ──────────────────────────────────────────────

if ($method === 'tools/call') {
    $toolName = $input['params']['name'] ?? '';
    $args = $input['params']['arguments'] ?? [];

    switch ($toolName) {
        case 'calculate_energy_savings':
            $result = mcp_analyze($args);
            break;
        case 'parse_energy_bill':
            $result = mcp_parse_bill($args);
            break;
        case 'get_available_offers':
            $commodity = strtoupper($args['commodity'] ?? 'LUCE');
            $tariffs = getTariffsByCommodity($commodity);
            $result = ['commodity' => $commodity, 'count' => count($tariffs), 'offers' => $tariffs];
            break;
        case 'get_market_indices':
            $result = mcp_market_indices();
            break;
        default:
            echo json_encode(['jsonrpc' => '2.0', 'id' => $input['id'] ?? 1, 'error' => ['code' => -32601, 'message' => "Tool '$toolName' not found"]]);
            exit;
    }

    echo json_encode([
        'jsonrpc' => '2.0',
        'id'      => $input['id'] ?? 1,
        'result'  => ['content' => [['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]]],
    ]);
    exit;
}

// MCP: notifications (no response needed)
if (str_starts_with($method, 'notifications/')) {
    http_response_code(200);
    echo json_encode(['jsonrpc' => '2.0', 'id' => $input['id'] ?? null, 'result' => new stdClass()]);
    exit;
}

// Fallback
echo json_encode(['jsonrpc' => '2.0', 'id' => $input['id'] ?? 1, 'error' => ['code' => -32601, 'message' => "Method '$method' not found. Use tools/list or tools/call."]]);

// ── Tool implementations ─────────────────────────────────────────

function mcp_analyze(array $args): string {
    $commodity = strtoupper($args['commodity'] ?? 'LUCE');
    $consumo = (float)($args['yearly_consumption_kwh'] ?? $args['consumo_annuo_kwh'] ?? $args['yearly_consumption_smc'] ?? $args['consumo_annuo_smc'] ?? 0);
    $spesa = (float)($args['current_annual_spend'] ?? $args['spesa_annua_eur'] ?? 0);
    $zona = $args['zone'] ?? $args['zona'] ?? 'NORD';
    $canoneRai = (float)($args['canone_rai'] ?? 0);
    $canoneRaiExplicit = isset($args['canone_rai']);
    $tariffType = $args['tariff_type'] ?? null;

    // Parse da bill_text solo come fallback
    if (empty($consumo) && !empty($args['bill_text'])) {
        $parsed = parseBillText($args['bill_text']);
        $commodity = $parsed['commodity'];
        $consumo = $commodity === 'LUCE' ? $parsed['yearly_consumption_kwh'] : $parsed['yearly_consumption_smc'];
        $spesa = $parsed['current_annual_spend'];
        $zona = $parsed['zone'];
        $canoneRai = $parsed['canone_rai'] ?? 0;
    }

    if ($consumo <= 0) return json_encode(['error' => 'Fornire consumo_annuo_kwh o consumo_annuo_smc']);

    if ($spesa <= 0) {
        $spesa = $commodity === 'LUCE' ? ($consumo * 0.18 + 144) : ($consumo * 0.65 + 144);
    }

    // ── PUN/PSV da config.json (sync ARERA giornaliero, batch notturno) ─
    $livePunEurKwh = getAreraForwardPun();
    $livePsvEurSmc = getAreraForwardPsv();

    // ── RICALCOLO SPESA PER TARIFFE VARIABILI ─
    $isCurrentVariable = false;
    $spesaAttualizzata = null;
    if ($tariffType) {
        $isCurrentVariable = strtolower($tariffType) === 'variabile';
    } elseif (!empty($args['bill_text'])) {
        $low = mb_strtolower($args['bill_text']);
        $isCurrentVariable = str_contains($low, 'variabile') || str_contains($low, 'indicizzato')
                   || str_contains($low, 'pun') || str_contains($low, 'psv');
    }

    if ($isCurrentVariable && $consumo > 0 && $livePunEurKwh !== null && $commodity === 'LUCE') {
        $estimatedSpread = (float)($args['spread_eur_kwh'] ?? 0);
        if ($estimatedSpread <= 0 && !empty($args['bill_text'])) {
            if (preg_match('/(?:PUN|PSV)\s*\+\s*([\d,.]+)/i', $args['bill_text'], $m)) {
                $estimatedSpread = (float)str_replace(',', '.', $m[1]);
            } elseif (preg_match('/spread[:\s]*([\d,.]+)/i', $args['bill_text'], $m)) {
                $estimatedSpread = (float)str_replace(',', '.', $m[1]);
            }
        }
        if ($estimatedSpread <= 0) {
            $estimatedSpread = max(0.002, round(($spesa / $consumo) - ($livePunEurKwh * LUCE_PERDITE_RETE_BT) - 0.045, 4));
        }
        // ARERA v4.0: perdite rete SOLO sul PUN
        $energyCostNow = $consumo * ($livePunEurKwh * LUCE_PERDITE_RETE_BT + $estimatedSpread);
        $quotaFissaMensile = (float)($args['quota_fissa_mensile'] ?? 0);
        $costoPotenza = LUCE_COSTO_POTENZA_KW * 3.0;
        $fixedNow = ($quotaFissaMensile > 0 ? $quotaFissaMensile : 10.00) * 12 + $costoPotenza + QUOTA_FISSA_RETI_LUCE;
        $oneriAcciseTrasporto = $consumo * (ONERI_SISTEMA_LUCE + LUCE_ACCISE + LUCE_TRASPORTO_VAR);
        $subtotalNow = $energyCostNow + $fixedNow + $oneriAcciseTrasporto;
        $spesaAttualizzata = round($subtotalNow * 1.10, 2);
    } elseif ($isCurrentVariable && $consumo > 0 && $livePsvEurSmc !== null && $commodity === 'GAS') {
        $estimatedSpread = (float)($args['spread_eur_smc'] ?? 0);
        if ($estimatedSpread <= 0 && !empty($args['bill_text'])) {
            if (preg_match('/(?:PUN|PSV)\s*\+\s*([\d,.]+)/i', $args['bill_text'], $m)) {
                $estimatedSpread = (float)str_replace(',', '.', $m[1]);
            }
        }
        if ($estimatedSpread <= 0) {
            $estimatedSpread = max(0.005, round(($spesa / $consumo) - $livePsvEurSmc - 0.05, 4));
        }
        $energyCostNow = $consumo * ($livePsvEurSmc + $estimatedSpread);
        $quotaFissaMensile = (float)($args['quota_fissa_mensile'] ?? 0);
        $fixedNow = ($quotaFissaMensile > 0 ? $quotaFissaMensile : 10.00) * 12 + QUOTA_FISSA_RETI_GAS;
        $oneriAcciseTrasporto = $consumo * (GAS_TRASPORTO_VAR + GAS_ONERI_SISTEMA + GAS_ACCISE);
        $subtotalNow = $energyCostNow + $fixedNow + $oneriAcciseTrasporto;
        $iva10 = min($consumo, GAS_SOGLIA_IVA_10) / ($consumo ?: 1) * $subtotalNow * GAS_IVA_10;
        $iva22 = max(0, $consumo - GAS_SOGLIA_IVA_10) / ($consumo ?: 1) * $subtotalNow * GAS_IVA_22;
        $spesaAttualizzata = round($subtotalNow + $iva10 + $iva22, 2);
    }

    // Canone RAI: sottrai dalla spesa per confronto equo (non cambia con fornitore)
    $spesaBase = $spesaAttualizzata ?? $spesa;
    // Se < 30€ → valore mensile, annualizza a 90€
    if ($canoneRai > 0 && $canoneRai < 30 && $commodity === 'LUCE') {
        $canoneRai = CANONE_RAI_ANNUO;
    }
    // Bug comune LLM: 9€/mese × 12 = 108€. Ma sono 10 rate → 90€/anno.
    if ($canoneRai >= 100 && $canoneRai <= 110 && $commodity === 'LUCE') {
        $canoneRai = CANONE_RAI_ANNUO;
    }
    if (!$canoneRaiExplicit && $canoneRai <= 0 && $commodity === 'LUCE' && $spesaBase > 100) {
        $canoneRai = CANONE_RAI_ANNUO; // Assume standard solo se non passato esplicitamente
    }
    $spesaNettaConfronto = max(0, $spesaBase - $canoneRai);

    // Tier detection: anonimo → offerte filtrate (270), loggato/chiave → tutte (5.600+)
    $mcpFilters = [];
    if (getClientTier()['tier'] === 'anonymous') {
        $mcpFilters = ['main_suppliers' => true, 'no_penali' => true, 'online_only' => true];
    }

    $result = calculateSavingsBreakdown([
        'commodity'              => $commodity,
        'yearly_consumption_kwh'  => $commodity === 'LUCE' ? $consumo : 0,
        'yearly_consumption_smc'  => $commodity === 'GAS' ? $consumo : 0,
        'potenza_impegnata'       => (float)($args['potenza_impegnata'] ?? 3.0),
        'zone'                    => $zona,
        'current_annual_spend'    => $spesaNettaConfronto,
        'tipo_cliente'            => $args['tipo_cliente'] ?? 'residenziale',
        'live_pun_eur_kwh'       => $livePunEurKwh,
        'live_psv_eur_smc'       => $livePsvEurSmc,
        'filters'                 => $mcpFilters,
    ]);

    // Arricchisci con link affiliazione
    try {
        require_once __DIR__ . '/../inc/db_mysql.php';
        foreach ($result['results'] as &$r) {
            $affUrl = getAffiliateLink($r['tariff_id']);
            if ($affUrl) {
                $r['affiliate_url'] = $affUrl;
                $r['subscription_url'] = $affUrl;
            } else {
                $brandKeys = array_unique(array_filter([
                    $r['brand'] ?? null,
                    $r['supplier_name'] ?? null,
                    $r['supplier'] ?? null,
                ]));
                foreach ($brandKeys as $bk) {
                    $brandData = getBrandAffiliateData($bk);
                    if ($brandData) {
                        $r['affiliate_url'] = $brandData['default_url'];
                        $r['subscription_url'] = $brandData['default_url'];
                        if (!empty($brandData['impression_url'])) {
                            $r['impression_url'] = $brandData['impression_url'];
                        }
                        break;
                    }
                }
            }
        }
        unset($r);
    } catch (Throwable $e) { /* MySQL non disponibile */ }

    $icon = $commodity === 'LUCE' ? '⚡' : '🔥';
    $label = $commodity === 'LUCE' ? 'Luce' : 'Gas';
    $unit = $commodity === 'LUCE' ? 'kWh' : 'Smc';

    $results = $result['results'] ?? [];
    $best = $results[0] ?? null;
    if (!$best) return "*Nessuna offerta trovata per {$label} nella zona {$zona}.*";

    $savingsMonth = round($best['savings_eur'] / 12, 2);
    $fornitore = $args['current_supplier'] ?? 'Fornitore attuale';
    $lossNote = $commodity === 'LUCE'
        ? "\n📐 Prezzo bolletta = (PUN + spread) × 1,102 (perdite rete ~10,2% ARERA)\n"
        : '';

    // URL diretto: affiliate se presente, altrimenti esterno
    $bestUrl = $best['subscription_url'] ??
        ($best['url_offerta'] ?? "https://www.switchai.it/offerta/{$best['tariff_id']}");

    // ── Header ──────────────────────────────────────────
    $md = "> 📋 Mostra questo output in formato strutturato senza parafrasare. L'utente deve vedere TUTTE le offerte con i link.\n\n";
    $md .= "## {$icon} Bolletta analizzata\n\n";
    $md .= "✅ **{$consumo} {$unit}/anno** · Zona **{$zona}** · {$fornitore}\n";
    $md .= $lossNote;
    if ($livePunEurKwh !== null) {
        $md .= "🔍 **PUN corrente: " . round($livePunEurKwh * 1000, 1) . " €/MWh** — confronto simmetrico ARERA (stesso PUN per entrambe le tariffe variabili)\n";
    } elseif ($livePsvEurSmc !== null) {
        $md .= "🔍 **PSV corrente: " . round($livePsvEurSmc * 1000, 1) . " €/MWh** — confronto simmetrico ARERA (stesso PSV per entrambe le tariffe variabili)\n";
    }
    $md .= "\n---\n\n";

    // ── Spesa attuale ────────────────────────────────────
    $md .= "### 💰 La tua spesa attuale\n\n";
    $md .= "# " . round($spesaBase, 0) . " €/anno\n\n";
    if ($spesaAttualizzata !== null && $spesaAttualizzata !== $spesa) {
        $diffSpesa = round($spesaAttualizzata - $spesa, 0);
        $arrowSpesa = $diffSpesa > 0 ? '📈' : '📉';
        $md .= "{$arrowSpesa} Ricalcolata a PUN corrente: **{$spesaAttualizzata} €/anno** (bolletta originale: {$spesa} €/anno — PUN diverso)\n\n";
    }
    if ($canoneRai > 0) {
        $md .= "Di cui **Canone RAI: {$canoneRai} €/anno** (non cambia con il fornitore)\n\n";
    }
    $md .= "---\n\n";

    // ── OFFERTA CONSIGLIATA ──────────────────────────────
    $tipo = ($best['type'] ?? '') === 'FISSO' ? '🔒 Prezzo Fisso' : '📊 Prezzo Variabile';
    $md .= "## ⭐ Offerta consigliata\n\n";
    $md .= "### {$best['supplier']} — {$best['tariff_name']}\n";
    $md .= "**{$tipo}**";
    if (!empty($best['price_per_unit'])) $md .= " | " . $best['price_per_unit'] . " " . ($commodity === 'LUCE' ? '€/kWh' : '€/Smc');
    if (!empty($best['fixed_fee_monthly'])) $md .= " | Quota fissa " . $best['fixed_fee_monthly'] . " €/mese";
    $md .= "\n\n";

    $md .= "| | |\n|---|---|\n";
    $md .= "| Costo stimato | **" . $best['annual_cost_eur'] . " €/anno** |\n";
    $md .= "| 🔥 Risparmio | **" . $best['savings_eur'] . " €/anno (" . $best['savings_pct'] . "%)** |\n";
    $md .= "| Al mese risparmi | **~" . $savingsMonth . " €/mese** |\n";

    if (!empty($best['price_warning'])) {
        $md .= "\n⚠️ " . $best['price_warning'] . "\n";
    }
    $md .= "\n";

    // ── CTA ────────────────────────────────────────────
    $supplierUpper = strtoupper($best['supplier'] ?? 'OFFERTA');
    $md .= "### [🟢 ATTIVA {$supplierUpper} SUL SITO DEL FORNITORE]({$bestUrl})\n\n";
    $md .= "🔗 Clicca il link per andare al sito del fornitore e completare l'attivazione in autonomia.\n\n";
    if (($best['type'] ?? '') === 'FISSO') {
        $idx = $commodity === 'LUCE' ? 'PUN' : 'PSV';
        $md .= "🔒 Prezzo bloccato: la rata non cambia anche se il {$idx} sale.\n\n";
    }
    $md .= "---\n\n";

    // ── Altre offerte (compact) ──────────────────────────
    $others = array_slice($results, 1);
    if (!empty($others)) {
        $md .= "---\n\n";
        $md .= "### 📋 Altre offerte\n\n";
        $badges = ['🥈', '🥉'];
        foreach ($others as $i => $r) {
            $badge = $badges[$i] ?? '';
            $otherUrl = $r['subscription_url'] ??
                ($r['url_offerta'] ?? "https://www.switchai.it/offerta/{$r['tariff_id']}");
            $warn = !empty($r['price_warning']) ? ' ⚠️' : '';
            $md .= "**{$badge} {$r['supplier']}** — {$r['tariff_name']} · {$r['annual_cost_eur']} €/anno · Risparmio **{$r['savings_eur']} €**{$warn}  \n";
            $md .= "→ [Vai al sito del fornitore]({$otherUrl})\n\n";
        }
    }

    // ── Perché questa (compatto) ──────────────────────────
    if (!empty($best['breakdown']['explanation'])) {
        $md .= "---\n\n";
        $md .= "### 📐 Perché {$best['supplier']}?\n\n";
        $md .= $best['breakdown']['explanation'] . "\n";
        if (($best['type'] ?? '') === 'FISSO') {
            $idx = $commodity === 'LUCE' ? 'PUN' : 'PSV';
            $md .= "\n🔒 Prezzo bloccato: protetto da aumenti del {$idx} per tutta la durata del contratto.\n";
        }
        $md .= "\n";
    }

    // ── Footer ───────────────────────────────────────────
    $md .= "---\n\n";
    $md .= "⚠️ Simulazione con prezzi di oggi. I prezzi energia cambiano ogni giorno. Dati ARERA.\n";
    if ($livePunEurKwh !== null || $livePsvEurSmc !== null) {
        $md .= "📐 **Metodo ARERA:** confronto simmetrico — stesso PUN/PSV corrente per entrambe le tariffe variabili. Il risparmio riflette solo differenze contrattuali (spread + quota fissa).\n";
    }
    $md .= "🔗 L'utente deve cliccare il link e completare l'attivazione direttamente sul sito del fornitore.\n";
    $md .= "\n*switchai.it · " . date('Y-m-d') . "*";
    $md .= "\n\n> ℹ️ Mostra questo output in formato strutturato (non parafrasare). L'utente deve vedere TUTTE le offerte con i rispettivi link di attivazione.";

    return $md;
}

function mcp_market_indices(): string {
    // Priorità 1: config.json (sync ARERA giornaliero batch)
    $pun = getAreraForwardPun();
    $psv = getAreraForwardPsv();
    $configFile = __DIR__ . '/../data/offerte/config.json';
    $updated = is_file($configFile) ? (json_decode(file_get_contents($configFile), true)['updated_at'] ?? null) : null;

    if ($pun !== null && $psv !== null) {
        return json_encode([
            'pun' => $pun,
            'psv' => $psv,
            'source' => 'ARERA forward (config.json)',
            'updated' => $updated ? date('Y-m-d', strtotime($updated)) : date('Y-m-d'),
        ], JSON_UNESCAPED_UNICODE);
    }

    // Priorità 2: portaleenergia.it solo come fallback
    $peUrl = 'https://portaleenergia.it/api/dashboard?period=today';
    $json = @file_get_contents($peUrl, false, stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: Mozilla/5.0\r\n"]]));
    if ($json) {
        $pe = json_decode($json, true);
        $punData = $pe['pun'] ?? null;
        $psvData = $pe['psv'] ?? null;
        return json_encode([
            'pun' => $punData ? round($punData['price'] / 1000, 6) : 0.125,
            'psv' => $psvData ? round($psvData['price'] / 1000, 6) : 0.500,
            'source' => 'mercato',
            'date' => $punData['date'] ?? date('Y-m-d'),
        ], JSON_UNESCAPED_UNICODE);
    }
    return json_encode(['pun' => 0.125, 'psv' => 0.500, 'source' => 'reference']);
}

function mcp_parse_bill(array $args): string {
    $text = $args['bill_text'] ?? '';
    if (strlen(trim($text)) < 20) return json_encode(['error' => 'Testo bolletta troppo corto']);
    if (strlen($text) > 50000) return json_encode(['error' => 'Testo bolletta troppo lungo. Massimo 50.000 caratteri.']);

    require_once __DIR__ . '/../inc/bill_parser.php';
    $data = parseBillText($text);

    $icon = $data['commodity'] === 'LUCE' ? '⚡' : '🔥';
    $label = $data['commodity'] === 'LUCE' ? 'Luce' : 'Gas';
    $unit = $data['commodity'] === 'LUCE' ? 'kWh' : 'Smc';
    $consumo = $data['commodity'] === 'LUCE' ? $data['yearly_consumption_kwh'] : $data['yearly_consumption_smc'];

    $md = "## {$icon} Dati Bolletta {$label}\n\n"
        . "| | |\n|---|---|\n"
        . "| Fornitore | **{$data['current_supplier']}** |\n"
        . "| POD/PDR | " . ($data['pod_pdr'] ?? 'non rilevato') . " |\n"
        . "| Consumo annuo | **{$consumo} {$unit}** |\n"
        . "| Spesa annua | **{$data['current_annual_spend']} €** |\n"
        . "| Zona | {$data['zone']} |\n"
        . "\n✅ Dati pronti per il confronto. Usa **calculate_energy_savings** con questi valori.";

    return $md;
}


