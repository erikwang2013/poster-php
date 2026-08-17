<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

class SessionStorageTest extends TestCase
{
    private SessionStorage $storage;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->storage = new SessionStorage();
    }

    public function testSetAndGet(): void
    {
        $this->storage->set('k1', ['foo' => 'bar']);
        $data = $this->storage->get('k1');
        // 只断言 data 自身字段：attempts 并入 get() 返回值的改动不影响此测试
        $this->assertSame('bar', $data['foo'] ?? null);
    }

    public function testGetMissingReturnsNull(): void
    {
        $this->assertNull($this->storage->get('missing'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->storage->has('k1'));
        $this->storage->set('k1', ['foo' => 1]);
        $this->assertTrue($this->storage->has('k1'));
    }

    public function testDel(): void
    {
        $this->storage->set('k1', ['foo' => 1]);
        $this->storage->del('k1');
        $this->assertNull($this->storage->get('k1'));
    }

    public function testIncrementAttempts(): void
    {
        $this->storage->set('k1', ['foo' => 1]);
        $this->assertSame(1, $this->storage->incrementAttempts('k1'));
        $this->assertSame(2, $this->storage->incrementAttempts('k1'));
    }

    public function testIncrementMissingReturnsZero(): void
    {
        $this->assertSame(0, $this->storage->incrementAttempts('missing'));
    }

    public function testExpiredEntryReturnsNull(): void
    {
        $this->storage->set('k1', ['foo' => 1], 1);
        sleep(2);
        $this->assertNull($this->storage->get('k1'));
    }
}
