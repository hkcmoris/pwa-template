<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/jwt.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/assets.php';

$namespaces = [
    'Components\\' => __DIR__ . '/lib/Components/',
    'Definitions\\' => __DIR__ . '/lib/Definitions/',
    'Images\\' => __DIR__ . '/lib/Images/',
    'Editor\\' => __DIR__ . '/lib/Editor/',
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
