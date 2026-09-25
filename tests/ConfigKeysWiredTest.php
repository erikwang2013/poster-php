<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * 配置键接线检查：config/poster.php 里声明的每个配置键，必须在 src/（或 helpers.php / native.php）
 * 里被真正读取。历史上有 5 个键（captcha.default_difficulty、image.quality、poster.jpeg_quality、
 * poster.png_compression、captcha.redis.connection）写在配置和文档里却从未被任何代码读取，
 * 用户改了毫无效果；这条用例把该类问题钉死在 CI 里。
 */

namespace Erikwang2013\Poster\Tests;

use PHPUnit\Framework\TestCase;

class ConfigKeysWiredTest extends TestCase
{
    /** 验证配置里的每个叶子键都被代码读取（含通过父级数组整体读取的形式） */
    public function testEveryConfigKeyIsReadSomewhere(): void
    {
        $root = dirname(__DIR__);
        $config = require "$root/config/poster.php";

        $paths = [];
        $walk = function (array $node, string $prefix) use (&$walk, &$paths) {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string) $key : "$prefix.$key";
                if (is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                    $walk($value, $path);           // 关联数组继续下钻
                } elseif (is_array($value)) {
                    $paths[] = $path;               // 顺序数组（词表/风格列表）按整体键算
                } else {
                    $paths[] = $path;
                }
            }
        };
        $walk($config, '');

        // 源码文本（不加载类，避免框架适配层缺依赖）
        $code = '';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/src"));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $code .= file_get_contents($file->getPathname());
            }
        }
        $code .= file_get_contents("$root/helpers.php") . file_get_contents("$root/native.php");

        // 精确收集「被读取的键」：本包 PosterConfig::get('k')，以及适配层 Laravel 的 config('poster.k')
        $reads = [];
        preg_match_all("/PosterConfig::get\('([^']+)'/", $code, $m1);
        $reads = array_merge($reads, $m1[1]);
        preg_match_all("/config\('poster\.([^']+)'/", $code, $m2);
        $reads = array_merge($reads, $m2[1]);

        // 运行时注入型键（对象/服务，无法写进可发布的配置文件）
        $runtimeOnly = ['captcha.cache.pool'];

        $unwired = [];
        foreach ($paths as $path) {
            if (in_array($path, $runtimeOnly, true)) {
                continue;
            }
            foreach ($reads as $read) {
                // 读取了该键本身，或读取了它的父级数组（如 get('captcha.tolerance') 覆盖 tolerance.*）
                if ($read === $path || str_starts_with($path, $read . '.')) {
                    continue 2;
                }
            }
            $unwired[] = $path;
        }

        $this->assertSame(
            [],
            $unwired,
            "以下配置键没有任何代码读取（改了不会生效，应接线或从配置删除）：\n  - " . implode("\n  - ", $unwired)
        );
    }

    /** 验证代码里读取的 captcha.* / poster.* / image.* 键都写在配置文件里（反向：避免隐形键） */
    public function testCodeReadsNoUndocumentedKey(): void
    {
        $root = dirname(__DIR__);
        $config = require "$root/config/poster.php";
        $defined = [];
        $walk = function (array $node, string $prefix) use (&$walk, &$defined) {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string) $key : "$prefix.$key";
                if (is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                    $walk($value, $path);
                } else {
                    $defined[] = $path;
                }
            }
        };
        $walk($config, '');

        $used = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/src"));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/PosterConfig::get\\('([^']+)'/", file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                $used[] = $key;
            }
        }

        // 运行时注入型键（对象/服务，无法写进可发布的配置文件）与框架侧（Laravel config('poster.*')）白名单
        $allow = ['captcha.cache.pool'];

        $missing = [];
        foreach (array_unique($used) as $key) {
            if (in_array($key, $allow, true)) {
                continue;
            }
            foreach ($defined as $d) {
                if ($d === $key || str_starts_with($d, $key . '.')) {
                    continue 2;
                }
            }
            $missing[] = $key;
        }

        $this->assertSame(
            [],
            $missing,
            "以下配置键被代码读取，但 config/poster.php 里没有（用户无从发现）：\n  - " . implode("\n  - ", $missing)
        );
    }
}
