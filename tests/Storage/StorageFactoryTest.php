<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use Erikwang2013\Poster\Storage\Psr16Storage;
use Erikwang2013\Poster\Storage\RedisStorage;
use Erikwang2013\Poster\Storage\SessionStorage;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;

/**
 * StorageFactory 路由测试。
 */
class StorageFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
        StorageFactory::reset();
        StorageFactory::setPsr16Pool(null);
    }

    /** 测试 create('file') 返回 FileStorage */
    public function testFileDriver(): void
    {
        $this->assertInstanceOf(FileStorage::class, StorageFactory::create('file'));
    }

    /** 测试 create('session') 返回 SessionStorage */
    public function testSessionDriver(): void
    {
        $this->assertInstanceOf(SessionStorage::class, StorageFactory::create('session'));
    }

    /** 测试 create('redis') 返回 RedisStorage；本机无 Redis 服务时跳过 */
    public function testRedisDriver(): void
    {
        // 新版 phpredis 连接失败会抛 RedisException（而非返回 false），未装扩展时 new Redis() 直接 Error
        try {
            $probe = new Redis();
            if (!$probe->connect('127.0.0.1', 6379, 0.5)) {
                $this->markTestSkipped('本地无 Redis 服务，跳过真实连接测试');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('本地无 Redis 服务，跳过真实连接测试');
        }
        $this->assertInstanceOf(RedisStorage::class, StorageFactory::create('redis'));
    }

    /** 测试未知驱动抛出 InvalidArgumentException 且消息包含驱动名 */
    public function testUnknownDriverThrows(): void
    {
        try {
            StorageFactory::create('bogus');
            $this->fail('应当抛出 InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('bogus', $e->getMessage());
        }
    }

    /** 测试 create(null) 走配置项 captcha.storage 路由 */
    public function testNullDriverUsesConfig(): void
    {
        PosterConfig::merge(['captcha' => ['storage' => 'file']]);
        $this->assertInstanceOf(FileStorage::class, StorageFactory::create());
    }

    /** 测试 create('auto') 返回实现了 StorageInterface 的实例（Redis 可用则回退链起点为 Redis） */
    public function testAutoDriver(): void
    {
        $storage = StorageFactory::create('auto');
        $this->assertInstanceOf(StorageInterface::class, $storage);
        $this->assertNotInstanceOf(SessionStorage::class, $storage); // CLI 下 auto 不会选 session
    }

    /** 测试所有驱动产物均实现 StorageInterface 契约 */
    public function testAllDriversImplementInterface(): void
    {
        $this->assertInstanceOf(StorageInterface::class, StorageFactory::create('file'));
        $this->assertInstanceOf(StorageInterface::class, StorageFactory::create('session'));
    }

    /** 测试 auto 只探测一次：后续 create('auto') 复用同一实例（生成与校验不会落到不同后端） */
    public function testAutoProbesOnceAndReusesInstance(): void
    {
        $first = StorageFactory::create('auto');
        $second = StorageFactory::create('auto');
        $third = StorageFactory::create('auto');

        $this->assertSame($first, $second, 'auto 第二次调用应复用首次探测结果');
        $this->assertSame($first, $third, 'auto 后续调用应继续复用同一实例');
        // 显式驱动仍每次新建，不受 auto 缓存影响
        $this->assertNotSame($first, StorageFactory::create('file'));
    }

    /** 测试 reset() 清空 auto 缓存后重新探测 */
    public function testResetClearsAutoCache(): void
    {
        $first = StorageFactory::create('auto');
        StorageFactory::reset();
        $second = StorageFactory::create('auto');

        $this->assertNotSame($first, $second);
        $this->assertSame(get_class($first), get_class($second), '重新探测的驱动类型应与之前一致');
    }

    /** 测试未注入 PSR-16 池时 create('cache') 抛明确异常且消息给出补救方式 */
    public function testCacheDriverWithoutPoolThrows(): void
    {
        try {
            StorageFactory::create('cache');
            $this->fail('未注入池时应当抛出 RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('setPsr16Pool', $e->getMessage());
        }
    }

    /** 测试配置注入池也可用（PosterConfig 里直接放池对象），且未注入时抛异常 */
    public function testCacheDriverWithPoolFromConfig(): void
    {
        $pool = new ArrayPsr16Pool();
        PosterConfig::merge(['captcha' => ['cache' => ['pool' => $pool]]]);

        $storage = StorageFactory::create('cache');
        $this->assertInstanceOf(Psr16Storage::class, $storage);
        $storage->set('k1', ['type' => 'slide'], 60);
        $this->assertArrayHasKey('poster:captcha:k1', $pool->items);

        PosterConfig::merge(['captcha' => ['cache' => ['pool' => null]]]);
        $this->expectException(RuntimeException::class);
        StorageFactory::create('cache');
    }

    /** 测试注入 PSR-16 池后 create('cache') 返回 Psr16Storage，且传递的池被真实使用 */
    public function testCacheDriverWithInjectedPool(): void
    {
        $pool = new ArrayPsr16Pool();
        StorageFactory::setPsr16Pool($pool);

        $storage = StorageFactory::create('cache');
        $this->assertInstanceOf(Psr16Storage::class, $storage);

        $storage->set('k1', ['type' => 'click'], 60);
        $this->assertArrayHasKey('poster:captcha:k1', $pool->items, '写入应落到注入的池上');

        StorageFactory::setPsr16Pool(null);
        $this->expectException(RuntimeException::class);
        StorageFactory::create('cache');
    }
}
