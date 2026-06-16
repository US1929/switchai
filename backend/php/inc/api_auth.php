<?php
/**
 * api_auth.php — Rate Limiting e Autenticazione B2B API
 *
 * Supporta due tier:
 *   B2C (free): limite 30 richieste/ora per IP
 *   B2B (a pagamento): chiave API con quota mensile
 *
 * Meccanica flat-file: ogni client ha un file JSON in data/api_clients/{sha256_key}.json
 */

function getClientTier(): array {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey)) return ['tier' => 'b2c', 'client_name' => 'anonymous'];

    $hash = hash('sha256', $apiKey);
    $file = __DIR__ . '/../../data/api_clients/' . $hash . '.json';

    if (!is_file($file)) return ['tier' => 'b2c', 'client_name' => 'anonymous']; // Key invalida → fallback B2C

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : ['tier' => 'b2c', 'client_name' => 'anonymous'];
}

/**
 * Verifica rate limit e incrementa contatore.
 * Ritorna true se OK, false se limite superato.
 * Usa flock() per evitare race conditions.
 */
function checkRateLimit(array $client): bool {
    if ($client['tier'] === 'b2c') {
        return checkB2CRateLimit();
    }
    return checkB2BRateLimit($client);
}

function checkB2CRateLimit(): bool {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash = hash('sha256', $ip);
    $file = __DIR__ . '/../../data/ratelimit/' . $ipHash . '.json';
    $dir = dirname($file);

    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $fp = @fopen($file, 'c+');
    if (!$fp) return true; // Se non possiamo scrivere, lasciamo passare (fail open)

    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $data = $raw ? json_decode($raw, true) : ['count' => 0, 'window_start' => time()];

        // Reset finestra dopo 1 ora
        if (time() - ($data['window_start'] ?? 0) > 3600) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        $data['count']++;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);

        $maxRequests = 30; // 30 richieste/ora per B2C
        $ok = $data['count'] <= $maxRequests;
        fclose($fp);
        return $ok;
    }

    fclose($fp);
    return true; // fail open
}

function checkB2BRateLimit(array $client): bool {
    $hash = hash('sha256', $_SERVER['HTTP_X_API_KEY'] ?? '');
    $file = __DIR__ . '/../../data/api_clients/' . $hash . '.json';

    if (!is_file($file)) return false;

    $fp = @fopen($file, 'c+');
    if (!$fp) return true;

    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $data = $raw ? json_decode($raw, true) : $client;

        // Reset mensile
        $now = date('Y-m');
        if (($data['last_reset'] ?? '') !== $now) {
            $data['calls_current_month'] = 0;
            $data['last_reset'] = $now . '-01T00:00:00Z';
        }

        $quota = $data['monthly_quota'] ?? 1000;
        $current = ($data['calls_current_month'] ?? 0) + 1;

        if ($current > $quota) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false; // Quota superata
        }

        $data['calls_current_month'] = $current;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    fclose($fp);
    return true;
}

/**
 * Registra un nuovo client API B2B (admin only).
 */
function registerApiClient(string $name, string $tier = 'basic'): array {
    $key = 'sk-' . bin2hex(random_bytes(24));
    $hash = hash('sha256', $key);

    $dir = __DIR__ . '/../../data/api_clients';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $quotas = ['basic' => 1000, 'pro' => 5000, 'premium' => 20000];
    $data = [
        'client_name'        => $name,
        'api_key_hash'       => $hash,
        'tier'               => $tier,
        'monthly_quota'      => $quotas[$tier] ?? 1000,
        'calls_current_month'=> 0,
        'last_reset'         => date('Y-m') . '-01T00:00:00Z',
        'created_at'         => date('c'),
    ];

    file_put_contents($dir . '/' . $hash . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    return ['api_key' => $key, 'client' => $data];
}
