<?php

declare(strict_types=1);

const CSRF_SESSION_KEY = '_csrf_token';
const CSRF_COOKIE_KEY = '_csrf_token';

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

function csrf_cookie_key(): string
{
    return CSRF_COOKIE_KEY;
}

function csrf_secure_cookies(): bool
{
    $secureOverride = getenv('SESSION_COOKIE_SECURE');
    if (is_string($secureOverride) && $secureOverride !== '') {
        $normalized = strtolower(trim($secureOverride));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $requestScheme = $_SERVER['REQUEST_SCHEME'] ?? '';
    if (is_string($requestScheme) && strtolower($requestScheme) === 'https') {
        return true;
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') {
        return true;
    }

    if (!app_is_dev()) {
        log_message("[csrf_secure_cookies()] Non-dev environment without HTTPS indicators; using insecure session cookie. Set SESSION_COOKIE_SECURE=1 to force secure cookies.", 'WARN');
    }

    return false;
}


function csrf_cookie_samesite(): string
{
    $sameSiteOverride = getenv('SESSION_COOKIE_SAMESITE');
    if (is_string($sameSiteOverride) && $sameSiteOverride !== '') {
        $normalized = strtolower(trim($sameSiteOverride));
        if ($normalized === 'none') {
            return 'None';
        }
        if ($normalized === 'strict') {
            return 'Strict';
        }
        if ($normalized === 'lax') {
            return 'Lax';
        }
    }

    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if (is_string($fetchSite) && in_array(strtolower($fetchSite), ['cross-site', 'none'], true)) {
        return 'None';
    }

    return 'Lax';
}

function csrf_ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        log_message("[csrf_ensure_session()] Session already active for CSRF protection");
        return;
    }
    log_message("[csrf_ensure_session()] Starting session for CSRF protection");
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
    log_message("[csrf_ensure_session()] Session cookie parameters: lifetime={$params['lifetime']}, path={$path}, domain={$params['domain']}, secure=" . ($params['secure'] ? 'true' : 'false') . ", httponly=" . ($params['httponly'] ? 'true' : 'false') . ", samesite={$params['samesite']}");
    log_message("[csrf_ensure_session()] Request context: HTTPS=" . (isset($_SERVER['HTTPS']) ? (string) $_SERVER['HTTPS'] : '') . ", REQUEST_SCHEME=" . (isset($_SERVER['REQUEST_SCHEME']) ? (string) $_SERVER['REQUEST_SCHEME'] : '') . ", X_FORWARDED_PROTO=" . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] : '') . ", SEC_FETCH_SITE=" . (isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? (string) $_SERVER['HTTP_SEC_FETCH_SITE'] : ''));
    if (headers_sent($file, $line)) {
        log_message("[csrf_ensure_session()] Headers already sent in $file on line $line", 'WARN');
    }
    $sameSite = csrf_cookie_samesite();
    $secureCookies = csrf_secure_cookies() || $sameSite === 'None';

    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => $path,
        'domain' => $params['domain'],
        'secure' => $secureCookies,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
    session_start();
}


function csrf_store_cookie_token(string $token): void
{
    if ($token === '' || headers_sent()) {
        return;
    }

    $base = defined('BASE_PATH') ? trim((string) BASE_PATH, '/') : '';
    $path = $base !== '' ? '/' . $base : '/';
    $sameSite = csrf_cookie_samesite();
    $secureCookies = csrf_secure_cookies() || $sameSite === 'None';

    setcookie(csrf_cookie_key(), $token, [
        'expires' => 0,
        'path' => $path,
        'domain' => '',
        'secure' => $secureCookies,
        'httponly' => false,
        'samesite' => $sameSite,
    ]);
}

function csrf_generate_token(): string
{
    log_message("[csrf_generate_token()] Generating new CSRF token");
    return bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    log_message("[csrf_token()] Retrieving CSRF token from session");
    csrf_ensure_session();
    $key = csrf_session_key();
    log_message("[csrf_token()] Looking for CSRF token in session key '{$key}'");
    $existing = $_SESSION[$key] ?? '';
    log_message("[csrf_token()] Existing CSRF token in session: " . (is_string($existing) && $existing !== '' ? $existing : 'none'));
    if (!is_string($existing) || $existing === '') {
        $cookieToken = $_COOKIE[csrf_cookie_key()] ?? '';
        if (is_string($cookieToken) && $cookieToken !== '') {
            log_message("[csrf_token()] Rehydrating CSRF token from CSRF cookie into session", 'WARN');
            $existing = $cookieToken;
        } else {
            log_message("[csrf_token()] No existing CSRF token found in session, generating new token");
            $existing = csrf_generate_token();
            log_message("[csrf_token()] Storing new CSRF token in session: {$existing}");
        }

        $_SESSION[$key] = $existing;
    }

    csrf_store_cookie_token($existing);
    return $existing;
}

function csrf_token_if_active(): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        log_message("[csrf_token_if_active()] Session active, returning CSRF token");
        return csrf_token();
    }

    $sessionName = session_name();
    if (!is_string($sessionName)) {
        log_message("[csrf_token_if_active()] Unable to determine session name, returning empty CSRF token", 'WARN');
        return '';
    }

    $sessionId = $_COOKIE[$sessionName] ?? '';
    if (!is_string($sessionId) || $sessionId === '') {
        log_message("[csrf_token_if_active()] No session cookie found, returning empty CSRF token", 'WARN');
        return '';
    }

    log_message("[csrf_token_if_active()] Session cookie '{$sessionName}' found, returning CSRF token");
    return csrf_token();
}

