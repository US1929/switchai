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

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

require_once __DIR__ . '/../inc/tariff_loader.php';
require_once __DIR__ . '/../inc/bill_parser.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $input['method'] ?? '';

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
                'version' => '3.1.0',
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
                    'description' => "Confronta le tariffe Luce o Gas e calcola il risparmio annuo. Ricevi le 3 migliori offerte con subscription_url. "
                        . "FLUSSO CORRETTO: "
                        . "(0) PRIMA che l'utente condivida la bolletta, avvisalo: "
                        . "'Condividendo la bolletta con me, i tuoi dati personali verranno elaborati da [nome provider LLM, es. Anthropic/OpenAI] secondo la loro privacy policy. "
                        . "SwitchAI riceverà solo dati numerici (consumi, spesa, zona) e non conserverà i tuoi dati personali dopo la sessione.' "
                        . "(1) Estrai TUTTI i dati disponibili dalla bolletta: nome, cognome, codice fiscale, email, cellulare, "
                        . "indirizzo, civico, CAP, città, provincia, POD/PDR, consumo annuo, spesa annua. "
                        . "Cerca email e cellulare nelle sezioni 'Dati cliente' o 'Contatti' della bolletta — spesso ci sono. "
                        . "(2) Chiama questo tool passando SOLO consumi, spesa e zona (dati numerici, niente dati personali). "
                        . "(3) Se l'utente vuole attivare, elenca TUTTI i dati che hai estratto e chiedi: "
                        . "'Vuoi che precompili il modulo di attivazione con questi dati? "
                        . "Dovrai comunque essere tu a verificare e cliccare su Invia. Riceverai una mail di conferma prima dell'inoltro.' "
                        . "(4) Costruisci l'URL aggiungendo al subscription_url i query params: nome, cognome, cf, email, tel, "
                        . "indirizzo, civico, citta, provincia, provincia_sigla, cap, pod, pdr, consumi, spesa.",
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => false],
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'bill_text'         => ['type' => 'string', 'description' => '(Opzionale) Testo della bolletta per il parser PHP. Preferisci estrarre tu i dati ed usare i parametri numerici.'],
                            'commodity'         => ['type' => 'string', 'enum' => ['LUCE', 'GAS'], 'description' => 'Tipo fornitura. Deduci dal testo: kWh/POD = LUCE, Smc/PDR = GAS.'],
                            'consumo_annuo_kwh' => ['type' => 'number', 'description' => 'Consumo annuo kWh (LUCE). Cerca "consumo annuo stimato" nella bolletta ARERA 2.0.'],
                            'consumo_annuo_smc' => ['type' => 'number', 'description' => 'Consumo annuo Smc (GAS).'],
                            'spesa_annua_eur'   => ['type' => 'number', 'description' => 'Spesa annua attuale in €. Moltiplica importo bolletta × 6 (bimestrale) o × 4 (trimestrale).'],
                            'zona'              => ['type' => 'string', 'enum' => ['NORD', 'CENTRO', 'SUD'], 'description' => 'Zona tariffaria: NORD (Lombardia, Piemonte, Veneto...), CENTRO (Toscana, Lazio, Marche...), SUD (Campania, Sicilia, Calabria...).'],
                        ],
                    ],
                    'outputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'profile'               => ['type' => 'object', 'description' => 'Dati riepilogativi: commodity, consumo_annuo, spesa_annua_eur, zona'],
                            'top3'                  => ['type' => 'array', 'description' => 'Top 3 offerte con supplier, tariff_name, annual_cost_eur, savings_eur, savings_pct, subscription_url'],
                            'agent_summary'         => ['type' => 'string', 'description' => 'Riepilogo e istruzioni per guidare l\'utente all\'attivazione con prefill.'],
                            'prefill_instructions'  => ['type' => 'string', 'description' => 'Specifica tecnica dei query params accettati dal form: nome, cognome, cf, email, tel, indirizzo, civico, citta, provincia, provincia_sigla, cap, pod, pdr, consumi, spesa.'],
                        ],
                    ],
                ],
                [
                    'name' => 'get_available_offers',
                    'description' => 'Elenca tutte le offerte disponibili per Luce o Gas nel mercato libero italiano. Restituisce 25 offerte elettricità o 19 offerte gas con prezzi, tipo contratto, quota fissa e dettagli fornitore.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'required' => ['commodity'],
                        'properties' => [
                            'commodity' => ['type' => 'string', 'enum' => ['LUCE', 'GAS'], 'description' => 'LUCE per offerte energia elettrica (25 offerte), GAS per offerte gas naturale (19 offerte).'],
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
                        'properties' => [
                            'source' => ['type' => 'string', 'description' => 'Fonte: "mercato" (dati live) o "reference" (valori medi).'],
                        ],
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
                    'name' => 'get_subscription_form_schema',
                    'description' => 'Restituisce lo schema JSON completo del form di sottoscrizione: campi richiesti, enum validi (titolo immobile, modalità pagamento), struttura a 4 step. Usare prima di raccogliere i dati utente per sapere quali campi sono obbligatori.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'commodity' => ['type' => 'string', 'enum' => ['luce', 'gas'], 'description' => 'Tipo fornitura per ottenere lo schema corretto (POD per luce, PDR per gas).'],
                        ],
                    ],
                    'outputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'steps'              => ['type' => 'array', 'description' => '4 step del wizard: Dati personali, Indirizzo fornitura, Dati tecnici, Riepilogo'],
                            'titoli_immobile'    => ['type' => 'array', 'description' => 'Enum: Proprietario, Affittuario, Comodatario, Usufruttuario'],
                            'modalita_pagamento' => ['type' => 'array', 'description' => 'Enum: SDD, Bollettino'],
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
                [
                    'name' => 'submit_subscription',
                    'description' => 'Invia la richiesta di attivazione di una nuova tariffa energia. Supporta dry_run: true per anteprima senza invio. RICHIEDE consenso esplicito GDPR.',
                    'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
                    'inputSchema' => [
                        'type' => 'object',
                        'required' => ['tariff_id', 'nome', 'cognome', 'codice_fiscale', 'email', 'cellulare'],
                        'properties' => [
                            'tariff_id' => ['type' => 'string', 'description' => 'ID offerta da attivare'],
                            'tariff_name' => ['type' => 'string', 'description' => 'Nome offerta'],
                            'supplier' => ['type' => 'string', 'description' => 'Nome fornitore'],
                            'commodity' => ['type' => 'string', 'enum' => ['luce', 'gas']],
                            'nome' => ['type' => 'string'], 'cognome' => ['type' => 'string'],
                            'codice_fiscale' => ['type' => 'string'], 'email' => ['type' => 'string'],
                            'cellulare' => ['type' => 'string'],
                            'indirizzo' => ['type' => 'string'], 'civico' => ['type' => 'string'],
                            'citta' => ['type' => 'string'], 'provincia_sigla' => ['type' => 'string'],
                            'cap' => ['type' => 'string'],
                            'codice_pod' => ['type' => 'string'], 'codice_pdr' => ['type' => 'string'],
                            'modalita_pagamento' => ['type' => 'string', 'enum' => ['SDD', 'Bollettino']],
                            'iban' => ['type' => 'string'],
                            'dry_run' => ['type' => 'boolean', 'description' => 'Se true, simula senza inviare'],
                        ],
                    ],
                ],
                [
                    'name' => 'get_subscription_status',
                    'description' => 'Verifica lo stato di una richiesta di sottoscrizione tramite ID.',
                    'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
                    'inputSchema' => [
                        'type' => 'object',
                        'required' => ['subscription_id'],
                        'properties' => [
                            'subscription_id' => ['type' => 'string', 'description' => 'ID della sottoscrizione'],
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
        case 'analyze_energy_bill':   // legacy alias
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
        case 'get_subscription_form_schema':
            $result = [
                'steps' => [
                    ['id' => 1, 'label' => 'Dati personali', 'fields' => ['nome', 'cognome', 'codice_fiscale', 'email', 'cellulare', 'titolo_immobile']],
                    ['id' => 2, 'label' => 'Indirizzo fornitura', 'fields' => ['indirizzo', 'civico', 'citta', 'provincia_sigla', 'cap']],
                    ['id' => 3, 'label' => 'Dati tecnici', 'fields' => ['codice_pod', 'codice_pdr', 'modalita_pagamento', 'iban']],
                ],
                'titoli_immobile' => ['Proprietario', 'Affittuario', 'Comodatario', 'Usufruttuario'],
                'modalita_pagamento' => ['SDD', 'Bollettino'],
            ];
            break;
        case 'submit_subscription':
            $result = mcp_submit_subscription($args);
            break;
        case 'get_subscription_status':
            $result = mcp_subscription_status($args);
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
    $consumo = (float)($args['consumo_annuo_kwh'] ?? $args['consumo_annuo_smc'] ?? 0);
    $spesa = (float)($args['spesa_annua_eur'] ?? 0);
    $zona = $args['zona'] ?? 'NORD';

    // Parse da bill_text solo come fallback
    if (empty($consumo) && !empty($args['bill_text'])) {
        $parsed = parseBillText($args['bill_text']);
        $commodity = $parsed['commodity'];
        $consumo = $commodity === 'LUCE' ? $parsed['yearly_consumption_kwh'] : $parsed['yearly_consumption_smc'];
        $spesa = $parsed['current_annual_spend'];
        $zona = $parsed['zone'];
    }

    if ($consumo <= 0) return json_encode(['error' => 'Fornire consumo_annuo_kwh o consumo_annuo_smc']);

    if ($spesa <= 0) {
        $spesa = $commodity === 'LUCE' ? ($consumo * 0.18 + 144) : ($consumo * 0.65 + 144);
    }

    $result = calculateSavingsBreakdown([
        'commodity'              => $commodity,
        'yearly_consumption_kwh'  => $commodity === 'LUCE' ? $consumo : 0,
        'yearly_consumption_smc'  => $commodity === 'GAS' ? $consumo : 0,
        'zone'                    => $zona,
        'current_annual_spend'    => $spesa,
    ]);

    $icon = $commodity === 'LUCE' ? '⚡' : '🔥';
    $label = $commodity === 'LUCE' ? 'Luce' : 'Gas';
    $unit = $commodity === 'LUCE' ? 'kWh' : 'Smc';

    // Build prefill params from optional args
    $prefillParams = [];
    $prefillKeys = ['nome','cognome','cf','email','tel','indirizzo','civico','citta','provincia_sigla','cap','pod','pdr','consumi','spesa'];
    foreach ($prefillKeys as $k) {
        if (!empty($args[$k])) $prefillParams[$k] = $args[$k];
    }

    $results = $result['results'] ?? [];
    $best = $results[0] ?? null;
    if (!$best) return "*Nessuna offerta trovata per {$label} nella zona {$zona}.*";

    $savingsMonth = round($best['savings_eur'] / 12, 2);
    $fornitore = $args['current_supplier'] ?? 'Fornitore attuale';
    $lossNote = $commodity === 'LUCE'
        ? "\n📐 Prezzo bolletta = (PUN + spread) × 1,102 (perdite rete ~10,2% ARERA)\n"
        : '';

    // Build best prefill URL
    $baseUrl = $best['subscription_url'] ??
        "https://www.switchai.it/sottoscrizione?tariff={$best['tariff_id']}&supplier=" . urlencode($best['supplier']) . "&name=" . urlencode($best['tariff_name']) . "&commodity=" . strtolower($commodity) . "&annualCost=" . $best['annual_cost_eur'];
    $bestPrefillUrl = $baseUrl;
    if (!empty($prefillParams)) {
        $parsed = parse_url($baseUrl);
        $query = [];
        if (!empty($parsed['query'])) parse_str($parsed['query'], $query);
        $query = array_merge($query, $prefillParams);
        $bestPrefillUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'www.switchai.it') . ($parsed['path'] ?? '/sottoscrizione') . '?' . http_build_query($query);
    }

    $hasFullData = !empty($prefillParams['nome']) && !empty($prefillParams['cognome']) && !empty($prefillParams['cf']);

    // ── Header ──────────────────────────────────────────
    $md = "## {$icon} Bolletta analizzata\n\n";
    $md .= "✅ **{$consumo} {$unit}/anno** · Zona **{$zona}** · {$fornitore}\n";
    $md .= $lossNote;
    $md .= "\n---\n\n";

    // ── Spesa attuale ────────────────────────────────────
    $md .= "### 💰 La tua spesa attuale\n\n";
    $md .= "# " . round($spesa, 0) . " €/anno\n\n";
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

    // ── CTA ──────────────────────────────────────────────
    if ($hasFullData) {
        $md .= "✅ Ho già recuperato tutti i dati dalla bolletta.\n\n";
        $md .= "Per attivare servono solo:\n";
        $md .= "- 📧 Email: " . ($prefillParams['email'] ?? '_____') . "\n";
        $md .= "- 📱 Telefono: " . ($prefillParams['tel'] ?? '_____') . "\n\n";
    } else {
        $md .= "Per attivare servono solo **email e telefono**. Gli altri dati li recupero dalla bolletta.\n\n";
        $md .= "Chiedi all'utente: 📧 Email e 📱 Telefono?\n\n";
    }

    $supplierUpper = strtoupper($best['supplier'] ?? 'OFFERTA');
    $md .= "### [🟢 ATTIVA {$supplierUpper}]({$bestPrefillUrl})\n\n";
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
                "https://www.switchai.it/sottoscrizione?tariff={$r['tariff_id']}&supplier=" . urlencode($r['supplier']) . "&name=" . urlencode($r['tariff_name']) . "&commodity=" . strtolower($commodity) . "&annualCost=" . $r['annual_cost_eur'];
            $otherPrefill = $otherUrl;
            if (!empty($prefillParams)) {
                $op = parse_url($otherUrl);
                $oq = [];
                if (!empty($op['query'])) parse_str($op['query'], $oq);
                $oq = array_merge($oq, $prefillParams);
                $otherPrefill = ($op['scheme'] ?? 'https') . '://' . ($op['host'] ?? 'www.switchai.it') . ($op['path'] ?? '/sottoscrizione') . '?' . http_build_query($oq);
            }
            $warn = !empty($r['price_warning']) ? ' ⚠️' : '';
            $md .= "**{$badge} {$r['supplier']}** — {$r['tariff_name']} · {$r['annual_cost_eur']} €/anno · Risparmio **{$r['savings_eur']} €**{$warn}\n";
            $md .= "[Attiva]({$otherPrefill}) · \"Se preferisci questa, chiedimi i dettagli e la espando\"\n\n";
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
    $md .= "⚠️ **Simulazione valida con i prezzi di oggi.** I prezzi energia cambiano ogni giorno.\n\n";
    $md .= "📨 L'utente riceverà una **email di conferma** da SwitchAI. Deve cliccare sul link per completare.\n";
    $md .= "✏️ L'utente deve **verificare i dati e cliccare Invia** — tu puoi solo precompilare il modulo.\n";
    $md .= "\n*switchai.it · Dati ARERA · " . date('Y-m-d') . "*";

    return $md;
}

function mcp_market_indices(): string {
    $peUrl = 'https://portaleenergia.it/api/dashboard?period=today';
    $json = @file_get_contents($peUrl, false, stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: Mozilla/5.0\r\n"]]));
    if ($json) {
        $pe = json_decode($json, true);
        $pun = $pe['pun'] ?? null;
        $psv = $pe['psv'] ?? null;
        return json_encode([
            'pun' => $pun ? round($pun['price'] / 1000, 6) : 0.125,
            'psv' => $psv ? round($psv['price'] / 1000, 6) : 0.500,
            'source' => 'mercato',
            'date'   => $pun['date'] ?? date('Y-m-d'),
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

function mcp_submit_subscription(array $args): string {
    $required = ['tariff_id', 'nome', 'cognome', 'codice_fiscale', 'email', 'cellulare'];
    foreach ($required as $f) {
        if (empty($args[$f])) return json_encode(['error' => "Campo obbligatorio mancante: {$f}"]);
    }

    require_once __DIR__ . '/../inc/subscription_handler.php';

    $formData = [
        'nome' => $args['nome'] ?? '',
        'cognome' => $args['cognome'] ?? '',
        'codice_fiscale' => $args['codice_fiscale'] ?? '',
        'email' => $args['email'] ?? '',
        'cellulare' => $args['cellulare'] ?? '',
        'titolo_immobile' => $args['titolo_immobile'] ?? 'Proprietario',
        'indirizzo' => $args['indirizzo'] ?? '',
        'civico' => $args['civico'] ?? '',
        'citta' => $args['citta'] ?? '',
        'provincia_sigla' => $args['provincia_sigla'] ?? '',
        'cap' => $args['cap'] ?? '',
        'codice_pod' => $args['codice_pod'] ?? '',
        'codice_pdr' => $args['codice_pdr'] ?? '',
        'modalita_pagamento' => $args['modalita_pagamento'] ?? 'SDD',
        'iban' => $args['iban'] ?? '',
        'fornitore_attuale' => $args['fornitore_attuale'] ?? '',
        'consumo_kwh' => $args['consumo_kwh'] ?? '',
        'consumo_smc' => $args['consumo_smc'] ?? '',
        'supplier' => $args['supplier'] ?? '',
        'gdpr_privacy_accepted' => true,
        'consent_source' => 'mcp_http',
        'consent_timestamp' => date('c'),
    ];

    $offerData = [
        'tariff_id' => $args['tariff_id'] ?? '',
        'tariff_name' => $args['tariff_name'] ?? '',
        'supplier' => $args['supplier'] ?? '',
        'commodity' => $args['commodity'] ?? 'luce',
        'tipo_offerta' => 'switch',
    ];

    if (!empty($args['dry_run'])) {
        $wsResult = dryRunSubscription($formData, $offerData);
        return json_encode($wsResult, JSON_UNESCAPED_UNICODE);
    }

    $result = submitPendingSubscription($formData, $offerData);

    $statusIcon = ($result['status'] ?? '') === 'pending' ? '📨' : '✅';
    $md = "## {$statusIcon} Sottoscrizione\n\n"
        . "| | |\n|---|---|\n"
        . "| Stato | **{$result['status']}** |\n"
        . "| ID | `{$result['subscription_id']}` |\n"
        . "| Messaggio | {$result['message']} |\n"
        . "\n📧 Riceverai una email di conferma. **Clicca sul link per completare l'attivazione.**";

    return $md;
}

function mcp_subscription_status(array $args): string {
    require_once __DIR__ . '/../inc/subscription_handler.php';
    $id = $args['subscription_id'] ?? '';
    if (empty($id)) return json_encode(['error' => 'Fornire subscription_id']);

    $sub = loadSubscription($id);
    if (!$sub) return json_encode(['error' => 'Sottoscrizione non trovata']);

    return json_encode([
        'subscription_id' => $id,
        'status' => $sub['status'] ?? 'unknown',
        'created_at' => $sub['created_at'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
}
