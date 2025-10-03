<?php

if (!function_exists('config_resolve_env')) {
    /**
     * @param array<string, mixed> $env
     * @param string               $key
     * @param mixed                $default
     * @return mixed
     */
    function config_resolve_env(array $env, string $key, $default = null)
    {
        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('config_jwt_secret')) {
    function config_jwt_secret(): string
    {
        if (!defined('JWT_SECRET')) {
            throw new RuntimeException('JWT_SECRET is not defined or not a non-empty string.');
        }

        /** @var mixed $secret */
        $secret = constant('JWT_SECRET');
        if (!is_string($secret)) {
            throw new RuntimeException('JWT_SECRET must resolve to a string value.');
        }

        $normalizedSecret = trim($secret);
        if ($normalizedSecret === '') {
            throw new RuntimeException('JWT_SECRET must not be an empty string.');
        }

        return $normalizedSecret;
    }
}
