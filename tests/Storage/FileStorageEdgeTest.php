<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * FileStorage 边界/异常路径补充测试（正常路径见 StorageTest.php）。
 */
class FileStorageEdgeTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-edge-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        @rmdir($this->tempDir);
    }

    /** 测试 get() 读取损坏 JSON 文件时返回 null */
    public function testGetCorruptFileReturnsNull(): void
    {
        file_put_contents($this->tempDir . '/' . md5('corrupt') . '.json', 'not-json{{');
        $this->assertNull($this->storage->get('corrupt'));
    }

    /** 测试 get() 读取空文件时返回 null */
    public function testGetEmptyFileReturnsNull(): void
    {
        file_put_contents($this->tempDir . '/' . md5('empty') . '.json', '');
        $this->assertNull($this->storage->get('empty'));
    }

    /** 测试 del() 对不存在的键返回 true（幂等） */
    public function testDelMissingKeyReturnsTrue(): void
    {
        $this->assertTrue($this->storage->del('missing'));
    }

    /** 测试 has() 对存在/不存在/已过期键的判断 */
    public function testHas(): void
    {
        $this->assertFalse($this->storage->has('k1'));
        $this->storage->set('k1', ['a' => 1], 60);
        $this->assertTrue($this->storage->has('k1'));
        $this->storage->set('k2', ['a' => 1], -10);
        $this->assertFalse($this->storage->has('k2'));
    }

    /** 测试 incrementAttempts() 键不存在时返回 0 */
    public function testIncrementMissingKeyReturnsZero(): void
    {
        $this->assertSame(0, $this->storage->incrementAttempts('missing'));
    }

    /** 测试 incrementAttempts() 文件内容损坏时返回 0 且不抛异常 */
    public function testIncrementCorruptFileReturnsZero(): void
    {
        file_put_contents($this->tempDir . '/' . md5('bad') . '.json', 'not-json');
        $this->assertSame(0, $this->storage->incrementAttempts('bad'));
    }

    /** 测试 incrementAttempts() 后 get() 能读到累加后的计数且原数据不变 */
    public function testIncrementPreservesData(): void
    {
        $this->storage->set('k1', ['type' => 'click'], 60);
        $this->assertSame(1, $this->storage->incrementAttempts('k1'));
        $this->assertSame(1, $this->storage->get('k1')['attempts']);
        $this->assertSame('click', $this->storage->get('k1')['type']);
    }

    /** 测试 set() 携带的 attempts 会被持久化并在 get() 中合并 */
    public function testSetWithInitialAttempts(): void
    {
        $this->storage->set('k1', ['type' => 'click', 'attempts' => 2], 60);
        $this->assertSame(2, $this->storage->get('k1')['attempts']);
        $this->assertSame(3, $this->storage->incrementAttempts('k1'));
    }

    /** 测试中文数据经 JSON 存储后完整往返 */
    public function testUnicodeRoundtrip(): void
    {
        $data = ['text' => '中文验证码', 'targets' => [['x' => 1, 'y' => 2]]];
        $this->storage->set('k1', $data, 60);
        $this->assertSame($data + ['attempts' => 0], $this->storage->get('k1'));
    }

    /** 测试空数组数据往返 */
    public function testEmptyDataRoundtrip(): void
    {
        $this->storage->set('k1', [], 60);
        $this->assertSame(['attempts' => 0], $this->storage->get('k1'));
    }

    /** 测试 set() 写入非法 UTF-8 数据时返回 false 且不写文件（不静默丢失） */
    public function testSetInvalidUtf8DataUnreadable(): void
    {
        $this->assertFalse($this->storage->set('k1', ["\xB1\x31" => 'x'], 60));
        $this->assertFileDoesNotExist($this->tempDir . '/' . md5('k1') . '.json');
        $this->assertNull($this->storage->get('k1'));
    }

    /** 测试构造函数自动创建不存在的目录 */
    public function testConstructorCreatesMissingDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/poster-test-newdir-' . uniqid();
        try {
            new FileStorage($dir);
            $this->assertDirectoryExists($dir);
        } finally {
            @rmdir($dir);
        }
    }

    /** 测试构造函数在路径为已存在文件时抛出 RuntimeException（无 E_WARNING） */
    public function testConstructorThrowsWhenPathIsFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'poster-notdir-');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('not a directory');
            new FileStorage($file);
        } finally {
            @unlink($file);
        }
    }

    /** 测试无参构造回退到系统临时目录下的默认路径 */
    public function testConstructorDefaultPath(): void
    {
        $storage = new FileStorage();
        $this->assertDirectoryExists(sys_get_temp_dir() . '/poster-captcha');
    }
}
