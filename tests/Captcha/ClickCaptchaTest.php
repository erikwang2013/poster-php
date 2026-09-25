<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Captcha\ClickCaptcha;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClickCaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private FileStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-click-' . uniqid();
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

    private function generate(string $difficulty = 'medium'): array
    {
        return $this->manager->create('click')->setDifficulty($difficulty)->generate();
    }

    /** 造一张指定尺寸的纯色背景图，返回路径 */
    private function backgroundImage(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        $path = $this->tempDir . '/' . uniqid() . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    /** 解码 data URI 图片为 GD 资源 */
    private function decode(string $dataUri)
    {
        return imagecreatefromstring(base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)));
    }

    /** 测试：setWords 的自定义词会被写入点击目标，顺序从 1 递增 */
    public function testSetWordsCustomWordsUsedInTargets(): void
    {
        $result = $this->manager->create('click')
            ->setWords(['甲', '乙'])
            ->setDifficulty('easy')
            ->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(2, $targets);
        foreach ($targets as $i => $t) {
            $this->assertContains($t['text'], ['甲', '乙']);
            $this->assertSame($i + 1, $t['order']);
        }
    }

    /** 测试：setWords([]) 空数组时回退到配置的 click_words */
    public function testEmptyWordsFallsBackToConfiguredWords(): void
    {
        PosterConfig::merge(['captcha' => ['click_words' => ['子', '丑']]]);
        $result = $this->manager->create('click')->setWords([])->setDifficulty('easy')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(2, $targets);
        foreach ($targets as $t) {
            $this->assertContains($t['text'], ['子', '丑']);
        }
    }

    /** 测试：不同难度对应不同目标数量（easy=2 / medium=3 / hard=4）
     * 数据内联为循环而非 @dataProvider：doc-comment 元数据在 PHPUnit 11 已废弃，
     * 而 PHP 8.0 只能配 PHPUnit 9（不支持 attributes），内联两者都兼容。
     */
    public function testTargetCountByDifficulty(): void
    {
        foreach (['easy' => 2, 'medium' => 3, 'hard' => 4] as $difficulty => $expected) {
            $result = $this->generate($difficulty);
            $targets = $this->storage->get($result['key'])['targets'];
            $this->assertCount($expected, $targets, "难度 {$difficulty} 应生成 {$expected} 个目标");
        }
    }

    /** 测试：默认难度为 medium，生成 3 个目标 */
    public function testDefaultDifficultyIsMedium(): void
    {
        $result = $this->manager->create('click')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(3, $targets);
    }

    /** 测试：extra.texts 与存储目标一一对应，包含 text 与 order */
    public function testExtraTextsMatchStoredTargets(): void
    {
        $result = $this->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(count($targets), $result['extra']['texts']);
        foreach ($result['extra']['texts'] as $i => $item) {
            $this->assertSame($targets[$i]['text'], $item['text']);
            $this->assertSame($targets[$i]['order'], $item['order']);
        }
    }

    /** 测试：setTargetType 与 setWords 均为链式方法（返回自身） */
    public function testAdditionalSettersAreFluent(): void
    {
        $captcha = $this->manager->create('click');
        $this->assertInstanceOf(ClickCaptcha::class, $captcha->setTargetType('text'));
        $this->assertInstanceOf(ClickCaptcha::class, $captcha->setWords(['一']));
    }

    /** 测试：默认 targetType 为 text 可正常生成；不支持的类型在 generate 时抛异常并列出合法取值 */
    public function testTargetTypeValidatedOnGenerate(): void
    {
        $result = $this->manager->create('click')->generate();
        $this->assertSame('click', $result['type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('text');
        $this->manager->create('click')->setTargetType('image')->generate();
    }

    /** 测试：默认 300×200 下目标仍落在旧的边距区间（x∈[40,260] y∈[40,120]），行为不变 */
    public function testDefaultCanvasKeepsLegacyPlacementRange(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $targets = $this->storage->get($this->generate('hard')['key'])['targets'];
            foreach ($targets as $t) {
                $this->assertGreaterThanOrEqual(40, $t['x']);
                $this->assertLessThanOrEqual(260, $t['x']);
                $this->assertGreaterThanOrEqual(40, $t['y']);
                $this->assertLessThanOrEqual(120, $t['y']);
            }
        }
    }

    /** 测试：目标两两间距 >= 2×容差（36px），任意点最多只落进一个目标的容差圆 */
    public function testTargetsKeepMinimumSpacing(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $targets = $this->storage->get($this->generate('hard')['key'])['targets'];
            $count = count($targets);
            for ($a = 0; $a < $count; $a++) {
                for ($b = $a + 1; $b < $count; $b++) {
                    $dx = $targets[$a]['x'] - $targets[$b]['x'];
                    $dy = $targets[$a]['y'] - $targets[$b]['y'];
                    $this->assertGreaterThanOrEqual(36, sqrt($dx * $dx + $dy * $dy));
                }
            }
        }
    }

    /** 测试：小画布装不下目标时明确拒绝并给出最小尺寸（旧实现会静默塌缩到同一像素：60×60 → 全在 (40,41)） */
    public function testTooSmallCanvasIsRejectedWithMinimumSize(): void
    {
        $path = $this->backgroundImage(60, 60);
        try {
            $this->manager->create('click')->setBackground($path)->generate();
            $this->fail('60×60 画布应抛 InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('too small', $e->getMessage());
            $this->assertStringContainsString('120x120', $e->getMessage());
        }
        unlink($path);
    }

    /** 测试：小画布在 easy 难度（2 目标）下同样拒绝，且不会把目标放到画布外 */
    public function testTinyCanvasRejectedOnEveryDifficulty(): void
    {
        $path = $this->backgroundImage(80, 40);
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            try {
                $this->manager->create('click')->setDifficulty($difficulty)->setBackground($path)->generate();
                $this->fail("{$difficulty} 难度下 80×40 画布应抛异常");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('too small', $e->getMessage());
            }
        }
        unlink($path);
    }

    /** 测试：刚好够用的画布可以出题（不是一律拒绝） */
    public function testMinimumCanvasStillGenerates(): void
    {
        $path = $this->backgroundImage(120, 120);
        $result = $this->manager->create('click')->setBackground($path)->generate();
        $this->assertSame('click', $result['type']);
        foreach ($this->storage->get($result['key'])['targets'] as $t) {
            $this->assertGreaterThanOrEqual(0, $t['x']);
            $this->assertLessThan(120, $t['x']);
            $this->assertGreaterThanOrEqual(0, $t['y']);
            $this->assertLessThan(120, $t['y']);
        }
        unlink($path);
    }

    /** 测试：目标颜色随机（旧实现恒定 #FF4444，按色分离即可精确还原坐标） */
    public function testTargetColorsAreRandomized(): void
    {
        $colors = [];
        for ($i = 0; $i < 8; $i++) {
            foreach ($this->storage->get($this->generate()['key'])['targets'] as $t) {
                $colors[$t['color']] = true;
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $t['color']);
            }
        }
        $this->assertGreaterThan(5, count($colors), '24 个目标应出现多种颜色');
    }

    /** 测试：出图里不再出现旧的恒定目标色 #FF4444（颜色分离的还原路径被破坏） */
    public function testGeneratedImageHasNoConstantTargetColor(): void
    {
        $result = $this->generate('hard');
        $image = $this->decode($result['image']);
        $hits = 0;
        for ($x = 0; $x < imagesx($image); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                if ((imagecolorat($image, $x, $y) & 0xFFFFFF) === 0xFF4444) {
                    $hits++;
                }
            }
        }
        imagedestroy($image);
        $this->assertSame(0, $hits);
    }

    /** 测试：目标带随机旋转角度（±15°），且记录在存储中 */
    public function testTargetsCarryRandomRotation(): void
    {
        $angles = [];
        for ($i = 0; $i < 6; $i++) {
            foreach ($this->storage->get($this->generate('hard')['key'])['targets'] as $t) {
                $this->assertGreaterThanOrEqual(-15, $t['angle']);
                $this->assertLessThanOrEqual(15, $t['angle']);
                $angles[$t['angle']] = true;
            }
        }
        $this->assertGreaterThan(3, count($angles));
    }

    /** 测试：icon 目标类型可用，extra.texts 每项带 thumb 数据 URI，形状同轮不重复 */
    public function testIconTargetsProvideThumbnails(): void
    {
        $result = $this->manager->create('click')->setTargetType('icon')->setDifficulty('easy')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(2, $targets);
        $this->assertNotSame($targets[0]['text'], $targets[1]['text']);
        $this->assertCount(2, $result['extra']['texts']);
        foreach ($result['extra']['texts'] as $i => $hint) {
            $this->assertSame($targets[$i]['text'], $hint['text']);
            $this->assertSame($targets[$i]['order'], $hint['order']);
            $this->assertStringStartsWith('data:image/png;base64,', $hint['thumb']);
        }
    }

    /** 测试：icon 目标仍是坐标比对，按存储坐标提交可通过 */
    public function testIconTargetsVerifyByCoordinates(): void
    {
        $result = $this->manager->create('click')->setTargetType('icon')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $data = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        $this->assertTrue($this->manager->verify($result['key'], ['type' => 'click', 'data' => $data]));

        $wrong = $this->manager->create('click')->setTargetType('icon')->generate();
        $this->assertFalse($this->manager->verify($wrong['key'], ['type' => 'click', 'data' => [[0, 0], [1, 1], [2, 2]]]));
    }

    /** 测试：icon 的形状名与图元渲染覆盖 8~12 种；hard 下 4 个目标形状互不相同 */
    public function testIconShapesAreDistinctVectors(): void
    {
        $result = $this->manager->create('click')->setTargetType('icon')->setDifficulty('hard')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $names = array_column($targets, 'text');
        $this->assertSame($names, array_unique($names));
        foreach ($names as $name) {
            $this->assertContains($name, ['circle', 'ring', 'square', 'rounded', 'bar', 'cross', 'x', 'chevron', 'semicircle', 'wedge', 'asterisk']);
        }

        // 不同形状的缩略图内容不同（同一渲染路径生成）
        $thumbs = array_column($result['extra']['texts'], 'thumb');
        $this->assertSame($thumbs, array_unique($thumbs));
    }

    /** 测试：缩略图用中性色渲染，不把画布上的随机目标色泄露给前端 */
    public function testIconThumbDoesNotLeakCanvasColor(): void
    {
        $result = $this->manager->create('click')->setTargetType('icon')->setDifficulty('hard')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        foreach ($result['extra']['texts'] as $i => $hint) {
            $hex = ltrim($targets[$i]['color'], '#');
            $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
            $marker = ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2];

            $thumb = $this->decode($hint['thumb']);
            $hits = 0;
            for ($x = 0; $x < imagesx($thumb); $x++) {
                for ($y = 0; $y < imagesy($thumb); $y++) {
                    if ((imagecolorat($thumb, $x, $y) & 0xFFFFFF) === $marker) {
                        $hits++;
                    }
                }
            }
            imagedestroy($thumb);
            $this->assertSame(0, $hits);
        }
    }
}
