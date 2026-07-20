<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster;

class PosterConfig
{
    private static ?array $config = null;
    private static int $loadedMtime = 0;

    public static function load(?string $path = null): array
    {
        $defaultPath = dirname(__DIR__) . '/config/poster.php';
        $resolvedPath = $path ?? self::findProjectConfig() ?? $defaultPath;

        $currentMtime = is_file($resolvedPath) ? (int) filemtime($resolvedPath) : 0;
        if (self::$config !== null && $path === null && $currentMtime === self::$loadedMtime) {
            return self::$config;
        }

        self::$config = require $resolvedPath;
        self::$loadedMtime = $currentMtime;
        return self::$config;
    }

    private static function findProjectConfig(): ?string
    {
        $projectRoot = dirname(__DIR__, 3);
        foreach ([
            $projectRoot . '/config/poster.php',
            $projectRoot . '/config/autoload/poster.php',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }
        return $config;
    }

    public static function merge(array $overrides): array
    {
        self::load();
        self::$config = array_replace_recursive(self::$config ?? [], $overrides);
        return self::$config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }
}
