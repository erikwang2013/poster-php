<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;

/**
 * 按键校验：类型（click/rotate/slider）从存储里读，调用方只需给出 key 与用户答案，
 * 表单校验场景下不必再把 type 一起传来传去。
 *
 * 本类不依赖任何 Laravel 类，可单独使用（Laravel 侧由 CaptchaRule / 校验扩展调用）。
 */
final class CaptchaVerifier
{
    private StorageInterface $storage;
    private CaptchaManager $manager;

    public function __construct(?StorageInterface $storage = null, ?CaptchaManager $manager = null)
    {
        $this->storage = $storage ?? StorageFactory::create(PosterConfig::get('captcha.storage'));
        $this->manager = $manager ?? new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            $this->storage
        );
    }

    /** 校验用户答案；key 不存在/已过期/类型不匹配/超出容差都返回 false（与 CaptchaManager::verify 一致） */
    public function verify(string $key, mixed $answer): bool
    {
        $stored = $this->storage->get($key);
        if ($stored === null) {
            return false;
        }
        return $this->manager->verify($key, ['type' => $stored['type'] ?? '', 'data' => $answer]);
    }
}
