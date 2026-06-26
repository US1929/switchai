<?php
/**
 * router.php — Router per il server built-in PHP
 * Reindirizza /api/* a api/index.php e serve i file statici.
 *
 * Uso: php -S localhost:8080 router.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// ── Blocca accesso a file interni ────────────────────────────────────
if (preg_match('#^/(inc|data|logs)/#', $path)) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    return true;
}

// ── Offerte e sitemap: pagine dinamiche per crawler ──────────────────
if (preg_match('#^/offerta/#', $path) || $path === '/sitemap.xml') {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

// ── Risorse SEO: pagine PHP indicizzabili ─────────────────────────
$risorseMap = [
    ''                            => 'index.php',
    'come-funziona-bolletta-luce' => 'bolletta-luce.php',
    'come-funziona-bolletta-gas'  => 'bolletta-gas.php',
    'glossario-energia'           => 'glossario.php',
    'prezzo-fisso-vs-indicizzato' => 'fisso-indicizzato.php',
    'calcolo-spesa-annua'        => 'calcolo.php',
    'come-leggere-bolletta'       => 'come-leggere.php',
];
if (preg_match('#^/risorse/?([a-z0-9-]+)?$#', $path, $m)) {
    $slug = $m[1] ?? '';
    $file = $risorseMap[$slug] ?? null;
    if ($file) {
        $fpath = __DIR__ . '/resources/' . $file;
        if (is_file($fpath)) { require $fpath; return true; }
    }
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="it"><head><title>Pagina non trovata | SwitchAI</title></head><body style="background:#070a12;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px"><h1>404</h1><p>Risorsa non trovata.</p><a href="/risorse/" style="color:#f59e0b">Tutte le risorse</a></body></html>';
    return true;
}

// ── MCP Server ────────────────────────────────────────────────────
if ($path === '/mcp') {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/mcp/index.php';
    require __DIR__ . '/mcp/index.php';
    return true;
}

// ── API: instrada a api/index.php ──────────────────────────────────────
if (preg_match('#^/api(/|$)#', $path)) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

// ── File statici: servili direttamente ─────────────────────────────────
$filePath = __DIR__ . $path;

// Se è un file esistente (non directory), lascia che PHP lo serva
if (is_file($filePath)) {
    return false;
}

// Root e index.html → servi index.html
if ($path === '/' || $path === '/index.html') {
    $idx = __DIR__ . '/index.html';
    if (is_file($idx)) {
        // Servi esplicitamente invece di return false
        // (return false con PHP built-in server a volte non funziona per la root)
        header('Content-Type: text/html; charset=utf-8');
        readfile($idx);
        return true;
    }
}

// ── SPA fallback: qualsiasi altra rotta → index.html ───────────────────
$idx = __DIR__ . '/index.html';
if (is_file($idx)) {
    $_SERVER['SCRIPT_NAME'] = '/index.html';
    header('Content-Type: text/html; charset=utf-8');
    readfile($idx);
    return true;
}

// ── 404 ────────────────────────────────────────────────────────────────
http_response_code(404);
echo json_encode(['error' => 'Not found', 'path' => $path]);
return true;
