<?php
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function generate_jwt(array $payload, string $secret, int $exp = 3600): string {
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $payload['exp'] = time() + $exp;
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];
    $signing_input = implode('.', $segments);
    $signature = base64url_encode(hash_hmac('sha256', $signing_input, $secret, true));
    $segments[] = $signature;
    return implode('.', $segments);
}

function verify_jwt(string $jwt, string $secret): array|false {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return false;
    }
    [$headb64, $bodyb64, $sigb64] = $parts;
    $signature = base64url_encode(hash_hmac('sha256', "$headb64.$bodyb64", $secret, true));
    if (!hash_equals($signature, $sigb64)) {
        return false;
    }
    $payload = json_decode(base64url_decode($bodyb64), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
        return false;
    }
    return $payload;
}
