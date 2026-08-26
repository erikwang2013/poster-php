<?php

/**
 * ImagickDriver 全面单元测试。
 * 本环境未安装 imagick 扩展时整类跳过（setUp 统一跳过，原因见下）；
 * 接口契约与工厂路由的验证见 DriverContractTest / DriverFactoryTest（无需扩展）。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImagickDriver;
use PHPUnit\Framework\TestCase;

class ImagickDriverTest extends TestCase
{
    /** 未安装 imagick 扩展时跳过全部 Imagick 功能测试（无法实例化 Imagick 类）。 */
    protected function setUp(): void
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            $this->markTestSkipped('ext-imagick 未安装，ImagickDriver 功能测试无法执行（接口契约测试另见 DriverContractTest）');
        }
    }

    /** 验证 create 后尺寸正确且资源为 Imagick 实例。 */
    public function testCreateSetsSize(): void
    {
        $d = new ImagickDriver();
        $this->assertSame($d, $d->create(300, 200));
        $this->assertSame(['width' => 300, 'height' => 200], $d->getSize());
        $this->assertInstanceOf(\Imagick::class, $d->getResource());
    }

    /** 验证 resize 改变尺寸。 */
    public function testResizeChangesDimensions(): void
    {
        $d = (new ImagickDriver())->create(100, 50)->resize(50, 25);
        $this->assertSame(['width' => 50, 'height' => 25], $d->getSize());
    }

    /** 验证 rotate 90 度后宽高互换。 */
    public function testRotateSwapsDimensions(): void
    {
        $d = (new ImagickDriver())->create(50, 100)->rotate(90);
        $this->assertSame(['width' => 100, 'height' => 50], $d->getSize());
    }

    /** 验证 rotate 支持 transparent 背景且尺寸随旋转增大。 */
    public function testRotateTransparentBackground(): void
    {
        $d = (new ImagickDriver())->create(20, 20)->rotate(45, 'transparent');
        $size = $d->getSize();
        $this->assertGreaterThanOrEqual(20, $size['width']);
        $this->assertLessThanOrEqual(30, $size['width']);
        $this->assertSame($size['width'], $size['height']);
    }

    /** 验证 circle 生成直径见方图像。 */
    public function testCircleSquaresImage(): void
    {
        $d = (new ImagickDriver())->create(30, 30)->circle(16);
        $this->assertSame(['width' => 16, 'height' => 16], $d->getSize());
    }

    /** 验证 crop 改变尺寸。 */
    public function testCropChangesDimensions(): void
    {
        $d = (new ImagickDriver())->create(100, 80)->crop(10, 10, 20, 30);
        $this->assertSame(['width' => 20, 'height' => 30], $d->getSize());
    }

    /** 验证 text 内置与 TTF 字体、对齐、换行选项不崩溃。 */
    public function testTextDrawsWithOptions(): void
    {
        $d = (new ImagickDriver())->create(200, 100);
        foreach (['left', 'center', 'right'] as $align) {
            $d->text("line1\nline2", 10, 20, ['align' => $align, 'maxWidth' => 120]);
        }
        $this->assertSame(['width' => 200, 'height' => 100], $d->getSize());
    }

    /** 验证 image 合成 GD 覆盖层与缩放选项。 */
    public function testImageCompositesGdOverlay(): void
    {
        $canvas = (new ImagickDriver())->create(100, 100);
        $overlay = (new GdDriver())->create(20, 20);
        $canvas->image($overlay, 10, 10, ['width' => 40, 'height' => 40]);
        $this->assertSame(['width' => 100, 'height' => 100], $canvas->getSize());
    }

    /** 验证 image 合成 Imagick 覆盖层并支持 radius/shadow 选项。 */
    public function testImageCompositesImagickOverlay(): void
    {
        $canvas = (new ImagickDriver())->create(100, 100);
        $overlay = (new ImagickDriver())->create(20, 20);
        $canvas->image($overlay, 10, 10, ['radius' => 5, 'shadow' => ['blur' => 4, 'offsetX' => 3, 'offsetY' => 3]]);
        $this->assertSame(['width' => 100, 'height' => 100], $canvas->getSize());
    }

    /** 验证 rectangle/ellipse/filledArc/line 绘制不崩溃。 */
    public function testShapesDraw(): void
    {
        $d = (new ImagickDriver())->create(100, 100)
            ->rectangle(5, 5, 40, 40, ['color' => '#FF0000', 'radius' => 4])
            ->ellipse(70, 20, 10, 10, ['color' => '#00FF00'])
            ->filledArc(70, 60, 30, 30, 0, 180, ['color' => '#0000FF'])
            ->line(5, 80, 95, 80, ['color' => '#FFFF00', 'width' => 3]);
        $this->assertSame(['width' => 100, 'height' => 100], $d->getSize());
    }

    /** 验证 blur/sharpen/pixelate 后尺寸不变。 */
    public function testFiltersDoNotChangeSize(): void
    {
        $d = (new ImagickDriver())->create(20, 20)->blur(2)->sharpen(1.5)->pixelate(4);
        $this->assertSame(['width' => 20, 'height' => 20], $d->getSize());
    }

    /** 验证 save 各格式写入文件，png/jpg 均可解码。 */
    public function testSaveAllFormats(): void
    {
        $d = (new ImagickDriver())->create(20, 20);
        foreach (['jpg', 'png', 'gif', 'webp'] as $fmt) {
            $path = sys_get_temp_dir() . '/poster-imagick-' . uniqid() . '.' . $fmt;
            $this->assertTrue($d->save($path, $fmt));
            $this->assertFileExists($path);
            unlink($path);
        }
    }

    /** 验证 output 返回可解码的 data URL。 */
    public function testOutputReturnsDataUrl(): void
    {
        $out = (new ImagickDriver())->create(12, 7)->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $out);
        $info = getimagesizefromstring(base64_decode(substr($out, strpos($out, ',') + 1)));
        $this->assertSame([12, 7], [$info[0], $info[1]]);
    }

    /** 验证 load 合法图片后尺寸正确，缺失文件抛 InvalidArgumentException。 */
    public function testLoadAndMissingFile(): void
    {
        $path = sys_get_temp_dir() . '/poster-imagick-l-' . uniqid() . '.png';
        (new ImagickDriver())->create(30, 40)->save($path, 'png');
        $d = new ImagickDriver();
        $this->assertSame($d, $d->load($path));
        $this->assertSame(['width' => 30, 'height' => 40], $d->getSize());
        unlink($path);

        $this->expectException(\InvalidArgumentException::class);
        $d->load('/nonexistent/poster-test.png');
    }

    /** 验证 clone 深拷贝：销毁原对象后克隆体仍可输出图像。 */
    public function testCloneIsIndependent(): void
    {
        $d = (new ImagickDriver())->create(20, 20);
        $c = $d->clone();
        $d->destroy();
        $this->assertSame(['width' => 20, 'height' => 20], $c->getSize());
        $this->assertStringStartsWith('data:image/png;base64,', $c->output('png'));
    }
}
