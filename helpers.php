<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

use Erikwang2013\Poster\PosterConfig;

if (!function_exists('captcha_create')) {
    /**
     * Create a captcha challenge.
     *
     * @param string $type    'click', 'rotate', or 'slider'
     * @param array  $options Additional options (difficulty, background, etc.)
     * @return array ['key' => '...', 'image' => '...', 'extra' => [...]]
     */
    function captcha_create(string $type, array $options = []): array
    {
        $config = PosterConfig::load();
        $manager = new \Erikwang2013\Poster\Captcha\CaptchaManager(
            \Erikwang2013\Poster\Storage\StorageFactory::create($config['captcha']['storage'] ?? 'auto'),
            \Erikwang2013\Poster\Drivers\DriverFactory::create($config['image']['driver'] ?? 'auto')
        );

        return $manager->create($type)
            ->setDifficulty($options['difficulty'] ?? $config['captcha']['default_difficulty'] ?? 'medium')
            ->setBackground($options['background'] ?? '')
            ->generate();
    }
}

if (!function_exists('captcha_verify')) {
    /**
     * Verify a captcha challenge.
     *
     * @param string $key  The captcha key
     * @param string $type 'click', 'rotate', or 'slider'
     * @param mixed  $data The user's answer data
     * @return bool
     */
    function captcha_verify(string $key, string $type, mixed $data): bool
    {
        $config = PosterConfig::load();
        $manager = new \Erikwang2013\Poster\Captcha\CaptchaManager(
            \Erikwang2013\Poster\Storage\StorageFactory::create($config['captcha']['storage'] ?? 'auto'),
            \Erikwang2013\Poster\Drivers\DriverFactory::create($config['image']['driver'] ?? 'auto')
        );

        return $manager->verify($key, ['type' => $type, 'data' => $data]);
    }
}

if (!function_exists('poster_create')) {
    /**
     * Create a poster builder instance.
     *
     * @param int   $width  Canvas width
     * @param int   $height Canvas height
     * @return \Erikwang2013\Poster\Poster\PosterBuilder
     */
    function poster_create(int $width = 750, int $height = 1334): \Erikwang2013\Poster\Poster\PosterBuilder
    {
        $config = PosterConfig::load();
        $imageDriver = \Erikwang2013\Poster\Drivers\DriverFactory::create($config['image']['driver'] ?? 'auto');

        return (new \Erikwang2013\Poster\Poster\PosterBuilder($imageDriver))
            ->width($width)
            ->height($height);
    }
}
