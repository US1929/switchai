<?php
/**
 * api_auth.php — Rate Limiting e Autenticazione B2B API
 *
 * Supporta tre modalità:
 *   Anonimo (no login): illimitato, offerte filtrate (270)
 *   Free loggato: 10 richieste/giorno, tutte le offerte (5.600+)
 *   B2B (API key): chiave API con quota mensile, tutte le offerte
 *
 * Meccanica flat-file: ogni client ha un file JSON in data/api_clients/{sha256_key}.json
 */

function getClientTier(): array {
    // Priority 1: MySQL API key (utenti registrati)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($apiKey)) {
        // Prova MySQL prima
        try {
            require_once __DIR__ . '/db_mysql.php';
            $keyData = verifyApiKey($apiKey);
            if ($keyData) {
                return [
                    'tier' => $keyData['tier'] === 'api_pro' ? 'api_pro' : 'free',
                    'client_name' => $keyData['nome'] . ' ' . $keyData['cognome'],
                    'user_id' => (int)$keyData['user_id'],
                    'api_key_id' => (int)$keyData['id'],
                    'daily_quota' => (int)$keyData['daily_quota'],
                ];
            }
        } catch (Throwable $e) { /* MySQL non disponibile, prova flat-file */ }

        // Fallback: flat-file B2B (chiavi generate da admin)
        $hash = hash('sha256', $apiKey);
        $file = __DIR__ . '/../../data/api_clients/' . $hash . '.json';
        if (is_file($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && ($data['tier'] ?? 'b2c') !== 'b2c') {
                return ['tier' => 'b2b', 'client_name' => $data['client_name'] ?? 'b2b'] + $data;
            }
        }
    }

    // Priority 2: X-Auth-Token (utente loggato o admin)
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!empty($token)) {
        // Prova utente MySQL
        try {
            require_once __DIR__ . '/db_mysql.php';
            $decoded = base64_decode($token, true);
            if ($decoded && str_contains($decoded, ':')) {
                $parts = explode(':', $decoded);
                if (count($parts) >= 3 && str_starts_with($parts[0], 'user_')) {
                    $userId = (int)substr($parts[0], 5);
                    $secret = getenv('API_KEY');
                    $timestamp = (int)$parts[2];
                    if ($secret && time() - $timestamp <= 86400) {
                        $expectedSig = hash_hmac('sha256', "user_{$userId}:{$timestamp}", $secret);
                        if (hash_equals($expectedSig, $parts[1])) {
                            $user = findUserById($userId);
                            if ($user && $user['email_verified']) {
                                return [
                                    'tier' => $user['tier'] === 'api_pro' ? 'api_pro' : 'free',
                                    'client_name' => $user['nome'] . ' ' . $user['cognome'],
                                    'user_id' => $userId,
                                    'daily_quota' => (int)$user['daily_quota'],
                                ];
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) { /* MySQL non disponibile */ }

        // Fallback: admin token
        if (verifyAuthTokenSimple($token)) {
            return ['tier' => 'free_logged', 'client_name' => 'admin'];
        }
    }

    // Anonymous: illimitato, offerte filtrate
    return ['tier' => 'anonymous', 'client_name' => 'anonymous'];
}

function verifyAuthTokenSimple(string $token): bool {
    $decoded = base64_decode($token, true);
    if (!$decoded || !str_contains($decoded, ':')) return false;
    $parts = explode(':', $decoded);
    if (count($parts) < 3) return false;
    $user = $parts[0];
    $expectedUser = getenv('STATS_USER') ?: 'admin';
    $secret = getenv('API_KEY');
    if (!$secret || $user !== $expectedUser) return false;
    $timestamp = (int)$parts[2];
    if (time() - $timestamp > 86400) return false; // 24h
    $expectedSig = hash_hmac('sha256', $user . ':' . $timestamp, $secret);
    return hash_equals($expectedSig, $parts[1]);
}

/**
 * Verifica rate limit e incrementa contatore.
 * Ritorna true se OK, false se limite superato.
 * Usa flock() per evitare race conditions.
 */
function checkRateLimit(array $client): bool {
    if ($client['tier'] === 'anonymous') {
        return true; // Anonimo: illimitato, ma vede solo offerte filtrate
    }
    if ($client['tier'] === 'free_logged') {
        return checkFreeLoggedRateLimit(); // admin, illimitato
    }
    // Utente MySQL (free, api_pro)
    if (isset($client['user_id'])) {
        return checkMySQLDailyQuota($client['user_id'], $client['daily_quota'] ?? 10);
    }
    return checkB2BRateLimit($client); // flat-file B2B
}

function checkMySQLDailyQuota(int $userId, int $limit): bool {
    try {
        require_once __DIR__ . '/db_mysql.php';
        $used = getUserDailyUsage($userId);
        if ($used >= $limit) {
            return false;
        }
        logRateUsage($userId, null, $_SERVER['REQUEST_URI'] ?? '/api', $_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REMOTE_ADDR'] ?? '');
        return true;
    } catch (Throwable $e) {
        error_log("checkMySQLDailyQuota error: " . $e->getMessage());
        return true; // fail open
    }
}

function checkFreeLoggedRateLimit(): bool {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? 'unknown';
    $tokenHash = hash('sha256', $token);
    $file = __DIR__ . '/../../data/ratelimit/' . $tokenHash . '.json';
    $dir = dirname($file);

    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $fp = @fopen($file, 'c+');
    if (!$fp) return true;

    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $data = $raw ? json_decode($raw, true) : ['count' => 0, 'window_start' => time()];

        if (time() - ($data['window_start'] ?? 0) > 86400) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        $data['count']++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);

        $ok = $data['count'] <= 10; // 10/giorno per free loggato
        fclose($fp);
        return $ok;
    }

    fclose($fp);
    return true;
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

        // Reset finestra dopo 24 ore
        if (time() - ($data['window_start'] ?? 0) > 86400) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        $data['count']++;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);

        $maxRequests = 10; // 10 richieste/giorno per B2C (free tier)
        $ok = $data['count'] <= $maxRequests;
        fclose($fp);
        return $ok;
    }

    fclose($fp);
    return true; // fail open
}

function getB2CRateLimitInfo(): array {
    $client = getClientTier();

    // Anonymous: illimitato
    if ($client['tier'] === 'anonymous') {
        return ['tier' => 'anonymous', 'used' => 0, 'limit' => -1, 'remaining' => -1, 'window_seconds' => 0, 'unlimited' => true];
    }

    // B2B: quota mensile
    if ($client['tier'] === 'b2b') {
        $quota = $client['monthly_quota'] ?? 1000;
        $used = $client['calls_current_month'] ?? 0;
        return ['tier' => 'b2b', 'used' => $used, 'limit' => $quota, 'remaining' => max(0, $quota - $used), 'window_seconds' => 0];
    }

    // Utente MySQL (free, api_pro)
    if (isset($client['user_id'])) {
        try {
            require_once __DIR__ . '/db_mysql.php';
            $used = getUserDailyUsage($client['user_id']);
            $limit = $client['daily_quota'] ?? 10;
            return [
                'tier' => $client['tier'] ?? 'free',
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'window_seconds' => 86400 - (time() % 86400),
            ];
        } catch (Throwable $e) {
            return ['tier' => $client['tier'] ?? 'free', 'used' => 0, 'limit' => 10, 'remaining' => 10, 'window_seconds' => 86400];
        }
    }

    // Free loggato (admin): illimitato
    if ($client['tier'] === 'free_logged') {
        return ['tier' => 'free_logged', 'used' => 0, 'limit' => -1, 'remaining' => -1, 'window_seconds' => 0, 'unlimited' => true];
    }

    return ['tier' => 'free_logged', 'used' => 0, 'limit' => -1, 'remaining' => -1, 'window_seconds' => 0, 'unlimited' => true];
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

/**
 * Rileva se la richiesta è premium (tutte le offerte, non filtrate).
 * Premium = loggato (admin token) oppure API key B2B.
 * Anonimo = free (offerte filtrate, illimitato).
 * @return bool true se premium (tutte le offerte), false se anonimo (filtrate)
 */
function isPremiumRequest(): bool {
    $tier = getClientTier()['tier'];
    return $tier !== 'anonymous'; // free, api_pro, free_logged, b2b vedono tutte le offerte
}
