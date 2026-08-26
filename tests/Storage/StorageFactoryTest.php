<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use Erikwang2013\Poster\Storage\RedisStorage;
use Erikwang2013\Poster\Storage\SessionStorage;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * StorageFactory 路由测试。
 */
class StorageFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
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
        $probe = new Redis();
        if (!$probe->connect('127.0.0.1', 6379, 0.5)) {
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
}
