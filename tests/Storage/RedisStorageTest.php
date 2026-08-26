<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\RedisStorage;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * RedisStorage 单元测试：全部使用 mock Redis 客户端，不依赖真实 Redis 服务器。
 */
class RedisStorageTest extends TestCase
{
    private const PREFIX = 'poster:test:';

    private Redis|\PHPUnit\Framework\MockObject\MockObject $redis;
    private RedisStorage $storage;

    protected function setUp(): void
    {
        PosterConfig::reset();
        PosterConfig::merge(['captcha' => ['redis' => ['prefix' => self::PREFIX]]]);
        $this->redis = $this->createMock(Redis::class);
        $this->storage = new RedisStorage($this->redis);
    }

    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 测试 set() 写入数据键与计数键，均带 ttl，且返回 true */
    public function testSetWritesPayloadAndCounterWithTtl(): void
    {
        $data = ['type' => 'click', 'targets' => [['x' => 10, 'y' => 20]]];
        $this->redis->expects($this->exactly(2))
            ->method('setex')
            ->willReturnCallback(function (string $key, int $ttl, int|string $json) use ($data): bool {
                $this->assertSame(60, $ttl);
                if ($key === self::PREFIX . 'k1') {
                    $payload = json_decode($json, true);
                    $this->assertSame($data, $payload['data']);
                    $this->assertSame(0, $payload['attempts']);
                    $this->assertIsInt($payload['expire_at']);
                } else {
                    $this->assertSame(self::PREFIX . 'k1:att', $key);
                    $this->assertSame(0, $json);
                }
                return true;
            });
        $this->assertTrue($this->storage->set('k1', $data, 60));
    }

    /** 测试 set() 未传 ttl 时使用默认 300 秒 */
    public function testSetUsesDefaultTtl(): void
    {
        $this->redis->expects($this->exactly(2))
            ->method('setex')
            ->with($this->anything(), 300, $this->anything())
            ->willReturn(true);
        $this->assertTrue($this->storage->set('k1', ['a' => 1]));
    }

    /** 测试 get() 能读回数据并合并计数键的值 */
    public function testGetReturnsDataWithAttempts(): void
    {
        $json = json_encode([
            'data'      => ['type' => 'click'],
            'expire_at' => time() + 300,
            'attempts'  => 0,
        ], JSON_UNESCAPED_UNICODE);
        $this->redis->method('get')->willReturnMap([
            [self::PREFIX . 'k1', $json],
            [self::PREFIX . 'k1:att', '2'],
        ]);
        $result = $this->storage->get('k1');
        $this->assertSame('click', $result['type']);
        $this->assertSame(2, $result['attempts']);
    }

    /** 测试 get() 键不存在时返回 null */
    public function testGetMissingKeyReturnsNull(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->assertNull($this->storage->get('missing'));
    }

    /** 测试 get() 读到损坏 JSON 时返回 null */
    public function testGetCorruptPayloadReturnsNull(): void
    {
        $this->redis->method('get')->willReturnMap([
            [self::PREFIX . 'bad', 'not-json{{'],
            [self::PREFIX . 'bad:att', false],
        ]);
        $this->assertNull($this->storage->get('bad'));
    }

    /** 测试 del() 同时删除数据键与计数键，返回 true */
    public function testDelRemovesBothKeys(): void
    {
        $this->redis->expects($this->once())
            ->method('del')
            ->with([self::PREFIX . 'k1', self::PREFIX . 'k1:att'])
            ->willReturn(2);
        $this->assertTrue($this->storage->del('k1'));
    }

    /** 测试 del() 对不存在的键同样返回 true（幂等） */
    public function testDelMissingKeyReturnsTrue(): void
    {
        $this->redis->method('del')->willReturn(0);
        $this->assertTrue($this->storage->del('missing'));
    }

    /** 测试 has() 依据 exists 返回值判断存在性 */
    public function testHas(): void
    {
        $this->redis->method('exists')->willReturnMap([
            [self::PREFIX . 'yes', 1],
            [self::PREFIX . 'no', 0],
        ]);
        $this->assertTrue($this->storage->has('yes'));
        $this->assertFalse($this->storage->has('no'));
    }

    /** 测试 incrementAttempts() 首次计数时按主键剩余 ttl 补上计数键过期时间 */
    public function testIncrementAttemptsFirstTimeSetsExpire(): void
    {
        $this->redis->method('incr')->with(self::PREFIX . 'k1:att')->willReturn(1);
        $this->redis->method('ttl')->with(self::PREFIX . 'k1')->willReturn(120);
        $this->redis->expects($this->once())
            ->method('expire')
            ->with(self::PREFIX . 'k1:att', 120);
        $this->assertSame(1, $this->storage->incrementAttempts('k1'));
    }

    /** 测试 incrementAttempts() 非首次计数时不再补过期时间 */
    public function testIncrementAttemptsSubsequentSkipsExpire(): void
    {
        $this->redis->method('incr')->with(self::PREFIX . 'k1:att')->willReturn(3);
        $this->redis->expects($this->never())->method('ttl');
        $this->redis->expects($this->never())->method('expire');
        $this->assertSame(3, $this->storage->incrementAttempts('k1'));
    }

    /** 测试 incrementAttempts() 主键已过期（ttl<=0）时不为计数键补过期时间 */
    public function testIncrementAttemptsWithExpiredMainKeySkipsExpire(): void
    {
        $this->redis->method('incr')->with(self::PREFIX . 'k1:att')->willReturn(1);
        $this->redis->method('ttl')->with(self::PREFIX . 'k1')->willReturn(-2);
        $this->redis->expects($this->never())->method('expire');
        $this->assertSame(1, $this->storage->incrementAttempts('k1'));
    }

    /** 测试构造函数注入 Redis 实例时不触发真实连接 */
    public function testConstructorWithInjectedClientSkipsConnect(): void
    {
        $this->assertInstanceOf(RedisStorage::class, new RedisStorage($this->createMock(Redis::class)));
    }
}
