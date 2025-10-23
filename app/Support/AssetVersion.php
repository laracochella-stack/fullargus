<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Genera URLs de assets con versionado automático basado en la fecha de modificación.
 */
class AssetVersion
{
    /**
     * Cache local de timestamps calculados durante la petición actual.
     *
     * @var array<string, int|null>
     */
    private static array $timestampCache = [];

    /**
     * Devuelve la URL del asset con el parámetro de versión correspondiente.
     *
     * @param string $publicPath Ruta pública relativa al proyecto o URL absoluta.
     */
    public static function url(string $publicPath): string
    {
        if (self::isExternal($publicPath)) {
            return $publicPath;
        }

        $timestamp = self::modifiedTime($publicPath);
        if ($timestamp === null) {
            return $publicPath;
        }

        $separator = strpos($publicPath, '?') !== false ? '&' : '?';

        return $publicPath . $separator . 'v=' . $timestamp;
    }

    private static function isExternal(string $path): bool
    {
        if (strncmp($path, '//', 2) === 0) {
            return true;
        }

        $scheme = parse_url($path, PHP_URL_SCHEME);

        return $scheme !== null && $scheme !== '';
    }

    private static function modifiedTime(string $publicPath): ?int
    {
        if (array_key_exists($publicPath, self::$timestampCache)) {
            return self::$timestampCache[$publicPath];
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $absolutePath = $basePath . '/' . ltrim($publicPath, '/');

        $mtime = @filemtime($absolutePath);
        if ($mtime === false) {
            self::$timestampCache[$publicPath] = null;

            return null;
        }

        self::$timestampCache[$publicPath] = $mtime;

        return $mtime;
    }
}
