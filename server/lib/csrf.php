<?php

declare(strict_types=1);

const CSRF_SESSION_KEY = '_csrf_token';

function app_env_value(): string
{
    $env = getenv('APP_ENV');
    if (is_string($env) && $env !== '') {
        return strtolower($env);
    }
    if (defined('APP_ENV')) {
        return strtolower((string) APP_ENV);
    }
    return 'dev';
}

function app_is_dev(): bool
{
    return app_env_value() === 'dev';
}

function csrf_session_key(): string
{
    return CSRF_SESSION_KEY;
}

function csrf_secure_cookies(): bool
{
    if (!app_is_dev()) {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') {
        return true;
    }
    return false;
}

function csrf_ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    /**
     * @var array{
     *     lifetime: int<0, max>,
     *     path: non-falsy-string,
     *     domain: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'
     * } $params
     */
    $params = session_get_cookie_params();
    $base = defined('BASE_PATH') ? trim((string) BASE_PATH, '/') : '';
    $path = $params['path'];
    if ($path === '/' && $base !== '') {
        $path = '/' . $base;
    }
    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => $path,
        'domain' => $params['domain'],
        'secure' => csrf_secure_cookies(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    csrf_ensure_session();
    $key = csrf_session_key();
    $existing = $_SESSION[$key] ?? '';
    if (!is_string($existing) || $existing === '') {
        $existing = csrf_generate_token();
        $_SESSION[$key] = $existing;
    }
    return $existing;
}

function csrf_token_if_active(): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return csrf_token();
    }

    $sessionName = session_name();
    if (!is_string($sessionName)) {
        return '';
    }

    $sessionId = $_COOKIE[$sessionName] ?? '';
    if (!is_string($sessionId) || $sessionId === '') {
        return '';
    }

    return csrf_token();
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_extract_from_request(?array $body = null): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return trim($header);
    }
    if ($body !== null && isset($body['_csrf']) && is_string($body['_csrf'])) {
        return trim($body['_csrf']);
    }
    if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
        return trim($_POST['_csrf']);
    }
    return '';
}

function csrf_verify(?string $token, bool $regenerate = false): bool
{
    csrf_ensure_session();
    if (!is_string($token) || $token === '') {
        return false;
    }
    $stored = $_SESSION[csrf_session_key()] ?? '';
    if (!is_string($stored) || $stored === '') {
        return false;
    }
    $valid = hash_equals($stored, $token);
    if ($valid && $regenerate) {
        $_SESSION[csrf_session_key()] = csrf_generate_token();
    }
    return $valid;
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_validate_request(?array $body = null, bool $regenerate = false): bool
{
    $token = csrf_extract_from_request($body);
    if ($token === '') {
        return false;
    }
    return csrf_verify($token, $regenerate);
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_require_valid(?array $body = null, string $responseType = 'json'): void
{
    if (csrf_validate_request($body)) {
        return;
    }
    http_response_code(419);
    if (!headers_sent()) {
        if ($responseType === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
        }
    }
    if ($responseType === 'json') {
        echo json_encode(['error' => 'Invalid CSRF token']);
    } else {
        echo 'Invalid CSRF token';
    }
    exit;
}
