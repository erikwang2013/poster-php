<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\Psr16Storage;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * PSR-16 适配测试：鸭子类型契约、json 编解码往返、过期、读改写计数。
 */
class Psr16StorageTest extends TestCase
{
    private const PREFIX = 'poster:test:';

    private ArrayPsr16Pool $pool;
    private Psr16Storage $storage;

    protected function setUp(): void
    {
        PosterConfig::reset();
        $this->pool = new ArrayPsr16Pool();
        $this->storage = new Psr16Storage($this->pool, self::PREFIX);
    }

    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 测试实现的接口契约 */
    public function testImplementsStorageInterface(): void
    {
        $this->assertInstanceOf(StorageInterface::class, $this->storage);
    }

    /** 测试鸭子类型：只有 get/set/delete 的对象即可用，缺方法时抛明确异常 */
    public function testRejectsPoolWithoutRequiredMethods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('get() / set() / delete()');
        new Psr16Storage(new stdClass());
    }

    /** 测试 set()/get() 往返：数据与 attempts 合并、键带前缀、TTL 传给池 */
    public function testSetAndGetRoundtrip(): void
    {
        $data = ['type' => 'click', 'targets' => [['x' => 1, 'y' => 2]], 'texts' => ['云' => 1]];
        $this->assertTrue($this->storage->set('k1', $data, 60));

        $this->assertArrayHasKey(self::PREFIX . 'k1', $this->pool->items);
        $this->assertSame(60, $this->pool->setTtls[self::PREFIX . 'k1']);

        $stored = $this->storage->get('k1');
        $this->assertSame('click', $stored['type']);
        $this->assertSame([['x' => 1, 'y' => 2]], $stored['targets']);
        $this->assertSame(0, $stored['attempts']);
    }

    /** 测试 get() 对未写入/非法 UTF-8 数据的行为（不静默返回半截数据） */
    public function testGetMissingAndInvalidDataReturnsNull(): void
    {
        $this->assertNull($this->storage->get('missing'));
        $this->assertFalse($this->storage->set('bad', ["\xB1\x31" => 'x'], 60));
        $this->assertNull($this->storage->get('bad'));

        $this->pool->set(self::PREFIX . 'garbage', 'not-json{{', 60);
        $this->assertNull($this->storage->get('garbage'));
    }

    /** 测试 del()/has() 与池的 delete() 对应 */
    public function testDelAndHas(): void
    {
        $this->assertTrue($this->storage->del('missing'));
        $this->storage->set('k1', ['type' => 'click'], 60);
        $this->assertTrue($this->storage->has('k1'));

        $this->assertTrue($this->storage->del('k1'));
        $this->assertFalse($this->storage->has('k1'));
        $this->assertNull($this->storage->get('k1'));
    }

    /** 测试 TTL 到期后 get() 返回 null（池和 payload 的 expire_at 双重兜底） */
    public function testExpiredEntryReturnsNull(): void
    {
        $this->storage->set('k1', ['type' => 'click'], -10);
        $this->assertNull($this->storage->get('k1'));

        // 池忽略 TTL 的场景：payload 的 expire_at 兜底
        $this->pool->items[self::PREFIX . 'k2'] = [
            'value' => json_encode(['data' => ['type' => 'click'], 'expire_at' => time() - 1, 'attempts' => 0]),
            'expire_at' => null,
        ];
        $this->assertNull($this->storage->get('k2'));
        $this->assertArrayNotHasKey(self::PREFIX . 'k2', $this->pool->items, '过期项应顺带删除');
    }

    /** 测试 incrementAttempts() 读改写：累加、保留数据、保留剩余 TTL、键不存在返回 0 */
    public function testIncrementAttemptsKeepsDataAndRemainingTtl(): void
    {
        $this->assertSame(0, $this->storage->incrementAttempts('missing'));

        $this->storage->set('k1', ['type' => 'click', 'targets' => [['x' => 3, 'y' => 4]]], 60);
        $this->assertSame(1, $this->storage->incrementAttempts('k1'));
        $this->assertSame(2, $this->storage->incrementAttempts('k1'));

        $stored = $this->storage->get('k1');
        $this->assertSame(2, $stored['attempts']);
        $this->assertSame([['x' => 3, 'y' => 4]], $stored['targets'], '读改写不得丢掉验证数据');

        $ttl = $this->pool->setTtls[self::PREFIX . 'k1'];
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl, '写回不得延长整体有效期');
    }

    /** 测试 set() 携带的 attempts 会被持久化并参与累加 */
    public function testSetWithInitialAttempts(): void
    {
        $this->storage->set('k1', ['attempts' => 2], 60);
        $this->assertSame(2, $this->storage->get('k1')['attempts']);
        $this->assertSame(3, $this->storage->incrementAttempts('k1'));
    }
}
