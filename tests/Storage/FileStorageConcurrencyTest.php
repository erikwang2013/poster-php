<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

/**
 * FileStorage 并发测试：多进程读 + 主进程原地自增。
 *
 * 修复前的实测：40 并发下 16 次 get() 因撞上 ftruncate 重写窗口而 json_decode 失败返回 null，
 * 真实用户答对也被判 false。现在读走 LOCK_SH + 写走「临时文件 + rename()」，
 * 这里用同样的多进程压测守住这个行为。
 */
class FileStorageConcurrencyTest extends TestCase
{
    private const READERS = 20;
    private const RUN_SECONDS = 0.8;

    private string $tempDir;
    private FileStorage $storage;
    private string $key = 'concurrent-key';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-conc-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        @rmdir($this->tempDir);
        @unlink(sys_get_temp_dir() . '/poster-captcha-lock-' . md5($this->tempDir) . '.lock');
    }

    /** 并发读不得读到半截/缺失内容，且并发自增不得丢计数 */
    public function testConcurrentReadNeverSeesPartialPayload(): void
    {
        $this->storage->set($this->key, ['type' => 'click', 'targets' => ['a', 'b']], 300);

        $processes = $this->startReaders();
        $this->assertCount(self::READERS, $processes, '读进程未全部启动');

        $increments = 0;
        $deadline = microtime(true) + self::RUN_SECONDS;
        while (microtime(true) < $deadline) {
            $this->storage->incrementAttempts($this->key);
            $increments++;
        }

        $ok = 0;
        $bad = 0;
        foreach ($processes as $process) {
            $result = json_decode($this->collect($process), true);
            $this->assertIsArray($result, '读进程未返回结果，无法判定并发行为');
            $ok += $result['ok'];
            $bad += $result['bad'];
        }

        $this->assertGreaterThan(0, $increments, '主进程没有完成任何一次自增，压测无效');
        $this->assertGreaterThan(0, $ok, '读进程一次都没读到完整数据，压测无效');
        $this->assertSame(0, $bad, "并发读到了 {$bad} 次损坏/缺失的 payload（torn read）");
        $this->assertSame($increments, $this->storage->get($this->key)['attempts'], '并发自增丢了计数');
    }

    /** @return resource[] */
    private function startReaders(): array
    {
        $command = [
            PHP_BINARY,
            __DIR__ . '/concurrency-worker.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
            $this->tempDir,
            $this->key,
            (string) self::RUN_SECONDS,
        ];
        $processes = [];
        for ($i = 0; $i < self::READERS; $i++) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $pipes = [];
            $process = proc_open($command, $descriptors, $pipes);
            $this->assertIsResource($process, '无法启动读进程（proc_open 失败）');
            $processes[] = [$process, $pipes];
        }
        return $processes;
    }

    /** @param array{0: resource, 1: array} $process */
    private function collect(array $process): string
    {
        [$resource, $pipes] = $process;
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($resource);

        $this->assertSame(0, $exitCode, "读进程异常退出：\n" . $stderr);
        return $stdout;
    }
}
