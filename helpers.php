<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;

if (!function_exists('captcha_create')) {
    function captcha_create(?string $type = null, array $options = []): array
    {
        $type ??= PosterConfig::get('captcha.default_type', 'random');
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        $captcha = $manager->create($type);
        // 难度：选项优先，其次 captcha.default_difficulty（此前只认选项，配置项是死配置）
        $difficulty = $options['difficulty'] ?? PosterConfig::get('captcha.default_difficulty');
        if ($difficulty !== null) $captcha->setDifficulty($difficulty);
        if (isset($options['background'])) $captcha->setBackground($options['background']);
        return $captcha->generate();
    }
}

if (!function_exists('captcha_verify')) {
    function captcha_verify(string $key, string $type, mixed $data): bool
    {
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        return $manager->verify($key, ['type' => $type, 'data' => $data]);
    }
}

if (!function_exists('poster_create')) {
    function poster_create(?int $width = null, ?int $height = null): PosterBuilder
    {
        $builder = new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver')));
        if ($width !== null) $builder->width($width);
        if ($height !== null) $builder->height($height);
        return $builder;
    }
}
