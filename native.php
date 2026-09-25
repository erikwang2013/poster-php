<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * 原生 PHP 入口 / Native PHP entry point
 *
 * 不依赖 Composer：把整个 poster-php 目录放进项目，直接引入本文件即可使用，
 * 无需任何框架、也无需 vendor/ 目录。
 *
 *     require '/path/to/poster-php/native.php';
 *
 *     $result  = captcha_create('click');         // 验证码
 *     $builder = poster_create(750, 1334);        // 海报
 *
 * 已用 Composer 安装的项目请继续使用 vendor/autoload.php；本文件可安全重复引入，
 * 也可与其它自动加载器（ThinkPHP / 自研 autoload 等）共存。
 *
 * No Composer required: drop the poster-php directory into your project and
 * require this file. Safe to include alongside Composer or any other autoloader.
 */

if (!defined('POSTER_PHP_NATIVE')) {
    define('POSTER_PHP_NATIVE', true);

    // PSR-4 自动加载：Erikwang2013\Poster\ → src/
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Erikwang2013\\Poster\\';
        if (!str_starts_with($class, $prefix)) {
            return;   // 不是本包的类，交给其它自动加载器
        }

        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });

    require_once __DIR__ . '/helpers.php';
}
