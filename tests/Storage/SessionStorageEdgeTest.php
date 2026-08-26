<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

/**
 * SessionStorage 边界/契约补充测试（基础路径见 SessionStorageTest.php）。
 */
class SessionStorageEdgeTest extends TestCase
{
    private SessionStorage $storage;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->storage = new SessionStorage();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /** 测试 set() 返回 true 且数据写入会话的固定前缀命名空间下 */
    public function testSetReturnsTrueAndWritesNamespaced(): void
    {
        $this->assertTrue($this->storage->set('k1', ['foo' => 'bar'], 60));
        $this->assertArrayHasKey('k1', $_SESSION['poster_captcha']);
    }

    /** 测试 get() 返回的数据合并了 attempts 计数 */
    public function testGetMergesAttempts(): void
    {
        $this->storage->set('k1', ['type' => 'click'], 60);
        $this->assertSame(0, $this->storage->get('k1')['attempts']);
        $this->storage->incrementAttempts('k1');
        $this->assertSame(1, $this->storage->get('k1')['attempts']);
        $this->assertSame('click', $this->storage->get('k1')['type']);
    }

    /** 测试 del() 对不存在的键返回 true（幂等） */
    public function testDelMissingKeyReturnsTrue(): void
    {
        $this->assertTrue($this->storage->del('missing'));
    }

    /** 测试 set() 携带的 attempts 会被持久化并参与累加 */
    public function testSetWithInitialAttempts(): void
    {
        $this->storage->set('k1', ['attempts' => 2], 60);
        $this->assertSame(2, $this->storage->get('k1')['attempts']);
        $this->assertSame(3, $this->storage->incrementAttempts('k1'));
        $this->assertSame(3, $this->storage->get('k1')['attempts']);
    }

    /** 测试已过期条目在 get() 时被清除且返回 null */
    public function testExpiredEntryRemovedOnGet(): void
    {
        $this->storage->set('k1', ['foo' => 1], -1);
        $this->assertNull($this->storage->get('k1'));
        $this->assertFalse(isset($_SESSION['poster_captcha']['k1']));
    }

    /** 测试不同键互不干扰 */
    public function testKeysAreIsolated(): void
    {
        $this->storage->set('k1', ['a' => 1], 60);
        $this->storage->set('k2', ['b' => 2], 60);
        $this->assertSame(['a' => 1, 'attempts' => 0], $this->storage->get('k1'));
        $this->assertSame(['b' => 2, 'attempts' => 0], $this->storage->get('k2'));
        $this->storage->del('k1');
        $this->assertNull($this->storage->get('k1'));
        $this->assertNotNull($this->storage->get('k2'));
    }
}
