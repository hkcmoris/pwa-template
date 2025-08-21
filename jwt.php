<?php
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
