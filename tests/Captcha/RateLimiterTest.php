<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\RateLimiter;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use Erikwang2013\Poster\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-ratelimit-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    private function limit(int $max, int $window = 60): RateLimiter
    {
        PosterConfig::merge(['captcha' => ['rate_limit' => ['max' => $max, 'window' => $window]]]);
        return new RateLimiter($this->storage);
    }

    /** 测试：窗口内到上限为止放行，第 max+1 次拒绝（跨 key 盲猜被挡在这一层） */
    public function testBlocksAfterMaxWithinWindow(): void
    {
        $limiter = $this->limit(3);
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($limiter->allow('sess-a'), "第 {$i} 次应放行");
        }
        $this->assertFalse($limiter->allow('sess-a'));
        $this->assertFalse($limiter->allow('sess-a'));
    }

    /** 测试：不同身份各自计数，一个身份被限不影响另一个 */
    public function testIdentityCountersAreIsolated(): void
    {
        $limiter = $this->limit(1);
        $this->assertTrue($limiter->allow('sess-a'));
        $this->assertFalse($limiter->allow('sess-a'));
        $this->assertTrue($limiter->allow('sess-b'));
    }

    /** 测试：计数键为 rate:md5(身份)，不落明文身份；窗口滚动后计数重置 */
    public function testCounterKeyIsHashedAndWindowRollsOver(): void
    {
        $limiter = $this->limit(1, 1);
        $this->assertTrue($limiter->allow('sess-a'));
        $this->assertFalse($limiter->allow('sess-a'));

        $record = $this->storage->get('rate:' . md5('sess-a'));
        $this->assertNotNull($record);
        $this->assertSame(2, $record['attempts']);
        $this->assertNull($this->storage->get('rate:sess-a'));

        sleep(1);
        $this->assertTrue($limiter->allow('sess-a'), '新窗口应重新计数');
    }

    /** 测试：max<=0 视为关闭限流（逃生开关） */
    public function testMaxZeroDisablesLimiter(): void
    {
        $limiter = $this->limit(0);
        for ($i = 0; $i < 40; $i++) {
            $this->assertTrue($limiter->allow('sess-a'));
        }
        $this->assertNull($this->storage->get('rate:' . md5('sess-a')), '关闭时不应写计数键');
    }

    /** 测试：计数自增不可用（返回 0）时失败关闭——拿不到计数就不放行，绝不当作「第 1 次」 */
    public function testFailsClosedWhenCounterUnavailable(): void
    {
        PosterConfig::merge(['captcha' => ['rate_limit' => ['max' => 30, 'window' => 60]]]);
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->willReturn(null);
        $storage->method('incrementAttempts')->willReturn(0);

        $this->assertFalse((new RateLimiter($storage))->allow('sess-a'));
    }

    /** 测试：判定取自存储的原子自增返回值，而不是先前读到的快照 */
    public function testDecisionUsesAtomicIncrementReturn(): void
    {
        $limiter = $this->limit(2);
        $this->assertTrue($limiter->allow('sess-a'));
        // 预置计数到上限（模拟并发实例已写入的次数），下一次自增结果 3 > 2 必须拒绝
        $key = 'rate:' . md5('sess-a');
        $record = $this->storage->get($key);
        $this->storage->set($key, ['window' => $record['window'], 'attempts' => 2], 60);
        $this->assertFalse($limiter->allow('sess-a'));
    }
}
