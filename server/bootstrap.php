<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/jwt.php';
require_once __DIR__ . '/lib/definitions.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/images.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Components\\';
    $baseDir = __DIR__ . '/lib/Components/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
