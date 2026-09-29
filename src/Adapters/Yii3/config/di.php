<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Yii3 DI 定义（composer.json 的 extra.config-plugin 已声明，装包即生效）。
 *
 * 容器里只放**无状态**的东西：
 *   - StorageInterface      无状态包装，共享 Redis 连接正是想要的
 *   - CaptchaManagerFactory 无状态工厂，每次产出独立的 Manager + 驱动
 *   - PosterBuilderFactory  同上，每次产出独立的 Builder + 驱动
 *
 * 图像驱动 / CaptchaManager / PosterBuilder 一律不进容器：
 * yiisoft/di 只管理共享实例，而它们都持有当前画布（GdDriver::$resource、
 * ImagickDriver::$imagick）；共享后一次渲染会覆盖另一次的画布，
 * 在 RoadRunner / Swoole 常驻进程下还会跨请求残留。
 *
 * 用法：
 *     final class CaptchaController
 *     {
 *         public function __construct(private PosterBuilderFactory $builders) {}
 *
 *         public function action(): void
 *         {
 *             $image = ($this->builders)()->width(750)->save('/tmp/a.jpg');
 *         }
 *     }
 */

use Erikwang2013\Poster\Adapters\Yii3\CaptchaManagerFactory;
use Erikwang2013\Poster\Adapters\Yii3\PosterBuilderFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

/** @var array $params */

// 合并 config/params.php 的覆盖层。merge() 内部会先 load()，
// 自动发现应用的 config/poster.php，找不到则回落包内默认值。
PosterConfig::merge($params['erikwang2013/poster-php'] ?? []);

return [
    StorageInterface::class => static function (ContainerInterface $container): StorageInterface {
        $driver = PosterConfig::get('captcha.storage', 'auto');

        // storage=cache 需要 PSR-16 池（Yii3 应用一般由 yiisoft/cache 提供），
        // 与 Laravel 适配器对齐：这里注入，否则该驱动必然抛异常。
        if ($driver === 'cache' && $container->has(CacheInterface::class)) {
            StorageFactory::setPsr16Pool($container->get(CacheInterface::class));
        }

        return StorageFactory::create($driver);
    },

    CaptchaManagerFactory::class => static fn (ContainerInterface $container): CaptchaManagerFactory
        => new CaptchaManagerFactory($container->get(StorageInterface::class)),

    PosterBuilderFactory::class => static fn (): PosterBuilderFactory
        => new PosterBuilderFactory(),
];
