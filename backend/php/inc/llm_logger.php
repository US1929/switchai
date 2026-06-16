<?php
/**
 * LLM Logger — Traccia il traffico AI vs umano
 *
 * Registra ogni richiesta API con User-Agent, IP, e classificazione.
 * Fornisce un report accessibile via /api/stats/traffic
 */

define('LOG_DIR', __DIR__ . '/../../logs');
define('TRAFFIC_LOG', LOG_DIR . '/traffic.jsonl');

/** Classifica uno User-Agent come LLM o umano */
function classifyAgent(?string $ua): array {
    if (!$ua) return ['type' => 'unknown', 'name' => 'No User-Agent'];

    $llmPatterns = [
        'claude'          => 'Claude (Anthropic)',
        'anthropic'       => 'Claude (Anthropic)',
        'chatgpt'         => 'ChatGPT (OpenAI)',
        'gptbot'          => 'GPTBot (OpenAI Crawler)',
        'openai'          => 'OpenAI Agent',
        'gemini'          => 'Gemini (Google)',
        'google-extended' => 'Google Extended (AI Crawler)',
        'bard'            => 'Bard (Google)',
        'meta-external'   => 'Meta AI Crawler',
        'amazonbot'       => 'Amazon AI Crawler',
        'perplexity'      => 'Perplexity AI',
        'webmcp'          => 'WebMCP Agent (Chrome)',
        'modelcontext'    => 'MCP Client',
        'switchaibot'     => 'SwitchAI Bot (self)',
    ];

    $uaLower = strtolower($ua);
    foreach ($llmPatterns as $pattern => $name) {
        if (str_contains($uaLower, $pattern)) {
            return ['type' => 'llm', 'name' => $name];
        }
    }

    // Bot/crawler tradizionali
    $botPatterns = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests', 'go-http', 'java/', 'axios'];
    foreach ($botPatterns as $p) {
        if (str_contains($uaLower, $p)) {
            return ['type' => 'bot', 'name' => 'Bot: ' . substr($ua, 0, 60)];
        }
    }

    // Browser umani
    if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)\//', $ua)) {
        return ['type' => 'human', 'name' => 'Browser'];
    }

    return ['type' => 'unknown', 'name' => substr($ua, 0, 80)];
}

/** Registra una richiesta nel log (JSONL con rotazione mensile) */
function logTraffic(string $endpoint, string $method = 'GET', array $meta = []): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }

    // Rotazione mensile: traffic_YYYY_MM.jsonl
    $logFile = LOG_DIR . '/traffic_' . date('Y_m') . '.jsonl';

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $classification = classifyAgent($ua);

    // Recupera IP reale
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
    $ip = explode(',', $ip)[0];

    $entry = json_encode([
        'timestamp'  => date('c'),
        'endpoint'   => $endpoint,
        'method'     => $method,
        'ip'         => trim($ip),
        'agent_type' => $classification['type'],
        'agent_name' => $classification['name'],
        'user_agent' => substr($ua, 0, 200),
        'referer'    => $_SERVER['HTTP_REFERER'] ?? '',
    ], JSON_UNESCAPED_UNICODE) . "\n";

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/** Genera un report del traffico (streaming JSONL — NON carica tutto in RAM) */
function getTrafficReport(int $hours = 24): array {
    $currentLog = LOG_DIR . '/traffic_' . date('Y_m') . '.jsonl';
    $prevLog    = LOG_DIR . '/traffic_' . date('Y_m', strtotime('-1 month')) . '.jsonl';

    $cutoff = time() - ($hours * 3600);
    $stats = ['total' => 0, 'by_type' => [], 'by_endpoint' => [], 'llm_visitors' => []];

    // Legge entrambi i file (mese corrente + precedente) in streaming
    foreach ([$currentLog, $prevLog] as $file) {
        if (!is_file($file)) continue;

        $fp = @fopen($file, 'r');
        if (!$fp) continue;

        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $entry = json_decode($line, true);
            if (!$entry) continue;

            $ts = strtotime($entry['timestamp'] ?? '');
            if ($ts < $cutoff) {
                // I file sono in ordine cronologico, quindi se troviamo una entry
                // vecchia nel file corrente, possiamo smettere di leggere
                // solo se siamo nel file corrente (file corrente ha date recenti)
                continue;
            }

            $stats['total']++;
            $type = $entry['agent_type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            $ep = $entry['endpoint'] ?? '/';
            $stats['by_endpoint'][$ep] = ($stats['by_endpoint'][$ep] ?? 0) + 1;

            if ($type === 'llm') {
                $name = $entry['agent_name'] ?? 'Unknown LLM';
                if (!isset($stats['llm_visitors'][$name])) {
                    $stats['llm_visitors'][$name] = [
                        'first_seen' => $entry['timestamp'] ?? '',
                        'last_seen'  => $entry['timestamp'] ?? '',
                        'calls'      => 0,
                        'ips'        => [],
                    ];
                }
                $stats['llm_visitors'][$name]['calls']++;
                $stats['llm_visitors'][$name]['last_seen'] = $entry['timestamp'] ?? '';
                if (!empty($entry['ip']) && !in_array($entry['ip'], $stats['llm_visitors'][$name]['ips'])) {
                    $stats['llm_visitors'][$name]['ips'][] = $entry['ip'];
                }
            }
        }
        fclose($fp);
    }

    return $stats;
}
