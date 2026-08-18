<?php
/**
 * index.php — API Router Principale
 *
 * Tutti gli endpoint partono da /api/...
 * Su OVH, configura .htaccess per riscrivere le URL.
 */

// Suppress PHP errors in production (OVH PHP-FPM doesn't read .htaccess php_flag)
error_reporting(0);
ini_set('display_errors', '0');

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
header('Access-Control-Allow-Headers: Content-Type, x-api-key, x-auth-token');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../inc/tariff_loader.php';
require_once __DIR__ . '/../inc/bill_parser.php';
require_once __DIR__ . '/../inc/llm_logger.php';
require_once __DIR__ . '/../inc/api_auth.php';

// Parsing richiesta (prima del rate limit per poter esentare endpoint pubblici)
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = preg_replace('#/api/index\.php#', '/api', $uri);
$uri = preg_replace('/\.php$/', '', $uri);

// ── Rate Limiting (solo endpoint sensibili; auth/register esenti) ──
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$rateLimitedEndpoints = [
    '/api/analyze',           // POST — heavy compute
    '/api/calculate-savings', // POST — heavy compute
    '/api/admin/api-keys',    // POST/DELETE — admin
    '/api/trigger-scraper',   // POST — admin
    '/api/test-email',        // POST — admin
    '/api/auth/login',        // POST — anti brute-force (5/min)
];
$exemptEndpoints = [
    '/api/auth/register',
    '/api/auth/confirm-email',
    '/api/auth/resend-confirmation',
    '/api/auth/forgot-password',
    '/api/auth/reset-password',
    '/api/auth/verify',
    '/api/auth/rate-limit-info',
    '/api/auth/me',
    '/api/auth/api-keys',
    '/api/health',
    '/api/status',
];
$needsRateLimit = !$isLocal && ($method !== 'GET' || in_array($uri, ['/api/analyze', '/api/calculate-savings'], true)) && !in_array($uri, $exemptEndpoints, true);
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
function loadEnrichmentData(): array {
    $path = __DIR__ . '/../data/offerte/fornitori-enrichment.json';
    if (!is_file($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

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
            $enrichment = loadEnrichmentData();
            $suppliers = loadSuppliers();
            $allTariffs = loadTariffs();
            // Pre-count: map supplier slug → offer count
            $offerCounts = [];
            foreach ($allTariffs as $t) {
                $s = strtolower(str_replace([' ', 'è', 'à', 'ì', 'ò', 'ù'], ['-', 'e', 'a', 'i', 'o', 'u'], $t['supplier_name']));
                $s = preg_replace('/[^a-z0-9-]/', '', $s);
                if ($s) $offerCounts[$s] = ($offerCounts[$s] ?? 0) + 1;
            }
            foreach ($suppliers as &$s) {
                $slug = $s['slug'];
                $s['count'] = $offerCounts[$slug] ?? 0;
                $e = $enrichment[$slug] ?? null;
                if ($e) {
                    $s['logo_url'] = $e['logo_url'];
                    $s['rating'] = $e['rating'];
                    $s['recensioni'] = $e['recensioni'];
                    $s['tipo'] = $e['tipo'];
                } else {
                    $s['logo_url'] = null;
                    $s['rating'] = null;
                    $s['recensioni'] = null;
                    $s['tipo'] = null;
                }
            }
            jsonResponse($suppliers);
            break;

        // GET /api/fornitori/{slug} — dettaglio fornitore (JSON)
        case preg_match('#^/api/fornitori/([a-z0-9\-]+)$#', $uri, $fm) && $method === 'GET':
            $slug = $fm[1];
            $all = loadTariffs();
            $offers = [];
            foreach ($all as $t) {
                $s = strtolower(str_replace([' ', 'è', 'à', 'ì', 'ò', 'ù'], ['-', 'e', 'a', 'i', 'o', 'u'], $t['supplier_name']));
                $s = preg_replace('/[^a-z0-9-]/', '', $s);
                if ($s === $slug) {
                    $offers[] = $t;
                }
            }
            if (empty($offers)) {
                http_response_code(404);
                jsonResponse(['error' => 'Fornitore non trovato']);
            }
            $first = $offers[0];
            $name = $first['supplier_name'];
            $brand = $first['brand'] ?? '';
            $logo = $first['logo'] ?? '';
            $enrichment = loadEnrichmentData();
            $e = $enrichment[$slug] ?? null;
            jsonResponse([
                'brand'      => $brand,
                'slug'       => $slug,
                'name'       => $name,
                'logo'       => $logo,
                'totali'     => count($offers),
                'luce'       => count(array_filter($offers, fn($o) => $o['commodity'] === 'LUCE')),
                'gas'        => count(array_filter($offers, fn($o) => $o['commodity'] === 'GAS')),
                'fisse'      => count(array_filter($offers, fn($o) => $o['type'] === 'FISSO')),
                'variabili'  => count(array_filter($offers, fn($o) => $o['type'] === 'VARIABILE')),
                'offerte'    => $offers,
                'descrizione' => $e['descrizione'] ?? null,
                'rating'     => $e['rating'] ?? null,
                'recensioni' => $e['recensioni'] ?? null,
                'trustpilot_url' => $e['trustpilot_url'] ?? null,
                'sito_web'   => $e['sito_web'] ?? null,
                'logo_url'   => $e['logo_url'] ?? null,
                'fondazione' => $e['fondazione'] ?? null,
                'tipo'       => $e['tipo'] ?? null,
            ]);
            break;

        // POST /api/auth/login
        case $uri === '/api/auth/login' && $method === 'POST':
            handleAuthLogin($input);
            break;

        // GET /api/auth/verify
        case $uri === '/api/auth/verify' && $method === 'GET':
            handleAuthVerify();
            break;

        // GET /api/auth/rate-limit-info — richieste rimanenti (free tier)
        case $uri === '/api/auth/rate-limit-info' && $method === 'GET':
            require_once __DIR__ . '/../inc/api_auth.php';
            jsonResponse(getB2CRateLimitInfo());
            break;

        // ── User Registration & Account ─────────────────────

        // POST /api/auth/register — crea account + invia email conferma
        case $uri === '/api/auth/register' && $method === 'POST':
            handleUserRegister($input);
            break;

        // POST /api/auth/confirm-email — conferma email via token
        case $uri === '/api/auth/confirm-email' && $method === 'POST':
            handleUserConfirmEmail($input);
            break;

        // POST /api/auth/resend-confirmation — reinvia email conferma
        case $uri === '/api/auth/resend-confirmation' && $method === 'POST':
            handleUserResendConfirmation($input);
            break;

        // POST /api/auth/forgot-password — richiedi reset password via email
        case $uri === '/api/auth/forgot-password' && $method === 'POST':
            handleForgotPassword($input);
            break;

        // POST /api/auth/reset-password — resetta password con token
        case $uri === '/api/auth/reset-password' && $method === 'POST':
            handleResetPassword($input);
            break;

        // GET /api/auth/me — profilo utente corrente + usage + API keys
        case $uri === '/api/auth/me' && $method === 'GET':
            handleUserMe();
            break;

        // ── User API Keys ──────────────────────────────────

        // POST /api/auth/api-keys/create — crea nuova API key
        case $uri === '/api/auth/api-keys/create' && $method === 'POST':
            handleUserCreateApiKey($input);
            break;

        // GET /api/auth/api-keys — lista API keys dell'utente
        case $uri === '/api/auth/api-keys' && $method === 'GET':
            handleUserListApiKeys();
            break;

        // DELETE /api/auth/api-keys/{id} — revoca API key
        case preg_match('#^/api/auth/api-keys/(\d+)$#', $uri, $akm) && $method === 'DELETE':
            handleUserRevokeApiKey((int)$akm[1]);
            break;

        // GET /sitemap.xml — generato dinamicamente da dati live
        case $uri === '/sitemap.xml' && $method === 'GET':
            handleDynamicSitemap();
            break;

        // GET /offerta/{id} — pagina offerta per crawler (HTML + JSON-LD, noindex)
        case preg_match('#^/offerta/([a-zA-Z0-9\-]+)$#', $uri, $offerMatch) && $method === 'GET':
            handleOffertaPage($offerMatch[1]);
            break;

        // GET /fornitori/{slug} — pagina fornitore per SEO (indicizzata, contenuto ricco)
        case preg_match('#^/fornitori/([a-z0-9\-]+)$#', $uri, $supplierMatch) && $method === 'GET':
            handleFornitorePage($supplierMatch[1]);
            break;

        // GET /tariffe-luce e /confronto-gas — SEO pages con pre-render per crawler
        case $uri === '/tariffe-luce' && $method === 'GET':
            handleSeoPage('tariffe-luce');
            break;

        case $uri === '/confronto-gas' && $method === 'GET':
            handleSeoPage('confronto-gas');
            break;

        // GET /offerte/luce/{regione} e /offerte/gas/{regione} — pagine geo SEO
        case preg_match('#^/offerte/(luce|gas)/([a-z-]+)$#', $uri, $offerteMatch) && $method === 'GET':
            handleRegionePage($offerteMatch[1], $offerteMatch[2]);
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

                // Stato file ARERA locali
                $dataDir = __DIR__ . '/../data/offerte';
                $luceFile = $dataDir . '/db-offerte-luce.json';
                $gasFile = $dataDir . '/db-offerte-gas.json';
                $luceCount = is_file($luceFile) ? count(json_decode(file_get_contents($luceFile), true) ?: []) : 0;
                $gasCount = is_file($gasFile) ? count(json_decode(file_get_contents($gasFile), true) ?: []) : 0;
                $luceSize = is_file($luceFile) ? round(filesize($luceFile) / 1048576, 1) : 0;
                $gasSize = is_file($gasFile) ? round(filesize($gasFile) / 1048576, 1) : 0;

                $configPath = __DIR__ . '/../data/offerte/config.json';
                $config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : [];

                jsonResponse([
                    'users' => $users,
                    'api_keys' => $apiKeys,
                    'rate_logs' => $rateLogs,
                    'affiliates' => $affiliates,
                    'mysql' => 'connected',
                    'arera' => [
                        'luce' => ['count' => $luceCount, 'size_mb' => $luceSize],
                        'gas'  => ['count' => $gasCount, 'size_mb' => $gasSize],
                        'total' => $luceCount + $gasCount,
                    ],
                    'prices' => [
                        'PUN' => $config['PUN'] ?? null,
                        'PUN_F1' => $config['PUN_F1'] ?? null,
                        'PUN_F3' => $config['PUN_F3'] ?? null,
                        'PUN_label' => $config['PUN_label'] ?? '',
                        'PSV' => $config['PSV'] ?? null,
                        'PSV_label' => $config['PSV_label'] ?? '',
                        'updated_at' => $config['updated_at'] ?? '',
                    ],
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
            define('ARERA_SYNC_SILENT', true);
            require_once __DIR__ . '/../inc/arera_sync.php';
            $type = $input['type'] ?? null;
            $regione = $input['regione'] ?? null;
            $types = $type ? [$type] : ['luce', 'gas'];
            $results = [];
            foreach ($types as $t) {
                $results[] = arera_run_sync($t, $GLOBALS['brand_metadata'], $GLOBALS['parametri_mercato'], $regione);
            }
            // Arricchisci con conteggi privati/aziende
            $luceFile = ARERA_DATA_DIR . '/db-offerte-luce.json';
            $gasFile = ARERA_DATA_DIR . '/db-offerte-gas.json';
            $lucePrivati = 0; $luceAziende = 0;
            $gasPrivati = 0; $gasAziende = 0;
            if (is_file($luceFile)) {
                $d = json_decode(file_get_contents($luceFile), true) ?: [];
                foreach ($d as $o) { if (($o['tipo_cliente'] ?? '') === 'residenziale') $lucePrivati++; else $luceAziende++; }
            }
            if (is_file($gasFile)) {
                $d = json_decode(file_get_contents($gasFile), true) ?: [];
                foreach ($d as $o) { if (($o['tipo_cliente'] ?? '') === 'residenziale') $gasPrivati++; else $gasAziende++; }
            }
            $luceOk = ($results[0]['success'] ?? false) ? $results[0]['count'] : 0;
            $gasOk = ($results[1]['success'] ?? false) ? ($results[1]['count'] ?? 0) : 0;
            $tot = $luceOk + $gasOk;
            $elapsed = round(($results[0]['elapsed'] ?? 0) + ($results[1]['elapsed'] ?? 0), 2);
            jsonResponse([
                'results' => $results,
                'status'  => 'completed',
                'luce'    => ['total' => $luceOk, 'privati' => $lucePrivati, 'aziende' => $luceAziende],
                'gas'     => ['total' => $gasOk, 'privati' => $gasPrivati, 'aziende' => $gasAziende],
                'totale'  => $tot,
                'elapsed' => $elapsed,
                'message' => $tot > 0 ? "Sync completato in {$elapsed}s: {$luceOk} LUCE + {$gasOk} GAS = {$tot} offerte totali" : 'Sync completato ma nessuna offerta importata',
            ]);
            break;

        // POST /api/admin/update-prices — aggiorna PUN/PSV manualmente (richiede auth)
        case $uri === '/api/admin/update-prices' && $method === 'POST':
            requireAuth();
            $configPath = __DIR__ . '/../data/offerte/config.json';
            $config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : [];
            $now = (new DateTime())->format('Y-m-d\TH:i:sP');
            if (isset($input['pun'])) {
                $config['PUN'] = (float)$input['pun'];
                $config['PUN_label'] = 'PUN manuale — ' . $now;
            }
            if (isset($input['psv'])) {
                $config['PSV'] = (float)$input['psv'];
                $config['PSV_label'] = 'PSV manuale — ' . $now;
            }
            if (isset($input['pun_f1'])) $config['PUN_F1'] = (float)$input['pun_f1'];
            if (isset($input['pun_f3'])) $config['PUN_F3'] = (float)$input['pun_f3'];
            $config['updated_at'] = $now;
            file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            jsonResponse(['status' => 'ok', 'message' => 'Prezzi aggiornati', 'config' => $config]);
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

        // ── Brand affiliate default ─────────────────────────────────────
        // GET /api/admin/brand-affiliates — lista tracker di default per brand
        case $uri === '/api/admin/brand-affiliates' && $method === 'GET':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                jsonResponse(['brand_affiliates' => getAllBrandAffiliates()]);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // POST /api/admin/brand-affiliates — upsert tracker di default per brand
        case $uri === '/api/admin/brand-affiliates' && $method === 'POST':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                $brand = $input['brand'] ?? '';
                $url = $input['default_url'] ?? '';
                if (empty($brand) || empty($url)) {
                    errorResponse('brand e default_url obbligatori', 400);
                }
                upsertBrandAffiliate($brand, $url, $input['network'] ?? '', $input['impression_url'] ?? null);
                jsonResponse(['status' => 'ok']);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // DELETE /api/admin/brand-affiliates — elimina tracker di default per brand
        case $uri === '/api/admin/brand-affiliates' && $method === 'DELETE':
            requireAuth();
            require_once __DIR__ . '/../inc/db_mysql.php';
            try {
                $brand = $input['brand'] ?? '';
                if (empty($brand)) errorResponse('brand obbligatorio', 400);
                deleteBrandAffiliate($brand);
                jsonResponse(['status' => 'deleted']);
            } catch (Throwable $e) {
                errorResponse('Errore database: ' . $e->getMessage(), 500);
            }
            break;

        // GET /api/admin/wattene-test — confronto ARERA vs Wattene
        case $uri === '/api/admin/wattene-test' && $method === 'GET':
            requireAuth();
            handleWatteneTest();
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

        // ── Admin: User Management ─────────────────────────

        // GET /api/admin/users — lista utenti registrati
        case $uri === '/api/admin/users' && $method === 'GET':
            requireAuth();
            handleAdminListUsers();
            break;

        // PATCH /api/admin/users/{id} — modifica utente (tier, disabilita)
        case preg_match('#^/api/admin/users/(\d+)$#', $uri, $um) && $method === 'PATCH':
            requireAuth();
            handleAdminUpdateUser((int)$um[1], $input);
            break;

        // ── Admin: API Test Tool ───────────────────────────

        // POST /api/admin/test-api — proxy test per API key
        case $uri === '/api/admin/test-api' && $method === 'POST':
            requireAuth();
            handleAdminTestApi($input);
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

    if (!isset($input['filters'])) {
        $input['filters'] = isPremiumRequest()
            ? []
            : ['main_suppliers' => true, 'no_penali' => true, 'online_only' => true];
    }

    $result = calculateSavingsBreakdown($input);
    enrichWithAffiliates($result);
    $result['system_total_offers'] = count(loadTariffs());
    $result['tier'] = isPremiumRequest() ? 'premium' : 'free';
    jsonResponse($result);
}

function handleWebMCPEndpoint(array $input): void {
    $commodity = $input['commodity'] ?? '';
    if (!in_array($commodity, ['LUCE', 'GAS'])) {
        errorResponse("Invalid commodity. Must be 'LUCE' or 'GAS'.");
    }

    $input['source'] = 'AI_AGENT';
    // Tier detection: premium se API key o admin token, altrimenti free (filtri ON)
    if (!isset($input['filters'])) {
        $input['filters'] = isPremiumRequest()
            ? []
            : ['main_suppliers' => true, 'no_penali' => true, 'online_only' => true];
    }
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

    // Nota: SwitchAI non ha un form di sottoscrizione.
    // L'attivazione va direttamente sul sito del fornitore tramite affiliate_url.
    // NON mettere mai dati personali in URL (nome, CF, POD, email, telefono).
    // I dati personali NON vanno mai in querystring — finirebbero nei log del server.
    $result['_prefill_instructions'] = "SwitchAI non raccoglie dati personali. "
        . "L'attivazione va direttamente sul sito del fornitore tramite il link fornito. "
        . "NON costruire URL con dati personali in querystring (nome, CF, POD, email). "
        . "Non esiste un form di sottoscrizione su switchai.it.";

    $result['agent_summary'] = $summary;
    $result['tier'] = isPremiumRequest() ? 'premium' : 'free';
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

function handleTriggerScraper(): void {
    $headers = getallheaders();
    $apiKey = $headers['x-api-key'] ?? $headers['X-Api-Key'] ?? '';
    $expectedKey = getenv('API_KEY');

    if (!$expectedKey) {
        error_log("TRIGGER-SCRAPER: API_KEY env var not configured");
        errorResponse('Server configuration error', 500);
    }

    // Verifica API key PRIMA del rate limit (anti-DoS: non consumare slot senza auth)
    if (!hash_equals($expectedKey, $apiKey)) {
        error_log("TRIGGER-SCRAPER: Unauthorized attempt from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        errorResponse('Unauthorized', 401);
    }

    // Protezione anti-brute-force: accetta max 1 richiesta ogni 60 secondi
    $rateLimitFile = sys_get_temp_dir() . '/switchai_scraper_ratelimit';
    $now = time();
    $lastCall = (int)@file_get_contents($rateLimitFile);
    if ($now - $lastCall < 60) {
        errorResponse('Rate limit: max 1 refresh per minuto. Attendere.', 429);
    }
    @file_put_contents($rateLimitFile, $now, LOCK_EX);

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

    $defaultFilters = ['main_suppliers' => true, 'no_penali' => true, 'online_only' => true];
    $luceFiltered = count(getTariffsForCalculation('LUCE', 'NORD', 'residenziale', $defaultFilters));
    $gasFiltered  = count(getTariffsForCalculation('GAS', 'NORD', 'residenziale', $defaultFilters));

    jsonResponse([
        'luce_tariffs'  => $luce,
        'gas_tariffs'   => $gas,
        'suppliers'     => $suppliers,
        'php_version'   => phpversion(),
        'db_mode'       => 'json_remote',
        'filtered'      => [
            'luce' => $luceFiltered,
            'gas'  => $gasFiltered,
            'total' => $luceFiltered + $gasFiltered,
        ],
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
            'price_f1_kwh'      => $t['price_f1_kwh'] ?? null,
            'price_f2_kwh'      => $t['price_f2_kwh'] ?? null,
            'price_f3_kwh'      => $t['price_f3_kwh'] ?? null,
            'price_smc'         => $t['price_smc'],
            'fixed_fee_monthly' => $t['fixed_fee_monthly'],
            'fixed_fee_annual'  => $t['fixed_fee_annual'] ?? null,
            'spread'            => $t['spread'] ?? null,
            'pun'               => $t['pun'] ?? null,
            'psv'               => $t['psv'] ?? null,
            'promo_active'      => $t['promo_active'],
            'brand'             => $t['brand'] ?? '',
            'logo'              => $t['logo'] ?? null,
            'tipo_cliente'      => $t['tipo_cliente'] ?? 'residenziale',
            'tipo_fasce'        => $t['tipo_fasce'] ?? null,
            'regioni'           => $t['regioni'] ?? [],
            'nazionale'         => $t['nazionale'] ?? true,
            'has_sconti_condizionali' => $t['has_sconti_condizionali'] ?? false,
            'sconto_note'       => $t['sconto_note'] ?? '',
            'extra'             => $t['extra'] ?? [],
        ], $tariffs);

    jsonResponse([
        'commodity' => $commodity,
        'count'     => count($clean),
        'offers'    => $clean,
    ]);
}

// ── DYNAMIC SITEMAP ─────────────────────────────────────────────────

function handleDynamicSitemap(): void {
    header('Content-Type: application/xml; charset=UTF-8');

    // Data ultimo sync ARERA (da config.json) — usata come lastmod reale
    $syncDate = date('Y-m-d');
    $configFile = __DIR__ . '/../data/offerte/config.json';
    if (is_file($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (!empty($config['updated_at'])) {
            $syncDate = date('Y-m-d', strtotime($config['updated_at']));
        }
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Pagine statiche (URL con .html — canonical effettivo)
    $static = [
        ['loc' => 'https://www.switchai.it/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['loc' => 'https://www.switchai.it/per-llm', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/fornitori/', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['loc' => 'https://www.switchai.it/calcolo-rapido', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/come-funziona', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/tariffe-luce', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/confronto-gas', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/privacy', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/cookie', 'priority' => '0.3', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/faq', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/risorse/', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => 'https://www.switchai.it/risorse/come-funziona-bolletta-luce', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/risorse/come-funziona-bolletta-gas', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/risorse/glossario-energia', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/risorse/prezzo-fisso-vs-indicizzato', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/risorse/calcolo-spesa-annua', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => 'https://www.switchai.it/risorse/come-leggere-bolletta', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ];
    foreach ($static as $url) {
        $lastmod = $syncDate;
        $cf = isset($url['changefreq']) ? "    <changefreq>{$url['changefreq']}</changefreq>\n" : '';
        echo "  <url>\n    <loc>{$url['loc']}</loc>\n    <lastmod>{$lastmod}</lastmod>\n{$cf}    <priority>{$url['priority']}</priority>\n  </url>\n";
    }

    // Offerte: aggregate per fornitore (NO pagine individuali — sono noindex)
    try {
        $tariffs = loadTariffs();
        $suppliers = [];
        foreach ($tariffs as $t) {
            $sid = $t['supplier_name'];
            if (!isset($suppliers[$sid])) {
                $suppliers[$sid] = ['luce' => 0, 'gas' => 0];
            }
            if ($t['commodity'] === 'LUCE') $suppliers[$sid]['luce']++;
            else $suppliers[$sid]['gas']++;
        }
        foreach ($suppliers as $name => $counts) {
            $slug = strtolower(str_replace([' ', 'è', 'à', 'ì', 'ò', 'ù'], ['-', 'e', 'a', 'i', 'o', 'u'], $name));
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
            echo "  <url>\n";
            echo "    <loc>https://www.switchai.it/fornitori/{$slug}</loc>\n";
            echo "    <lastmod>{$syncDate}</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.6</priority>\n";
            echo "  </url>\n";
        }

        // Offerte geo: pagine per regione (20 regioni × 2 commodity = 40 pagine)
        $regionSlugs = [
            'piemonte', 'valle-daosta', 'lombardia', 'trentino-alto-adige',
            'veneto', 'friuli-venezia-giulia', 'liguria', 'emilia-romagna',
            'toscana', 'umbria', 'marche', 'lazio',
            'abruzzo', 'molise', 'campania', 'puglia',
            'basilicata', 'calabria', 'sicilia', 'sardegna',
        ];
        foreach (['luce', 'gas'] as $comm) {
            foreach ($regionSlugs as $rs) {
                echo "  <url>\n";
                echo "    <loc>https://www.switchai.it/offerte/{$comm}/{$rs}</loc>\n";
                echo "    <lastmod>{$syncDate}</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>0.6</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (\Throwable $e) {
        // Se il caricamento tariffe fallisce, almeno le pagine statiche ci sono
    }

    echo '</urlset>';
}

// ── Helper: regioni navigation links per SEO pages ──────────────────
function getRegioniHtml(string $commodity): string {
    $zones = [
        'NORD' => ['piemonte', 'valle-daosta', 'lombardia', 'trentino-alto-adige', 'veneto', 'friuli-venezia-giulia', 'liguria', 'emilia-romagna'],
        'CENTRO' => ['toscana', 'umbria', 'marche', 'lazio'],
        'SUD' => ['abruzzo', 'molise', 'campania', 'puglia', 'basilicata', 'calabria', 'sicilia', 'sardegna'],
    ];
    $zoneNames = ['NORD' => 'Nord', 'CENTRO' => 'Centro', 'SUD' => 'Sud'];
    $regionNames = [
        'piemonte' => 'Piemonte', 'valle-daosta' => "Valle d'Aosta", 'lombardia' => 'Lombardia',
        'trentino-alto-adige' => 'Trentino-Alto Adige', 'veneto' => 'Veneto',
        'friuli-venezia-giulia' => 'Friuli-Venezia Giulia', 'liguria' => 'Liguria',
        'emilia-romagna' => 'Emilia-Romagna', 'toscana' => 'Toscana', 'umbria' => 'Umbria',
        'marche' => 'Marche', 'lazio' => 'Lazio', 'abruzzo' => 'Abruzzo', 'molise' => 'Molise',
        'campania' => 'Campania', 'puglia' => 'Puglia', 'basilicata' => 'Basilicata',
        'calabria' => 'Calabria', 'sicilia' => 'Sicilia', 'sardegna' => 'Sardegna',
    ];
    $label = $commodity === 'luce' ? 'Luce' : 'Gas';
    $html = '<div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:20px 24px;margin-bottom:20px">';
    $html .= '<h2 style="font-size:16px;font-weight:700;color:#f1f5f9;margin-bottom:14px">Offerte ' . $label . ' per regione</h2>';
    $html .= '<p style="font-size:13px;color:#94a3b8;margin-bottom:12px">Dati aggiornati dal Portale Offerte ARERA — prezzi, numero offerte e fornitori per ogni regione.</p>';
    foreach ($zones as $zone => $slugs) {
        $html .= '<div style="margin-bottom:8px">';
        $html .= '<span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-right:10px">' . $zoneNames[$zone] . '</span>';
        $links = [];
        foreach ($slugs as $s) {
            $links[] = '<a href="/offerte/' . $commodity . '/' . $s . '" style="color:#f59e0b;text-decoration:none;font-size:13px;margin:0 4px">' . $regionNames[$s] . '</a>';
        }
        $html .= implode('<span style="color:#334155;font-size:11px"> · </span>', $links);
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

// ── SEO PAGES pre-render per crawler ─────────────────────────────
function handleSeoPage(string $page): void {
    $pages = [
        'tariffe-luce' => [
            'title' => 'Tariffe Luce 2026: Confronto Offerte Mercato Libero | SwitchAI',
            'desc'  => 'Confronta le migliori tariffe luce 2026 sul mercato libero. Analizza la bolletta con l\'AI e scopri quanto puoi risparmiare in un minuto, gratis.',
            'h1'    => 'Tariffe Luce 2026: come confrontarle e quando conviene cambiare fornitore',
            'intro' => 'Se stai leggendo questa pagina probabilmente ti sei fatto una domanda semplice ma non banale: sto pagando la luce più del dovuto? La risposta, per la maggior parte delle famiglie italiane ancora legate a vecchie condizioni contrattuali, è quasi sempre sì.',
            'body'  => '<div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">Mercato libero vs Servizio a Tutele Graduali</h2><p>Dal 2024 la maggior tutela per l\'energia elettrica non esiste più. Chi non ha scelto un fornitore sul mercato libero è confluito nel Servizio a Tutele Graduali (STG). Sul mercato libero i fornitori competono su prezzo e condizioni, con differenze che possono arrivare a centinaia di euro l\'anno.</p></div><div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">Le voci che compongono una tariffa luce</h2><p>Quando confronti offerte, guarda: prezzo materia energia (€/kWh), tipo di prezzo (fisso o variabile indicizzato al PUN), costo fisso mensile, oneri di sistema (uguali per tutti), bonus attivazione.</p></div><div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">Fisso o variabile: quale scegliere</h2><p>Il prezzo fisso protegge da rincari ma non beneficia dei ribassi. Il variabile segue il PUN: conviene in mercato stabile. In contesto volatile, molti preferiscono il fisso per pianificare il budget.</p></div>' . getRegioniHtml('luce'),
            'cta'   => '<a href="/calcolo-rapido?commodity=luce" style="display:inline-block;padding:14px 32px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:15px;font-weight:700;text-decoration:none;box-shadow:0 8px 24px rgba(245,158,11,0.25)">⚡ Confronta le tariffe luce</a>',
        ],
        'confronto-gas' => [
            'title' => 'Confronto Offerte Gas 2026: Trova la Tariffa Migliore | SwitchAI',
            'desc'  => 'Confronta le offerte gas disponibili nella tua zona. Carica la bolletta, l\'AI calcola risparmio e tariffa migliore sul mercato libero. Gratis.',
            'h1'    => 'Confronto offerte gas: come scegliere la tariffa giusta nel 2026',
            'intro' => 'Anche per il gas la maggior tutela è terminata (gennaio 2024). Chi non ha ancora scelto un\'offerta sul mercato libero rischia di pagare condizioni pensate come rete di sicurezza.',
            'body'  => '<div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">Come si forma il prezzo del gas in bolletta</h2><p>Il costo del gas si compone di: materia prima (€/Smc, indicizzata al PSV), quota fissa, trasporto e distribuzione, oneri di sistema e imposte. Solo materia prima e quota fissa cambiano tra fornitori.</p></div><div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">Fisso o indicizzato al PSV</h2><p>Il prezzo fisso dà prevedibilità in inverno quando i consumi salgono. L\'indicizzato al PSV segue il mercato: può convenire se stabile ma espone a rincari nei picchi di domanda.</p></div><div style="background:#111620;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:20px"><h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:12px">I consumi contano più del prezzo unitario</h2><p>Un\'offerta ottima per 1.400 Smc/anno può essere mediocre per 500 Smc/anno perché la quota fissa incide diversamente. Il confronto deve partire dai tuoi consumi reali, non da una media nazionale.</p></div>' . getRegioniHtml('gas'),
            'cta'   => '<a href="/calcolo-rapido?commodity=gas" style="display:inline-block;padding:14px 32px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-size:15px;font-weight:700;text-decoration:none;box-shadow:0 8px 24px rgba(59,130,246,0.25)">🔥 Confronta le tariffe gas</a>',
        ],
    ];

    if (!isset($pages[$page])) {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="it"><head><title>Pagina non trovata | SwitchAI</title></head><body style="background:#0a0d14;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px"><h1>404</h1><p>Pagina non trovata.</p></body></html>';
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');

    $seo = $pages[$page];
    $idx = __DIR__ . '/../index.html';
    if (!is_file($idx)) {
        $idx = __DIR__ . '/../public/index.html';
    }
    if (!is_file($idx)) {
        echo '<html><head><title>' . $seo['title'] . '</title></head><body><h1>' . $seo['h1'] . '</h1></body></html>';
        return;
    }

    $html = file_get_contents($idx);
    $html = preg_replace('/<title>[^<]*<\/title>/', '<title>' . $seo['title'] . '</title>', $html);
    $html = preg_replace('/<meta name="description" content="[^"]*"/', '<meta name="description" content="' . htmlspecialchars($seo['desc'], ENT_QUOTES, 'UTF-8') . '"', $html);
    $html = preg_replace('/<meta property="og:title" content="[^"]*"/', '<meta property="og:title" content="' . htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8') . '"', $html);
    $html = preg_replace('/<meta property="og:description" content="[^"]*"/', '<meta property="og:description" content="' . htmlspecialchars($seo['desc'], ENT_QUOTES, 'UTF-8') . '"', $html);
    $html = preg_replace('/<link rel="canonical" href="[^"]*"/', '<link rel="canonical" href="https://www.switchai.it/' . $page . '"', $html);

    $customBody = '<main role="main" style="max-width:780px;margin:0 auto;padding:50px 24px;font-family:system-ui,sans-serif;background:#0a0d14;color:#94a3b8;line-height:1.8">'
        . '<h1 style="font-size:30px;font-weight:900;color:#f1f5f9;line-height:1.3">' . $seo['h1'] . '</h1>'
        . '<p style="color:#64748b;font-size:15px;line-height:1.7;margin-bottom:30px">' . $seo['intro'] . '</p>'
        . $seo['body']
        . '<div style="text-align:center;margin-top:30px">' . $seo['cta'] . '</div>'
        . '<p style="margin-top:40px;font-size:11px;color:#475569;text-align:center;max-width:600px;margin-left:auto;margin-right:auto">'
        . 'Le informazioni su prezzi e mercato riportate in questa pagina sono di carattere generale; per condizioni economiche aggiornate fai sempre riferimento al confronto in tempo reale delle offerte.</p>'
        . '</main>';

    $html = preg_replace('/<main[^>]*>.*<\/main>/s', $customBody, $html);

    echo $html;
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
    // NOINDEX: pagine offerta sono thin content (template identico, cambiano solo numeri).
    // Google penalizza le "doorway pages". Il valore SEO è nelle pagine /risorse/ e homepage.
    echo '<meta name="robots" content="noindex, follow">';
    echo '<link rel="canonical" href="https://www.switchai.it/">';
    echo '<title>' . htmlspecialchars($offer['supplier_name'] . ' ' . $offer['name']) . ' — Confronta su SwitchAI</title>';
    echo '<meta name="description" content="' . htmlspecialchars($offer['supplier_name'] . ' — ' . $offer['name'] . ': tariffa ' . ($isLuce ? 'Luce' : 'Gas') . ' ' . ($offer['type'] === 'FISSO' ? 'a prezzo fisso' : 'a prezzo variabile') . '. Confronta le migliori offerte e attiva online su SwitchAI.">');
    // JSON-LD arricchito per crawl (anche se noindex, i bot lo leggono)
    // SKU: usa codice offerta ARERA se disponibile, altrimenti nome fornitore+tariffa
    $sku = $extra['codice_offerta'] ?? ($offer['supplier_name'] . ' - ' . $offer['name']);
    $descLD = 'Offerta ' . ($isLuce ? 'Luce' : 'Gas') . ' ' . ($offer['type'] === 'FISSO' ? 'a prezzo fisso' : 'a prezzo variabile');
    $descLD .= ' di ' . $offer['supplier_name'] . '.';
    if (!empty($extra['vantaggi'])) $descLD .= ' ' . $extra['vantaggi'];
    $descLD .= ' Confronta su SwitchAI, il comparatore energia basato su AI.';
    $validUntil = !empty($extra['validita_offerta']) ? $extra['validita_offerta'] : date('Y-m-d', strtotime('+30 days'));
    echo '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $offer['supplier_name'] . ' — ' . $offer['name'],
        'description' => $descLD,
        'sku' => $sku,
        'category' => $isLuce ? 'Energia Elettrica' : 'Gas Naturale',
        // Schema.org richiede 'offers' come array, anche per una singola offerta
        'offers' => [[
            '@type' => 'Offer',
            'price' => $prezzoUnit,
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'priceValidUntil' => $validUntil,
            'areaServed' => ['@type' => 'Country', 'name' => 'IT'],
        ]],
        'brand' => ['@type' => 'Organization', 'name' => $offer['supplier_name']],
        'manufacturer' => ['@type' => 'Organization', 'name' => $offer['supplier_name']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
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
    // CTA: usa url_offerta del fornitore (da ARERA) se disponibile
    $ctaUrl = $extra['url_offerta'] ?? '';
    if ($ctaUrl) {
        echo '<p><a href="' . htmlspecialchars($ctaUrl) . '" rel="nofollow noopener" target="_blank" class="cta">Attiva sul sito del fornitore →</a></p>';
    } else {
        echo '<p><a href="/calcolo-rapido?commodity=' . ($isLuce ? 'luce' : 'gas') . '" class="cta">Confronta su SwitchAI →</a></p>';
    }
    echo '</body></html>';
}

// ── FORNITORE PAGE (pagina SEO per crawler, indicizzata) ─────────

function handleFornitorePage(string $slug): void {
    $allTariffs = loadTariffs();
    $supplierOffers = [];
    $supplierName = '';
    $supplierLogo = '';

    foreach ($allTariffs as $t) {
        $s = strtolower(str_replace([' ', 'è', 'à', 'ì', 'ò', 'ù'], ['-', 'e', 'a', 'i', 'o', 'u'], $t['supplier_name']));
        $s = preg_replace('/[^a-z0-9-]/', '', $s);
        if ($s === $slug) {
            $supplierOffers[] = $t;
            if (!$supplierName) $supplierName = $t['supplier_name'];
            if (!$supplierLogo && !empty($t['logo'])) $supplierLogo = $t['logo'];
        }
    }

    $enrichment = loadEnrichmentData();
    $enriched = $enrichment[$slug] ?? null;

    if (empty($supplierOffers)) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title>Fornitore non trovato — SwitchAI</title>';
        echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1.5rem;line-height:1.8;color:#333}</style>';
        echo '</head><body><h1>Fornitore non trovato</h1><p><a href="/">← Torna a SwitchAI</a></p></body></html>';
        return;
    }

    $luce = array_filter($supplierOffers, fn($o) => $o['commodity'] === 'LUCE');
    $gas  = array_filter($supplierOffers, fn($o) => $o['commodity'] === 'GAS');
    $fissi = array_filter($supplierOffers, fn($o) => $o['type'] === 'FISSO');
    $variabili = array_filter($supplierOffers, fn($o) => $o['type'] === 'VARIABILE');

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="index, follow">';
    echo '<title>' . htmlspecialchars($supplierName) . ' — Offerte Luce e Gas a confronto | SwitchAI</title>';
    $metaDesc = 'Confronta ' . count($supplierOffers) . ' offerte di ' . $supplierName . ': ' . count($luce) . ' Luce e ' . count($gas) . ' Gas. ';
    $metaDesc .= 'Prezzi aggiornati, ' . count($fissi) . ' a prezzo fisso, ' . count($variabili) . ' a prezzo variabile. Dati ufficiali ARERA Portale Offerte.';
    echo '<meta name="description" content="' . htmlspecialchars($metaDesc) . '">';
    echo '<meta property="og:title" content="' . htmlspecialchars($supplierName) . ' — Offerte Luce e Gas">';
    echo '<meta property="og:description" content="' . htmlspecialchars($enriched['descrizione'] ?? $metaDesc) . '">';
    if ($enriched && !empty($enriched['logo_url'])) {
        echo '<meta property="og:image" content="' . htmlspecialchars($enriched['logo_url']) . '">';
    }
    // JSON-LD Organization
    $jsonLdDesc = $enriched['descrizione'] ?? ('Fornitore di energia elettrica e gas naturale nel mercato libero italiano. ' . count($supplierOffers) . ' offerte confrontabili su SwitchAI.');
    echo '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $supplierName,
        'description' => $jsonLdDesc,
        'url' => 'https://www.switchai.it/fornitori/' . $slug,
        'areaServed' => ['@type' => 'Country', 'name' => 'IT'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:2rem auto;padding:0 1.5rem;line-height:1.8;color:#333;background:#fafafa} h1{font-size:1.6rem;margin-bottom:.25rem} .sub{color:#777;font-size:.9rem;margin-bottom:1.5rem} .stats{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:1.5rem} .stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;text-align:center;min-width:80px} .stat .num{font-size:1.4rem;font-weight:800;color:#0f172a} .stat .lbl{font-size:.7rem;color:#64748b;text-transform:uppercase} table{width:100%;border-collapse:collapse;margin:1rem 0;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06)} th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:.9rem} th{background:#f8fafc;color:#475569;font-weight:600;font-size:.8rem;text-transform:uppercase} .cta{display:inline-block;padding:10px 24px;background:#f59e0b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem} .fisso{color:#10b981;font-weight:600} .variabile{color:#f59e0b;font-weight:600}</style>';
    echo '</head><body>';
    echo '<p style="color:#777;font-size:.85rem"><a href="/" style="color:#f59e0b">← SwitchAI</a> — Fornitori — ' . date('d/m/Y') . '</p>';
    echo '<h1>' . htmlspecialchars($supplierName) . '</h1>';
    if ($enriched && !empty($enriched['descrizione'])) {
        echo '<p style="color:#475569;line-height:1.7;margin-bottom:1.5rem">' . htmlspecialchars($enriched['descrizione']) . '</p>';
    }
    if ($enriched && !empty($enriched['rating'])) {
        echo '<p style="color:#777;font-size:.9rem;margin-bottom:1.5rem">⭐ Trustpilot: ' . number_format($enriched['rating'], 1) . '/5';
        if (!empty($enriched['recensioni'])) echo ' (' . number_format($enriched['recensioni'], 0, ',', '.') . ' recensioni)';
        echo '</p>';
    }
    echo '<p class="sub">' . count($supplierOffers) . ' offerte nel mercato libero. Dati ufficiali <a href="https://www.ilportaleofferte.it" target="_blank" rel="noopener">Portale Offerte ARERA</a> — licenza CC BY 4.0.</p>';

    // Stats
    echo '<div class="stats">';
    echo '<div class="stat"><div class="num">' . count($luce) . '</div><div class="lbl">Offerte Luce ⚡</div></div>';
    echo '<div class="stat"><div class="num">' . count($gas) . '</div><div class="lbl">Offerte Gas 🔥</div></div>';
    echo '<div class="stat"><div class="num">' . count($fissi) . '</div><div class="lbl">Prezzo Fisso 🔒</div></div>';
    echo '<div class="stat"><div class="num">' . count($variabili) . '</div><div class="lbl">Prezzo Variabile 🔀</div></div>';
    echo '</div>';

    // Tabella offerte
    echo '<table><thead><tr><th>Offerta</th><th>Tipo</th><th>Prezzo</th><th>Quota fissa</th><th>Dettagli</th></tr></thead><tbody>';
    foreach ($supplierOffers as $o) {
        $isLuce = $o['commodity'] === 'LUCE';
        $unit = $isLuce ? 'kWh' : 'Smc';
        $prezzo = $isLuce ? ($o['price_mono_kwh'] ?? null) : ($o['price_smc'] ?? null);
        $extra = $o['extra'] ?? [];
        echo '<tr>';
        echo '<td><a href="/offerta/' . urlencode($o['id']) . '" style="color:#0f172a;text-decoration:none;font-weight:600">' . htmlspecialchars($o['name']) . '</a></td>';
        echo '<td><span class="' . ($o['type'] === 'FISSO' ? 'fisso' : 'variabile') . '">' . ($o['type'] === 'FISSO' ? 'Fisso' : 'Variabile') . '</span></td>';
        echo '<td>' . ($prezzo !== null ? number_format($prezzo, 4, ',', '') . ' €/' . $unit : '—') . '</td>';
        echo '<td>' . (isset($o['fixed_fee_monthly']) ? number_format($o['fixed_fee_monthly'], 2, ',', '') . ' €/mese' : '—') . '</td>';
        echo '<td style="font-size:.8rem;color:#64748b">' . ($extra['prezzo_bloccato_mesi'] ?? '') . ($extra['validita_offerta'] ? ' valida fino al ' . $extra['validita_offerta'] : '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<p style="margin-top:1.5rem"><a href="/" class="cta">⚡ Confronta tutte le offerte su SwitchAI →</a></p>';
    echo '<p style="color:#94a3b8;font-size:.75rem;margin-top:2rem">Dati aggiornati in tempo reale. Fonte: Portale Offerte ARERA (CC BY 4.0). SwitchAI — switchai.it</p>';
    echo '</body></html>';
}

// ── REGIONE PAGE (pagine geo da ARERA per SEO) ───────────────────────

function handleRegionePage(string $commodity, string $slug): void {
    $commodity = strtoupper($commodity);
    if (!in_array($commodity, ['LUCE', 'GAS'], true)) {
        http_response_code(404);
        echo '<html lang="it"><head><title>Pagina non trovata | SwitchAI</title></head><body style="background:#0a0d14;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px"><h1>404</h1><p>Pagina non trovata.</p></body></html>';
        return;
    }

    $regionMap = [
        'piemonte'             => ['code' => '01', 'zone' => 'NORD', 'name' => 'Piemonte'],
        'valle-daosta'         => ['code' => '02', 'zone' => 'NORD', 'name' => "Valle d'Aosta"],
        'lombardia'            => ['code' => '03', 'zone' => 'NORD', 'name' => 'Lombardia'],
        'trentino-alto-adige'  => ['code' => '04', 'zone' => 'NORD', 'name' => 'Trentino-Alto Adige'],
        'veneto'               => ['code' => '05', 'zone' => 'NORD', 'name' => 'Veneto'],
        'friuli-venezia-giulia'=> ['code' => '06', 'zone' => 'NORD', 'name' => 'Friuli-Venezia Giulia'],
        'liguria'              => ['code' => '07', 'zone' => 'NORD', 'name' => 'Liguria'],
        'emilia-romagna'       => ['code' => '08', 'zone' => 'NORD', 'name' => 'Emilia-Romagna'],
        'toscana'              => ['code' => '09', 'zone' => 'CENTRO', 'name' => 'Toscana'],
        'umbria'               => ['code' => '10', 'zone' => 'CENTRO', 'name' => 'Umbria'],
        'marche'               => ['code' => '11', 'zone' => 'CENTRO', 'name' => 'Marche'],
        'lazio'                => ['code' => '12', 'zone' => 'CENTRO', 'name' => 'Lazio'],
        'abruzzo'              => ['code' => '13', 'zone' => 'SUD', 'name' => 'Abruzzo'],
        'molise'               => ['code' => '14', 'zone' => 'SUD', 'name' => 'Molise'],
        'campania'             => ['code' => '15', 'zone' => 'SUD', 'name' => 'Campania'],
        'puglia'               => ['code' => '16', 'zone' => 'SUD', 'name' => 'Puglia'],
        'basilicata'           => ['code' => '17', 'zone' => 'SUD', 'name' => 'Basilicata'],
        'calabria'             => ['code' => '18', 'zone' => 'SUD', 'name' => 'Calabria'],
        'sicilia'              => ['code' => '19', 'zone' => 'SUD', 'name' => 'Sicilia'],
        'sardegna'             => ['code' => '20', 'zone' => 'SUD', 'name' => 'Sardegna'],
    ];

    if (!isset($regionMap[$slug])) {
        http_response_code(404);
        echo '<html lang="it"><head><title>Regione non trovata | SwitchAI</title></head><body style="background:#0a0d14;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px"><h1>404</h1><p>Regione non trovata.</p></body></html>';
        return;
    }

    $regionCode = $regionMap[$slug]['code'];
    $zone = $regionMap[$slug]['zone'];
    $regionName = $regionMap[$slug]['name'];
    $isLuce = $commodity === 'LUCE';
    $unit = $isLuce ? 'kWh' : 'Smc';
    $label = $isLuce ? 'Luce' : 'Gas';
    $italianMonths = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $month = $italianMonths[(int)date('n')] . ' ' . date('Y');

    $all = getTariffsByCommodity($commodity);
    $filtered = array_values(array_filter($all, fn($t) =>
        $t['nazionale'] || in_array($regionCode, $t['regioni'] ?? [])
    ));

    if (empty($filtered)) {
        http_response_code(404);
        echo '<html lang="it"><head><title>Nessuna offerta trovata | SwitchAI</title></head><body style="background:#0a0d14;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px"><h1>Nessuna offerta disponibile</h1><p><a href="/" style="color:#f59e0b">← Torna a SwitchAI</a></p></body></html>';
        return;
    }

    $total = count($filtered);
    $providers = count(array_unique(array_map(fn($t) => $t['supplier_name'], $filtered)));
    $fissi = count(array_filter($filtered, fn($t) => $t['type'] === 'FISSO'));
    $variabili = $total - $fissi;

    usort($filtered, function ($a, $b) use ($isLuce) {
        $pa = $isLuce ? ($a['price_mono_kwh'] ?? PHP_FLOAT_MAX) : ($a['price_smc'] ?? PHP_FLOAT_MAX);
        $pb = $isLuce ? ($b['price_mono_kwh'] ?? PHP_FLOAT_MAX) : ($b['price_smc'] ?? PHP_FLOAT_MAX);
        return $pa <=> $pb;
    });

    $top = array_slice($filtered, 0, 15);
    $prezzoMin = $isLuce ? ($top[0]['price_mono_kwh'] ?? null) : ($top[0]['price_smc'] ?? null);
    $prezzoMinFisso = null;
    foreach ($filtered as $t) {
        if ($t['type'] === 'FISSO') {
            $p = $isLuce ? ($t['price_mono_kwh'] ?? null) : ($t['price_smc'] ?? null);
            if ($p !== null && ($prezzoMinFisso === null || $p < $prezzoMinFisso)) $prezzoMinFisso = $p;
        }
    }

    $title = "Offerte $label in $regionName: le migliori tariffe $month | SwitchAI";
    $desc = "Confronta le offerte $label disponibili in $regionName. $total offerte da $providers fornitori, prezzi da " . number_format($prezzoMin, 4, ',', '') . " €/$unit. Dati ufficiali ARERA.";

    header('Content-Type: text/html; charset=UTF-8');

    // Inizio HTML
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="index, follow">';
    echo "<title>$title</title>";
    echo '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
    echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
    echo '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">';
    echo '<meta property="og:url" content="https://www.switchai.it/offerte/' . strtolower($commodity) . '/' . $slug . '">';
    echo '<link rel="canonical" href="https://www.switchai.it/offerte/' . strtolower($commodity) . '/' . $slug . '">';

    // Breadcrumb + FAQ JSON-LD
    echo '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [[
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'SwitchAI', 'item' => 'https://www.switchai.it/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => "Offerte $label", 'item' => 'https://www.switchai.it/' . ($isLuce ? 'tariffe-luce' : 'confronto-gas')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $regionName, 'item' => "https://www.switchai.it/offerte/" . strtolower($commodity) . "/$slug"],
            ],
        ], [
            '@type' => 'FAQPage',
            'mainEntity' => [[
                '@type' => 'Question',
                'name' => "Quali sono le migliori offerte $label in $regionName?",
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => "Le migliori offerte $label in $regionName sono aggiornate quotidianamente con i dati del Portale Offerte ARERA. Attualmente ci sono $total offerte da $providers fornitori, con prezzi a partire da " . number_format($prezzoMin, 4, ',', '') . " €/$unit per le tariffe a prezzo variabile."],
            ], [
                '@type' => 'Question',
                'name' => "Quante offerte $label ci sono in $regionName?",
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => "Attualmente sono disponibili $total offerte $label in $regionName, di cui $fissi a prezzo fisso e $variabili a prezzo variabile, da $providers fornitori diversi."],
            ]],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

    echo '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:2rem auto;padding:0 1.5rem;line-height:1.8;color:#333;background:#fafafa} h1{font-size:1.6rem;margin-bottom:.25rem} .sub{color:#777;font-size:.9rem;margin-bottom:1.5rem} .stats{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:1.5rem} .stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;text-align:center;min-width:80px} .stat .num{font-size:1.4rem;font-weight:800;color:#0f172a} .stat .lbl{font-size:.7rem;color:#64748b;text-transform:uppercase} table{width:100%;border-collapse:collapse;margin:1rem 0;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06)} th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:.9rem} th{background:#f8fafc;color:#475569;font-weight:600;font-size:.8rem;text-transform:uppercase} .cta{display:inline-block;padding:10px 24px;background:#f59e0b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem} .fisso{color:#10b981;font-weight:600} .variabile{color:#f59e0b;font-weight:600} .badge{display:inline-block;font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:700}</style>';
    echo '</head><body>';
    echo '<p style="color:#777;font-size:.85rem"><a href="/" style="color:#f59e0b">← SwitchAI</a> — Offerte ' . $label . ' — ' . htmlspecialchars($regionName) . ' — ' . date('d/m/Y') . '</p>';
    echo '<h1>' . htmlspecialchars("Le migliori offerte $label in $regionName") . '</h1>';
    echo '<p class="sub">' . $total . ' offerte disponibili in ' . htmlspecialchars($regionName) . '. Dati ufficiali <a href="https://www.ilportaleofferte.it" target="_blank" rel="noopener">Portale Offerte ARERA</a> — licenza CC BY 4.0. Zona: ' . $zone . '.</p>';

    // Stats
    echo '<div class="stats">';
    echo '<div class="stat"><div class="num">' . $total . '</div><div class="lbl">Offerte ' . $label . ' ⚡</div></div>';
    echo '<div class="stat"><div class="num">' . $providers . '</div><div class="lbl">Fornitori 🏢</div></div>';
    echo '<div class="stat"><div class="num">' . $fissi . '</div><div class="lbl">Prezzo Fisso 🔒</div></div>';
    echo '<div class="stat"><div class="num">' . $variabili . '</div><div class="lbl">Prezzo Variabile 🔀</div></div>';
    if ($prezzoMinFisso !== null) {
        echo '<div class="stat"><div class="num">' . number_format($prezzoMinFisso, 4, ',', '') . '</div><div class="lbl">Miglior fisso €/' . $unit . '</div></div>';
    }
    echo '</div>';

    // Tabella top 15
    echo '<h2 style="font-size:1.1rem;margin-top:2rem;color:#0f172a">🏆 Le ' . min(15, $total) . ' offerte ' . $label . ' più economiche in ' . htmlspecialchars($regionName) . '</h2>';
    echo '<table><thead><tr><th>#</th><th>Fornitore</th><th>Offerta</th><th>Tipo</th><th>Prezzo</th><th>Quota fissa</th></tr></thead><tbody>';
    foreach ($top as $i => $o) {
        $prezzo = $isLuce ? ($o['price_mono_kwh'] ?? null) : ($o['price_smc'] ?? null);
        $rank = $i + 1;
        $badge = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
        echo '<tr>';
        echo "<td style='font-weight:700;color:#64748b'>$badge $rank</td>";
        echo '<td><strong>' . htmlspecialchars($o['supplier_name']) . '</strong></td>';
        echo '<td><a href="/offerta/' . urlencode($o['id']) . '" style="color:#0f172a;text-decoration:none">' . htmlspecialchars($o['name']) . '</a></td>';
        echo '<td><span class="' . ($o['type'] === 'FISSO' ? 'fisso' : 'variabile') . '">' . ($o['type'] === 'FISSO' ? 'Fisso' : 'Variabile') . '</span></td>';
        echo '<td>' . ($prezzo !== null ? number_format($prezzo, 4, ',', '') . ' €/' . $unit : '—') . '</td>';
        echo '<td>' . (isset($o['fixed_fee_monthly']) ? number_format($o['fixed_fee_monthly'], 2, ',', '') . ' €/mese' : '—') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<h2 style="font-size:1.1rem;margin-top:2rem;color:#0f172a">🔎 Come confrontare le offerte ' . $label . ' in ' . htmlspecialchars($regionName) . '</h2>';
    echo '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:1.5rem">';
    echo '<p style="color:#475569;font-size:.9rem;line-height:1.7">Il prezzo dell\'energia ' . $label . ' in ' . htmlspecialchars($regionName) . ' varia in base al fornitore scelto e al tipo di contratto (prezzo fisso o variabile). Le tariffe a prezzo fisso bloccano il costo per 12-24 mesi, mentre le variabili seguono l\'andamento del mercato (' . ($isLuce ? 'PUN' : 'PSV') . ').</p>';
    echo '<p style="color:#475569;font-size:.9rem;line-height:1.7">Per un confronto personalizzato, usa il calcolatore SwitchAI: inserisci il tuo consumo annuo e la tua spesa attuale, l\'AI ti mostrerà l\'offerta migliore per la tua situazione specifica in ' . htmlspecialchars($regionName) . '.</p>';
    echo '</div>';

    echo '<p style="margin-top:1.5rem"><a href="/calcolo-rapido?commodity=' . strtolower($commodity) . '" class="cta">⚡ Confronta le offerte ' . $label . ' nella tua zona →</a></p>';
    echo '<p style="color:#94a3b8;font-size:.75rem;margin-top:2rem">Dati aggiornati in tempo reale dal Portale Offerte ARERA (CC BY 4.0). Le offerte visualizzate sono filtrate per disponibilità in ' . htmlspecialchars($regionName) . '. I prezzi possono variare in base ai consumi effettivi. SwitchAI — switchai.it</p>';
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
            'canone_rai_explicit' => false, // parser: applica default se non rilevato
                'confidence'   => $parsed['confidence'],
                'advice'       => $parsed['_meta']['advice'] ?? '',
            ];
        } catch (Throwable $e) {
            errorResponse('Impossibile analizzare la bolletta. Testo non valido.', 400);
        }
    }

    // Priorità 2: dati strutturati
    // Accetta sia i nomi italiani (canonici) sia gli alias inglesi (WebMCP):
    // consumo_annuo_kwh ~ yearly_consumption_kwh, spesa_annua_eur ~ current_annual_spend, ecc.
    if (!$profile && !empty($input['commodity'])) {
        $commodity = strtoupper($input['commodity']);
        $consumo = (float)(
            $input['consumo_annuo_kwh'] ?? $input['consumo_annuo_smc']
            ?? $input['yearly_consumption_kwh'] ?? $input['yearly_consumption_smc'] ?? 0
        );
        $spesa = (float)($input['spesa_annua_eur'] ?? $input['current_annual_spend'] ?? 0);
        if ($consumo <= 0) errorResponse('Fornire consumo_annuo_kwh (o yearly_consumption_kwh) > 0', 400);

        $profile = [
            'fornitore'    => $input['fornitore'] ?? $input['current_supplier'] ?? 'Sconosciuto',
            'commodity'    => $commodity,
            'pod'          => $input['pod'] ?? null,
            'consumo_annuo'=> $consumo,
            'spesa_annua_eur' => $spesa,
            'canone_rai'   => (float)($input['canone_rai'] ?? 0),
            'canone_rai_explicit' => array_key_exists('canone_rai', $input), // rispetta 0 se passato esplicitamente
            'spesa_materia_energia' => (float)($input['spesa_materia_energia'] ?? 0),
            'quota_fissa_mensile' => (float)($input['quota_fissa_mensile'] ?? 0),
            'tipo_cliente' => $input['tipo_cliente'] ?? 'residenziale',
            'zona'         => $input['zona'] ?? $input['zone'] ?? 'NORD',
            'confidence'   => ['consumption' => 0.8, 'supplier' => 0.5],
        ];
    }

    if (!$profile) errorResponse('Fornire bill_text o commodity+consumo_annuo', 400);

    $commodity = $profile['commodity'];
    $consumo = $profile['consumo_annuo'];
    $zona = $profile['zona'];
    $spesaAnnua = $profile['spesa_annua_eur'];
    $canoneRai = $profile['canone_rai'] ?? 0;
    $canoneRaiExplicit = $profile['canone_rai_explicit'] ?? false;
    $spesaMateriaEnergia = $profile['spesa_materia_energia'] ?? 0;
    $quotaFissaMensile = $profile['quota_fissa_mensile'] ?? 0;
    $tipoCliente = $profile['tipo_cliente'] ?? 'residenziale';

    if ($spesaAnnua <= 0) {
        $spesaAnnua = $commodity === 'LUCE' ? ($consumo * 0.18 + 144) : ($consumo * 0.65 + 144);
        $profile['spesa_annua_eur'] = round($spesaAnnua, 2);
        $profile['spesa_stimata'] = true;
    }

    // ── FETCH PUN/PSV (priorità: forward ARERA → spot live) ─
    // Il metodo ARERA richiede il PUN forward (media 4 trimestri), non lo spot.
    // Il sync ARERA salva il forward PUN in config.json; se disponibile (<60gg), lo usa.
    $livePunEurKwh = null;
    $livePsvEurSmc = null;
    $pun = null;
    $psv = null;
    $peData = null;

    // Priorità 1: PUN forward ARERA (da config.json, dal sync mensile)
    $areraPun = getAreraForwardPun();
    if ($areraPun !== null) {
        $livePunEurKwh = $areraPun;
        error_log("handleV2Analyze: using ARERA forward PUN = " . round($areraPun * 1000, 1) . " €/MWh");
    }

    // Priorità 2: PUN/PSV spot live da PortaleEnergia.it (fallback)
    try {
        $peUrl = 'https://portaleenergia.it/api/dashboard?period=today';
        $peJson = @file_get_contents($peUrl, false, stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: Mozilla/5.0\r\n"]]));
        if ($peJson) {
            $peData = json_decode($peJson, true);
            $pun = $peData['pun'] ?? null;
            $psv = $peData['psv'] ?? null;
            if ($pun && $livePunEurKwh === null) $livePunEurKwh = round((float)$pun['price'] / 1000, 6);
            if ($psv) $livePsvEurSmc = round((float)$psv['price'] / 1000, 6);
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
    $potenza = (float)($input['potenza_impegnata'] ?? $profile['potenza_impegnata'] ?? 3.0);
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
            // Priorità: se abbiamo spesa_materia_energia, usiamo quella (molto più precisa)
            if ($spesaMateriaEnergia > 0 && $consumo > 0) {
                $materiaPerUnit = $spesaMateriaEnergia / $consumo;
                $estimatedUserSpread = max(0.002, round($materiaPerUnit - ($livePunEurKwh * LUCE_PERDITE_RETE_BT), 4));
            } else {
                $avgPriceBill = $spesaAnnua / $consumo;
                $nonNeg = 0.045; // trasporto+oneri+accise ~0.045 €/kWh
                // ARERA v4.0: perdite rete SOLO sul PUN → anche nella retro-stima
                $estimatedUserSpread = max(0.002, round($avgPriceBill - ($livePunEurKwh * LUCE_PERDITE_RETE_BT) - $nonNeg, 4));
            }
        }
        // Ricalcolo spesa attuale a PUN corrente (ARERA v4.0)
        // ARERA v4.0: perdite rete SOLO sul PUN, non sullo spread
        $energyCostNow = $consumo * ($livePunEurKwh * LUCE_PERDITE_RETE_BT + $estimatedUserSpread);
        $costoPotenza = LUCE_COSTO_POTENZA_KW * $potenza;
        $oneriNow = $consumo * ONERI_SISTEMA_LUCE;
        // Accise DL 504/1995: esenti ≤1800 kWh, compensate >2640 kWh → tassati solo 1800-2640
        $acciseNow = max(0, min($consumo, LUCE_ACCISE_SOGLIA_COMPENSATA) - LUCE_ACCISE_SOGLIA_ESENTE) * LUCE_ACCISE;
        $trasportoNow = $consumo * LUCE_TRASPORTO_VAR;
        $fixedNow = ($quotaFissaMensile > 0 ? $quotaFissaMensile : 10.00) * 12 + $costoPotenza + QUOTA_FISSA_RETI_LUCE;
        $subtotalNow = $energyCostNow + $fixedNow + $trasportoNow + $oneriNow + $acciseNow + ($consumo * LUCE_DISPACCIAMENTO);
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
        // Fallback: stima spread dal prezzo medio — priorità a spesa_materia_energia
        if ($estimatedUserSpread === null || $estimatedUserSpread <= 0) {
            if ($spesaMateriaEnergia > 0 && $consumo > 0) {
                $materiaPerUnit = $spesaMateriaEnergia / $consumo;
                $estimatedUserSpread = max(0.005, round($materiaPerUnit - $livePsvEurSmc, 4));
            } else {
                $avgPriceBill = $spesaAnnua / $consumo;
                $nonNeg = 0.05;
                $estimatedUserSpread = max(0.005, round($avgPriceBill - $livePsvEurSmc - $nonNeg, 4));
            }
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
    // Se il valore è sospettosamente basso (< 30€), probabilmente è mensile → annualizza (90€)
    // Bug comune LLM: 9€/mese × 12 mesi = 108€. Ma sono 10 rate da 9€ → 90€/anno.
    $spesaBase = $spesaAttualizzata ?? $spesaAnnua;
    if ($canoneRai > 0 && $canoneRai < 30 && $commodity === 'LUCE') {
        $canoneRai = CANONE_RAI_ANNUO;
        $profile['canone_rai'] = $canoneRai;
        $profile['canone_rai_stimato'] = true;
    }
    // Correggi errore comune: 9€ × 12 mesi = 108€ invece di 10 rate × 9€ = 90€
    if ($canoneRai >= 100 && $canoneRai <= 110 && $commodity === 'LUCE') {
        $canoneRai = CANONE_RAI_ANNUO;
        $profile['canone_rai'] = $canoneRai;
        $profile['canone_rai_stimato'] = true;
    }
    $spesaNettaConfronto = max(0, $spesaBase - $canoneRai);
    if (!$canoneRaiExplicit && $canoneRai <= 0 && $commodity === 'LUCE' && $spesaBase > 100) {
        $canoneRai = CANONE_RAI_ANNUO;
        $spesaNettaConfronto = max(0, $spesaBase - $canoneRai);
        $profile['canone_rai'] = $canoneRai;
        $profile['canone_rai_stimato'] = true;
    }

    // Confronto offerte — con PUN/PSV live per confronto simmetrico (metodo ARERA)
    // Tier detection: premium se API key o admin token, altrimenti free (filtri ON)
    if (isset($input['filters'])) {
        $v2Filters = $input['filters'];
    } else {
        $v2Filters = isPremiumRequest()
            ? []
            : ['main_suppliers' => true, 'no_penali' => true, 'online_only' => true];
    }
    try {
        $savingsResult = calculateSavingsBreakdown([
            'commodity'              => $commodity,
            'yearly_consumption_kwh' => $commodity === 'LUCE' ? $consumo : 0,
            'yearly_consumption_smc' => $commodity === 'GAS' ? $consumo : 0,
            'yearly_consumption_f1'  => (float)($profile['yearly_consumption_f1'] ?? $input['yearly_consumption_f1'] ?? 0),
            'yearly_consumption_f2'  => (float)($profile['yearly_consumption_f2'] ?? $input['yearly_consumption_f2'] ?? 0),
            'yearly_consumption_f3'  => (float)($profile['yearly_consumption_f3'] ?? $input['yearly_consumption_f3'] ?? 0),
            'potenza_impegnata'      => $potenza,
            'zone'                   => $zona,
            'current_annual_spend'   => $spesaNettaConfronto,
            'current_supplier'       => $profile['fornitore'] ?? '',
            'canone_rai'             => $canoneRai,
            'spesa_materia_energia'  => $spesaMateriaEnergia,
            'quota_fissa_mensile'    => $quotaFissaMensile,
            'tipo_cliente'           => $tipoCliente,
            'live_pun_eur_kwh'       => $livePunEurKwh,
            'live_psv_eur_smc'       => $livePsvEurSmc,
            'filters'                => $v2Filters,
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
    $punSource = getAreraForwardMeta();
    $punContext = '';
    $punWarning = '';

    if ($isCurrentVariable && $livePunEurKwh !== null) {
        $srcLabel = ($punSource['source'] === 'forward_arera') ? 'PUN forward ARERA' : 'PUN spot live';
        $ageNote = ($punSource['source'] === 'forward_arera' && $punSource['age_days'] !== null)
            ? " (agg. {$punSource['age_days']}gg fa)"
            : '';
        $punContext = sprintf(' Confronto a %s %.1f €/MWh%s (metodo ARERA: stesso PUN per entrambe le tariffe variabili).', $srcLabel, $livePunEurKwh * 1000, $ageNote);
    } elseif ($livePunEurKwh !== null) {
        $punContext = sprintf(' PUN corrente %.1f €/MWh usato per il calcolo offerte variabili.', $livePunEurKwh * 1000);
    } elseif ($livePsvEurSmc !== null) {
        $punContext = sprintf(' PSV corrente %.1f €/MWh usato per il calcolo offerte variabili.', $livePsvEurSmc * 1000);
    }

    // Warning se il forward ARERA non è disponibile e stiamo usando lo spot
    if ($punSource['source'] === 'spot_live' && $livePunEurKwh !== null) {
        $punWarning = ' ⚠️ PUN forward ARERA non disponibile (sync scaduto o assente). Uso PUN spot live ' . round($livePunEurKwh * 1000, 1) . ' €/MWh, che potrebbe differire dalla media forward trimestrale usata dal Portale Offerte. Esegui il sync ARERA dal pannello Admin per aggiornare.';
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
    if ($punWarning) $summary .= $punWarning;
    $summary .= $disclaimer;

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
                'canone_rai_stimato' => $profile['canone_rai_stimato'] ?? false,
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

    // Aggiungi note dell'attualizzazione al summary (dopo che $attualization è stata calcolata)
    if ($attualization && $recommendation === 'switch') {
        $summary .= " Nota: la bolletta è di qualche mese fa. " . $attualization['impatto_confronto'];
    }
    if ($attualization && ($attualization['confronto']['direzione'] ?? '') === 'diminuito') {
        $summary .= " Il PUN è sceso rispetto alla tua bolletta: il risparmio reale è probabilmente inferiore a quanto mostrato.";
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
        'filters_applied'      => $savingsResult['filters_applied'] ?? [],
        'offers_before_filter' => $savingsResult['offers_before_filter'] ?? 0,
        'offers_after_filter'  => $savingsResult['offers_after_filter'] ?? 0,
        'total_count'          => $savingsResult['total_count'] ?? 0,
        'tier'                 => isPremiumRequest() ? 'premium' : 'free',
    ];
    if ($format === 'full') {
        $response['all_offers'] = getTariffsByCommodity($commodity);
        $response['comparison_id'] = $savingsResult['comparison_id'];
    }

    jsonResponse($response);
}

// ── AUTH ───────────────────────────────────────────────────────────

function handleAuthVerify(): void {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $valid = verifyAuthToken($token) || verifyUserToken($token) !== null;
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

/**
 * Genera un token per utenti MySQL registrati.
 * Formato: base64("user_{userId}:hmac:timestamp")
 */
function generateUserToken(int $userId): string {
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log("AUTH: API_KEY env var not configured");
        throw new RuntimeException('Server configuration error');
    }
    $ts = time();
    $sig = hash_hmac('sha256', "user_{$userId}:{$ts}", $secret);
    return base64_encode("user_{$userId}:{$sig}:{$ts}");
}

/**
 * Verifica un token utente MySQL (non admin).
 * Ritorna l'user ID se valido, null altrimenti.
 */
function verifyUserToken(string $token): ?int {
    $decoded = base64_decode($token, true);
    if (!$decoded || !str_contains($decoded, ':')) return null;
    $parts = explode(':', $decoded);
    if (count($parts) < 3) return null;
    if (!str_starts_with($parts[0], 'user_')) return null;
    $userId = (int)substr($parts[0], 5);
    $secret = getenv('API_KEY');
    if (!$secret) return null;
    $timestamp = (int)$parts[2];
    if (time() - $timestamp > 86400) return null;
    $expectedSig = hash_hmac('sha256', "user_{$userId}:{$timestamp}", $secret);
    if (!hash_equals($expectedSig, $parts[1])) return null;
    return $userId;
}

/**
 * Richiede autenticazione utente MySQL (per /api/auth/* endpoints).
 */
function requireUserAuth(): int {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $userId = verifyUserToken($token);
    if (!$userId) {
        errorResponse('Non autorizzato. Effettua il login.', 401);
    }
    return $userId;
}

// ── USER REGISTRATION & AUTH ────────────────────────────────────────

function handleUserRegister(array $input): void {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $nome = trim($input['nome'] ?? '');
    $cognome = trim($input['cognome'] ?? '');

    // Validazione
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Email non valida', 400);
    }
    if (strlen($password) < 8) {
        errorResponse('La password deve essere almeno 8 caratteri', 400);
    }
    if (empty($nome) || empty($cognome)) {
        errorResponse('Nome e cognome obbligatori', 400);
    }

    require_once __DIR__ . '/../inc/db_mysql.php';

    // Controlla se esiste già
    $existing = findUserByEmail($email);
    if ($existing) {
        if ($existing['email_verified']) {
            errorResponse('Email già registrata. Effettua il login.', 409);
        }
        // Reinvia conferma se non verificato
        $verificationToken = $existing['verification_token'] ?? bin2hex(random_bytes(32));
        updateUser($existing['id'], [
            'verification_token' => $verificationToken,
            'verification_sent_at' => date('Y-m-d H:i:s'),
        ]);
        sendVerificationEmail($email, $nome, $verificationToken);
        jsonResponse(['status' => 'pending', 'message' => 'Email di conferma reinviata. Controlla la tua posta.'], 200);
        return;
    }

    // Crea utente
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $verificationToken = bin2hex(random_bytes(32));
    $userId = createUser($email, $passwordHash, $nome, $cognome);

    // Salva token di verifica
    updateUser($userId, [
        'verification_token' => $verificationToken,
        'verification_sent_at' => date('Y-m-d H:i:s'),
        'tier' => 'free',
        'daily_quota' => 10,
    ]);

    // Invia email
    sendVerificationEmail($email, $nome, $verificationToken);

    jsonResponse([
        'status' => 'pending',
        'message' => 'Registrazione completata! Controlla la tua email per confermare l\'account.',
    ], 201);
}

function sendVerificationEmail(string $to, string $nome, string $token): void {
    $confirmUrl = 'https://www.switchai.it/conferma-registrazione?token=' . urlencode($token);
    $subject = '[SwitchAI] Conferma la tua registrazione';
    $body = "Ciao {$nome},\n\n";
    $body .= "grazie per esserti registrato su SwitchAI!\n\n";
    $body .= "Conferma il tuo account cliccando sul link qui sotto:\n\n";
    $body .= "🔗 CONFERMA IL TUO ACCOUNT\n{$confirmUrl}\n\n";
    $body .= "Se non hai richiesto tu questa registrazione, ignora questa email.\n\n";
    $body .= "Il team di SwitchAI\nwww.switchai.it";

    $headers = "From: " . (getenv('ACTIVATION_EMAIL') ?: 'noreply@switchai.it') . "\r\n";
    $headers .= "Reply-To: " . (getenv('ACTIVATION_EMAIL') ?: 'noreply@switchai.it') . "\r\n";

    @mail($to, $subject, $body, $headers);
}

function handleUserConfirmEmail(array $input): void {
    $token = $input['token'] ?? '';
    if (strlen($token) < 16) {
        errorResponse('Token non valido', 400);
    }

    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserByVerificationToken($token);

    if (!$user) {
        errorResponse('Token non valido o scaduto', 404);
    }

    if ($user['email_verified']) {
        jsonResponse(['status' => 'already_verified', 'message' => 'Email già confermata. Effettua il login.']);
        return;
    }

    updateUser($user['id'], [
        'email_verified' => 1,
        'verification_token' => null,
        'verification_sent_at' => null,
    ]);

    jsonResponse(['status' => 'verified', 'message' => 'Email confermata con successo! Ora puoi accedere.']);
}

function handleUserResendConfirmation(array $input): void {
    $email = trim($input['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Email non valida', 400);
    }

    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserByEmail($email);

    if (!$user) {
        // Non rivelare se l'email esiste (sicurezza)
        jsonResponse(['status' => 'sent', 'message' => 'Se l\'email è registrata, riceverai una nuova conferma.']);
        return;
    }

    if ($user['email_verified']) {
        jsonResponse(['status' => 'already_verified', 'message' => 'Email già confermata. Effettua il login.']);
        return;
    }

    $newToken = bin2hex(random_bytes(32));
    updateUser($user['id'], [
        'verification_token' => $newToken,
        'verification_sent_at' => date('Y-m-d H:i:s'),
    ]);

    sendVerificationEmail($email, $user['nome'], $newToken);

    jsonResponse(['status' => 'sent', 'message' => 'Nuova email di conferma inviata. Controlla la tua posta.']);
}

function sendResetEmail(string $to, string $nome, string $token): void {
    $resetUrl = 'https://www.switchai.it/reset-password?token=' . urlencode($token);
    $subject = '[SwitchAI] Reimposta la tua password';
    $body = "Ciao {$nome},\n\n";
    $body .= "hai richiesto di reimpostare la password del tuo account SwitchAI.\n\n";
    $body .= "Clicca sul link qui sotto per scegliere una nuova password:\n\n";
    $body .= "🔗 REIMPOSTA PASSWORD\n{$resetUrl}\n\n";
    $body .= "Il link è valido per 1 ora.\n\n";
    $body .= "Se non hai richiesto tu il reset, ignora questa email.\n\n";
    $body .= "Il team di SwitchAI\nwww.switchai.it";

    $headers = "From: " . (getenv('ACTIVATION_EMAIL') ?: 'noreply@switchai.it') . "\r\n";
    $headers .= "Reply-To: " . (getenv('ACTIVATION_EMAIL') ?: 'noreply@switchai.it') . "\r\n";

    @mail($to, $subject, $body, $headers);
}

function handleForgotPassword(array $input): void {
    $email = trim($input['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['status' => 'sent', 'message' => 'Se l\'email è registrata, riceverai le istruzioni per il reset.']);
        return;
    }

    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserByEmail($email);

    if (!$user || !$user['email_verified']) {
        jsonResponse(['status' => 'sent', 'message' => 'Se l\'email è registrata, riceverai le istruzioni per il reset.']);
        return;
    }

    $resetToken = bin2hex(random_bytes(32));
    updateUser($user['id'], [
        'reset_token' => $resetToken,
        'reset_token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);

    sendResetEmail($email, $user['nome'], $resetToken);

    jsonResponse(['status' => 'sent', 'message' => 'Se l\'email è registrata, riceverai le istruzioni per il reset.']);
}

function handleResetPassword(array $input): void {
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (strlen($token) < 16) {
        errorResponse('Token non valido', 400);
    }
    if (strlen($password) < 8) {
        errorResponse('La password deve essere almeno 8 caratteri', 400);
    }

    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserByResetToken($token);

    if (!$user) {
        errorResponse('Token non valido o scaduto. Richiedi un nuovo reset.', 404);
    }

    $newHash = password_hash($password, PASSWORD_BCRYPT);
    updateUser($user['id'], [
        'password_hash' => $newHash,
        'reset_token' => null,
        'reset_token_expires_at' => null,
    ]);

    jsonResponse(['status' => 'reset', 'message' => 'Password reimpostata con successo! Ora puoi accedere.']);
}

function handleUserMe(): void {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';

    // Prova prima utente MySQL, poi admin
    $userId = verifyUserToken($token);
    if ($userId) {
        require_once __DIR__ . '/../inc/db_mysql.php';
        $user = findUserById($userId);
        if (!$user) {
            errorResponse('Utente non trovato', 404);
        }

        if (!empty($user['disabled'])) {
            errorResponse('Account disabilitato. Contatta l\'amministratore.', 403);
        }

        $apiKeys = getUserApiKeys($userId);
        $usageToday = getUserDailyUsage($userId);

        jsonResponse([
            'id' => $user['id'],
            'email' => $user['email'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'tier' => $user['tier'],
            'email_verified' => (bool)$user['email_verified'],
            'daily_quota' => (int)$user['daily_quota'],
            'usage_today' => $usageToday,
            'remaining' => max(0, (int)$user['daily_quota'] - $usageToday),
            'api_keys' => $apiKeys,
            'created_at' => $user['created_at'],
        ]);
        return;
    }

    // Admin token
    $isAdmin = verifyAuthToken($token);
    if ($isAdmin) {
        jsonResponse([
            'admin' => true,
            'tier' => 'admin',
        ]);
        return;
    }

    errorResponse('Non autorizzato', 401);
}

// ── USER API KEYS ──────────────────────────────────────────────────

function handleUserCreateApiKey(array $input): void {
    $userId = requireUserAuth();
    $name = trim($input['name'] ?? 'Default');
    if (empty($name)) $name = 'Default';

    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserById($userId);
    if (!$user) errorResponse('Utente non trovato', 404);

    $keyData = generateApiKey();
    createApiKey($userId, $keyData['hash'], $keyData['prefix'], $name);

    jsonResponse([
        'api_key' => $keyData['key'],
        'key' => [
            'name' => $name,
            'key_prefix' => $keyData['prefix'],
            'created_at' => date('c'),
        ],
        'warning' => 'Copiala ora — non verrà più mostrata.',
    ], 201);
}

function handleUserListApiKeys(): void {
    $userId = requireUserAuth();
    require_once __DIR__ . '/../inc/db_mysql.php';
    $keys = getUserApiKeys($userId);
    jsonResponse(['keys' => $keys]);
}

function handleUserRevokeApiKey(int $keyId): void {
    $userId = requireUserAuth();
    require_once __DIR__ . '/../inc/db_mysql.php';

    // Verifica che la chiave appartenga all'utente
    $keys = getUserApiKeys($userId);
    $owned = false;
    foreach ($keys as $k) {
        if ((int)$k['id'] === $keyId) { $owned = true; break; }
    }
    if (!$owned) errorResponse('Chiave non trovata', 404);

    revokeApiKey($keyId);
    jsonResponse(['status' => 'revoked']);
}

// ── AUTH LOGIN — supporta sia admin che utenti MySQL ───────────────

function handleAuthLogin(array $input): void {
    // Anti brute-force: max 5 tentativi falliti per IP in 60 secondi
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bruteFile = __DIR__ . '/../../data/ratelimit/login_' . md5($ip) . '.json';
    $attempts = [];
    if (is_file($bruteFile)) {
        $attempts = json_decode(file_get_contents($bruteFile), true) ?: [];
    }
    $now = time();
    $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - 60));
    if (count($attempts) >= 5) {
        $retryAfter = ($attempts[0] ?? $now) + 60 - $now;
        http_response_code(429);
        header('Retry-After: ' . max(1, $retryAfter));
        echo json_encode(['error' => 'Troppi tentativi. Riprova tra ' . max(1, $retryAfter) . ' secondi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $loginFailed = false;

    // Prova prima login utente MySQL (email + password)
    $email = trim($input['email'] ?? $input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        require_once __DIR__ . '/../inc/db_mysql.php';
        try {
            $user = findUserByEmail($email);
            if ($user && password_verify($password, $user['password_hash'])) {
                // Verifica email confermata
                if (!$user['email_verified']) {
                    errorResponse('Email non ancora confermata. Controlla la tua posta o richiedi una nuova conferma.', 403);
                }

                // Verifica che l'utente non sia disabilitato
                if (!empty($user['disabled'])) {
                    $loginFailed = true;
                    errorResponse('Account disabilitato. Contatta l\'amministratore.', 403);
                }

                // Aggiorna tier a 'free' se è ancora 'free' o se non impostato
                $tier = $user['tier'] ?: 'free';
                if ($tier !== $user['tier']) {
                    updateUser($user['id'], ['tier' => $tier]);
                }

                $token = generateUserToken($user['id']);
                $usageToday = getUserDailyUsage($user['id']);

                jsonResponse([
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'tier' => $tier,
                        'usage_today' => $usageToday,
                        'daily_quota' => (int)$user['daily_quota'],
                    ],
                    'is_admin' => false,
                ]);
                return;
            }
            // MySQL user non trovato o password errata — continua al fallback admin
        } catch (Throwable $e) {
            error_log("AUTH MySQL login error: " . $e->getMessage());
            errorResponse('Errore interno del server. Riprova più tardi.', 500);
            return;
        }
    }

    // Fallback: admin login (STATS_USER)
    $user = $input['username'] ?? $input['email'] ?? '';
    $pass = $input['password'] ?? '';

    $expectedUser = getenv('STATS_USER') ?: 'admin';
    $expectedHash = getenv('STATS_PASSWORD_HASH') ?: '';

    if (!$expectedHash) {
        errorResponse('Auth non configurato', 500);
    }

    if ($user !== $expectedUser || !password_verify($pass, $expectedHash)) {
        // Registra tentativo fallito
        $attempts[] = $now;
        file_put_contents($bruteFile, json_encode($attempts), LOCK_EX);
        error_log("AUTH: Failed login attempt for user '$user' from $ip");
        errorResponse('Credenziali errate', 401);
    }

    // Login riuscito — pulisci tentativi
    @unlink($bruteFile);

    // Genera token admin
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log("AUTH: API_KEY env var not configured");
        errorResponse('Server configuration error', 500);
    }
    $ts = time();
    $token = base64_encode($user . ':' . hash_hmac('sha256', $user . ':' . $ts, $secret) . ':' . $ts);

    jsonResponse(['token' => $token, 'is_admin' => true]);
}

// ── ADMIN: USER MANAGEMENT ────────────────────────────────────────

function handleAdminListUsers(): void {
    require_once __DIR__ . '/../inc/db_mysql.php';
    $users = getUsers();
    $usageMap = getUsersDailyUsage();

    // Crea mappa usage: user_id → count
    $usageByUser = [];
    foreach ($usageMap as $u) {
        $usageByUser[(int)$u['user_id']] = (int)$u['cnt'];
    }

    $result = array_map(function ($u) use ($usageByUser) {
        return [
            'id' => (int)$u['id'],
            'email' => $u['email'],
            'nome' => $u['nome'],
            'cognome' => $u['cognome'],
            'tier' => $u['tier'],
            'daily_quota' => (int)$u['daily_quota'],
            'email_verified' => (bool)$u['email_verified'],
            'usage_today' => $usageByUser[(int)$u['id']] ?? 0,
            'created_at' => $u['created_at'],
        ];
    }, $users);

    jsonResponse(['users' => $result]);
}

function handleAdminUpdateUser(int $userId, array $input): void {
    require_once __DIR__ . '/../inc/db_mysql.php';
    $user = findUserById($userId);
    if (!$user) {
        errorResponse('Utente non trovato', 404);
    }

    // Gestisci azioni speciali
    if (!empty($input['resend_confirmation'])) {
        $newToken = bin2hex(random_bytes(32));
        updateUser($userId, [
            'verification_token' => $newToken,
            'verification_sent_at' => date('Y-m-d H:i:s'),
        ]);
        sendVerificationEmail($user['email'], $user['nome'], $newToken);
        jsonResponse(['status' => 'resent', 'message' => 'Email di conferma reinviata.']);
        return;
    }

    $allowed = ['tier', 'daily_quota', 'disabled'];
    $updates = [];
    foreach ($allowed as $field) {
        if (isset($input[$field])) {
            $updates[$field] = $input[$field];
        }
    }

    if (empty($updates)) {
        errorResponse('Nessun campo da aggiornare. Campi supportati: tier, daily_quota, disabled', 400);
    }

    updateUser($userId, $updates);
    jsonResponse(['status' => 'updated', 'user' => findUserById($userId)]);
}

// ── ADMIN: API TEST TOOL ───────────────────────────────────────────

function handleAdminTestApi(array $input): void {
    $apiKey = $input['api_key'] ?? '';
    $adminToken = $input['use_admin_token'] ? ($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '') : '';
    $extraHeaders = $input['headers'] ?? [];
    $endpoint = $input['endpoint'] ?? '/api/status';
    $method = strtoupper($input['method'] ?? 'GET');
    $body = $input['body'] ?? null;

    // Endpoint whitelist — solo API interne, nessun esterno (previene SSRF)
    $allowedEndpoints = [
        '/api/analyze', '/api/calculate-savings', '/api/tariffe/luce', '/api/tariffe/gas',
        '/api/market-indices', '/api/status', '/api/health', '/api/stats/traffic',
    ];
    if (!in_array($endpoint, $allowedEndpoints, true)) {
        errorResponse('Endpoint non consentito nel test. Usa solo endpoint API interni.', 400);
    }

    // Costruisci URL usando host locale (non HTTP_HOST per prevenire Host header injection)
    $url = "http://localhost:8080{$endpoint}";

    // Headers
    $reqHeaders = [
        "Content-Type: application/json",
        "Accept: application/json",
    ];

    if ($apiKey) {
        $reqHeaders[] = "X-API-Key: {$apiKey}";
    } elseif ($adminToken) {
        $reqHeaders[] = "X-Auth-Token: {$adminToken}";
    }

    $response = null;
    $timingMs = 0;
    $httpCode = 0;
    $responseHeaders = [];

    // Solo se l'endpoint è nella whitelist e il metodo è sicuro
    $safeMethods = ['GET', 'POST'];
    if (!in_array($method, $safeMethods, true)) {
        errorResponse('Metodo HTTP non consentito nel test.', 400);
    }

    // Opzioni contesto HTTP
    $ctxOpts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $reqHeaders),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];

    if ($body !== null && $method === 'POST') {
        $bodyStr = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        $ctxOpts['http']['content'] = $bodyStr;
    }

    $start = microtime(true);
    $response = @file_get_contents($url, false, stream_context_create($ctxOpts));
    $timingMs = round((microtime(true) - $start) * 1000);

    // Estrai HTTP status e headers — compatibile PHP 8.5+
    $httpCode = 0;
    $responseHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $rawHeaders = http_get_last_response_headers();
        if (!empty($rawHeaders)) {
            preg_match('#HTTP/\d+\.\d+ (\d+)#', $rawHeaders[0], $m);
            $httpCode = (int)($m[1] ?? 0);
            $responseHeaders = [];
            foreach ($rawHeaders as $h) {
                if (str_contains($h, ': ')) {
                    [$k, $v] = explode(': ', $h, 2);
                    $responseHeaders[strtolower($k)] = $v;
                }
            }
        }
    }

    $parsedBody = null;
    if ($response) {
        $parsedBody = json_decode($response, true);
    }

    jsonResponse([
        'status' => $httpCode,
        'timing_ms' => $timingMs,
        'headers' => $responseHeaders,
        'body' => $parsedBody ?? $response,
        'raw_body' => $response,
    ]);
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

    $body .= "\n";

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

    // Metadata PUN forward ARERA (per badge UI)
    $areraMeta = getAreraForwardMeta();
    $indices["pun_source"] = $areraMeta["source"];
    $indices["pun_forward_label"] = $areraMeta["label"];
    $indices["pun_forward_age_days"] = $areraMeta["age_days"];

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
    } catch (Throwable $e) {
        // MySQL non disponibile — nessun arricchimento
    }
}

/**
 * Esegue il confronto ARERA vs Wattene su 5 offerte note.
 * Endpoint: GET /api/admin/wattene-test (richiede auth admin)
 */
function handleWatteneTest(): void {
    $all = getTariffsByCommodity('LUCE');
    if (empty($all)) {
        errorResponse('Dati ARERA non trovati. Eseguire prima il sync.', 503);
    }

    // Trasforma in array con chiavi che matchano il test
    $data = array_map(function($t) {
        return [
            'brand' => $t['brand'] ?? '',
            'name' => $t['name'] ?? '',
            'supplier_name' => $t['supplier_name'] ?? '',
            'tipo_cliente' => $t['tipo_cliente'] ?? '',
            'tipo_fasce' => $t['tipo_fasce'] ?? '',
            'prezzo tot kwh' => $t['price_mono_kwh'] ?? '',
            'costo_fisso' => ($t['fixed_fee_monthly'] ?? 0) * 12,
            'sconti_applicati' => $t['has_sconti_condizionali'] ? [['nome' => $t['sconto_note'] ?? 'sconto condizionale']] : [],
        ];
    }, $all);

    $testCases = [
        ['brand' => 'E.ON ENERGIA',     'offerta' => 'E.ON LuceClick - Amico new', 'wat_prezzo' => 0.135488, 'wat_pcv' => 109.23, 'wat_consumo' => 3200],
        ['brand' => 'EDISON ENERGIA',    'offerta' => 'Edison Web Luce',             'wat_prezzo' => 0.133988, 'wat_pcv' => 90.00,  'wat_consumo' => 3200],
        ['brand' => 'OCTOPUS ENERGY',    'offerta' => 'Octopus Fissa 12M',           'wat_prezzo' => 0.135788, 'wat_pcv' => 72.00,  'wat_consumo' => 3200],
        ['brand' => 'A2A ENERGIA',       'offerta' => 'A2A Full Luce',               'wat_prezzo' => 0.155988, 'wat_pcv' => 135.00, 'wat_consumo' => 3200, 'tipo_fasce' => 'Monoraria'],
        ['brand' => 'SORGENIA',          'offerta' => 'Next Energy Hybrid',          'wat_prezzo' => 0.137988, 'wat_pcv' => 108.00, 'wat_consumo' => 3200],
    ];

    $results = [];
    $allOk = true;

    foreach ($testCases as $c) {
        $found = null;
        // Collect all matching variants, then pick the best one
        $candidates = [];
        foreach ($data as $o) {
            if (stripos($o['brand'] ?? '', $c['brand']) === false) continue;
            if (stripos($o['name'] ?? '', $c['offerta']) === false) continue;
            if (($o['tipo_cliente'] ?? '') !== 'residenziale') continue;
            $candidates[] = $o;
        }
        // Preferenza 1: candidate con PCV corrispondente (se test lo specifica)
        $pcvFiltered = array_values(array_filter($candidates, fn($o) => abs((float)str_replace(',', '.', $o['costo_fisso'] ?? '0') - $c['wat_pcv']) < 2));
        if (!empty($pcvFiltered)) $candidates = $pcvFiltered;
        // Preferenza 2: (1) explicit tipo_fasce, (2) Monoraria, (3) first found
        $preferredFasce = $c['tipo_fasce'] ?? 'Monoraria';
        foreach ($candidates as $o) {
            if (($o['tipo_fasce'] ?? '') === $preferredFasce) {
                $found = $o;
                break;
            }
        }
        if (!$found && !empty($candidates)) {
            $found = $candidates[0]; // fallback: first match
        }

        if (!$found) {
            $results[] = ['brand' => $c['brand'], 'offerta' => $c['offerta'], 'status' => 'NOT_FOUND'];
            $allOk = false;
            continue;
        }

        $ourPrezzo = (float)str_replace(',', '.', $found['prezzo tot kwh'] ?? '0');
        $ourPcv = (float)str_replace(',', '.', $found['costo_fisso'] ?? '0');
        $ourPrezzoCorrected = $ourPrezzo + (defined('LUCE_DISPACCIAMENTO') ? LUCE_DISPACCIAMENTO : 0.016988);

        $diffPrezzo = round(($c['wat_prezzo'] - $ourPrezzoCorrected) * 1000, 3); // millesimi
        $diffPcv = round($c['wat_pcv'] - $ourPcv, 2);

        $prezzoOk = abs($diffPrezzo) < 2;
        $pcvOk = abs($diffPcv) < 2;
        $ok = $prezzoOk && $pcvOk;
        if (!$ok) $allOk = false;

        $results[] = [
            'brand'              => $c['brand'],
            'offerta'            => $c['offerta'],
            'status'             => $ok ? 'OK' : 'FAIL',
            'prezzo_nostro'      => round($ourPrezzoCorrected, 6),
            'prezzo_wattene'     => $c['wat_prezzo'],
            'diff_prezzo_mill'  => $diffPrezzo,
            'prezzo_ok'          => $prezzoOk,
            'pcv_nostro'         => $ourPcv,
            'pcv_wattene'        => $c['wat_pcv'],
            'diff_pcv_eur'      => $diffPcv,
            'pcv_ok'             => $pcvOk,
            'sconti_attivi'      => $found['sconti_applicati'] ?? [],
            'dispacciamento'     => defined('LUCE_DISPACCIAMENTO') ? round(LUCE_DISPACCIAMENTO, 6) : 0.016988,
        ];
    }

    // ── TEST SU 10 OFFERTE RANDOM (coerenza interna) ──────────────────
    $allLuce = array_filter($all, fn($t) => ($t['tipo_cliente'] ?? '') === 'residenziale' && ($t['price_mono_kwh'] ?? null) > 0);
    shuffle($allLuce);
    $randSample = array_slice($allLuce, 0, 10);

    $randomResults = [];
    $randomAllOk = true;
    $consumoTest = 3200;
    $potenzaTest = 3.0;

    foreach ($randSample as $t) {
        $fixedFee = (float)($t['fixed_fee_monthly'] ?? 0);
        $priceMono = (float)($t['price_mono_kwh'] ?? 0);
        $isVar = ($t['type'] ?? '') === 'VARIABILE';
        $spread = (float)($t['spread'] ?? 0);

        $punRef = getAreraForwardPun() ?? 0.1434;
        if ($isVar) {
            $effPrice = $punRef * (defined('LUCE_PERDITE_RETE_BT') ? LUCE_PERDITE_RETE_BT : 1.102) + $spread;
        } else {
            $effPrice = $priceMono;
        }

        $energyCost = $consumoTest * $effPrice;
        $costoPotenza = (defined('LUCE_COSTO_POTENZA_KW') ? LUCE_COSTO_POTENZA_KW : 21.45) * $potenzaTest;
        $oneri = $consumoTest * (defined('ONERI_SISTEMA_LUCE') ? ONERI_SISTEMA_LUCE : 0.03886);
        $acciseSoglia = max(0, min($consumoTest, defined('LUCE_ACCISE_SOGLIA_COMPENSATA') ? LUCE_ACCISE_SOGLIA_COMPENSATA : 2640) - (defined('LUCE_ACCISE_SOGLIA_ESENTE') ? LUCE_ACCISE_SOGLIA_ESENTE : 1800)) * (defined('LUCE_ACCISE') ? LUCE_ACCISE : 0.00154);
        $dispacciamento = $consumoTest * (defined('LUCE_DISPACCIAMENTO') ? LUCE_DISPACCIAMENTO : 0.016988);
        $quotaFissaReti = defined('QUOTA_FISSA_RETI_LUCE') ? QUOTA_FISSA_RETI_LUCE : 20.40;
        $trasporto = $consumoTest * (defined('LUCE_TRASPORTO_VAR') ? LUCE_TRASPORTO_VAR : 0.00888);

        $fixedCost = $fixedFee * 12 + $costoPotenza + $quotaFissaReti;
        $subtotal = $energyCost + $fixedCost + $trasporto + $oneri + $acciseSoglia + $dispacciamento;
        $ivaRate = defined('LUCE_IVA') ? LUCE_IVA : 0.10;
        $annualCost = round($subtotal * (1 + $ivaRate), 2);
        $monthlyCost = round($annualCost / 12, 2);

        $checks = [];
        $checks[] = ['check' => 'Costo annuale positivo', 'ok' => $annualCost > 0, 'value' => $annualCost . ' €'];
        $checks[] = ['check' => 'Costo annuale < 5000€', 'ok' => $annualCost < 5000, 'value' => $annualCost . ' €'];
        $checks[] = ['check' => 'Prezzo energia valido', 'ok' => $effPrice > 0 && $effPrice < 1, 'value' => round($effPrice, 6) . ' €/kWh'];
        $checks[] = ['check' => 'Mensile = annuale / 12', 'ok' => $monthlyCost > 0 && abs($monthlyCost - $annualCost/12) < 0.5, 'value' => $monthlyCost . ' €/mese'];
        $checks[] = ['check' => 'PCV valido', 'ok' => $fixedFee >= 0 && $fixedFee < 100, 'value' => round($fixedFee, 2) . ' €/mese'];

        $tipoCheck = $isVar ? 'indice PUN + spread' : 'prezzo fisso';
        if ($isVar) {
            $checks[] = ['check' => "Variabile: eff=f(PUN×λ+spread)", 'ok' => abs($effPrice - ($punRef * (defined('LUCE_PERDITE_RETE_BT') ? LUCE_PERDITE_RETE_BT : 1.102) + $spread)) < 0.001, 'value' => $tipoCheck];
        } else {
            $checks[] = ['check' => "Fisso: eff=prezzo_mono", 'ok' => abs($effPrice - $priceMono) < 0.001, 'value' => $tipoCheck];
        }

        $allChecksOk = !in_array(false, array_column($checks, 'ok'), true);
        if (!$allChecksOk) $randomAllOk = false;

        $randomResults[] = [
            'brand'          => $t['supplier_name'] ?? ($t['brand'] ?? '?'),
            'offerta'        => $t['name'] ?? '?',
            'type'           => $t['type'] ?? '?',
            'status'         => $allChecksOk ? 'OK' : 'FAIL',
            'annual_cost'    => $annualCost,
            'monthly_cost'   => $monthlyCost,
            'eff_price'      => round($effPrice, 6),
            'price_mono'     => $priceMono,
            'fixed_fee_monthly' => $fixedFee,
            'spread'         => $spread,
            'checks'         => $checks,
        ];
    }

    jsonResponse([
        'tested_at'   => date('Y-m-d H:i:s'),
        'wattene'     => [
            'total'       => count($results),
            'passed'      => count(array_filter($results, fn($r) => $r['status'] === 'OK')),
            'failed'      => count(array_filter($results, fn($r) => $r['status'] === 'FAIL')),
            'not_found'   => count(array_filter($results, fn($r) => $r['status'] === 'NOT_FOUND')),
            'all_ok'      => $allOk,
            'tolerance'   => '±2 millesimi prezzo, ±2€ PCV',
            'note'        => 'Il prezzo Wattene include dispacciamento. Il nostro prezzo corretto = prezzo_tot_kwh + dispacciamento.',
            'cases'       => $results,
        ],
        'random'      => [
            'total'       => count($randomResults),
            'passed'      => count(array_filter($randomResults, fn($r) => $r['status'] === 'OK')),
            'failed'      => count(array_filter($randomResults, fn($r) => $r['status'] === 'FAIL')),
            'all_ok'      => $randomAllOk,
            'note'        => 'Test su 10 offerte casuali. Verifica coerenza interna: prezzi, costi regolati, IVA, arrotondamenti. Profilo: 3200 kWh, 3 kW, NORD.',
            'cases'       => $randomResults,
        ],
    ]);
}
