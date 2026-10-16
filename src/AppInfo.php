<?php

/**
 * Static application metadata (name-independent) used by the About dialog.
 */
class AppInfo
{
    public const REPOSITORY_URL = 'https://github.com/rollopack/mikrotik-wireguard';

    /**
     * Read the application version from the VERSION file.
     *
     * @param string|null $file Path override (mainly for tests).
     * @return string Trimmed version, or "unknown" when unavailable.
     */
    public static function version(?string $file = null): string
    {
        $path = $file ?? __DIR__ . '/../VERSION';
        if (!is_file($path)) {
            return 'unknown';
        }

        $version = trim((string)file_get_contents($path));

        return $version === '' ? 'unknown' : $version;
    }

    public static function repositoryUrl(): string
    {
        return self::REPOSITORY_URL;
    }
}
