<?php

namespace Erikwang2013\Poster;

class Installer
{
    public static function copyConfig(): void
    {
        $source = dirname(__DIR__) . '/config/poster.php';
        $dest = getcwd() . '/config/poster.php';

        if (is_file($source) && !is_file($dest)) {
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            copy($source, $dest);
        }
    }
}
