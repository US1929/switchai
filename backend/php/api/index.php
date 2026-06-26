<?php
/**
 * index.php — API Router Principale
 *
 * Tutti gli endpoint partono da /api/...
 * Su OVH, configura .htaccess per riscrivere le URL.
 */

// ── Carica variabili d'ambiente ─────────────────────────────────────
// Cerca .env nella stessa cartella di questo file o nelle cartelle superiori
$envPaths = [
    __DIR__ . '/../../.env',       // dist/.env
    __DIR__ . '/../.env',          // api/.env
    __DIR__ . '/.env',             // api/index.php/.env
    $_SERVER['DOCUMENT_ROOT'] . '/.env',
];
foreach ($envPaths as $envFile) {
    if (is_file($envFile) && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                putenv($line);
            }
        }
        break; // Usa solo il primo .env trovato
    }
}

// CORS headers
$allowed_origins = [
    'https://www.switchai.it',
    'https://switchai.it',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:8080'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback if no origin or not allowed (for local testing/curl, you might want to allow it, but better strict)
    // Actually, WebMCP and MCP servers might not send an origin, or might send something else.
    // Let's keep it open for now or add Claude/Chrome extensions if needed.
    // Wait, the MCP server runs locally and hits the API. If it uses fetch, it might not send an origin.
    // So if $origin is empty, we don't set CORS, which is fine (CORS is a browser thing).
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../inc/tariff_loader.php';
require_once __DIR__ . '/../inc/bill_parser.php';
require_once __DIR__ . '/../inc/subscription_handler.php';
require_once __DIR__ . '/../inc/llm_logger.php';
require_once __DIR__ . '/../inc/api_auth.php';

// Parsing richiesta (prima del rate limit per poter esentare endpoint pubblici)
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = preg_replace('#/api/index\.php#', '/api', $uri);
$uri = preg_replace('/\.php$/', '', $uri);

// ── Rate Limiting (solo endpoint sensibili; lettura pubblica esente) ──
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$rateLimitedEndpoints = [
    '/api/analyze',           // POST — heavy compute
    '/api/calculate-savings', // POST — heavy compute
    '/api/subscription/submit', // POST — write
    '/api/activate',          // POST — write
    '/api/auth/login',        // POST — auth
    '/api/admin/api-keys',    // POST/DELETE — admin
    '/api/trigger-scraper',   // POST — admin
    '/api/test-email',        // POST — admin
];
$needsRateLimit = !$isLocal && ($method !== 'GET' || in_array($uri, ['/api/analyze', '/api/calculate-savings'], true));
if ($needsRateLimit) {
    $client = getClientTier();
    if (!checkRateLimit($client)) {
        http_response_code(429);
        header('Retry-After: 3600');
        echo json_encode(['error' => 'Rate limit superato. Riprova più tardi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Logga TUTTE le richieste API
logTraffic($uri, $method);

// Leggi body JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper per response JSON
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('X-Robots-Tag: noindex, nofollow');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function errorResponse(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}

// ── ROUTER ──────────────────────────────────────────────────────────────

try {
    switch (true) {
        // POST /api/calculate-savings
        case $uri === '/api/calculate-savings' && $method === 'POST':
            handleCalculateSavings($input);
            break;

        // POST /api/webmcp-endpoint
        case $uri === '/api/webmcp-endpoint' && $method === 'POST':
            handleWebMCPEndpoint($input);
            break;

        // POST /api/parse-bill-text
        case $uri === '/api/parse-bill-text' && $method === 'POST':
            handleParseBillText($input);
            break;

        // POST /api/activate
        case $uri === '/api/activate' && $method === 'POST':
            handleActivate($input);
            break;

        // POST /api/trigger-scraper
        case $uri === '/api/trigger-scraper' && $method === 'POST':
            handleTriggerScraper();
            break;

        // GET /api/market-indices — PUN e PSV correnti
        case $uri === '/api/market-indices' && $method === 'GET':
            handleMarketIndices();
            break;

        // GET /api/arera-constants — costanti regolatorie ARERA correnti
        case $uri === '/api/arera-constants' && $method === 'GET':
            jsonResponse(getAreraConstants());
            break;

        // GET /api/health
        case $uri === '/api/health' && $method === 'GET':
            handleHealth();
            break;

        // GET /api/status
        case $uri === '/api/status' && $method === 'GET':
            handleStatus();
            break;

        // GET /api/tariffe/luce
        case $uri === '/api/tariffe/luce' && $method === 'GET':
            handleTariffe('LUCE');
            break;

        // GET /api/tariffe/gas
        case $uri === '/api/tariffe/gas' && $method === 'GET':
            handleTariffe('GAS');
            break;

        // GET /api/fornitori
        case $uri === '/api/fornitori' && $method === 'GET':
            jsonResponse(loadSuppliers());
            break;

        // POST /api/subscription/submit — step 1: pending + invia email conferma
        case $uri === '/api/subscription/submit' && $method === 'POST':
            handleSubscriptionSubmit($input);
            break;

        // GET /api/subscription/conferma — step 2: conferma via token
        case $uri === '/api/subscription/conferma' && $method === 'GET' && !empty($_GET['token']):
            handleSubscriptionConfirm($_GET['token']);
            break;

        // GET /api/subscription/status/{id}
        case preg_match('#^/api/subscription/status/([a-zA-Z0-9\-]+)$#', $uri, $subMatch) && $method === 'GET':
            handleSubscriptionStatus($subMatch[1]);
            break;

        // GET /api/subscription/form-schema
        case $uri === '/api/subscription/form-schema' && $method === 'GET':
            handleSubscriptionFormSchema();
            break;

        // POST /api/auth/login
        case $uri === '/api/auth/login' && $method === 'POST':
            handleAuthLogin($input);
            break;

        // GET /api/auth/verify
        case $uri === '/api/auth/verify' && $method === 'GET':
            handleAuthVerify();
            break;

        // GET /sitemap.xml — generato dinamicamente da dati live
        case $uri === '/sitemap.xml' && $method === 'GET':
            handleDynamicSitemap();
            break;

        // GET /offerta/{id} — pagina offerta per crawler (HTML + JSON-LD)
        case preg_match('#^/offerta/([a-zA-Z0-9\-]+)$#', $uri, $offerMatch) && $method === 'GET':
            handleOffertaPage($offerMatch[1]);
            break;

        // POST /api/analyze — endpoint unificato V2 (parse + confronto + risk)
        case $uri === '/api/analyze' && $method === 'POST':
            handleV2Analyze($input);
            break;

        // POST /api/admin/api-keys/create — crea chiave B2B (auth)
        case $uri === '/api/admin/api-keys/create' && $method === 'POST':
            requireAuth();
            $result = registerApiClient($input['name'] ?? 'Cliente', $input['tier'] ?? 'basic');
            jsonResponse($result);
            break;

        // GET /api/admin/api-keys — lista clienti B2B (auth)
        case $uri === '/api/admin/api-keys' && $method === 'GET':
            requireAuth();
            $clients = [];
            foreach (glob(__DIR__ . '/../../data/api_clients/*.json') as $f) {
                $clients[] = json_decode(file_get_contents($f), true);
            }
            jsonResponse($clients);
            break;

        // DELETE /api/admin/api-keys/{hash} — disattiva chiave (auth)
        case preg_match('#^/api/admin/api-keys/([a-f0-9]{64})$#', $uri, $keyMatch) && $method === 'DELETE':
            requireAuth();
            $file = __DIR__ . '/../../data/api_clients/' . basename($keyMatch[1]) . '.json';
            if (is_file($file)) {
                $data = json_decode(file_get_contents($file), true);
                $data['disabled'] = true;
                file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                jsonResponse(['status' => 'disabled']);
            } else {
                errorResponse('Client non trovato', 404);
            }
            break;

        // GET /api/admin/data-stats — statistiche database (richiede auth)
        case $uri === '/api/admin/data-stats' && $method === 'GET':
            requireAuth();
            try {
                require_once __DIR__ . '/../inc/db_mysql.php';
                $db = getMySQL();
                $users = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $apiKeys = (int)$db->query('SELECT COUNT(*) FROM api_keys WHERE disabled = 0')->fetchColumn();
                $rateLogs = (int)$db->query('SELECT COUNT(*) FROM rate_log')->fetchColumn();
                $affiliates = (int)$db->query('SELECT COUNT(*) FROM affiliate_links WHERE is_active = 1')->fetchColumn();
                jsonResponse([
                    'users' => $users,
                    'api_keys' => $apiKeys,
                    'rate_logs' => $rateLogs,
                    'affiliates' => $affiliates,
                    'mysql' => 'connected',
                ]);
            } catch (Throwable $e) {
                jsonResponse([
                    'mysql' => 'error',
                    'error' => $e->getMessage(),
                ]);
            }
            break;

        // POST /api/admin/sync-arera — trigger sincronizzazione ARERA (richiede auth)
        case $uri === '/api/admin/sync-arera' && $method === 'POST':
            requireAuth();
            try {
                $syncFile = __DIR__ . '/../inc/arera_sync.php';
                if (!is_file($syncFile)) {
                    errorResponse('Script sync non trovato', 500);
                }
                // Esegue sync in background (non blocca la risposta)
                $cmd = 'php ' . escapeshellarg($syncFile) . ' > /dev/null 2>&1 &';
                exec($cmd);
                jsonResponse([
                    'status' => 'started',
                    'message' => 'Sync ARERA avviato in background. Controlla i log tra qualche minuto.',
                ]);
            } catch (Throwable $e) {
                errorResponse('Errore sync: ' . $e->getMessage(), 500);
            }
            break;

        // GET /api/admin/affiliates — lista link affiliazione (richiede auth)
        case $uri === '/api/admin/affiliates' && $method === 'GET':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                $links = getAllAffiliateLinks();
                jsonResponse(['affiliates' => $links]);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // POST /api/admin/affiliates — crea/aggiorna link affiliazione (richiede auth)
        case $uri === '/api/admin/affiliates' && $method === 'POST':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                $tariffId = $input['tariff_id'] ?? '';
                $url = $input['affiliate_url'] ?? '';
                if (empty($tariffId) || empty($url)) {
                    errorResponse('tariff_id e affiliate_url obbligatori', 400);
                }
                upsertAffiliateLink(
                    $tariffId,
                    $url,
                    $input['supplier'] ?? '',
                    $input['tariff_name'] ?? '',
                    $input['commodity'] ?? '',
                    $input['network'] ?? ''
                );
                jsonResponse(['status' => 'ok']);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // DELETE /api/admin/affiliates — disattiva link (richiede auth)
        case $uri === '/api/admin/affiliates' && $method === 'DELETE':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                $tariffId = $input['tariff_id'] ?? '';
                if (empty($tariffId)) errorResponse('tariff_id obbligatorio', 400);
                deleteAffiliateLink($tariffId);
                jsonResponse(['status' => 'deleted']);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // GET /api/stats/traffic — report traffico LLM vs umano (richiede auth)
        case $uri === '/api/stats/traffic' && $method === 'GET':
            requireAuth();
            $hours = (int)($_GET['hours'] ?? 24);
            jsonResponse(getTrafficReport(min($hours, 720))); // max 30 giorni
            break;

        // POST /api/test-email — invia email di test
        case $uri === '/api/test-email' && $method === 'POST':
            handleTestEmail($input);
            break;

        default:
            errorResponse('Endpoint non trovato: ' . $method . ' ' . $uri, 404);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    errorResponse('Errore interno del server', 500);
}

// ── HANDLER ─────────────────────────────────────────────────────────────

function handleCalculateSavings(array $input): void {
    $commodity = $input['commodity'] ?? '';
    if (!in_array($commodity, ['LUCE', 'GAS'])) {
        errorResponse("Invalid commodity. Must be 'LUCE' or 'GAS'.");
    }
    
    $input['source'] = 'WEB';
    $result = calculateSavingsBreakdown($input);
    enrichWithAffiliates($result);
    jsonResponse($result);
}

function handleWebMCPEndpoint(array $input): void {
    $commodity = $input['commodity'] ?? '';
    if (!in_array($commodity, ['LUCE', 'GAS'])) {
        errorResponse("Invalid commodity. Must be 'LUCE' or 'GAS'.");
    }

    $input['source'] = 'AI_AGENT';
    $result = calculateSavingsBreakdown($input);
    enrichWithAffiliates($result);
    
    // Genera summary in linguaggio naturale
    $results = $result['results'] ?? [];
    $label = $commodity === 'LUCE' ? 'Luce' : 'Gas';
    $zone = $input['zone'] ?? 'NORD';
    
    if (empty($results)) {
        $summary = "Non ho trovato tariffe $label attive compatibili.";
    } else {
        $best = $results[0];
        $savingsText = $best['savings_eur'] > 0
            ? "{$best['savings_eur']}€ all'anno ({$best['savings_pct']}%)"
            : "un costo simile";
        $breakdownText = $best['breakdown']['explanation'] ?? '';

        // Info extra per Q&A
        $contractInfo = $best['contract_detail'] ?? ($best['type'] === 'FISSO' ? 'Prezzo fisso' : 'Prezzo variabile');
        $monthlyInfo = isset($best['monthly_cost_eur']) ? "circa {$best['monthly_cost_eur']}€ al mese" : '';
        $fixedInfo = isset($best['fixed_fee_monthly']) ? "con quota fissa di {$best['fixed_fee_monthly']}€/mese" : '';
        $paymentInfo = isset($best['payment_method']) ? "pagamento via {$best['payment_method']}" : '';
        $validityInfo = isset($best['valid_until']) ? "offerta valida fino al {$best['valid_until']}" : '';

        $summary = "Ho confrontato le tariffe $label per la zona $zone. " .
                   "L'offerta più conveniente è **{$best['tariff_name']}** di **{$best['supplier']}** " .
                   "($contractInfo). Costo stimato: {$best['annual_cost_eur']}€/anno ($monthlyInfo). " .
                   "Il risparmio stimato è di **$savingsText**. ";

        if ($fixedInfo) $summary .= "$fixedInfo. ";
        if ($paymentInfo) $summary .= "$paymentInfo. ";
        if ($validityInfo) $summary .= "$validityInfo. ";
        if ($breakdownText) $summary .= "Ecco perché risparmi: $breakdownText ";
        $summary .= "Vuoi attivare questa offerta?";
    }

    // Istruzioni per LLM: come precompilare il form di sottoscrizione
    $result['_prefill_instructions'] = "Parametri URL per precompilare il form: nome, cognome, cf, email, tel, "
        . "indirizzo, civico, citta, provincia, provincia_sigla, cap, pod, pdr, consumi, spesa. "
        . "Cerca sempre email e cellulare nella bolletta — sono spesso nella sezione Dati cliente. "
        . "PRIMA di farsi dare la bolletta, avvisa l'utente che i dati verranno elaborati dal provider LLM; "
        . "SwitchAI riceve solo dati numerici (consumi, spesa, zona). "
        . "NON includere dati personali nell'URL senza consenso esplicito. "
        . "Esempio URL: /sottoscrizione?tariff=ID&supplier=X&name=Y&commodity=luce&annualCost=500"
        . "&nome=Mario&cognome=Rossi&cf=RSSMRA80A01H501U&email=m.rossi@email.com&tel=+393401234567"
        . "&indirizzo=Via+Roma&civico=15&citta=Milano&cap=20121&pod=IT001E123456789&consumi=2700&spesa=650. "
        . "Spiega sempre: 'Dovrai verificare e inviare tu. Riceverai una mail di conferma prima dell'inoltro.'";

    $result['agent_summary'] = $summary;
    jsonResponse($result);
}

function handleParseBillText(array $input): void {
    $text = $input['text'] ?? '';
    if (strlen(trim($text)) < 20) {
        errorResponse("Testo bolletta troppo corto o mancante.");
    }
    if (strlen($text) > 50000) {
        errorResponse("Testo bolletta troppo lungo. Massimo 50.000 caratteri.");
    }

    $billData = parseBillText($text);
    jsonResponse($billData);
}

function handleActivate(array $input): void {
    $required = ['tariff_id', 'user_name', 'user_email', 'user_phone', 'pod_pdr'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Campo obbligatorio mancante: $field");
        }
    }
    
    // Invia email di notifica
    $to = getenv('ACTIVATION_EMAIL') ?: 'attivazioni@switchai.it';
    $subject = "[SwitchAI] Nuova richiesta attivazione - " . ($input['tariff_name'] ?? $input['tariff_id']);
    $message = "Nuova richiesta di attivazione:\n\n" .
               "Tariffa: {$input['tariff_id']}\n" .
               "Nome: {$input['user_name']}\n" .
               "Email: {$input['user_email']}\n" .
               "Telefono: {$input['user_phone']}\n" .
               "POD/PDR: {$input['pod_pdr']}\n" .
               "Data: " . date('d/m/Y H:i') . "\n";
    
    if (!empty($input['comparison_id'])) {
        $message .= "Comparison ID: {$input['comparison_id']}\n";
    }
    
    $headers = "From: attivazioni@switchai.it\r\nReply-To: {$input['user_email']}";
    
    $mailSent = @mail($to, $subject, $message, $headers);
    
    if (!$mailSent) {
        error_log("ACTIVATION (fallback mail): " . $message);
    }
    
    jsonResponse([
        'status'         => 'success',
        'activation_id'  => deterministicUuid('activation-' . microtime(true)),
        'message'        => 'Richiesta di attivazione registrata con successo! Riceverai una email di conferma.',
        'email_notified' => $mailSent,
    ], 201);
}

function handleTriggerScraper(): void {
    $headers = getallheaders();
    $apiKey = $headers['x-api-key'] ?? $headers['X-Api-Key'] ?? '';
    $expectedKey = getenv('API_KEY');

    if (!$expectedKey) {
        error_log("TRIGGER-SCRAPER: API_KEY env var not configured");
        errorResponse('Server configuration error', 500);
    }

    // Protezione anti-brute-force: accetta max 1 richiesta ogni 60 secondi
    $rateLimitFile = sys_get_temp_dir() . '/switchai_scraper_ratelimit';
    $now = time();
    $lastCall = (int)@file_get_contents($rateLimitFile);
    if ($now - $lastCall < 60) {
        errorResponse('Rate limit: max 1 refresh per minuto. Attendere.', 429);
    }
    @file_put_contents($rateLimitFile, $now, LOCK_EX);

    // Verifica API key con hash (timing-safe)
    if (!hash_equals($expectedKey, $apiKey)) {
        error_log("TRIGGER-SCRAPER: Unauthorized attempt from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        errorResponse('Unauthorized', 401);
    }

    // Forza refresh della cache delle tariffe
    try {
        $tariffs = loadTariffs();
        jsonResponse([
            'status'  => 'success',
            'message' => 'Cache tariffe aggiornata. ' . count($tariffs) . ' offerte caricate.',
            'count'   => count($tariffs),
        ]);
    } catch (Exception $e) {
        errorResponse('Errore aggiornamento cache: ' . $e->getMessage(), 500);
    }
}

function handleHealth(): void {
    jsonResponse([
        'status'         => 'ok',
        'version'        => '2.0.0',
        'server'         => 'PHP ' . phpversion(),
        'db_mode'        => 'json_remote',
        'timestamp'      => date('c'),
    ]);
}

function handleStatus(): void {
    $luce = count(getTariffsByCommodity('LUCE'));
    $gas  = count(getTariffsByCommodity('GAS'));
    $suppliers = count(loadSuppliers());
    
    jsonResponse([
        'luce_tariffs'  => $luce,
        'gas_tariffs'   => $gas,
        'suppliers'     => $suppliers,
        'php_version'   => phpversion(),
        'db_mode'       => 'json_remote',
    ]);
}

function handleTariffe(string $commodity): void {
    $tariffs = getTariffsByCommodity($commodity);
    $clean = array_map(fn($t) => [
        'id'                => $t['id'],
        'supplier_name'     => $t['supplier_name'],
        'name'              => $t['name'],
        'type'              => $t['type'],
        'price_mono_kwh'    => $t['price_mono_kwh'],
        'price_smc'         => $t['price_smc'],
        'fixed_fee_monthly' => $t['fixed_fee_monthly'],
        'fixed_fee_annual'  => $t['fixed_fee_annual'] ?? null,
        'spread'            => $t['spread'] ?? null,
        'pun'               => $t['pun'] ?? null,
        'psv'               => $t['psv'] ?? null,
        'promo_active'      => $t['promo_active'],
        'brand'             => $t['brand'] ?? '',
        'logo'              => $t['logo'] ?? null,
        'extra'             => $t['extra'] ?? [],
    ], $tariffs);

    jsonResponse([
        'commodity' => $commodity,
        'count'     => count($clean),
        'offers'    => $clean,
    ]);
}

// ── SUBSCRIPTION HANDLERS ──────────────────────────────────────────────

function handleSubscriptionSubmit(array $input): void {
    // Validazione campi obbligatori
    $required = ['tariff_id', 'nome', 'cognome', 'codice_fiscale', 'email', 'cellulare'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Campo obbligatorio mancante: $field");
        }
    }

    // GDPR: consenso privacy obbligatorio
    if (empty($input['gdpr_privacy_accepted']) || $input['gdpr_privacy_accepted'] !== true) {
        errorResponse("È necessario accettare l'informativa privacy (gdpr_privacy_accepted: true). "
                     . "L'LLM deve chiedere esplicitamente il consenso prima di invocare questo tool.", 400);
    }

    $formData = [
        'nome'              => $input['nome'] ?? '',
        'cognome'           => $input['cognome'] ?? '',
        'codice_fiscale'    => $input['codice_fiscale'] ?? '',
        'email'             => $input['email'] ?? '',
        'cellulare'         => $input['cellulare'] ?? '',
        'titolo_immobile'   => $input['titolo_immobile'] ?? 'Proprietario',
        'indirizzo'         => $input['indirizzo'] ?? '',
        'civico'            => $input['civico'] ?? '',
        'citta'             => $input['citta'] ?? '',
        'provincia_sigla'   => $input['provincia_sigla'] ?? '',
        'cap'               => $input['cap'] ?? '',
        'codice_pod'        => $input['codice_pod'] ?? '',
        'codice_pdr'        => $input['codice_pdr'] ?? '',
        'modalita_pagamento'=> $input['modalita_pagamento'] ?? '',
        'iban'              => $input['iban'] ?? '',
        'indirizzo_coincide'=> $input['indirizzo_coincide'] ?? 'si',
        'indirizzo_residenza'       => $input['indirizzo_residenza'] ?? '',
        'civico_residenza'          => $input['civico_residenza'] ?? '',
        'citta_residenza'           => $input['citta_residenza'] ?? '',
        'provincia_residenza_sigla' => $input['provincia_residenza_sigla'] ?? '',
        'cap_residenza'             => $input['cap_residenza'] ?? '',
        'fornitore_attuale' => $input['fornitore_attuale'] ?? '',
        'consumo_kwh'       => $input['consumo_kwh'] ?? '',
        'consumo_smc'       => $input['consumo_smc'] ?? '',
        'potenza'           => $input['potenza'] ?? '3',
        'supplier'          => $input['supplier'] ?? '',
        // GDPR
        'gdpr_privacy_accepted'  => true,
        'consent_source'         => $input['consent_source'] ?? 'api_direct',
        'consent_timestamp'      => $input['consent_timestamp'] ?? date('c'),
        'conversation_snippet'   => $input['conversation_snippet'] ?? '',
    ];

    $offerData = [
        'tariff_id'    => $input['tariff_id'] ?? '',
        'tariff_name'  => $input['tariff_name'] ?? '',
        'supplier'     => $input['supplier'] ?? '',
        'commodity'    => $input['commodity'] ?? 'luce',
        'tipo_offerta' => $input['tipo_offerta'] ?? 'switch',
    ];

    // Dry-run?
    if (!empty($input['dry_run'])) {
        $wsResult = dryRunSubscription($formData, $offerData);
        jsonResponse($wsResult);
        return;
    }

    // DOUBLE OPT-IN: salva in pending, invia email di conferma
    $result = submitPendingSubscription($formData, $offerData, [
        'source_url' => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);

    // Notifica admin — SOLO dati minimi (GDPR: i dati sensibili arrivano dopo la conferma)
    $to = getenv('ACTIVATION_EMAIL') ?: 'attivazioni@switchai.it';
    $commodityLabel = ($offerData['commodity'] ?? 'luce') === 'luce' ? '⚡ LUCE' : '🔥 GAS';
    $subject = "[SwitchAI] PENDING — {$formData['nome']} {$formData['cognome']}";
    $msg = "NUOVA SOTTOSCRIZIONE IN ATTESA DI CONFERMA\n\n";
    $msg .= "📋 " . ($offerData['tariff_name'] ?? 'N/D') . " — " . ($input['supplier'] ?? 'N/D') . " — $commodityLabel\n";
    $msg .= "👤 {$formData['nome']} {$formData['cognome']}\n";
    $msg .= "📧 {$formData['email']}\n";
    $msg .= "🔑 ID: {$result['subscription_id']}\n\n";
    $msg .= "⚠️ I dati completi (CF, indirizzo, POD, IBAN) NON sono in questa email.\n";
    $msg .= "   Verranno inviati SOLO dopo che l'utente avrà cliccato il link di conferma.\n\n";
    $msg .= "───────────────────────────────────────\n";
    $msg .= "WS: " . (getenv('WS_ENABLED') ?: 'false') . " | " . date('d/m/Y H:i:s') . "\n";

    @mail($to, $subject, $msg, "From: SwitchAI <attivazioni@switchai.it>\r\nReply-To: {$formData['email']}\r\nContent-Type: text/plain; charset=UTF-8");

    jsonResponse($result, 201);
}

function handleSubscriptionConfirm(string $token): void {
    $result = confirmSubscription($token);

    if ($result['status'] === 'error') {
        errorResponse($result['error'], 404);
    }

    jsonResponse($result);
}

function handleSubscriptionStatus(string $id): void {
    $sub = loadSubscription($id);
    if (!$sub) {
        errorResponse('Sottoscrizione non trovata', 404);
    }
    jsonResponse([
        'subscription_id' => $id,
        'status'          => $sub['status'] ?? 'unknown',
        'created_at'      => $sub['created_at'] ?? null,
    ]);
}

function handleSubscriptionFormSchema(): void {
    jsonResponse([
        'steps' => [
            ['id' => 1, 'label' => 'Dati personali', 'fields' => ['nome', 'cognome', 'codice_fiscale', 'email', 'cellulare', 'titolo_immobile']],
            ['id' => 2, 'label' => 'Indirizzo fornitura', 'fields' => ['indirizzo', 'civico', 'citta', 'provincia', 'cap', 'indirizzo_coincide']],
            ['id' => 3, 'label' => 'Dati tecnici', 'fields' => ['codice_pod', 'codice_pdr', 'modalita_pagamento', 'iban']],
            ['id' => 4, 'label' => 'Riepilogo e invio', 'fields' => []],
        ],
        'titoli_immobile' => [
            'Proprietario', 'Affittuario', 'Comodatario', 'Usufruttuario',
        ],
        'modalita_pagamento' => [
            'SDD', 'Bollettino',
        ],
    ]);
}

// ── DYNAMIC SITEMAP ─────────────────────────────────────────────────

function handleDynamicSitemap(): void {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Pagine statiche (URL con .html — canonical effettivo)
    $static = [
        ['loc' => 'https://www.switchai.it/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['loc' => 'https://www.switchai.it/per-llm', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/come-funziona', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/privacy', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/cookie', 'priority' => '0.3', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/faq', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ];
    foreach ($static as $url) {
        $lastmod = date('Y-m-d');
        $cf = isset($url['changefreq']) ? "    <changefreq>{$url['changefreq']}</changefreq>\n" : '';
        echo "  <url>\n    <loc>{$url['loc']}</loc>\n    <lastmod>{$lastmod}</lastmod>\n{$cf}    <priority>{$url['priority']}</priority>\n  </url>\n";
    }

    // Offerte dinamiche (da dati live — sempre aggiornate)
    try {
        $tariffs = loadTariffs();
        $added = [];
        foreach ($tariffs as $t) {
            if (isset($added[$t['id']])) continue;
            $added[$t['id']] = true;
            echo "  <url>\n";
            echo "    <loc>https://www.switchai.it/offerta/{$t['id']}</loc>\n";
            echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.7</priority>\n";
            echo "  </url>\n";
        }
    } catch (\Throwable $e) {
        // Se il caricamento tariffe fallisce, almeno le pagine statiche ci sono
    }

    echo '</urlset>';
}

// ── OFFERTA PAGE (HTML statico generato da dati live) ──────────────

function handleOffertaPage(string $id): void {
    $allTariffs = loadTariffs();
    $offer = null;
    foreach ($allTariffs as $t) {
        if ($t['id'] === $id) { $offer = $t; break; }
    }
    if (!$offer) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title>Offerta non trovata — SwitchAI</title>';
        echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1.5rem;line-height:1.8;color:#333}</style>';
        echo '</head><body>';
        echo '<h1>Offerta non trovata</h1><p>Questa offerta potrebbe non essere più disponibile.</p>';
        echo '<p><a href="/">← Torna a SwitchAI</a></p>';
        echo '</body></html>';
        return;
    }

    $isLuce = $offer['commodity'] === 'LUCE';
    $unit = $isLuce ? 'kWh' : 'Smc';
    $indice = $isLuce ? 'PUN' : 'PSV';
    $prezzoUnit = $isLuce ? ($offer['price_mono_kwh'] ?? null) : ($offer['price_smc'] ?? null);
    $spread = $offer['spread'] ?? null;
    $fixed = $offer['fixed_fee_monthly'] ?? null;
    $fixedAnnual = $offer['fixed_fee_annual'] ?? null;
    $extra = $offer['extra'] ?? [];
    $logo = $offer['logo'] ?? '';

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="index, follow">';
    echo '<title>' . htmlspecialchars($offer['supplier_name'] . ' ' . $offer['name']) . ' — SwitchAI</title>';
    echo '<meta name="description" content="' . htmlspecialchars($offer['supplier_name'] . ' ' . $offer['name'] . ' — ' . ($offer['type'] === 'FISSO' ? 'Prezzo Fisso' : 'Prezzo Variabile') . ' ' . ($isLuce ? 'Luce' : 'Gas') . '. Confronta e attiva su SwitchAI.">');
    echo '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $offer['supplier_name'] . ' ' . $offer['name'],
        'description' => 'Tariffa ' . ($isLuce ? 'Luce' : 'Gas') . ' ' . ($offer['type'] === 'FISSO' ? 'a prezzo fisso' : 'a prezzo variabile'),
        'offers' => ['@type' => 'Offer', 'price' => $prezzoUnit, 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock'],
        'brand' => ['@type' => 'Organization', 'name' => $offer['supplier_name']]
    ], JSON_UNESCAPED_UNICODE) . '</script>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1.5rem;line-height:1.8;color:#333} h1{font-size:1.5rem} .label{color:#777;font-size:.85rem} .value{font-weight:600} .cta{display:inline-block;margin-top:1rem;padding:12px 28px;background:#f59e0b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700} table{width:100%;border-collapse:collapse;margin:1rem 0} th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #eee} th{background:#f9f9f9}</style>';
    echo '</head><body>';
    echo '<p style="color:#777;font-size:.85rem"><a href="/" style="color:#f59e0b">← SwitchAI</a> — ' . ($isLuce ? '⚡ Luce' : '🔥 Gas') . ' — ' . date('d/m/Y') . '</p>';
    echo '<h1>' . htmlspecialchars($offer['supplier_name']) . ' — ' . htmlspecialchars($offer['name']) . '</h1>';

    echo '<table>';
    echo '<tr><td class="label">Tipologia</td><td class="value">' . ($offer['type'] === 'FISSO' ? 'Prezzo Fisso' : 'Prezzo Variabile') . '</td></tr>';
    if ($prezzoUnit) echo '<tr><td class="label">Prezzo ' . $unit . '</td><td class="value">' . number_format($prezzoUnit, 4, ',', '') . ' €/' . $unit . '</td></tr>';
    if ($offer['type'] === 'VARIABILE' && $spread) echo '<tr><td class="label">Spread</td><td class="value">' . number_format($spread, 3, ',', '') . ' €/' . $unit . '</td></tr>';
    if ($offer['type'] === 'VARIABILE') echo '<tr><td class="label">Indice</td><td class="value">' . $indice . ' (GME/ARERA)</td></tr>';
    if ($fixed) echo '<tr><td class="label">Quota fissa</td><td class="value">' . number_format($fixed, 2, ',', '') . ' €/mese</td></tr>';
    if ($fixedAnnual) echo '<tr><td class="label">Costo fisso annuo</td><td class="value">' . number_format($fixedAnnual, 2, ',', '') . ' €/anno</td></tr>';
    if (!empty($extra['prezzo_bloccato_mesi'])) echo '<tr><td class="label">Prezzo bloccato</td><td class="value">' . htmlspecialchars($extra['prezzo_bloccato_mesi']) . ' mesi</td></tr>';
    if (!empty($extra['modalita_pagamento'])) echo '<tr><td class="label">Pagamento</td><td class="value">' . htmlspecialchars($extra['modalita_pagamento']) . '</td></tr>';
    if (!empty($extra['vantaggi'])) echo '<tr><td class="label">Vantaggi</td><td class="value">' . htmlspecialchars($extra['vantaggi']) . '</td></tr>';
    if (!empty($extra['validita_offerta'])) echo '<tr><td class="label">Valida fino al</td><td class="value">' . htmlspecialchars($extra['validita_offerta']) . '</td></tr>';
    echo '</table>';

    echo '<p style="font-size:.85rem;color:#777">Dati aggiornati in tempo reale. Fonte: SwitchAI (switchai.it)</p>';
    echo '<a href="/sottoscrizione?tariff=' . urlencode($offer['id']) . '&supplier=' . urlencode($offer['supplier_name']) . '&name=' . urlencode($offer['name']) . '&commodity=' . ($isLuce ? 'luce' : 'gas') . '" class="cta">Attiva Online →</a>';
    echo '</body></html>';
}

// ── V2 ANALYZE (endpoint unificato) ─────────────────────────────────

function handleV2Analyze(array $input): void {
    $profile = null;

    // Priorità 1: bill_text → parse ARERA
    if (!empty($input['bill_text'])) {
        if (strlen($input['bill_text']) > 50000) {
            errorResponse('Testo bolletta troppo lungo. Massimo 50.000 caratteri.', 400);
        }
        try {
            $parsed = parseBillText($input['bill_text']);
            $profile = [
                'fornitore'    => $parsed['current_supplier'],
                'commodity'    => $parsed['commodity'],
                'pod'          => $parsed['pod_pdr'],
                'consumo_annuo'=> $parsed['commodity'] === 'LUCE'
                    ? $parsed['yearly_consumption_kwh']
                    : $parsed['yearly_consumption_smc'],
                'spesa_annua_eur' => $parsed['current_annual_spend'],
                'zona'         => $parsed['zone'],
                'canone_rai'   => $parsed['canone_rai'] ?? 0,
                'confidence'   => $parsed['confidence'],
                'advice'       => $parsed['_meta']['advice'] ?? '',
            ];
        } catch (Throwable $e) {
            errorResponse('Impossibile analizzare la bolletta. Testo non valido.', 400);
        }
    }

    // Priorità 2: dati strutturati
    if (!$profile && !empty($input['commodity'])) {
        $commodity = strtoupper($input['commodity']);
        $consumo = (float)($input['consumo_annuo_kwh'] ?? $input['consumo_annuo_smc'] ?? 0);
        $spesa = (float)($input['spesa_annua_eur'] ?? 0);
        if ($consumo <= 0) errorResponse('Fornire consumo_annuo_kwh o consumo_annuo_smc', 400);

        $profile = [
            'fornitore'    => $input['fornitore'] ?? 'Sconosciuto',
            'commodity'    => $commodity,
            'pod'          => $input['pod'] ?? null,
            'consumo_annuo'=> $consumo,
            'spesa_annua_eur' => $spesa,
            'canone_rai'   => (float)($input['canone_rai'] ?? 0),
            'spesa_materia_energia' => (float)($input['spesa_materia_energia'] ?? 0),
            'quota_fissa_mensile' => (float)($input['quota_fissa_mensile'] ?? 0),
            'tipo_cliente' => $input['tipo_cliente'] ?? 'residenziale',
            'zona'         => $input['zona'] ?? 'NORD',
            'confidence'   => ['consumption' => 0.8, 'supplier' => 0.5],
        ];
    }

    if (!$profile) errorResponse('Fornire bill_text o commodity+consumo_annuo', 400);

    $commodity = $profile['commodity'];
    $consumo = $profile['consumo_annuo'];
    $zona = $profile['zona'];
    $spesaAnnua = $profile['spesa_annua_eur'];
    $canoneRai = $profile['canone_rai'] ?? 0;
    $spesaMateriaEnergia = $profile['spesa_materia_energia'] ?? 0;
    $quotaFissaMensile = $profile['quota_fissa_mensile'] ?? 0;
    $tipoCliente = $profile['tipo_cliente'] ?? 'residenziale';

    if ($spesaAnnua <= 0) {
        $spesaAnnua = $commodity === 'LUCE' ? ($consumo * 0.18 + 144) : ($consumo * 0.65 + 144);
        $profile['spesa_annua_eur'] = round($spesaAnnua, 2);
        $profile['spesa_stimata'] = true;
    }

    // ── FETCH LIVE PUN/PSV (PRIMA del confronto, per calcolo simmetrico ARERA) ─
    $livePunEurKwh = null;
    $livePsvEurSmc = null;
    $pun = null;
    $psv = null;
    $peData = null;
    try {
        $peUrl = 'https://portaleenergia.it/api/dashboard?period=today';
        $peJson = @file_get_contents($peUrl, false, stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: Mozilla/5.0\r\n"]]));
        if ($peJson) {
            $peData = json_decode($peJson, true);
            $pun = $peData['pun'] ?? null;
            $psv = $peData['psv'] ?? null;
            if ($pun) $livePunEurKwh = round((float)$pun['price'] / 1000, 6); // €/MWh → €/kWh
            if ($psv) $livePsvEurSmc = round((float)$psv['price'] / 1000, 6); // €/MWh → €/Smc
        }
    } catch (Throwable $e) { /* PUN/PSV non disponibile, si usa fallback */ }

    // ── RICALCOLO SPESA ATTUALE PER TARIFFE VARIABILI (PUN Forward simmetrico) ─
    // Se la tariffa attuale è variabile, la spesa dalla bolletta ha un PUN "vecchio".
    // Ricalcoliamo con il PUN corrente per un confronto equo con le nuove offerte variabili.
    $isCurrentVariable = false;
    $estimatedUserSpread = null;
    if (!empty($input['bill_text'])) {
        $low = mb_strtolower($input['bill_text']);
        $isCurrentVariable = str_contains($low, 'variabile') || str_contains($low, 'indicizzato')
                   || str_contains($low, 'pun') || str_contains($low, 'psv');
    } elseif (!empty($input['tariff_type'])) {
        $isCurrentVariable = strtolower($input['tariff_type']) === 'variabile';
    }

    $spesaAttualizzata = null; // Spesa ricalcolata a PUN corrente (solo per variabili)
    if ($isCurrentVariable && $consumo > 0 && $livePunEurKwh !== null && $commodity === 'LUCE') {
        // Estrai spread dalla bolletta
        $estimatedUserSpread = null;
        if (!empty($input['bill_text'])) {
            if (preg_match('/(?:PUN|PSV)\s*\+\s*([\d,.]+)/i', $input['bill_text'], $m)) {
                $estimatedUserSpread = (float)str_replace(',', '.', $m[1]);
            } elseif (preg_match('/spread[:\s]*([\d,.]+)/i', $input['bill_text'], $m)) {
                $estimatedUserSpread = (float)str_replace(',', '.', $m[1]);
            }
        }
        // Anche da input strutturato (frontend invia spread_eur_kwh)
        if (($estimatedUserSpread === null || $estimatedUserSpread <= 0) && !empty($input['spread_eur_kwh'])) {
            $estimatedUserSpread = (float)$input['spread_eur_kwh'];
        }
        // Fallback: stima spread dal prezzo medio in bolletta (ultima risorsa)
        if ($estimatedUserSpread === null || $estimatedUserSpread <= 0) {
            $avgPriceBill = $spesaAnnua / $consumo;
            $nonNeg = 0.045; // trasporto+oneri+accise ~0.045 €/kWh
            $estimatedUserSpread = max(0.002, round($avgPriceBill - $livePunEurKwh - $nonNeg, 4));
        }
        // Ricalcolo spesa attuale a PUN corrente (ARERA v4.0)
        $potenza = (float)($input['potenza_impegnata'] ?? $profile['potenza_impegnata'] ?? 3.0);
        // ARERA v4.0: perdite rete SOLO sul PUN, non sullo spread
        $energyCostNow = $consumo * ($livePunEurKwh * LUCE_PERDITE_RETE_BT + $estimatedUserSpread);
        $costoPotenza = LUCE_COSTO_POTENZA_KW * $potenza;
        $oneriNow = $consumo * ONERI_SISTEMA_LUCE;
        // Accise DL 504/1995 con soglie
        if ($consumo <= LUCE_ACCISE_SOGLIA_ESENTE) {
            $acciseNow = 0;
        } elseif ($consumo <= LUCE_ACCISE_SOGLIA_COMPENSATA) {
            $acciseNow = ($consumo - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
        } else {
            $esenzioneResidua = max(0, LUCE_ACCISE_SOGLIA_ESENTE - ($consumo - LUCE_ACCISE_SOGLIA_COMPENSATA));
            $acciseNow = ($consumo - $esenzioneResidua) * LUCE_ACCISE;
        }
        $trasportoNow = $consumo * LUCE_TRASPORTO_VAR;
        $fixedNow = ($quotaFissaMensile > 0 ? $quotaFissaMensile : 10.00) * 12 + $costoPotenza + QUOTA_FISSA_RETI_LUCE;
        $subtotalNow = $energyCostNow + $fixedNow + $trasportoNow + $oneriNow + $acciseNow;
        $ivaRate = $tipoCliente === 'business' ? 0.22 : 0.10;
        $spesaAttualizzata = round($subtotalNow * (1 + $ivaRate), 2);
    } elseif ($isCurrentVariable && $consumo > 0 && $livePsvEurSmc !== null && $commodity === 'GAS') {
        $estimatedUserSpread = null;
        if (!empty($input['bill_text'])) {
            if (preg_match('/(?:PUN|PSV)\s*\+\s*([\d,.]+)/i', $input['bill_text'], $m)) {
                $estimatedUserSpread = (float)str_replace(',', '.', $m[1]);
            } elseif (preg_match('/spread[:\s]*([\d,.]+)/i', $input['bill_text'], $m)) {
                $estimatedUserSpread = (float)str_replace(',', '.', $m[1]);
            }
        }
        // Anche da input strutturato
        if (($estimatedUserSpread === null || $estimatedUserSpread <= 0) && !empty($input['spread_eur_smc'])) {
            $estimatedUserSpread = (float)$input['spread_eur_smc'];
        }
        if ($estimatedUserSpread === null || $estimatedUserSpread <= 0) {
            $avgPriceBill = $spesaAnnua / $consumo;
            $nonNeg = 0.05;
            $estimatedUserSpread = max(0.005, round($avgPriceBill - $livePsvEurSmc - $nonNeg, 4));
        }
        $energyCostNow = $consumo * ($livePsvEurSmc + $estimatedUserSpread);
        $trasportoNow = $consumo * GAS_TRASPORTO_VAR;
        $oneriNow = $consumo * GAS_ONERI_SISTEMA;
        $acciseNow = $consumo * GAS_ACCISE;
        $addizionaleNow = $consumo * GAS_ADDIZIONALE_REGIONALE;
        $fixedNow = ($quotaFissaMensile > 0 ? $quotaFissaMensile : 10.00) * 12 + QUOTA_FISSA_RETI_GAS;
        $subtotalNow = $energyCostNow + $fixedNow + $trasportoNow + $oneriNow + $acciseNow + $addizionaleNow;
        $iva10 = min($consumo, GAS_SOGLIA_IVA_10) / ($consumo ?: 1) * $subtotalNow * GAS_IVA_10;
        $iva22 = max(0, $consumo - GAS_SOGLIA_IVA_10) / ($consumo ?: 1) * $subtotalNow * GAS_IVA_22;
        $spesaAttualizzata = round($subtotalNow + $iva10 + $iva22, 2);
    }

    // Canone RAI: NON cambia con il fornitore, va sottratto dalla spesa per il confronto
    // Se il valore è sospettosamente basso (< 30€), probabilmente è mensile → annualizza
    $spesaBase = $spesaAttualizzata ?? $spesaAnnua; // Usa spesa attualizzata se disponibile
    if ($canoneRai > 0 && $canoneRai < 30 && $commodity === 'LUCE') {
        // Valore mensile o anomalo: usa CANONE_RAI_ANNUO standard
        $canoneRai = CANONE_RAI_ANNUO;
        $profile['canone_rai'] = $canoneRai;
        $profile['canone_rai_stimato'] = true;
    }
    $spesaNettaConfronto = max(0, $spesaBase - $canoneRai);
    if ($canoneRai <= 0 && $commodity === 'LUCE' && $spesaBase > 100) {
        $canoneRai = CANONE_RAI_ANNUO;
        $spesaNettaConfronto = max(0, $spesaBase - $canoneRai);
        $profile['canone_rai'] = $canoneRai;
        $profile['canone_rai_stimato'] = true;
    }

    // Confronto offerte — con PUN/PSV live per confronto simmetrico (metodo ARERA)
    try {
        $savingsResult = calculateSavingsBreakdown([
            'commodity'              => $commodity,
            'yearly_consumption_kwh' => $commodity === 'LUCE' ? $consumo : 0,
            'yearly_consumption_smc' => $commodity === 'GAS' ? $consumo : 0,
            'zone'                   => $zona,
            'current_annual_spend'   => $spesaNettaConfronto,
            'current_supplier'       => $profile['fornitore'] ?? '',
            'canone_rai'             => $canoneRai,
            'spesa_materia_energia'  => $spesaMateriaEnergia,
            'quota_fissa_mensile'    => $quotaFissaMensile,
            'tipo_cliente'           => $tipoCliente,
            'live_pun_eur_kwh'       => $livePunEurKwh,
            'live_psv_eur_smc'       => $livePsvEurSmc,
        ]);
    } catch (Throwable $e) {
        errorResponse('Impossibile caricare le offerte. Riprova.', 503);
    }

    // ── Arricchisci con link affiliazione (da MySQL) ─
    enrichWithAffiliates($savingsResult);

    // Risk assessment (usa dati PUN/PSV già fetchati sopra)
    $risk = null;
    if ($peData) {
        try {

            // LUCE: risk basato sul PUN
            if ($pun && $commodity === 'LUCE') {
                $punCorr = (float)$pun['price'];
                $punAvg30 = (float)$pun['avg_30d'];
                $punMin = (float)($pun['daily_min'] ?? $punAvg30 * 0.8);
                $punMax = (float)($pun['daily_max'] ?? $punAvg30 * 1.2);
                $vol = $punAvg30 > 0 ? round((($punMax - $punMin) / $punAvg30) * 100, 1) : 0;
                $risk = [
                    'indice'           => 'PUN',
                    'volatilita_pct'   => $vol,
                    'level'            => $vol > 40 ? 'alta' : ($vol > 20 ? 'moderata' : 'bassa'),
                    'prezzo_corrente'  => $punCorr,
                    'media_30gg'       => $punAvg30,
                    'unita'            => '€/MWh',
                    'raccomandazione'  => $vol > 30 ? 'fisso' : 'variabile',
                    'motivazione'      => $vol > 30
                        ? "PUN oscillato {$vol}%. Prezzo fisso consigliato."
                        : "PUN stabile ({$vol}%). Variabile può convenire.",
                ];
            }

            // GAS: risk basato sul PSV (stessa API, campo 'psv')
            if ($psv && $commodity === 'GAS') {
                $psvCorr = (float)$psv['price'];
                $psvAvg30 = (float)$psv['avg_30d'];
                $psvMin = (float)($psv['price_min'] ?? $psvAvg30 * 0.85);
                $psvMax = (float)($psv['price_max'] ?? $psvAvg30 * 1.15);
                $vol = $psvAvg30 > 0 ? round((($psvMax - $psvMin) / $psvAvg30) * 100, 1) : 0;
                $risk = [
                    'indice'           => 'PSV',
                    'volatilita_pct'   => $vol,
                    'level'            => $vol > 40 ? 'alta' : ($vol > 20 ? 'moderata' : 'bassa'),
                    'prezzo_corrente'  => $psvCorr,
                    'media_30gg'       => $psvAvg30,
                    'unita'            => '€/MWh',
                    'raccomandazione'  => $vol > 30 ? 'fisso' : 'variabile',
                    'motivazione'      => $vol > 30
                        ? "PSV oscillato {$vol}%. Prezzo fisso consigliato."
                        : "PSV stabile ({$vol}%). Variabile può convenire.",
                ];
            }
        } catch (Throwable $e) { /* risk non disponibile */ }
    }

    // Bill token
    $billToken = 'sha256:' . substr(sha1(($profile['pod'] ?? '') . ':' . $consumo . ':' . $zona . ':' . $spesaAnnua), 0, 12);

    // Agent summary — ONESTO: consiglia cambio solo se c'è vantaggio reale
    $best = $savingsResult['results'][0] ?? null;
    $recommendation = 'stay'; // stay | evaluate | switch
    $summary = '';

    // Contesto PUN per trasparenza metodologica
    $punContext = '';
    if ($isCurrentVariable && $livePunEurKwh !== null) {
        $punContext = sprintf(' Confronto a PUN corrente %.1f €/MWh (metodo ARERA: stesso PUN per entrambe le tariffe variabili).', $livePunEurKwh * 1000);
    } elseif ($livePunEurKwh !== null) {
        $punContext = sprintf(' PUN corrente %.1f €/MWh usato per il calcolo offerte variabili.', $livePunEurKwh * 1000);
    } elseif ($livePsvEurSmc !== null) {
        $punContext = sprintf(' PSV corrente %.1f €/MWh usato per il calcolo offerte variabili.', $livePsvEurSmc * 1000);
    }

    if ($best && $best['savings_eur'] > 0) {
        $savings = $best['savings_eur'];
        $savingsPct = $best['savings_pct'];
        // Costo totale nuova offerta = costo energia + Canone RAI (il Canone RAI non cambia con fornitore)
        $bestTotalWithRai = $best['annual_cost_eur'] + $canoneRai;
        // Soglie di onestà: sotto 30€/anno o 5% non è un vero risparmio
        if ($savings >= 50 && $savingsPct >= 5) {
            $recommendation = 'switch';
            $summary = sprintf(
                "✅ CONVIENE CAMBIARE. Spesa attuale %.0f€/anno. Migliore offerta: %s %s: %.0f€/anno. Risparmio reale: %.0f€/anno (%.0f%%). %s.%s",
                $spesaAnnua, $best['supplier'], $best['tariff_name'],
                $bestTotalWithRai, $savings, $savingsPct,
                $best['contract_detail'],
                $punContext
            );
        } elseif ($savings >= 30 || $savingsPct >= 3) {
            $recommendation = 'evaluate';
            $summary = sprintf(
                "⚠️ MODESTO VANTAGGIO. Spesa attuale %.0f€/anno. La migliore offerta (%s %s: %.0f€/anno) ti farebbe risparmiare solo %.0f€/anno (%.0f%%). Valuta se il cambio vale la pena considerando anche servizio clienti, app, fatturazione. %s.%s",
                $spesaAnnua, $best['supplier'], $best['tariff_name'],
                $bestTotalWithRai, $savings, $savingsPct,
                $best['contract_detail'],
                $punContext
            );
        } else {
            $recommendation = 'stay';
            $summary = sprintf(
                "❌ NESSUN VANTAGGIO SIGNIFICATIVO. La tua spesa attuale (%.0f€/anno) è già competitiva. La migliore alternativa (%s %s: %.0f€/anno) offre un risparmio trascurabile di %.0f€/anno (%.0f%%). Non vale la pena cambiare.%s",
                $spesaAnnua, $best['supplier'], $best['tariff_name'],
                $bestTotalWithRai, $savings, $savingsPct,
                $punContext
            );
        }

        if ($risk) $summary .= " Mercato {$risk['indice']}: {$risk['raccomandazione']} — {$risk['motivazione']}";
    } elseif ($best && $best['savings_eur'] <= 0) {
        $recommendation = 'stay';
        $bestTotalWithRai = $best['annual_cost_eur'] + $canoneRai;
        $summary = sprintf(
            "❌ NESSUNA OFFERTA PIÙ CONVENIENTE. La tua spesa attuale (%.0f€/anno) è già la più bassa tra le %d offerte confrontate nella zona %s. Non cambiare: hai già una buona tariffa.",
            $spesaAnnua, count($savingsResult['results'] ?? []), $zona
        );
    } else {
        $recommendation = 'evaluate';
        $summary = "Nessuna offerta confrontabile trovata per $commodity nella zona $zona.";
    }

    // Disclaimer — sempre presente
    $disclaimer = " SwitchAI è un'intelligenza artificiale: i prezzi e le offerte possono variare. Controlla sempre le condizioni del contratto prima di sottoscrivere, puoi farti aiutare dalla tua AI (Claude, ChatGPT, Gemini) per verificare la coerenza con quanto ti abbiamo mostrato.";

    // Attach recommendation to response
    $honestyBadge = $recommendation === 'switch' ? '✅ CONVIENE' : ($recommendation === 'evaluate' ? '⚠️ VALUTA' : '❌ NON CONVIENE');
    $summary .= $disclaimer;

    // $attualization is defined later — skip if not yet set
    if (isset($attualization) && $attualization && $recommendation === 'switch') {
        $summary .= " Nota: la bolletta è di qualche mese fa. " . $attualization['impatto_confronto'];
    }
    if (isset($attualization) && $attualization && $attualization['confronto']['direzione'] === 'diminuito') {
        $summary .= " Il PUN è sceso rispetto alla tua bolletta: il risparmio reale è probabilmente inferiore a quanto mostrato.";
    }

    // Why better analysis — per l'LLM per spiegare il risparmio
    $whyBetter = null;
    if ($best && $best['savings_eur'] > 0) {
        $bd = $best['breakdown'] ?? [];
        $whyBetter = [
            'savings_breakdown' => [
                'monthly'  => round($best['savings_eur'] / 12, 2),
                'annual'   => $best['savings_eur'],
                'percent'  => $best['savings_pct'],
                'over_3_years' => round($best['savings_eur'] * 3, 0),
            ],
            'cost_comparison' => [
                'current' => ['annual' => round($spesaAnnua, 2), 'monthly' => round($spesaAnnua / 12, 2), 'per_unit' => null],
                'new'     => ['annual' => $best['annual_cost_eur'] + $canoneRai, 'monthly' => round(($best['annual_cost_eur'] + $canoneRai) / 12, 2), 'per_unit' => $best['price_per_unit']],
                'canone_rai' => $canoneRai,
            ],
            'key_reasons' => [],
            'contract_advantage' => $best['type'] === 'FISSO'
                ? 'Prezzo bloccato: la tua rata non cambia per tutta la durata del contratto, anche se il PUN sale.'
                : 'Prezzo indicizzato: paghi il prezzo di mercato senza ricarichi eccessivi.',
            'risk_context' => $risk ? $risk['motivazione'] : null,
        ];

        // Motivi chiave del risparmio
        if (!empty($bd['energy_diff']) && $bd['energy_diff'] > 0) {
            $whyBetter['key_reasons'][] = sprintf(
                'Risparmi %.2f€/anno sulla materia prima energia: il nuovo prezzo (%.4f €/%s) è più basso del tuo attuale.',
                $bd['energy_diff'],
                $best['price_per_unit'],
                $best['unit']
            );
        }
        if (!empty($bd['fixed_diff']) && $bd['fixed_diff'] > 0) {
            $whyBetter['key_reasons'][] = sprintf(
                'Quota fissa più bassa: risparmi %.2f€/anno sui costi fissi di commercializzazione.',
                $bd['fixed_diff']
            );
        }
        if ($best['type'] === 'FISSO' && !empty($best['spread'])) {
            $whyBetter['key_reasons'][] = "Prezzo fisso: sei protetto dagli aumenti del PUN per tutta la durata del contratto.";
        }
        if (empty($whyBetter['key_reasons'])) {
            $whyBetter['key_reasons'][] = "Il costo totale annuale è inferiore grazie a un miglior bilanciamento tra prezzo energia e quota fissa.";
        }

        // Riferimento prezzo attuale
        if ($best['unit'] === 'kWh') {
            $whyBetter['cost_comparison']['current']['per_unit'] = $consumo > 0 ? round($spesaAnnua / $consumo, 4) : null;
        }
    }

    // ── ATTUALIZZAZIONE BOLLETTA ────────────────────────────────────
    // Fornisce contesto su come la bolletta si confronta con il PUN/PSV odierno
    // Nota: il ricalcolo per il confronto è già stato fatto prima di calculateSavingsBreakdown()
    $attualization = null;

    if ($isCurrentVariable && $consumo > 0 && $spesaAttualizzata !== null) {
        $todayIndex = $commodity === 'LUCE' ? 'PUN' : 'PSV';
        $todayPriceMwh = $commodity === 'LUCE'
            ? round($livePunEurKwh * 1000, 1)
            : round($livePsvEurSmc * 1000, 1);
        $todayPriceUnit = $commodity === 'LUCE' ? $livePunEurKwh : $livePsvEurSmc;

        $diff = round($spesaAttualizzata - $spesaAnnua, 2);
        $diffPct = $spesaAnnua > 0 ? round(($diff / $spesaAnnua) * 100, 1) : 0;
        $direction = $diff > 0 ? 'aumentato' : 'diminuito';
        $arrow = $diff > 0 ? '📈' : '📉';

        $attualization = [
            'bolletta_originale' => [
                'spesa_annua'      => round($spesaAnnua, 2),
                'data_stimata'     => '2-3 mesi fa',
                'spread_utente'    => $estimatedUserSpread ? round($estimatedUserSpread, 4) : null,
            ],
            'oggi' => [
                'indice'           => $todayIndex,
                'valore'           => $todayPriceMwh,
                'unita'            => '€/MWh',
                'spread_stimato'   => round($estimatedUserSpread ?? 0, 4),
                'prezzo_energia'   => round($todayPriceUnit + ($estimatedUserSpread ?? 0), 6),
                'totale_stimato'   => $spesaAttualizzata,
            ],
            'confronto' => [
                'differenza_eur'   => abs($diff),
                'differenza_pct'   => abs($diffPct),
                'direzione'        => $direction,
                'messaggio'        => "Con la stessa tariffa variabile ({$todayIndex} + " . round($estimatedUserSpread ?? 0, 4) . "€), oggi spenderesti circa {$spesaAttualizzata}€/anno — {$arrow} " . abs($diff) . "€ ({$direction} del " . abs($diffPct) . "%) rispetto ai " . round($spesaAnnua, 0) . "€ della bolletta caricata.",
            ],
            'impatto_confronto' => $diff < 0
                ? "La bolletta sovrastima la spesa attuale perché il PUN era più alto. Il confronto è già stato corretto usando il PUN corrente ({$todayPriceMwh} €/MWh) per entrambe le tariffe."
                : "Il PUN è salito rispetto alla bolletta. Il confronto usa già il PUN corrente ({$todayPriceMwh} €/MWh) in modo simmetrico.",
            'metodo' => 'Confronto simmetrico ARERA: stesso PUN Forward per entrambe le tariffe variabili. Il risparmio riflette solo differenze contrattuali (spread + quota fissa).',
        ];
    }

    // Chart-ready cost breakdown (per grafici comparativi)
    $costBreakdown = null;
    if ($best) {
        $unit = $commodity === 'LUCE' ? 'kWh' : 'Smc';
        // Usa dati reali dalla bolletta se disponibili, altrimenti stime ARERA
        $realMateriaEnergia = $spesaMateriaEnergia > 0 ? $spesaMateriaEnergia : round($consumo * ($commodity === 'LUCE' ? 0.16 : 0.55), 2);
        $realQuotaFissa = $quotaFissaMensile > 0 ? round($quotaFissaMensile * 12, 2) : round(10 * 12, 2);
        $ivaRate = $tipoCliente === 'business' ? 0.22 : 0.10;

        $costBreakdown = [
            'current' => [
                'materia_energia' => $realMateriaEnergia,
                'quota_fissa'     => $realQuotaFissa,
                'trasporto_oneri' => round($consumo * ($commodity === 'LUCE' ? 0.0227 : 0), 2),
                'imposte_iva'     => round($spesaAnnua * $ivaRate, 2),
                'canone_rai'      => $canoneRai,
                'totale'          => round($spesaAnnua, 2),
            ],
            'best_offer' => [
                'materia_energia' => round($consumo * (float)($best['price_per_unit'] ?? 0), 2),
                'quota_fissa'     => round(($best['fixed_fee_monthly'] ?? 0) * 12, 2),
                'trasporto_oneri' => round($consumo * ($commodity === 'LUCE' ? 0.0227 : 0), 2),
                'imposte_iva'     => round($best['annual_cost_eur'] * $ivaRate, 2),
                'canone_rai'      => $canoneRai, // Il Canone RAI è uguale per tutti i fornitori
                'totale'          => $best['annual_cost_eur'] + $canoneRai,
            ],
            'chart_data' => [
                'labels'    => ['Materia Energia', 'Quota Fissa', 'Trasporto e Oneri', 'Imposte e IVA', 'Canone RAI'],
                'current'   => [],
                'best_offer'=> [],
                'risparmio' => [],
            ],
        ];
        // Popola chart_data arrays
        foreach (['materia_energia', 'quota_fissa', 'trasporto_oneri', 'imposte_iva', 'canone_rai'] as $k) {
            $costBreakdown['chart_data']['current'][]    = $costBreakdown['current'][$k];
            $costBreakdown['chart_data']['best_offer'][] = $costBreakdown['best_offer'][$k];
            $costBreakdown['chart_data']['risparmio'][]  = round($costBreakdown['current'][$k] - $costBreakdown['best_offer'][$k], 2);
        }
    }

    $format = $input['options']['response_format'] ?? 'compact';
    $response = [
        'bill_token'     => $billToken,
        'profile'        => $profile,
        'top3'           => $best ? $savingsResult['results'] : [],
        'why_better'         => $whyBetter,
        'cost_breakdown'     => $costBreakdown,
        'bill_attualization' => $attualization,
        'risk'               => $risk,
        'honesty'            => ['recommendation' => $recommendation, 'badge' => $honestyBadge],
        'agent_summary'      => $summary,
        'cached'         => false,
        'parsed_at'      => date('c'),
    ];
    if ($format === 'full') {
        $response['all_offers'] = getTariffsByCommodity($commodity);
        $response['comparison_id'] = $savingsResult['comparison_id'];
    }

    jsonResponse($response);
}

// ── AUTH ───────────────────────────────────────────────────────────

function handleAuthLogin(array $input): void {
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';

    $expectedUser = getenv('STATS_USER') ?: 'admin';
    $expectedHash = getenv('STATS_PASSWORD_HASH') ?: '';

    if (!$expectedHash) {
        errorResponse('Auth non configurato', 500);
    }

    if ($user !== $expectedUser || !password_verify($pass, $expectedHash)) {
        error_log("AUTH: Failed login attempt for user '$user' from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        errorResponse('Credenziali errate', 401);
    }

    // Genera token semplice: base64 di user:hash:timestamp firmato con API_KEY
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log("AUTH: API_KEY env var not configured");
        errorResponse('Server configuration error', 500);
    }
    $token = base64_encode($user . ':' . hash_hmac('sha256', $user . ':' . time(), $secret) . ':' . time());

    jsonResponse(['token' => $token]);
}

function handleAuthVerify(): void {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $valid = verifyAuthToken($token);
    jsonResponse(['valid' => $valid]);
}

function verifyAuthToken(string $token): bool {
    if (empty($token)) return false;
    $decoded = base64_decode($token, true);
    if (!$decoded || !str_contains($decoded, ':')) return false;

    $parts = explode(':', $decoded);
    if (count($parts) < 3) return false;

    $user = $parts[0];
    $expectedUser = getenv('STATS_USER') ?: 'admin';
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log("AUTH: API_KEY env var not configured");
        return false;
    }

    // Ricostruisci il signature e confronta
    // Il token è: user:hash_hmac(user:timestamp):timestamp
    $receivedSig = $parts[1];
    $timestamp = (int)$parts[2];

    // Scade dopo 24 ore
    if (time() - $timestamp > 86400) return false;

    $expectedSig = hash_hmac('sha256', $user . ':' . $timestamp, $secret);
    return hash_equals($expectedSig, $receivedSig) && $user === $expectedUser;
}

function requireAuth(): void {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!verifyAuthToken($token)) {
        errorResponse('Non autorizzato', 401);
    }
}

// ── TEST EMAIL ──────────────────────────────────────────────────────

function handleTestEmail(array $input): void {
    $to = getenv('ACTIVATION_EMAIL') ?: 'attivazioni@switchai.it';
    $subject = '[SwitchAI] Email di test — ' . date('d/m/Y H:i');

    $testData = $input['test_data'] ?? [];
    $body = "TEST — Configurazione email SwitchAI riuscita!\n\n";
    $body .= "Data/ora: " . date('d/m/Y H:i:s') . "\n";
    $body .= "Server: " . phpversion() . "\n";
    $body .= "Host: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n\n";

    if (!empty($testData)) {
        $body .= "Dati test sottoscrizione:\n";
        $body .= "  Nome: " . ($testData['nome'] ?? 'N/D') . ' ' . ($testData['cognome'] ?? '') . "\n";
        $body .= "  Email: " . ($testData['email'] ?? 'N/D') . "\n";
        $body .= "  Telefono: " . ($testData['cellulare'] ?? 'N/D') . "\n";
        $body .= "  Offerta: " . ($testData['tariff_name'] ?? 'N/D') . "\n";
        $body .= "  Fornitore: " . ($testData['supplier'] ?? 'N/D') . "\n";
        $body .= "  POD/PDR: " . ($testData['codice_pod'] ?? $testData['codice_pdr'] ?? 'N/D') . "\n";
    }

    $body .= "\nWS_ENABLED: " . (getenv('WS_ENABLED') ?: 'true') . "\n";
    $body .= "WS_URL: " . (getenv('WS_SUBSCRIPTION_URL') ?: 'N/A') . "\n";

    $headers = "From: SwitchAI <attivazioni@switchai.it>\r\n";
    $headers .= "Reply-To: attivazioni@switchai.it\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($to, $subject, $body, $headers);

    if (!$sent) {
        // Log per debug su OVH
        error_log("TEST EMAIL FAILED — to:$to subject:$subject");
    }

    jsonResponse([
        'status'  => $sent ? 'sent' : 'failed',
        'to'      => $to,
        'subject' => $subject,
        'body'    => $body,
        'tip'     => $sent ? '✅ Email inviata! Controlla la casella.' : '❌ Invio fallito. Controlla i log PHP su OVH.',
    ]);
}

// ── MARKET INDICES (PUN / PSV) ────────────────────────────────────────

/**
 * Recupera PUN e PSV live da PortaleEnergia.it (API pubblica).
 * Fallback: GME → valori di riferimento.
 * Cache: 1 ora.
 */
function handleMarketIndices(): void {
    $cacheFile = sys_get_temp_dir() . '/switchai_market_indices.json';
    // Cache 24h + jitter casuale (±3 ore) per non chiamare sempre allo stesso orario
    $cacheTTL = 86400 + random_int(-10800, 10800);

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) {
            jsonResponse($cached);
            return;
        }
    }

    $indices = ['pun' => null, 'psv' => null, 'source' => 'reference', 'updated' => date('c')];

    // ── Fonte 1: API live ────────────────────────────────────────
    $peData = fetchPortaleEnergiaData();
    if ($peData) {
        $indices['pun'] = round($peData['pun'] / 1000, 6);
        $indices['psv'] = round($peData['psv'] / 1000, 6);
        $indices['source'] = 'mercato';
        $indices['pun_daily_avg'] = round($peData['pun_avg'] / 1000, 6);
        $indices['psv_avg_30d'] = round($peData['psv_30d'] / 1000, 6);
        $indices['data_date'] = $peData['date'];

        // Salva storico giornaliero per trend analysis
        $historyFile = __DIR__ . '/../../data/market_history.json';
        $history = is_file($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
        $today = $peData['date'] ?? date('Y-m-d');

        // Aggiungi oggi se non già presente
        if (!isset($history[$today])) {
            $history[$today] = [
                'pun' => round($peData['pun'] / 1000, 6),
                'psv' => round($peData['psv'] / 1000, 6),
                'pun_mwh' => $peData['pun'],
                'psv_mwh' => $peData['psv'],
            ];
            // Tieni solo ultimi 90 giorni
            if (count($history) > 90) {
                $history = array_slice($history, -90, 90, true);
            }
            @file_put_contents($historyFile, json_encode($history, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }

        // Calcola trend
        $trend = calculateMarketTrend($history);
        $indices['trend'] = $trend;
    }

    // ── Fallback: valori di riferimento ─────────────────────────────
    if ($indices['pun'] === null) $indices['pun'] = 0.125;
    if ($indices['psv'] === null) $indices['psv'] = 0.500;

    // Formatta per visualizzazione
    $indices['pun_display'] = number_format($indices['pun'] * 1000, 1, ',', '') . ' €/MWh (' . number_format($indices['pun'], 4, ',', '') . ' €/kWh)';
    $indices['psv_display'] = number_format($indices['psv'] * 1000, 1, ',', '') . ' €/MWh (' . number_format($indices['psv'], 4, ',', '') . ' €/Smc)';

    @file_put_contents($cacheFile, json_encode($indices, JSON_UNESCAPED_UNICODE), LOCK_EX);
    jsonResponse($indices);
}

/** Calcola trend PUN/PSV dagli ultimi 7 e 30 giorni */
function calculateMarketTrend(array $history): array {
    if (count($history) < 2) return ['direction' => 'stable', 'icon' => '☀️', 'message' => 'Dati insufficienti per il trend'];

    $values = array_values($history);
    $last = end($values);
    $count = count($values);

    // Ultimi 7 giorni
    $week = array_slice($values, max(0, $count - 7));
    $weekAvg = count($week) > 1 ? round(array_sum(array_column($week, 'pun_mwh')) / count($week), 1) : $last['pun_mwh'];
    $weekFirst = $week[0]['pun_mwh'];
    $weekChange = $weekFirst > 0 ? round((($last['pun_mwh'] - $weekFirst) / $weekFirst) * 100, 1) : 0;

    // 30 giorni
    $month = array_slice($values, max(0, $count - 30));
    $monthFirst = $month[0]['pun_mwh'];
    $monthChange = $monthFirst > 0 ? round((($last['pun_mwh'] - $monthFirst) / $monthFirst) * 100, 1) : 0;

    // Direzione e icona
    $direction = abs($weekChange) < 3 ? 'stable' : ($weekChange > 0 ? 'up' : 'down');
    $icon = $direction === 'up' ? '📈' : ($direction === 'down' ? '📉' : '➡️');

    // Messaggio "momento buono per cambiare?"
    if ($direction === 'down' && $monthChange < -5) {
        $message = "Il PUN è in calo ({$monthChange}% in 30gg). Buon momento per valutare un fisso: i prezzi sono più bassi della media.";
        $moment = 'good';
    } elseif ($direction === 'up' && $monthChange > 10) {
        $message = "Il PUN sta salendo ({$monthChange}% in 30gg). Se hai un variabile, valuta di passare a un fisso per bloccare il prezzo.";
        $moment = 'alert';
    } else {
        $message = "Mercato stabile. I prezzi sono in linea con la media degli ultimi 30 giorni.";
        $moment = 'neutral';
    }

    return [
        'direction'      => $direction,
        'icon'           => $icon,
        'moment'         => $moment,
        'pun_today'      => $last['pun_mwh'],
        'week_avg'       => $weekAvg,
        'week_change_pct'=> $weekChange,
        'month_change_pct'=> $monthChange,
        'message'        => $message,
    ];
}

/** Fetch dati live da PortaleEnergia.it */
function fetchPortaleEnergiaData(): ?array {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36';
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'header' => "User-Agent: $ua\r\nAccept: application/json\r\nReferer: https://portaleenergia.it/\r\n",
    ]]);

    $json = @file_get_contents('https://portaleenergia.it/api/dashboard?period=today', false, $ctx);

    if ($json === false && function_exists('curl_init')) {
        $ch = curl_init('https://portaleenergia.it/api/dashboard?period=today');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_REFERER => 'https://portaleenergia.it/',
        ]);
        $json = curl_exec($ch); curl_close($ch);
    }

    if (!$json) return null;
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['pun'])) return null;

    return [
        'pun'     => (float)($data['pun']['price'] ?? 0),
        'pun_avg' => (float)($data['pun']['daily_avg'] ?? 0),
        'psv'     => (float)($data['psv']['price'] ?? 0),
        'psv_30d' => (float)($data['psv']['avg_30d'] ?? 0),
        'date'    => $data['pun']['date'] ?? $data['last_data_update']['date'] ?? date('Y-m-d'),
    ];
}

/**
 * Arricchisce i risultati con link di affiliazione (da MySQL).
 * Se un'offerta ha un affiliate_url, sostituisce il subscription_url.
 */
function enrichWithAffiliates(array &$result): void {
    try {
        require_once __DIR__ . '/../inc/db_mysql.php';
        foreach ($result['results'] as &$r) {
            $affUrl = getAffiliateLink($r['tariff_id']);
            if ($affUrl) {
                $r['affiliate_url'] = $affUrl;
                $r['subscription_url'] = $affUrl; // sovrascrivi con link affiliazione
            }
        }
        unset($r);
    } catch (Throwable $e) {
        // MySQL non disponibile — nessun arricchimento
    }
}
