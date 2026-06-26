<?php
/**
 * auth.php — Autenticazione JWT per SwitchAI.
 * Token: base64(payload).HMAC-SHA256
 */

require_once __DIR__ . '/db_mysql.php';

define('AUTH_TOKEN_TTL', 86400 * 30); // 30 giorni

function generateToken(array $user): string {
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log('[SECURITY] API_KEY env var missing — token generation refused');
        throw new RuntimeException('Configurazione di sicurezza mancante');
    }
    $payload = json_encode([
        'sub'   => $user['id'],
        'email' => $user['email'],
        'tier'  => $user['tier'],
        'iat'   => time(),
        'exp'   => time() + AUTH_TOKEN_TTL,
    ]);
    $payloadB64 = base64_encode($payload);
    $sig = hash_hmac('sha256', $payloadB64, $secret);
    return $payloadB64 . '.' . $sig;
}

function verifyToken(string $token): ?array {
    $secret = getenv('API_KEY');
    if (!$secret) {
        error_log('[SECURITY] API_KEY env var missing — token verification refused');
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;

    [$payloadB64, $sig] = $parts;
    $expectedSig = hash_hmac('sha256', $payloadB64, $secret);
    if (!hash_equals($expectedSig, $sig)) return null;

    $payload = json_decode(base64_decode($payloadB64, true), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;

    return $payload;
}

function getAuthUser(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (str_starts_with($header, 'Bearer ')) {
        $header = substr($header, 7);
    }
    if (empty($header)) return null;

    $payload = verifyToken($header);
    if (!$payload) return null;

    return findUserById((int)$payload['sub']);
}

function requireUser(): array {
    $user = getAuthUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non autenticato'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}

function registerUser(array $input): array {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Email non valida'];
    }
    if (strlen($password) < 8) {
        return ['error' => 'Password troppo corta (min 8 caratteri)'];
    }

    $existing = findUserByEmail($email);
    if ($existing) {
        return ['error' => 'Email già registrata'];
    }

    $id = createUser(
        $email,
        password_hash($password, PASSWORD_BCRYPT),
        trim($input['nome'] ?? ''),
        trim($input['cognome'] ?? '')
    );

    // Set additional fields
    if (!empty($input['company']) || !empty($input['piva'])) {
        $fields = [];
        if (!empty($input['company'])) $fields['company'] = trim($input['company']);
        if (!empty($input['piva'])) $fields['piva'] = trim($input['piva']);
        updateUser($id, $fields);
    }

    $user = findUserById($id);
    $token = generateToken($user);
    return [
        'user'  => sanitizeUser($user),
        'token' => $token,
    ];
}

function loginUser(string $email, string $password): array {
    $user = findUserByEmail($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Credenziali errate'];
    }
    $token = generateToken($user);
    return [
        'user'  => sanitizeUser($user),
        'token' => $token,
    ];
}

function sanitizeUser(array $user): array {
    return [
        'id'                  => $user['id'],
        'email'               => $user['email'],
        'nome'                => $user['nome'],
        'cognome'             => $user['cognome'],
        'company'             => $user['company'],
        'piva'                => $user['piva'],
        'tier'                => $user['tier'],
        'daily_quota'         => (int)$user['daily_quota'],
        'subscription_status' => $user['subscription_status'],
        'subscription_ends_at'=> $user['subscription_ends_at'],
        'email_verified'      => (bool)$user['email_verified'],
        'created_at'          => $user['created_at'],
    ];
}
