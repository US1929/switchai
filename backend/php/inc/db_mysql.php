<?php
/**
 * db_mysql.php — Connessione MySQL per SwitchAI.
 *
 * Gestisce: utenti, API keys, rate log, affiliazioni.
 * I dati ARERA (offerte) restano in JSON flat-file.
 *
 * Configurazione via .env:
 *   MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB
 */

function getMySQL(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = getenv('MYSQL_HOST') ?: '';
    $user = getenv('MYSQL_USER') ?: '';
    $pass = getenv('MYSQL_PASS') ?: '';
    $db   = getenv('MYSQL_DB')   ?: '';
    $port = getenv('MYSQL_PORT') ?: '3306';

    if (!$host || !$user || !$db) {
        throw new RuntimeException('MySQL configuration missing. Set MYSQL_HOST, MYSQL_USER, MYSQL_DB in .env');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);

    initMySQLSchema($pdo);
    return $pdo;
}

function initMySQLSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        nome VARCHAR(128) NOT NULL DEFAULT '',
        cognome VARCHAR(128) NOT NULL DEFAULT '',
        company VARCHAR(255) NOT NULL DEFAULT '',
        piva VARCHAR(16) NOT NULL DEFAULT '',
        tier ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
        daily_quota INT NOT NULL DEFAULT 100,
        stripe_customer_id VARCHAR(255) DEFAULT NULL,
        stripe_subscription_id VARCHAR(255) DEFAULT NULL,
        subscription_status ENUM('active','inactive','past_due','canceled') NOT NULL DEFAULT 'inactive',
        subscription_ends_at DATETIME DEFAULT NULL,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        verification_token VARCHAR(64) DEFAULT NULL,
        verification_sent_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        key_hash VARCHAR(255) NOT NULL UNIQUE,
        key_prefix VARCHAR(16) NOT NULL,
        name VARCHAR(128) NOT NULL DEFAULT 'Default',
        last_used_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        disabled TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        api_key_id INT DEFAULT NULL,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rate_user_date (user_id, date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS affiliate_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tariff_id VARCHAR(64) NOT NULL UNIQUE,
        supplier VARCHAR(128) NOT NULL DEFAULT '',
        tariff_name VARCHAR(256) NOT NULL DEFAULT '',
        commodity ENUM('LUCE','GAS') DEFAULT NULL,
        affiliate_url TEXT NOT NULL,
        network VARCHAR(64) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration: aggiungi colonne mancanti se la tabella esiste già
    try { $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL AFTER email_verified"); } catch (PDOException $e) { /* già esistente */ }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN verification_sent_at TIMESTAMP NULL DEFAULT NULL AFTER verification_token"); } catch (PDOException $e) { /* già esistente */ }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN disabled TINYINT(1) NOT NULL DEFAULT 0 AFTER daily_quota"); } catch (PDOException $e) { /* già esistente */ }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL AFTER disabled"); } catch (PDOException $e) { /* già esistente */ }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires_at TIMESTAMP NULL DEFAULT NULL AFTER reset_token"); } catch (PDOException $e) { /* già esistente */ }
    try { $pdo->exec("ALTER TABLE users MODIFY tier ENUM('free','pro','enterprise','api_pro') NOT NULL DEFAULT 'free'"); } catch (PDOException $e) { /* già aggiornato */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS brand_affiliates (
        brand VARCHAR(128) NOT NULL PRIMARY KEY,
        default_url TEXT NOT NULL,
        impression_url TEXT DEFAULT NULL,
        network VARCHAR(64) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migrazione: aggiungi impression_url se mancante
    try { $pdo->exec("ALTER TABLE brand_affiliates ADD COLUMN impression_url TEXT DEFAULT NULL AFTER default_url"); } catch (PDOException $e) { /* già esistente */ }
}

// ── User CRUD ────────────────────────────────────────────────

function findUserByEmail(string $email): ?array {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function findUserById(int $id): ?array {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function createUser(string $email, string $passwordHash, string $nome = '', string $cognome = ''): int {
    $db = getMySQL();
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, nome, cognome) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $passwordHash, $nome, $cognome]);
    return (int)$db->lastInsertId();
}

