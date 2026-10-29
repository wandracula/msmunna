<?php
declare(strict_types=1);

/*
 * Configure os dados do MySQL do seu provedor.
 * Em hospedagem compartilhada, use os dados exibidos no painel do provedor.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'seu_banco';
const DB_USER = 'seu_usuario';
const DB_PASS = 'sua_senha';

const APP_NAME = 'Meu MSN';
const MAX_AVATAR_BYTES = 2 * 1024 * 1024; // 2 MB

session_name('meu_msn_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function logged_in(): bool { return isset($_SESSION['user_id']); }
function require_login(): void {
    if (!logged_in()) json_response(['ok'=>false, 'error'=>'Faça login novamente.'], 401);
}
function json_response(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        json_response(['ok'=>false, 'error'=>'Sessão expirada. Atualize a página e tente novamente.'], 419);
    }
}
