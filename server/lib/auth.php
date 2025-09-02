<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

function refresh_token_random(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function refresh_token_hash(string $token): string {
    return hash('sha256', $token);
}

function create_refresh_token(int $userId, int $ttlSeconds = 1209600): string {
    $token = refresh_token_random();
    $hash = refresh_token_hash($token);
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $db = get_db_connection();
    $stmt = $db->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)');
    $stmt->execute([':user_id' => $userId, ':hash' => $hash, ':expires' => $expires]);
    return $token;
}

function find_valid_refresh_token(string $token): array|false {
    $hash = refresh_token_hash($token);
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT id, user_id, token_hash, expires_at, revoked FROM refresh_tokens WHERE token_hash = :hash LIMIT 1');
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if ((int)$row['revoked'] === 1) return false;
    if (strtotime($row['expires_at']) <= time()) return false;
    return $row;
}

function revoke_refresh_token_by_hash(string $hash): void {
    $db = get_db_connection();
    $stmt = $db->prepare('UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE token_hash = :hash');
    $stmt->execute([':hash' => $hash]);
}

function revoke_refresh_token(string $token): void {
    $hash = refresh_token_hash($token);
    revoke_refresh_token_by_hash($hash);
}

function rotate_refresh_token(string $token, int $userId, int $ttlSeconds = 1209600): string|false {
    $row = find_valid_refresh_token($token);
    if (!$row || (int)$row['user_id'] !== $userId) return false;
    revoke_refresh_token_by_hash($row['token_hash']);
    return create_refresh_token($userId, $ttlSeconds);
}

