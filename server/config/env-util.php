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
        if (defined('JWT_SECRET')) {
            $secret = constant('JWT_SECRET');
            if (is_string($secret) && $secret !== '') {
                return $secret;
            }
        }

        throw new RuntimeException('JWT_SECRET is not defined or not a non-empty string.');
    }
}
