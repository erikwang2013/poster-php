<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class SliderCaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private FileStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-slider-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
        $this->manager = new CaptchaManager(new GdDriver(), $this->storage);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.{png,json}', GLOB_BRACE));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    /** 测试：默认难度下拼图块 50x50，puzzle 为 data:image/png 数据 */
    public function testDefaultPuzzleSizeIs50(): void
    {
        $result = $this->manager->create('slider')->generate();
        $this->assertSame(50, $result['extra']['puzzle_w']);
        $this->assertSame(50, $result['extra']['puzzle_h']);
        $this->assertStringStartsWith('data:image/png;base64,', $result['extra']['puzzle']);
    }

    /** 测试：hard 难度拼图块缩小为 40x40 */
    public function testHardDifficultyShrinksPuzzle(): void
    {
        $result = $this->manager->create('slider')->setDifficulty('hard')->generate();
        $this->assertSame(40, $result['extra']['puzzle_w']);
        $this->assertSame(40, $result['extra']['puzzle_h']);
    }

    /** 测试：小背景（100x80）下拼图 x/y 被钳制到最小合法值 50/20（确定性） */
    public function testSmallBackgroundClampsPuzzlePosition(): void
    {
        $img = imagecreatetruecolor(100, 80);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        $path = $this->tempDir . '/small.png';
        imagepng($img, $path);
        imagedestroy($img);

        $result = $this->manager->create('slider')->setBackground($path)->generate();
        $stored = $this->storage->get($result['key']);
        $this->assertSame(50, $stored['x']);
        $this->assertSame(20, $stored['y']);
        unlink($path);
    }

    /** 测试：真实 x 及 ±4 像素边界可通过，±5 像素失败 */
    public function testVerifyToleranceBoundary(): void
    {
        foreach ([0, 4, -4] as $offset) {
            $result = $this->manager->create('slider')->generate();
            $x = $this->storage->get($result['key'])['x'];
            $this->assertTrue($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x + $offset]));
        }

        $result = $this->manager->create('slider')->generate();
        $x = $this->storage->get($result['key'])['x'];
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x + 5]));
    }

    /** 测试：非数字滑块数据验证失败 */
    public function testVerifyNonNumericFails(): void
    {
        $result = $this->manager->create('slider')->generate();
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'slider', 'data' => 'NaN']));
    }
}
