<?php

/**
 * Configuration applicative globale.
 * - Chargement simplifié des variables depuis .env
 * - Mode d'exécution: local|central
 * - Paramètres de synchronisation inter-instances
 */

if (!function_exists('app_load_env')) {
    function app_load_env()
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }

        $loaded = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (!is_file($envFile)) {
            return $loaded;
        }

        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $loaded;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");

            if ($key !== '') {
                $loaded[$key] = $value;
            }
        }

        return $loaded;
    }
}

if (!function_exists('app_env')) {
    function app_env($key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false && $value !== null && $value !== '') {
            return $value;
        }

        $loaded = app_load_env();
        if (array_key_exists($key, $loaded)) {
            return $loaded[$key];
        }

        return $default;
    }
}

if (!function_exists('app_mode')) {
    function app_mode()
    {
        $mode = strtolower(trim((string) app_env('APP_MODE', 'local')));
        return in_array($mode, ['local', 'central'], true) ? $mode : 'local';
    }
}

if (!function_exists('is_central_mode')) {
    function is_central_mode()
    {
        return app_mode() === 'central';
    }
}

if (!function_exists('app_bool_env')) {
    function app_bool_env($key, $default = false)
    {
        $value = app_env($key, null);
        if ($value === null) {
            return $default;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('sync_instance_id')) {
    function sync_instance_id()
    {
        $configured = trim((string) app_env('SYNC_INSTANCE_ID', ''));
        $hostName = trim((string) php_uname('n'));
        $hostSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($hostName)) ?: 'client';
        $hostSlug = trim($hostSlug, '-_');
        if ($hostSlug === '') {
            $hostSlug = 'client';
        }

        $configuredSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($configured)) ?: '';
        $configuredSlug = trim($configuredSlug, '-_');

        if ($configuredSlug === '' || in_array($configuredSlug, ['local', 'client', 'site-local-01', 'default'], true)) {
            return substr($hostSlug, 0, 50);
        }

        if (str_contains($configuredSlug, $hostSlug)) {
            return substr($configuredSlug, 0, 50);
        }

        return substr($configuredSlug . '-' . $hostSlug, 0, 50);
    }
}

if (!function_exists('sync_config')) {
    function sync_config()
    {
        return [
            'instance_id' => sync_instance_id(),
            'central_url' => rtrim((string) app_env('SYNC_CENTRAL_URL', ''), '/'),
            'shared_token' => trim((string) app_env('SYNC_SHARED_TOKEN', '')),
            'timeout' => max(10, (int) app_env('SYNC_TIMEOUT', 45)),
            'connect_timeout' => max(3, (int) app_env('SYNC_CONNECT_TIMEOUT', 10)),
            'max_retries' => min(5, max(1, (int) app_env('SYNC_MAX_RETRIES', 3))),
            'retry_delay_ms' => max(250, (int) app_env('SYNC_RETRY_DELAY_MS', 1200)),
            'require_https' => app_bool_env('SYNC_REQUIRE_HTTPS', true),
            'site_code' => trim((string) app_env('SYNC_SITE_CODE', sync_instance_id())),
            'site_name' => trim((string) app_env('SYNC_SITE_NAME', '')),
            'site_region' => trim((string) app_env('SYNC_SITE_REGION', '')),
            'site_timezone' => trim((string) app_env('SYNC_SITE_TIMEZONE', date_default_timezone_get() ?: 'UTC')),
            'server_region' => trim((string) app_env('SYNC_SERVER_REGION', 'NEW YORK')),
            'allowed_tables' => ['equipes', 'controles']
        ];
    }
}
