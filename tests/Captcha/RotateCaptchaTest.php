<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Captcha\RotateCaptcha;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class RotateCaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private FileStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-rotate-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
        $this->manager = new CaptchaManager(new GdDriver(), $this->storage);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    /** 测试：setSize 将尺寸钳制在 60~400 之间 */
    public function testSetSizeClampsToBounds(): void
    {
        foreach ([10 => 60, 500 => 400, 200 => 200] as $input => $expected) {
            $result = $this->manager->create('rotate')->setSize($input)->generate();
            $orig = $this->storage->get($result['key'])['orig_size'];
            $this->assertSame($expected, $orig['width']);
            $this->assertSame($expected, $orig['height']);
        }
    }

    /** 测试：min>max 的角度区间会被钳制到最小角（确定性验证） */
    public function testSetAngleRangeInvertedClampsToMin(): void
    {
        $result = $this->manager->create('rotate')
            ->setAngleRange(100, 50)
            ->setDifficulty('medium')
            ->generate();
        $stored = $this->storage->get($result['key']);
        $this->assertSame(100, $stored['angle']);
    }

    /** 测试：easy/hard 难度下实际角度落在对应区间内 */
    public function testAngleWithinDifficultyRanges(): void
    {
        $easy = $this->manager->create('rotate')->setDifficulty('easy')->generate();
        $this->assertGreaterThanOrEqual(10, $this->storage->get($easy['key'])['angle']);
        $this->assertLessThanOrEqual(90, $this->storage->get($easy['key'])['angle']);

        $hard = $this->manager->create('rotate')->setDifficulty('hard')->generate();
        $this->assertGreaterThanOrEqual(90, $this->storage->get($hard['key'])['angle']);
        $this->assertLessThanOrEqual(330, $this->storage->get($hard['key'])['angle']);
    }

    /** 测试：真实角度及 ±360 取模等价角度均可验证通过 */
    public function testVerifyWithWrappedAnglesPasses(): void
    {
        foreach ([0, 360, -360] as $offset) {
            $result = $this->manager->create('rotate')->setDifficulty('easy')->generate();
            $angle = $this->storage->get($result['key'])['angle'];
            $this->assertTrue($this->manager->verify(
                $result['key'],
                ['type' => 'rotate', 'data' => $angle + $offset]
            ));
        }
    }

    /** 测试：偏离真实角度超过容忍度（6 度）时验证失败 */
    public function testVerifyAngleBeyondToleranceFails(): void
    {
        $result = $this->manager->create('rotate')->setDifficulty('easy')->generate();
        $angle = $this->storage->get($result['key'])['angle'];
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'rotate', 'data' => $angle + 6]));
    }

    /** 测试：非数字角度数据验证失败 */
    public function testVerifyNonNumericAngleFails(): void
    {
        $result = $this->manager->create('rotate')->setDifficulty('easy')->generate();
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'rotate', 'data' => 'abc']));
    }

    /** 测试：setSize 与 setAngleRange 均为链式方法（返回自身） */
    public function testSettersAreFluent(): void
    {
        $captcha = $this->manager->create('rotate');
        $this->assertInstanceOf(RotateCaptcha::class, $captcha->setSize(300));
        $this->assertInstanceOf(RotateCaptcha::class, $captcha->setAngleRange(10, 100));
    }
}
