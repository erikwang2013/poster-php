<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

return [
    // ── Image Driver 图像处理驱动 ──
    'image' => [
        // 驱动类型 / Driver type: 'auto' | 'gd' | 'imagick'
        // 'auto' auto-detects available driver / 自动检测可用驱动
        'driver' => 'auto',

        // save()/output() 未显式指定质量时的默认 JPEG 质量 0-100
        // Default JPEG quality used by save()/output() when none is given
        'quality' => 90,

        // 默认字体路径 / Default font path
        'font' => dirname(__DIR__) . '/src/fonts/Alibaba-PuHuiTi-Regular.ttf',
    ],

    // ── Captcha Module 验证码模块 ──
    'captcha' => [
        // 验证数据存储 / Storage driver: 'auto' | 'file' | 'session' | 'redis'
        // 'auto': Redis > Session > File, auto-detect / 自动检测
        'storage' => 'auto',

        // 验证码有效期（秒）/ TTL in seconds
        // Key expires after this duration / 超时后 key 作废
        'ttl' => 300,

        // 同一 key 最多验证次数 / Max verification attempts per key
        // Prevents brute-force enumeration / 防暴力枚举
        'max_attempts' => 3,

        // 会话/账号级限流（跨 key 生效）/ Per-session (or per-account) rate limit
        // 单 key 计数挡不住「每次换新 key 再猜一次」，故再加一层窗口限流
        // 窗口内校验次数超过 max 即拒绝 / Reject once verify calls exceed max within window
        'rate_limit' => [
            'max'    => 30,   // 每个窗口允许的校验次数 / verifications per window
            'window' => 60,   // 窗口秒数 / window size in seconds
        ],

        // 行为轨迹校验 / Interaction trajectory checks (slider & rotate)
        // 开启后要求前端提交拖动轨迹（data 传 ['x'=>.., 'trail'=>[[x,y,t],..], 'duration'=>ms]）
        // 旧前端仍可只传数值，但开启此项后会因缺少轨迹而被拒
        'trajectory' => [
            'enabled'      => false,   // 默认关闭，避免误杀触屏/无障碍设备
            'min_points'   => 4,       // 最少采样点 / minimum sample points
            'min_duration' => 300,     // 最短耗时（毫秒）/ minimum duration
            'max_duration' => 5000,    // 最长耗时（毫秒）/ maximum duration
            'max_linearity' => 0.99,   // 线性度高于此值判为机器 / linearity above this = bot
        ],

        // 默认验证码类型 / Default captcha type: 'click' | 'rotate' | 'slider' | 'random'
        'default_type' => 'random',

        // 默认难度 / Default difficulty: 'easy' | 'medium' | 'hard'
        'default_difficulty' => 'medium',

        // 默认背景图目录（放 png/jpg/gif/webp），随机选用
        // null = 使用程序化生成
        // Background image directory; null = procedural generation
        'background_dir' => dirname(__DIR__) . '/assets/backgrounds',

        // 程序化背景风格 / Procedural background styles
        // Available: 'minimal', 'vibrant', 'natural'
        'background_styles' => ['minimal', 'vibrant', 'natural'],

        // 验证误差容忍 / Verification tolerance
        'tolerance' => [
            'click'  => 18,   // 点击验证像素半径 / Click: pixel radius
            'rotate' => 5,    // 旋转验证角度 / Rotate: degrees
            'slider' => 4,    // 滑块验证像素 / Slider: pixels
        ],

        // Redis 存储配置（storage=redis 时生效）/ Redis config (effective when storage=redis)
        'redis' => [
            // Redis key prefix / Redis 键前缀
            'prefix'     => 'poster:captcha:',
            // Redis connection name (framework-specific) / Redis 连接名（框架相关）
            'connection' => 'default',
        ],

        // 文件存储配置（storage=file 时生效）/ File storage config (effective when storage=file)
        'file' => [
            // 存储路径 / Storage path, null = system temp dir / 系统临时目录
            'path' => null,
        ],
        'click_words' => [
            '合',
            '家',
            '欢',
            '乐',
            '良',
            '辰',
            '美',
            '景',
            '千',
            '变',
            '万',
            '化',
            '心',
            '有',
            '灵',
            '犀',
            '五',
            '湖',
            '四',
            '海',
            '山',
            '川',
            '美',
            '景',
            '花',
            '好',
            '月',
            '圆'

        ]
    ],

    // ── Poster Module 海报生成模块 ──
    'poster' => [
        // 画布默认宽高（px）/ Default canvas width & height
        'default_width'  => 750,
        'default_height' => 1334,

        // 默认字体路径 / Default font path
        'font' => dirname(__DIR__) . '/src/fonts/Alibaba-PuHuiTi-Regular.ttf',

        // save($path) 未显式指定质量时的默认值 0-100
        // Default quality for save($path) when no quality argument is passed
        'jpeg_quality' => 90,

        // PNG compression level / PNG 压缩级别 0-9
        // 0 = no compression / 不压缩, 9 = max / 最大压缩
        // output('png') 与 save('*.png') 使用 / used by output('png') and save('*.png')
        'png_compression' => 6,

        // 缺失图片的占位图 / Placeholder for missing image files
        // null = 跳过缺失的图片（默认，不改变既有行为）
        // 设为内置吉祥物 dirname(__DIR__) . '/assets/pet.png' 可在缺图位置绘制 Posty
        // null = skip missing images (default); point it at the bundled mascot
        'placeholder' => null,
    ],
];
