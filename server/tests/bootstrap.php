<?php
declare(strict_types=1);

// Autoload App\Tests\Support\* test doubles
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Tests\\Support\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = __DIR__ . '/Support/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
