<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Yii3;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageInterface;

/**
 * 产出新的 CaptchaManager。
 *
 * 图像驱动内部就是当前画布（GdDriver::$resource / ImagickDriver::$imagick），
 * 共享驱动等于共享画布，故驱动与 Manager 都不进容器（yiisoft/di 只有共享实例），
 * 由这个无状态工厂每次产出独立一套。
 *
 * 存储反过来：它是无状态的包装，且共享 Redis 连接正是想要的，
 * 所以由容器注入同一个 $storage，而不是每次新建。
 */
class CaptchaManagerFactory
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function __invoke(): CaptchaManager
    {
        return new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            $this->storage
        );
    }
}
