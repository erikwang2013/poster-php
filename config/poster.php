<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

return [
    // ── 图像处理驱动 ──
    'image' => [
        // 驱动类型: 'auto' | 'gd' | 'imagick'；'auto' 自动检测可用驱动
        'driver' => 'auto',
        // JPEG 输出质量 0-100
        'quality' => 90,
        // 默认字体路径，null 则使用包自带字体
        'font' => null,
    ],

    // ── 验证码模块 ──
    'captcha' => [
        // 验证数据存储: 'auto' | 'file' | 'session' | 'redis'
        'storage' => 'auto',
        // 验证码有效期（秒）
        'ttl' => 300,
        // 同一 key 最多验证次数
        'max_attempts' => 3,
        // 默认难度: 'easy' | 'medium' | 'hard'
        'default_difficulty' => 'medium',
        // 验证误差容忍
        'tolerance' => [
            'click'  => 18,
            'rotate' => 5,
            'slider' => 4,
        ],
        // Redis 存储配置
        'redis' => [
            'prefix'     => 'poster:captcha:',
            'connection' => 'default',
        ],
        // 文件存储配置
        'file' => [
            'path' => null,
        ],
    ],

    // ── 海报生成模块 ──
    'poster' => [
        'default_width'  => 750,
        'default_height' => 1334,
        'font' => null,
        'jpeg_quality' => 90,
        'png_compression' => 6,
    ],
];
