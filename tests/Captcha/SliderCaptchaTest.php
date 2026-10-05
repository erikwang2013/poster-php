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
use InvalidArgumentException;
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

    /** 测试：jigsaw 拼图块为外扩后的外接矩形（50+2×10=70），轮廓外透明、本体内保留原像素 */
    public function testJigsawPieceIsPaddedAndMasked(): void
    {
        $result = $this->manager->create('slider')->setShape('jigsaw')->generate();
        $this->assertSame(50, $result['extra']['puzzle_w']);
        $this->assertSame(50, $result['extra']['puzzle_h']);

        $img = $this->pieceImage($result);
        $this->assertSame(70, imagesx($img), '外接矩形 = 本体 + 两侧凸出半径');
        $this->assertSame(70, imagesy($img));
        $this->assertSame(127, (imagecolorat($img, 0, 0) >> 24) & 0x7F, '外接矩形四角应在轮廓外（透明）');
        $this->assertSame(0, (imagecolorat($img, 35, 35) >> 24) & 0x7F, '本体中心应保留原像素');
    }

    /** 测试：hard 难度 jigsaw 凸出半径随本体缩小（40/5=8，外接 56×56） */
    public function testHardJigsawShrinksKnob(): void
    {
        $result = $this->manager->create('slider')->setDifficulty('hard')->setShape('jigsaw')->generate();
        $img = $this->pieceImage($result);
        $this->assertSame(56, imagesx($img));
        $this->assertSame(56, imagesy($img));
    }

    /** 测试：jigsaw 存储坐标是拼图块 PNG 左上角（本体位置减凸出半径），仍保留多位随机性 */
    public function testJigsawStoresPaddedOrigin(): void
    {
        $positions = [];
        for ($i = 0; $i < 8; $i++) {
            $stored = $this->storage->get($this->manager->create('slider')->setShape('jigsaw')->generate()['key']);
            // 本体 x∈[50,200] y∈[20,130]（同 square），各减凸出半径 10
            $this->assertGreaterThanOrEqual(40, $stored['x']);
            $this->assertLessThanOrEqual(190, $stored['x']);
            $this->assertGreaterThanOrEqual(10, $stored['y']);
            $this->assertLessThanOrEqual(120, $stored['y']);
            $positions[$stored['x']] = true;
        }
        $this->assertGreaterThan(2, count($positions), '外扩坐标不应退化为固定值');
    }

    /** 测试：jigsaw 的验证容差与 square 相同（存储坐标即前端放置坐标，±4 通过 ±5 失败） */
    public function testJigsawVerifyToleranceBoundary(): void
    {
        foreach ([0, 4, -4] as $offset) {
            $result = $this->manager->create('slider')->setShape('jigsaw')->generate();
            $x = $this->storage->get($result['key'])['x'];
            $this->assertTrue($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x + $offset]));
        }

        $result = $this->manager->create('slider')->setShape('jigsaw')->generate();
        $x = $this->storage->get($result['key'])['x'];
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x + 5]));
    }

    /** 测试：captcha.slider_shape 配置生效（未显式 setShape 时取配置） */
    public function testJigsawShapeFromConfig(): void
    {
        PosterConfig::merge(['captcha' => ['slider_shape' => 'jigsaw']]);
        $img = $this->pieceImage($this->manager->create('slider')->generate());
        $this->assertSame(70, imagesx($img));
    }

    /** 测试：未知形状明确拒绝（不静默回退为 square） */
    public function testUnknownShapeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown slider shape');
        $this->manager->create('slider')->setShape('triangle')->generate();
    }

    /** 测试：小背景（100x80）无法容纳拼图 + 边距时明确拒绝（旧实现把 x 钳成唯一值 50，盲猜必中） */
    public function testSmallBackgroundIsRejected(): void
    {
        $img = imagecreatetruecolor(100, 80);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        $path = $this->tempDir . '/small.png';
        imagepng($img, $path);

        try {
            $this->manager->create('slider')->setBackground($path)->generate();
            $this->fail('100×80 画布应抛 InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('too small', $e->getMessage());
            $this->assertStringContainsString('200x100', $e->getMessage());
        }
        unlink($path);
    }

    /** 测试：刚好达到最小尺寸（200x100）的画布可以出题，缺口仍有多个可能位置 */
    public function testMinimumCanvasStillGenerates(): void
    {
        $img = imagecreatetruecolor(200, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        $path = $this->tempDir . '/min.png';
        imagepng($img, $path);

        $positions = [];
        for ($i = 0; $i < 10; $i++) {
            $stored = $this->storage->get($this->manager->create('slider')->setBackground($path)->generate()['key']);
            $this->assertGreaterThanOrEqual(50, $stored['x']);
            $this->assertLessThanOrEqual(100, $stored['x']);
            $positions[$stored['x']] = true;
        }
        $this->assertGreaterThan(2, count($positions), '最小尺寸下缺口仍有多个可能位置');
        unlink($path);
    }

    /** 测试：默认 300×200 下缺口位置仍在旧的取值区间（x∈[50,200] y∈[20,130]），行为不变 */
    public function testDefaultCanvasKeepsLegacyPositionRange(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $stored = $this->storage->get($this->manager->create('slider')->generate()['key']);
            $this->assertGreaterThanOrEqual(50, $stored['x']);
            $this->assertLessThanOrEqual(200, $stored['x']);
            $this->assertGreaterThanOrEqual(20, $stored['y']);
            $this->assertLessThanOrEqual(130, $stored['y']);
        }
    }

    /** 测试：缺口位置确有随机性（钳制退化的回归防线：固定值会被盲猜命中） */
    public function testGapPositionHasEntropy(): void
    {
        $positions = [];
        for ($i = 0; $i < 20; $i++) {
            $positions[$this->storage->get($this->manager->create('slider')->generate()['key'])['x']] = true;
        }
        $this->assertGreaterThan(5, count($positions));
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

    /** 解码 extra.puzzle 的 data URI 为 GD 图像（含 alpha）。 */
    private function pieceImage(array $result): \GdImage
    {
        $png = base64_decode(substr($result['extra']['puzzle'], strlen('data:image/png;base64,')));
        $img = imagecreatefromstring($png);
        $this->assertNotFalse($img);
        return $img;
    }
}
