<?php

function refresh_token_random(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function refresh_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function create_refresh_token(int $userId, int $ttlSeconds = 1209600): string
{
    $token = refresh_token_random();
    $hash = refresh_token_hash($token);
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $db = get_db_connection();
    $stmt = $db->prepare(
        'INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)'
    );
    $stmt->execute([':user_id' => $userId, ':hash' => $hash, ':expires' => $expires]);
    return $token;
}

/**
 * @return array{id:int,user_id:int,token_hash:string,expires_at:string,revoked:int}|false
 */
function find_valid_refresh_token(string $token)
{
    $hash = refresh_token_hash($token);
    $db = get_db_connection();
    $stmt = $db->prepare(
        'SELECT id, user_id, token_hash, expires_at, revoked FROM refresh_tokens WHERE token_hash = :hash LIMIT 1'
    );
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return false;
    }
    $normalized = [
        'id' => isset($row['id']) ? (int)$row['id'] : 0,
        'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : 0,
        'token_hash' => isset($row['token_hash']) ? (string)$row['token_hash'] : '',
        'expires_at' => isset($row['expires_at']) ? (string)$row['expires_at'] : '',
        'revoked' => isset($row['revoked']) ? (int)$row['revoked'] : 0,
    ];
    if ($normalized['id'] === 0 || $normalized['user_id'] === 0 || $normalized['token_hash'] === '') {
        return false;
    }
    if ($normalized['revoked'] === 1) {
        return false;
    }
    if ($normalized['expires_at'] === '' || strtotime($normalized['expires_at']) <= time()) {
        return false;
    }
    return $normalized;
}

function revoke_refresh_token_by_hash(string $hash): void
{
    $db = get_db_connection();
    $stmt = $db->prepare('UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE token_hash = :hash');
    $stmt->execute([':hash' => $hash]);
}

function revoke_refresh_token(string $token): void
{
    $hash = refresh_token_hash($token);
    revoke_refresh_token_by_hash($hash);
}

/**
 * @return string|false New refresh token string on success, false on failure
 */
function rotate_refresh_token(string $token, int $userId, int $ttlSeconds = 1209600)
{
    $row = find_valid_refresh_token($token);
    if (!$row || $row['user_id'] !== $userId) {
        return false;
    }
    revoke_refresh_token_by_hash($row['token_hash']);
    return create_refresh_token($userId, $ttlSeconds);
}

/**
 * Returns the current authenticated user based on the access token cookie.
 * NOTE: Name intentionally prefixed to avoid clashing with PHP's built-in get_current_user().
 * @return array{id:int,username:string,email:string,role:string}|null
 */
function app_get_current_user(): ?array
{
    $token = $_COOKIE['token'] ?? '';
    if ($token === '') {
        return null;
    }
    $payload = verify_jwt($token, JWT_SECRET);
    if (!$payload || empty($payload['sub'])) {
        return null;
    }
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT id, username, email, role FROM users WHERE id = :id');
        $stmt->execute([':id' => (int)$payload['sub']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'username' => isset($row['username']) ? (string)$row['username'] : '',
            'email' => isset($row['email']) ? (string)$row['email'] : '',
            'role' => isset($row['role']) && $row['role'] !== '' ? (string)$row['role'] : 'user',
        ];
    } catch (Throwable $e) {
        log_message('app_get_current_user error: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}
