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
    private static ?string $loadedPath = null;

    public static function load(?string $path = null): array
    {
        $defaultPath = dirname(__DIR__) . '/config/poster.php';
        $resolvedPath = $path ?? self::$loadedPath ?? self::findProjectConfig() ?? $defaultPath;

        $currentMtime = is_file($resolvedPath) ? (int) filemtime($resolvedPath) : 0;
        if (self::$config !== null
            && self::$loadedPath === $resolvedPath
            && $currentMtime === self::$loadedMtime) {
            return self::$config;
        }

        self::$config = require $resolvedPath;
        self::$loadedMtime = $currentMtime;
        self::$loadedPath = $resolvedPath;
        return self::$config;
    }

    private static function findProjectConfig(): ?string
    {
        // 项目根推导：与 Installer::copyConfig 发布位置一致，取当前工作目录；
        // 兜底 src/ 上一级（经典布局 项目/包根/src 时即项目根）。
        $roots = array_unique(array_filter([getcwd() ?: '', dirname(__DIR__, 2)]));
        foreach ($roots as $projectRoot) {
            foreach ([
                $projectRoot . '/config/poster.php',
                $projectRoot . '/config/autoload/poster.php',
            ] as $f) {
                if (is_file($f)) {
                    return $f;
                }
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
        self::$loadedPath = null;
    }
}
