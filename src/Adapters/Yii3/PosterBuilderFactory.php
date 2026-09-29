<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Yii3;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;

/**
 * 产出新的 PosterBuilder。
 *
 * Yii3 容器（yiisoft/di）只管理共享实例，而 PosterBuilder 是有状态的
 * （width()/height()/add() 会累积），注册进容器就成了共享单例，
 * 在 RoadRunner / Swoole 常驻进程下会把上一次请求的元素带进下一次。
 * 所以容器里放的是这个无状态工厂，每次 __invoke() 产出独立实例。
 */
class PosterBuilderFactory
{
    public function __invoke(): PosterBuilder
    {
        return new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver')));
    }
}
