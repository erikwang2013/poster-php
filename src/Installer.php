<?php

namespace Erikwang2013\Poster;

class Installer
{
    public static function copyConfig(): void
    {
        $source = dirname(__DIR__) . '/config/poster.php';
        if (!is_file($source)) {
            return;
        }

        $projectRoot = getcwd();

        // Standard location (Laravel / ThinkPHP / Webman)
        self::publish($source, $projectRoot . '/config/poster.php');

        // Hyperf convention
        self::publish($source, $projectRoot . '/config/autoload/poster.php');
    }

    private static function publish(string $source, string $dest): void
    {
        if (is_file($dest)) {
            return;
        }
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        copy($source, $dest);
    }
}
