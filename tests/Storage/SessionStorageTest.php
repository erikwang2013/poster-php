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
        $this->startSession();
        $_SESSION = [];
        $this->storage = new SessionStorage();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /** SessionStorage 现在要求会话已启动（否则抛异常），CLI 下需显式启动 */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // session.* 只能在有输出之前修改：PHPUnit 在部分 PHP 版本（如 8.0 配 PHPUnit 9）
        // 下已经写入了进度输出，此时 ini_set 会报 "headers have already been sent"。
        // 因此这里降级为「尽力设置」：设不上就用现有 ini 启动，失败才跳过。
        if (!headers_sent()) {
            ini_set('session.cache_limiter', '');
            ini_set('session.use_cookies', '0');
            ini_set('session.save_path', sys_get_temp_dir());
        } else {
            @ini_set('session.use_cookies', '0');   // 关不掉也继续，@ 只是抑制告警
            @ini_set('session.save_path', sys_get_temp_dir());
        }
        @session_start();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->markTestSkipped('当前环境无法启动会话，跳过 SessionStorage 行为测试');
        }
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
