<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/jwt.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';

// Start session early so CSRF can safely read/write cookies/tokens later.
csrf_ensure_session();

if (!headers_sent()) {
    $appEnvValue = getenv('APP_ENV');
    if (!is_string($appEnvValue) || $appEnvValue === '') {
        $appEnvValue = defined('APP_ENV') ? (string) APP_ENV : 'dev';
    }
    $isDevEnv = ($appEnvValue === 'dev');
    $cspNonce = base64_encode(random_bytes(16));
    $GLOBALS['csp_nonce'] = $cspNonce;

    $scriptSrc = ["'self'", "'nonce-{$cspNonce}'"];
    $styleSrc = ["'self'"];
    $imgSrc = ["'self'", "data:", "blob:"];
    $fontSrc = ["'self'", "data:"];
    $connectSrc = ["'self'"];

    if ($isDevEnv) {
        $scriptSrc[] = 'http://localhost:5173';
        $styleSrc[] = "'unsafe-inline'";
        $styleSrc[] = 'http://localhost:5173';
        $imgSrc[] = 'http://localhost:5173';
        $fontSrc[] = 'http://localhost:5173';
        $connectSrc[] = 'http://localhost:5173';
        $connectSrc[] = 'ws://localhost:5173';
    } else {
        $styleSrc[] = "'nonce-{$cspNonce}'";
    }

    $cspDirectives = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        'script-src ' . implode(' ', $scriptSrc),
        'style-src ' . implode(' ', $styleSrc),
        'img-src ' . implode(' ', $imgSrc),
        'font-src ' . implode(' ', $fontSrc),
        'connect-src ' . implode(' ', $connectSrc),
        "trusted-types default",
        "upgrade-insecure-requests",
    ];

    header('Content-Security-Policy: ' . implode('; ', $cspDirectives));
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Frame-Options: DENY');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        );

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Enable gzip for dynamic output if the server doesn't do it
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', '1');
        ini_set('zlib.output_compression_level', '5'); // 1-9
    }
}

require_once __DIR__ . '/lib/assets.php';

$namespaces = [
    'Shared\\' => __DIR__ . '/lib/Shared/',
    'Components\\' => __DIR__ . '/lib/Components/',
    'Configuration\\' => __DIR__ . '/lib/Configuration/',
    'Definitions\\' => __DIR__ . '/lib/Definitions/',
    'Images\\' => __DIR__ . '/lib/Images/',
    'Editor\\' => __DIR__ . '/lib/Editor/',
    'Administration\\' => __DIR__ . '/lib/Administration/',
];

spl_autoload_register(static function (string $class) use ($namespaces): void {
    foreach ($namespaces as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }

        return;
    }
});
