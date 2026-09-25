<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;
use RuntimeException;

class StorageFactory
{
    /** 'auto' 解析结果进程内复用：同一次请求里生成与校验必须落在同一后端 */
    private static ?StorageInterface $autoInstance = null;

    /** 由调用方注入的 PSR-16 池（框架缓存），'cache' 驱动专用 */
    private static ?object $psr16Pool = null;

    public static function create(?string $driver = null): StorageInterface
    {
        $driver = $driver ?? PosterConfig::get('captcha.storage', 'auto');

        if ($driver === 'auto') {
            // 探测只做一次：Redis 探测偶发失败会让「写入」和「校验」落到不同后端（验证码永远验不过）
            return self::$autoInstance ??= self::detect();
        }

        return match ($driver) {
            'redis'   => new RedisStorage(),
            'session' => new SessionStorage(),
            'file'    => new FileStorage(),
            'cache'   => self::fromPsr16Pool(),
            default   => throw new InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }

    /**
     * 注入 PSR-16 池（任何具备 get/set/delete 的对象），启用 'cache' 驱动。
     * Laravel：StorageFactory::setPsr16Pool(Cache::store())。
     * 传 null 清除注入。
     */
    public static function setPsr16Pool(?object $pool): void
    {
        self::$psr16Pool = $pool;
    }

    /** 清空 auto 解析缓存（长驻进程里 Redis 恢复/切换后想重新探测时调用；测试也用它复位） */
    public static function reset(): void
    {
        self::$autoInstance = null;
    }

    private static function fromPsr16Pool(): Psr16Storage
    {
        // 池来源二选一：setPsr16Pool() 注入，或配置项 captcha.cache.pool 里直接放池对象
        $pool = self::$psr16Pool ?? PosterConfig::get('captcha.cache.pool');
        if (!is_object($pool)) {
            throw new RuntimeException(
                'Storage driver "cache" needs a PSR-16 pool: call StorageFactory::setPsr16Pool($pool) first '
                . '(Laravel: StorageFactory::setPsr16Pool(Cache::store())), or set captcha.cache.pool; '
                . 'otherwise use the redis / file / session driver.'
            );
        }
        return new Psr16Storage($pool);
    }

    private static function detect(): StorageInterface
    {
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                return new RedisStorage();
            } catch (\Throwable $e) {
                // Redis unreachable, fall through to session/file
            }
        }
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            return new SessionStorage();
        }
        return new FileStorage();
    }
}