function updateUser(int $id, array $fields): void {
    $db = getMySQL();
    $sets = [];
    $vals = [];
    foreach ($fields as $col => $val) {
        $sets[] = "`$col` = ?";
        $vals[] = $val;
    }
    $vals[] = $id;
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

// ── API Keys CRUD ────────────────────────────────────────────

function createApiKey(int $userId, string $keyHash, string $keyPrefix, string $name = 'Default'): int {
    $db = getMySQL();
    $stmt = $db->prepare('INSERT INTO api_keys (user_id, key_hash, key_prefix, name) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $keyHash, $keyPrefix, $name]);
    return (int)$db->lastInsertId();
}

function verifyApiKey(string $plainKey): ?array {
    $hash = hash('sha256', $plainKey);
    $db = getMySQL();
    $stmt = $db->prepare("SELECT k.*, u.tier, u.daily_quota, u.subscription_status
        FROM api_keys k JOIN users u ON k.user_id = u.id
        WHERE k.key_hash = ? AND k.disabled = 0");
    $stmt->execute([$hash]);
    $key = $stmt->fetch();
    if ($key) {
        // Update last_used_at
        $db->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$key['id']]);
    }
    return $key ?: null;
}

function revokeApiKey(int $keyId): void {
    getMySQL()->prepare('UPDATE api_keys SET disabled = 1 WHERE id = ?')->execute([$keyId]);
}

function getUserApiKeys(int $userId): array {
    $stmt = getMySQL()->prepare('SELECT * FROM api_keys WHERE user_id = ? AND disabled = 0 ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ── Rate Log ──────────────────────────────────────────────────

function logRateUsage(?int $userId, ?int $apiKeyId, string $endpoint, string $method, string $ip): void {
    $db = getMySQL();
    $stmt = $db->prepare('INSERT INTO rate_log (user_id, api_key_id, endpoint, method, ip, date) VALUES (?, ?, ?, ?, ?, CURDATE())');
    $stmt->execute([$userId, $apiKeyId, $endpoint, $method, $ip]);
}

function getUserDailyUsage(int $userId): int {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT COUNT(*) FROM rate_log WHERE user_id = ? AND date = CURDATE()');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function findUserByVerificationToken(string $token): ?array {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT * FROM users WHERE verification_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function findUserByResetToken(string $token): ?array {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getUsers(): array {
    $db = getMySQL();
    return $db->query('SELECT id, email, nome, cognome, tier, daily_quota, email_verified, disabled, created_at, updated_at FROM users ORDER BY created_at DESC')->fetchAll();
}

function getUsersDailyUsage(): array {
    $db = getMySQL();
    return $db->query('SELECT user_id, COUNT(*) as cnt FROM rate_log WHERE date = CURDATE() GROUP BY user_id')->fetchAll() ?: [];
}

function generateApiKey(): array {
    $key = 'sk-' . bin2hex(random_bytes(24));
    $hash = hash('sha256', $key);
    $prefix = 'sk-' . substr($key, 3, 8);
    return ['key' => $key, 'hash' => $hash, 'prefix' => $prefix];
}

// ── Affiliate Links ───────────────────────────────────────────

function getAffiliateLink(string $tariffId): ?string {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT affiliate_url FROM affiliate_links WHERE tariff_id = ? AND is_active = 1');
    $stmt->execute([$tariffId]);
    $url = $stmt->fetchColumn();
    return $url ?: null;
}

function getAllAffiliateLinks(): array {
    return getMySQL()->query('SELECT * FROM affiliate_links ORDER BY supplier, tariff_name')->fetchAll();
}

function upsertAffiliateLink(string $tariffId, string $url, string $supplier = '', string $tariffName = '', string $commodity = '', string $network = ''): void {
    $db = getMySQL();
    $stmt = $db->prepare('INSERT INTO affiliate_links (tariff_id, supplier, tariff_name, commodity, affiliate_url, network)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE affiliate_url = VALUES(affiliate_url), network = VALUES(network), is_active = 1');
    $stmt->execute([$tariffId, $supplier, $tariffName, $commodity ?: null, $url, $network ?: null]);
}

function deleteAffiliateLink(string $tariffId): void {
    getMySQL()->prepare('UPDATE affiliate_links SET is_active = 0 WHERE tariff_id = ?')->execute([$tariffId]);
}

// ── Brand Affiliate Defaults ────────────────────────────────────

function getBrandAffiliateUrl(string $brand): ?string {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT default_url FROM brand_affiliates WHERE brand = ?');
    $stmt->execute([$brand]);
    $url = $stmt->fetchColumn();
    return $url ?: null;
}

function getBrandAffiliateData(string $brand): ?array {
    $db = getMySQL();
    $stmt = $db->prepare('SELECT * FROM brand_affiliates WHERE brand = ?');
    $stmt->execute([$brand]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getAllBrandAffiliates(): array {
    return getMySQL()->query('SELECT * FROM brand_affiliates ORDER BY brand')->fetchAll();
}

function upsertBrandAffiliate(string $brand, string $url, string $network = '', ?string $impressionUrl = null): void {
    $db = getMySQL();
    $stmt = $db->prepare('INSERT INTO brand_affiliates (brand, default_url, impression_url, network)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE default_url = VALUES(default_url), impression_url = VALUES(impression_url), network = VALUES(network)');
    $stmt->execute([$brand, $url, $impressionUrl, $network ?: null]);
}

function deleteBrandAffiliate(string $brand): void {
    getMySQL()->prepare('DELETE FROM brand_affiliates WHERE brand = ?')->execute([$brand]);
}