function csrf_field(): string
{
    log_message("[csrf_field()] Generating CSRF hidden input field for forms");
    $token = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}


/**
 * @param array<string, mixed>|null $body
 * @return list<string>
 */
function csrf_extract_candidates(?array $body = null): array
{
    log_message("[csrf_extract_candidates()] Extracting CSRF token candidates from body, POST parameters, and header");

    $tokens = [];
    if ($body !== null && isset($body['_csrf']) && is_string($body['_csrf'])) {
        $bodyToken = trim($body['_csrf']);
        if ($bodyToken !== '') {
            $tokens[] = $bodyToken;
            log_message("[csrf_extract_candidates()] CSRF token candidate found in body");
        }
    }

    if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
        $postToken = trim($_POST['_csrf']);
        if ($postToken !== '' && !in_array($postToken, $tokens, true)) {
            $tokens[] = $postToken;
            log_message("[csrf_extract_candidates()] CSRF token candidate found in POST parameters");
        }
    }

    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header)) {
        $headerToken = trim($header);
        if ($headerToken !== '' && !in_array($headerToken, $tokens, true)) {
            $tokens[] = $headerToken;
            log_message("[csrf_extract_candidates()] CSRF token candidate found in header");
        }
    }

    return $tokens;
}

/**
 * @param array<string, mixed>|null $body
 * @return array<string, mixed>|null
 */
function csrf_redact_body(?array $body): ?array
{
    if ($body === null) {
        return null;
    }

    $redacted = $body;
    foreach (['password', 'pass', 'token', 'refresh_token', '_csrf', 'csrf'] as $sensitiveKey) {
        if (isset($redacted[$sensitiveKey])) {
            $redacted[$sensitiveKey] = '[REDACTED]';
        }
    }

    return $redacted;
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_extract_from_request(?array $body = null): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    log_message("[csrf_extract_from_request()] Extracting CSRF token from request. Checking header 'X-CSRF-Token', body parameter '_csrf', and POST parameter '_csrf'");
    if (is_string($header) && $header !== '') {
        log_message("[csrf_extract_from_request()] CSRF token found in request header: {$header}");
        return trim($header);
    }
    if ($body !== null && isset($body['_csrf']) && is_string($body['_csrf'])) {
        log_message("[csrf_extract_from_request()] CSRF token found in request body: {$body['_csrf']}");
        return trim($body['_csrf']);
    }
    if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
        log_message("[csrf_extract_from_request()] CSRF token found in POST parameters: {$_POST['_csrf']}");
        return trim($_POST['_csrf']);
    }
    log_message("[csrf_extract_from_request()] CSRF token not found in request headers, body, or POST parameters", 'WARN');
    return '';
}

function csrf_verify(?string $token, bool $regenerate = false): bool
{
    csrf_ensure_session();
    if (!is_string($token) || $token === '') {
        log_message("[csrf_verify()] No CSRF token provided in request", 'WARN');
        return false;
    }
    $stored = $_SESSION[csrf_session_key()] ?? '';
    $cookieToken = $_COOKIE[csrf_cookie_key()] ?? '';

    $sessionValid = is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    if ($sessionValid) {
        if ($regenerate) {
            log_message("[csrf_verify()] CSRF token valid via session token, regenerating token for next request");
            $_SESSION[csrf_session_key()] = csrf_generate_token();
        }
        return true;
    }

    $cookieValid = is_string($cookieToken) && $cookieToken !== '' && hash_equals($cookieToken, $token);
    if ($cookieValid) {
        log_message("[csrf_verify()] CSRF token valid via CSRF cookie fallback; rehydrating session token", 'WARN');
        $_SESSION[csrf_session_key()] = $token;
        csrf_store_cookie_token($token);
        return true;
    }

    if (!is_string($stored) || $stored === '') {
        log_message("[csrf_verify()] No CSRF token stored in session for verification", 'WARN');
    }

    return false;
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_validate_request(?array $body = null, bool $regenerate = false): bool
{
    $candidates = csrf_extract_candidates($body);
    if ($candidates === []) {
        log_message("[csrf_validate_request()] CSRF token extraction failed, no token to validate", 'WARN');
        return false;
    }

    foreach ($candidates as $index => $token) {
        log_message("[csrf_validate_request()] Validating CSRF token candidate #" . ($index + 1));
        if (csrf_verify($token, $regenerate)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed>|null $body
 */
function csrf_require_valid(?array $body = null, string $responseType = 'json'): void
{
    if (csrf_validate_request($body)) {
        log_message("[csrf_require_valid()] CSRF token valid, proceeding with request");
        return;
    }
    log_message("[csrf_require_valid()] CSRF token invalid or missing, rejecting request. " . json_encode(csrf_redact_body($body)), 'ERROR');
    log_message("[csrf_require_valid()] Responding with 419 status code for invalid CSRF token", 'ERROR');
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
