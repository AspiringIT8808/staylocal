<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'staylocal_update';
const DB_USER = 'root';
const DB_PASS = '';

function dbEnv(string $name, string $fallback): string
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $fallback : $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = dbEnv('DB_HOST', DB_HOST);
    $port = dbEnv('DB_PORT', DB_PORT);
    $name = dbEnv('DB_NAME', DB_NAME);
    $user = dbEnv('DB_USER', DB_USER);
    $pass = getenv('DB_PASS');
    $pass = $pass === false ? DB_PASS : $pass;

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function pesos(int|float $amount): string
{
    return '₱' . number_format((float) $amount, 0);
}

function adminSessionStart(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('staylocal_admin');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'use_strict_mode' => true,
        ]);
    }
}

function adminIsLoggedIn(): bool
{
    adminSessionStart();
    return isset($_SESSION['admin_id'], $_SESSION['admin_email']);
}

function requireAdmin(): void
{
    if (!adminIsLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminCsrfToken(): string
{
    adminSessionStart();
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function verifyAdminCsrf(?string $token): bool
{
    adminSessionStart();
    return is_string($token) && isset($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $token);
}

function tenantSessionStart(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('staylocal_tenant');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'use_strict_mode' => true,
        ]);
    }
}

function tenantIsLoggedIn(): bool
{
    tenantSessionStart();
    return isset($_SESSION['tenant_id'], $_SESSION['tenant_email']);
}

function requireTenant(): void
{
    if (!tenantIsLoggedIn()) {
        header('Location: tenant-login.php');
        exit;
    }
    try {
        $tenant = currentTenant();
    } catch (Throwable $exception) {
        $tenant = null;
    }
    if (!$tenant) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        header('Location: tenant-login.php?session=expired');
        exit;
    }
}

function tenantCsrfToken(): string
{
    tenantSessionStart();
    if (empty($_SESSION['tenant_csrf'])) $_SESSION['tenant_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['tenant_csrf'];
}

function verifyTenantCsrf(?string $token): bool
{
    tenantSessionStart();
    return is_string($token) && isset($_SESSION['tenant_csrf']) && hash_equals($_SESSION['tenant_csrf'], $token);
}

function currentTenant(): ?array
{
    if (!tenantIsLoggedIn()) return null;
    try {
        $statement = db()->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['tenant_id']]);
        return $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}
