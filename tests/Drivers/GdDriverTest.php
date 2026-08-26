<?php

/**
 * GdDriver 全面单元测试：创建/加载/变换/绘制/合成/输出全操作，
 * 直接以 GD 内存缓冲区断言尺寸与像素，覆盖正常与异常/边界路径。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GdDriverTest extends TestCase
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    /** 验证 create 后尺寸正确且背景为全透明。 */
    public function testCreateSetsSizeAndTransparentBackground(): void
    {
        $d = new GdDriver();
        $d->create(10, 10);
        $this->assertSame(['width' => 10, 'height' => 10], $d->getSize());
        $this->assertSame(127, (imagecolorat($d->getResource(), 0, 0) >> 24) & 0x7F);
    }

    /** 验证 create 非法尺寸（0/负数）抛出 InvalidArgumentException。 */
    public function testCreateWithZeroSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than 0');
        (new GdDriver())->create(0, 0);
    }

    public function testCreateWithNegativeSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GdDriver())->create(10, -5);
    }

    /** 验证 resize 改变尺寸。 */
    public function testResizeChangesDimensions(): void
    {
        $d = new GdDriver();
        $this->assertSame($d, $d->create(100, 50)->resize(50, 25));
        $this->assertSame(['width' => 50, 'height' => 25], $d->getSize());
    }

    /** 验证 rotate 90 度后宽高互换。 */
    public function testRotate90SwapsDimensions(): void
    {
        $d = new GdDriver();
        $d->create(50, 100)->rotate(90);
        $this->assertSame(['width' => 100, 'height' => 50], $d->getSize());
    }

    /** 验证 rotate 指定背景色时角落为不透明纯色。 */
    public function testRotateWithSolidBackgroundFillsCorners(): void
    {
        $d = new GdDriver();
        $d->create(20, 20)->rotate(45, '#FF0000');
        $px = imagecolorat($d->getResource(), 0, 0);
        $this->assertSame(0, ($px >> 24) & 0x7F);
        $this->assertGreaterThan(200, ($px >> 16) & 0xFF);
    }

    /** 验证 rotate 使用 transparent 背景时角落保留全透明。 */
    public function testRotateWithTransparentBackgroundKeepsAlpha(): void
    {
        $d = new GdDriver();
        $d->create(20, 20)->rotate(45, 'transparent');
        $this->assertSame(127, (imagecolorat($d->getResource(), 0, 0) >> 24) & 0x7F);
    }

    /** 验证 circle 生成直径见方图像，角落透明、中心不透明。 */
    public function testCircleMasksCornersTransparent(): void
    {
        $d = new GdDriver();
        $d->create(20, 20)->circle(16);
        $this->assertSame(['width' => 16, 'height' => 16], $d->getSize());
        $this->assertSame(127, (imagecolorat($d->getResource(), 0, 0) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($d->getResource(), 8, 8) >> 24) & 0x7F);
    }

    /** 验证 crop 改变尺寸并保留区域像素。 */
    public function testCropKeepsSelectedPixels(): void
    {
        $d = new GdDriver();
        $d->create(30, 30)->rectangle(5, 5, 20, 20, ['color' => '#FF0000'])->crop(5, 5, 20, 20);
        $this->assertSame(['width' => 20, 'height' => 20], $d->getSize());
        $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 10, 10) & 0xFFFFFF);
    }

    /** 验证内置字体 text 实际绘制出前景色像素。 */
    public function testTextBuiltinFontDrawsPixels(): void
    {
        $d = new GdDriver();
        $d->create(30, 20)->text('W', 0, 0, ['color' => '#FF0000']);
        $this->assertTrue($this->hasColorNear($d->getResource(), 255, 0, 0, 9, 15));
    }

    /** 验证 text 多行与对齐选项（left/center/right）不崩溃且不改变尺寸。 */
    public function testTextMultilineAndAlignments(): void
    {
        $d = new GdDriver();
        $d->create(100, 60);
        foreach (['left', 'center', 'right'] as $align) {
            $d->text("line1\nline2", 50, 10, ['align' => $align, 'maxWidth' => 80]);
        }
        $this->assertSame(['width' => 100, 'height' => 60], $d->getSize());
    }

    /** 验证 TTF 字体 text 实际绘制像素（无字体文件时跳过）。 */
    public function testTextTtfDrawsWithFontFile(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('系统无 TTF 字体可用');
        }
        $d = new GdDriver();
        $d->create(120, 40)->text('TTF', 0, 24, ['font' => self::FONT, 'size' => 20, 'color' => '#FF0000']);
        $this->assertTrue($this->hasColorNear($d->getResource(), 255, 0, 0, 69, 39));
    }

    /** 验证 TTF 换行（maxWidth）与倾斜（angle）选项不崩溃。 */
    public function testTextTtfWrapAndAngle(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('系统无 TTF 字体可用');
        }
        $d = new GdDriver();
        $d->create(200, 120);
        $d->text('This is a long text that must wrap onto multiple lines', 5, 20,
            ['font' => self::FONT, 'size' => 16, 'maxWidth' => 100, 'angle' => 30]);
        $this->assertSame(['width' => 200, 'height' => 120], $d->getSize());
    }

    /** 验证指定不存在的字体文件时回退到内置字体而不崩溃。 */
    public function testTextWithMissingFontFallsBackToBuiltin(): void
    {
        $d = new GdDriver();
        $d->create(50, 30)->text('fallback', 0, 10, ['font' => '/no/such/font.ttf']);
        $this->assertSame(['width' => 50, 'height' => 30], $d->getSize());
    }

    /** 验证 image 在指定坐标合成覆盖层像素。 */
    public function testImageOverlayCompositesAtPosition(): void
    {
        $canvas = (new GdDriver())->create(100, 100)->rectangle(0, 0, 100, 100, ['color' => '#FFFFFF']);
        $overlay = (new GdDriver())->create(20, 20)->rectangle(0, 0, 20, 20, ['color' => '#FF0000']);
        $canvas->image($overlay, 10, 10);
        $this->assertSame(0xFF0000, imagecolorat($canvas->getResource(), 15, 15) & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, imagecolorat($canvas->getResource(), 5, 5) & 0xFFFFFF);
    }

    /** 验证 image 支持 width/height 缩放选项。 */
    public function testImageOverlayWithScaleOptions(): void
    {
        $canvas = (new GdDriver())->create(100, 100)->rectangle(0, 0, 100, 100, ['color' => '#FFFFFF']);
        $overlay = (new GdDriver())->create(20, 20)->rectangle(0, 0, 20, 20, ['color' => '#FF0000']);
        $canvas->image($overlay, 10, 10, ['width' => 40, 'height' => 40]);
        $this->assertSame(0xFF0000, imagecolorat($canvas->getResource(), 45, 45) & 0xFFFFFF);
    }

    /** 验证 image 支持 radius 圆角与 shadow 阴影选项而不崩溃。 */
    public function testImageOverlayWithRadiusAndShadow(): void
    {
        $canvas = (new GdDriver())->create(100, 100);
        $overlay = (new GdDriver())->create(20, 20);
        $canvas->image($overlay, 10, 10, [
            'radius' => 5,
            'shadow' => ['color' => '#000000', 'offsetX' => 4, 'offsetY' => 4, 'blur' => 4],
        ]);
        $this->assertInstanceOf(\GdImage::class, $canvas->getResource());
    }

    /** 验证实心矩形绘制出填充色像素。 */
    public function testRectangleFilledDrawsPixel(): void
    {
        $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => '#FF0000']);
        $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 5, 5) & 0xFFFFFF);
    }

    /** 验证空心矩形仅边框着色、内部留空。 */
    public function testRectangleUnfilledLeavesInterior(): void
    {
        $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => '#FF0000', 'filled' => false]);
        $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 0, 0) & 0xFFFFFF);
        $this->assertNotSame(0xFF0000, imagecolorat($d->getResource(), 5, 5) & 0xFFFFFF);
    }

    /** 验证矩形圆角半径选项不崩溃。 */
    public function testRectangleWithRadiusDraws(): void
    {
        $d = (new GdDriver())->create(40, 40)->rectangle(5, 5, 30, 30, ['color' => '#FF0000', 'radius' => 6]);
        $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 20, 20) & 0xFFFFFF);
    }

    /** 验证矩形 opacity 选项转换为半透明 alpha（0.5 -> 63）。 */
    public function testRectangleOpacitySetsAlpha(): void
    {
        $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => '#FF0000', 'opacity' => 0.5]);
        $this->assertSame(63, (imagecolorat($d->getResource(), 5, 5) >> 24) & 0x7F);
    }

    /** 验证 8 位十六进制颜色（RRGGBBAA）解析 alpha。 */
    public function testRectangleHexAlphaColor(): void
    {
        $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => '#FF000080']);
        $this->assertSame(63, (imagecolorat($d->getResource(), 5, 5) >> 24) & 0x7F);
    }

    /** 验证实心椭圆中心着色、空心椭圆仅边缘着色。 */
    public function testEllipseFilledAndUnfilled(): void
    {
        $filled = (new GdDriver())->create(40, 40)->ellipse(20, 20, 10, 10, ['color' => '#FF0000']);
        $this->assertSame(0xFF0000, imagecolorat($filled->getResource(), 20, 20) & 0xFFFFFF);
        $hollow = (new GdDriver())->create(40, 40)->ellipse(20, 20, 10, 10, ['color' => '#FF0000', 'filled' => false]);
        $this->assertNotSame(0xFF0000, imagecolorat($hollow->getResource(), 20, 20) & 0xFFFFFF);
        $this->assertSame(0xFF0000, imagecolorat($hollow->getResource(), 20, 10) & 0xFFFFFF);
    }

    /** 验证 filledArc 按 GD 顺时针角度约定填充扇形（0-180 度覆盖下半部）。 */
    public function testFilledArcSweepsClockwise(): void
    {
        $d = (new GdDriver())->create(40, 40)->filledArc(20, 20, 20, 20, 0, 180, ['color' => '#FF0000']);
        $this->assertNotSame(0xFF0000, imagecolorat($d->getResource(), 20, 15) & 0xFFFFFF);
        $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 20, 25) & 0xFFFFFF);
    }

    /** 验证 line 绘制像素，且 thickness 选项加粗。 */
    public function testLineDrawsWithThickness(): void
    {
        $thin = (new GdDriver())->create(20, 20)->line(5, 5, 15, 5, ['color' => '#FF0000']);
        $this->assertSame(0xFF0000, imagecolorat($thin->getResource(), 10, 5) & 0xFFFFFF);
        $thick = (new GdDriver())->create(20, 20)->line(5, 10, 15, 10, ['color' => '#FF0000', 'width' => 3]);
        $this->assertSame(0xFF0000, imagecolorat($thick->getResource(), 10, 9) & 0xFFFFFF);
        $this->assertSame(0xFF0000, imagecolorat($thick->getResource(), 10, 10) & 0xFFFFFF);
    }

    /** 验证 blur/sharpen/pixelate 链式调用后尺寸不变。 */
    public function testFiltersDoNotChangeSize(): void
    {
        $d = (new GdDriver())->create(20, 20)->blur(2)->sharpen(1.5)->pixelate(4);
        $this->assertSame(['width' => 20, 'height' => 20], $d->getSize());
    }

    /** 验证 save 各格式（jpg/png/gif/webp，含大写格式名）写入文件且可被解码。 */
    public function testSaveAllFormats(): void
    {
        $d = (new GdDriver())->create(20, 20);
        foreach (['jpg', 'png', 'gif', 'webp', 'PNG'] as $fmt) {
            if ($fmt === 'webp' && !(gd_info()['WebP Support'] ?? false)) {
                continue;
            }
            $path = sys_get_temp_dir() . '/poster-save-' . uniqid() . '.' . strtolower($fmt);
            $this->assertTrue($d->save($path, $fmt));
            $info = getimagesize($path);
            $this->assertSame([20, 20], [$info[0], $info[1]]);
            unlink($path);
        }
    }

    /** 验证 save 自动递归创建不存在的目录。 */
    public function testSaveCreatesNestedDirectories(): void
    {
        $nested = sys_get_temp_dir() . '/poster-nested-' . uniqid() . '/sub';
        $path = $nested . '/img.png';
        $this->assertTrue((new GdDriver())->create(10, 10)->save($path, 'png'));
        $this->assertFileExists($path);
        unlink($path);
        rmdir($nested);
        rmdir(dirname($nested));
    }

    /** 验证 save 的极端质量参数（0/100）仍能写出文件。 */
    public function testSaveQualityExtremes(): void
    {
        $d = (new GdDriver())->create(10, 10);
        foreach ([0, 100] as $q) {
            $path = sys_get_temp_dir() . '/poster-q-' . uniqid() . '.jpg';
            $this->assertTrue($d->save($path, 'jpg', $q));
            $this->assertFileExists($path);
            unlink($path);
        }
    }

    /** 验证 output 各格式返回可解码的 data URL 且尺寸正确。 */
    public function testOutputFormatsReturnValidDataUrls(): void
    {
        $d = (new GdDriver())->create(12, 7);
        foreach (['jpg', 'png', 'gif', 'webp'] as $fmt) {
            if ($fmt === 'webp' && !(gd_info()['WebP Support'] ?? false)) {
                continue;
            }
            $out = $d->output($fmt);
            $this->assertStringStartsWith("data:image/$fmt;base64,", $out);
            $info = getimagesizefromstring(base64_decode(substr($out, strpos($out, ',') + 1)));
            $this->assertSame([12, 7], [$info[0], $info[1]]);
        }
    }

    /** 验证 load 合法 PNG 后尺寸正确（GD 往返）。 */
    public function testLoadValidPngRoundTrip(): void
    {
        $path = sys_get_temp_dir() . '/poster-l-' . uniqid() . '.png';
        (new GdDriver())->create(30, 40)->save($path, 'png');
        $d = new GdDriver();
        $this->assertSame($d, $d->load($path));
        $this->assertSame(['width' => 30, 'height' => 40], $d->getSize());
        unlink($path);
    }

    /** 验证 load 不存在的文件抛出 InvalidArgumentException。 */
    public function testLoadMissingFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');
        (new GdDriver())->load('/nonexistent/poster-test.png');
    }

    /** 验证 load 无法解析的文件抛出 RuntimeException（"Cannot read image"）。 */
    public function testLoadUnreadableContentThrows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'poster-');
        file_put_contents($path, 'not an image at all');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read image');
            (new GdDriver())->load($path);
        } finally {
            unlink($path);
        }
    }

    /** 验证 load 不支持的类型（ICO）抛出 RuntimeException。 */
    public function testLoadUnsupportedTypeThrows(): void
    {
        $ico = "\x00\x00\x01\x00\x01\x00" . pack('CCCCvvVV', 16, 16, 0, 0, 1, 32, 22, 22);
        $path = tempnam(sys_get_temp_dir(), 'poster-') . '.ico';
        file_put_contents($path, $ico);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsupported image type');
            (new GdDriver())->load($path);
        } finally {
            unlink($path);
        }
    }

    /** 验证 getResource 返回 GdImage 实例，getSize 返回结构化尺寸。 */
    public function testGetResourceAndGetSizeTypes(): void
    {
        $d = (new GdDriver())->create(5, 7);
        $this->assertInstanceOf(\GdImage::class, $d->getResource());
        $this->assertSame(['width' => 5, 'height' => 7], $d->getSize());
    }

    /** 验证 setGdResource 接管外部创建的 GD 图像。 */
    public function testSetGdResourceAdoptsExternalImage(): void
    {
        $img = imagecreatetruecolor(5, 7);
        $d = new GdDriver();
        $d->setGdResource($img);
        $this->assertSame(['width' => 5, 'height' => 7], $d->getSize());
        $this->assertSame($img, $d->getResource());
    }

    /** 验证 clone 深拷贝：销毁原对象后克隆体仍可用。 */
    public function testCloneIsIndependent(): void
    {
        $d = (new GdDriver())->create(20, 20);
        $c = $d->clone();
        $d->destroy();
        $this->assertSame(['width' => 20, 'height' => 20], $c->getSize());
        $this->assertStringStartsWith('data:image/png;base64,', $c->output('png'));
    }

    /** 验证 destroy 释放资源后 getResource 返回 null。 */
    public function testDestroyClearsResource(): void
    {
        $d = (new GdDriver())->create(10, 10);
        $d->destroy();
        $this->assertNull($d->getResource());
    }

    /** 在指定区域内查找近似颜色像素（容忍抗锯齿）。 */
    private function hasColorNear(\GdImage $img, int $r, int $g, int $b, int $maxX, int $maxY, int $tol = 60): bool
    {
        for ($y = 0; $y <= $maxY; $y++) {
            for ($x = 0; $x <= $maxX; $x++) {
                $px = imagecolorat($img, $x, $y);
                if (abs((($px >> 16) & 0xFF) - $r) <= $tol
                    && abs((($px >> 8) & 0xFF) - $g) <= $tol
                    && abs(($px & 0xFF) - $b) <= $tol) {
                    return true;
                }
            }
        }
        return false;
    }
}
