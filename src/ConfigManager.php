<?php

class ConfigManager
{
    private static ?array $availableServers = null;
    private static ?array $activeConfig = null;

    public static function getConfigsDir(): string
    {
        return __DIR__ . '/../configs';
    }

    public static function getAvailableServers(): array
    {
        if (self::$availableServers !== null) {
            return self::$availableServers;
        }

        $dir = self::getConfigsDir();
        $files = glob($dir . '/*.php');
        if ($files === false || count($files) === 0) {
            throw new RuntimeException('No server configuration files found in ' . $dir);
        }

        $servers = [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            if ($key === '' || $key[0] === '.') {
                continue;
            }
            $config = require $file;
            $servers[$key] = [
                'key' => $key,
                'name' => $config['comment'] ?? $config['interface'] ?? $key,
                'host' => $config['host'] ?? '?',
            ];
        }

        if (count($servers) === 0) {
            throw new RuntimeException('No valid server configuration files found in ' . $dir);
        }

        uksort($servers, 'strcasecmp');
        self::$availableServers = $servers;
        return $servers;
    }

    public static function getActiveServerKey(): string
    {
        if (isset($_GET['server']) && is_string($_GET['server']) && $_GET['server'] !== '') {
            $key = $_GET['server'];
            $servers = self::getAvailableServers();
            if (isset($servers[$key])) {
                return $key;
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['active_server'])) {
            $key = $_SESSION['active_server'];
            $servers = self::getAvailableServers();
            if (isset($servers[$key])) {
                return $key;
            }
        }

        $servers = self::getAvailableServers();
        reset($servers);
        return key($servers);
    }

    public static function serverExists($key): bool
    {
        if (!is_string($key)) {
            return false;
        }
        if (self::$availableServers === null) {
            self::$availableServers = self::getAvailableServers();
        }
        return isset(self::$availableServers[$key]);
    }

    /**
     * Validate the ?server= request parameter.
     *
     * Single source of truth for the entrypoints (api.php, public-api.php):
     * an unknown value must be rejected instead of silently falling back to
     * the default config.
     *
     * @param mixed $requested Raw $_GET['server'] value (or null when absent).
     * @return string|null Error message when the requested server is unknown,
     *                     null when the value is valid or absent.
     */
    public static function validateRequestedServer($requested): ?string
    {
        if ($requested === null || $requested === '') {
            return null;
        }
        if (!self::serverExists($requested)) {
            return 'Unknown server: ' . (is_string($requested) ? $requested : 'invalid');
        }
        return null;
    }

    public static function resolveConfig(): array
    {
        if (self::$activeConfig !== null) {
            return self::$activeConfig;
        }

        $key = self::getActiveServerKey();
        $dir = self::getConfigsDir();
        $file = $dir . '/' . $key . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("Configuration file not found: " . $file);
        }

        $config = require $file;
        $config['_server_key'] = $key;
        self::$activeConfig = $config;

        return $config;
    }

    public static function persistServerKey(string $key): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['active_server'] = $key;
        }
    }
}
