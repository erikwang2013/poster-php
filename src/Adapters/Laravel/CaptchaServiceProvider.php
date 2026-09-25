<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Adapters\Laravel\Rules\CaptchaRule;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\RedisStorage;
use Erikwang2013\Poster\Storage\StorageInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/poster.php', 'poster');

        // 存储单例：'cache' 驱动用 Laravel 的 Cache::store()（本身即 PSR-16）作池，注入后即可用
        $this->app->singleton(StorageInterface::class, function ($app) {
            $driver = config('poster.captcha.storage') ?: 'auto';
            if ($driver === 'cache') {
                StorageFactory::setPsr16Pool($app['cache']->store(config('poster.captcha.cache.store')));
            }
            if ($driver === 'redis') {
                // captcha.redis.connection 由框架解析：连接名 → Laravel Redis 连接 → 底层 phpredis 客户端。
                // 核心库不认识框架连接名，这个键只有在这里才用得上。
                $name = config('poster.captcha.redis.connection');
                if ($name !== null && $name !== '') {
                    return new RedisStorage($app['redis']->connection($name)->client());
                }
            }
            return StorageFactory::create($driver);
        });

        $this->app->singleton('poster.captcha', fn($app) => new CaptchaManager(
            DriverFactory::create(config('poster.image.driver')),
            $app->make(StorageInterface::class)
        ));
        $this->app->alias('poster.captcha', CaptchaManager::class);

        $this->app->singleton(CaptchaImage::class, fn($app) => new CaptchaImage(
            $app->make(StorageInterface::class),
            $app->make('poster.captcha'),
            intval(config('poster.captcha.ttl', 300))
        ));
        $this->app->singleton(CaptchaVerifier::class, fn($app) => new CaptchaVerifier(
            $app->make(StorageInterface::class),
            $app->make('poster.captcha')
        ));
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 3) . '/config/poster.php' => config_path('poster.php')], 'poster-config');

        // 图片端点（可选）：默认关闭，应用侧也可以自己写 Route::get('/captcha/{key}', ...)
        if (config('poster.captcha.route.enabled', false)) {
            Route::get(config('poster.captcha.route.path') ?: '/captcha/{key}', [CaptchaImageController::class, 'show'])
                ->name('poster.captcha.image');
        }

        // 'captcha:key' 字符串规则；对象写法见 Rules\CaptchaRule
        Validator::extend('captcha', function ($attribute, $value, $parameters) {
            return isset($parameters[0])
                && $this->app->make(CaptchaVerifier::class)->verify((string) $parameters[0], $value);
        }, CaptchaRule::MESSAGE);
    }
}
