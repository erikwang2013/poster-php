<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * 原生 PHP 入口（native.php）测试：证明在完全没有 Composer / vendor/ 的环境下可用。
 */

namespace Erikwang2013\Poster\Tests;

use PHPUnit\Framework\TestCase;

class NativeEntryTest extends TestCase
{
    /** 验证无 Composer：裸拷包目录后仅 require native.php，自动加载、全局函数与出图全通 */
    public function testNativeEntryWorksWithoutComposer(): void
    {
        $root = dirname(__DIR__);
        $tmp = sys_get_temp_dir() . '/poster-native-' . uniqid();

        // 复制包本体，但不含 vendor/、tests/、docs/，也不含 40MB 字体（本用例只画拉丁字母，
        // 字体缺失时驱动会退回内置位图字体，恰好覆盖无字体场景）
        mkdir($tmp . '/src', 0777, true);
        copy("$root/native.php", "$tmp/native.php");
        copy("$root/helpers.php", "$tmp/helpers.php");
        foreach (['Captcha', 'Poster', 'Drivers', 'Storage', 'Qrcode', 'Adapters'] as $dir) {
            $this->copyDir("$root/src/$dir", "$tmp/src/$dir");
        }
        copy("$root/src/PosterConfig.php", "$tmp/src/PosterConfig.php");
        $this->copyDir("$root/config", "$tmp/config");
        $this->copyDir("$root/assets", "$tmp/assets");

        $this->assertDirectoryDoesNotExist("$tmp/vendor", '临时目录必须没有 vendor/，否则测不出原生场景');

        $script = <<<'PHP'
            require $argv[1] . '/native.php';
            if (!function_exists('captcha_create') || !function_exists('poster_create')) {
                fwrite(STDERR, "helpers 未加载\n"); exit(2);
            }
            $builder = poster_create(120, 60);
            $builder->background('#FFFFFF')->addText('OK', ['x' => 6, 'y' => 32, 'size' => 16])->save($argv[2], 90);
            echo is_file($argv[2]) ? 'RENDERED' : 'FAILED';
            PHP;

        $out = [];
        $rc = 0;
        exec(sprintf('%s -r %s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script),
            escapeshellarg($tmp), escapeshellarg($tmp . '/out.jpg')), $out, $rc);

        try {
            $this->assertSame(0, $rc, "子进程失败：\n" . implode("\n", $out));
            $this->assertContains('RENDERED', $out, '原生入口未产出图片');
            $this->assertGreaterThan(200, filesize($tmp . '/out.jpg'));
        } finally {
            $this->removeDir($tmp);
        }
    }

    /** 验证重复引入、以及与其它自动加载器共存时不冲突 */
    public function testNativeEntryIsIdempotent(): void
    {
        $script = <<<'PHP'
            require $argv[1] . '/native.php';
            require $argv[1] . '/native.php';           // 重复引入
            spl_autoload_register(function ($c) {});    // 模拟项目自带自动加载器
            echo class_exists('Erikwang2013\Poster\Poster\PosterBuilder') ? 'OK' : 'FAIL';
            PHP;

        $out = [];
        $rc = 0;
        exec(sprintf('%s -r %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script),
            escapeshellarg(dirname(__DIR__))), $out, $rc);

        $this->assertSame(0, $rc, implode("\n", $out));
        $this->assertContains('OK', $out);
    }

    private function copyDir(string $src, string $dst): void
    {
        mkdir($dst, 0777, true);
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            is_dir("$src/$item") ? $this->copyDir("$src/$item", "$dst/$item") : copy("$src/$item", "$dst/$item");
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            is_dir("$dir/$item") ? $this->removeDir("$dir/$item") : unlink("$dir/$item");
        }
        rmdir($dir);
    }
}
