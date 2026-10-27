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

    /**
     * Return a cache key that changes with the deployed static assets, even
     * when code is deployed without changing VERSION.
     */
    public static function assetVersion(): string
    {
        $root = __DIR__ . '/../assets';
        $assets = [
            'css/app.css',
            'css/fonts.css',
            'css/login.css',
            'js/app.js',
            'js/qrcode-generator.min.js',
            'fonts/outfit-latin.woff2',
            'fonts/outfit-latin-ext.woff2',
        ];
        $timestamps = [];

        foreach ($assets as $asset) {
            $path = $root . '/' . $asset;
            $timestamps[] = is_file($path) ? (string)filemtime($path) : '0';
        }

        return self::version() . '-' . implode('-', $timestamps);
    }

    public static function repositoryUrl(): string
    {
        return self::REPOSITORY_URL;
    }
}
