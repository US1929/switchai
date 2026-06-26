<?php
/**
 * risorse.php — Router per pagine SEO (/risorse/).
 * Usato da .htaccess in produzione: RewriteRule ^risorse/... → risorse.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

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
        if (is_file($fpath)) { require $fpath; exit; }
    }
}

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pagina non trovata | SwitchAI</title>
    <meta name="robots" content="noindex">
</head>
<body style="background:#070a12;color:#f1f5f9;font-family:sans-serif;text-align:center;padding:80px 20px">
    <h1>404</h1>
    <p>Risorsa non trovata.</p>
    <a href="/risorse/" style="color:#f59e0b">Tutte le risorse</a>
</body>
</html>
