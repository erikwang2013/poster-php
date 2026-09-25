<?php

/**
 * GdDriver 入参守卫、错误语义与编码参数接线测试：
 * 像素预算按 memory_limit 动态计算、非法尺寸/颜色抛 InvalidArgumentException（不再是 GD 原始 ValueError）、
 * opacity 夹取、destroy() 后状态明确报错、jpg/jpeg 的 MIME 正确、PNG 级别读 poster.png_compression、
 * JPEG 默认质量读 image.quality。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use PHPUnit\Framework\TestCase;

class GdDriverGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 像素预算 = memory_limit / 4（每像素 4 字节），memory_limit 无限时回退历史常量 40M。 */
    public function testPixelBudgetFollowsMemoryLimit(): void
    {
        $limit = self::reflect('memoryLimitBytes');
        $budget = self::reflect('maxPixels');
        if ($limit > 0) {
            $this->assertSame(intdiv($limit, 4), $budget);
        } else {
            $this->assertSame(40000000, $budget);
        }
    }

    /** 超过像素预算的 create 抛 InvalidArgumentException（旧阈值 40M 在 128M 下拦不住 34~40M）。 */
    public function testCreateRejectsCanvasOverPixelBudget(): void
    {
        $side = (int) ceil(sqrt(self::reflect('maxPixels'))) + 8;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pixel budget');
        (new GdDriver())->create($side, $side);
    }

    /** create/resize/crop/circle/image 的非法尺寸统一抛 InvalidArgumentException，而不是 GD 的 ValueError。 */
    public function testInvalidDimensionsThrowInvalidArgumentException(): void
    {
        $driver = (new GdDriver())->create(20, 20);
        $overlay = (new GdDriver())->create(5, 5);
        $cases = [
            'create' => function () { (new GdDriver())->create(0, 0); },
            'create negative' => function () { (new GdDriver())->create(-3, 5); },
            'resize' => function () use ($driver) { $driver->resize(0, 0); },
            'crop' => function () use ($driver) { $driver->crop(0, 0, 0, 0); },
            'circle' => function () use ($driver) { $driver->circle(0); },
            'image' => function () use ($driver, $overlay) { $driver->image($overlay, 0, 0, ['width' => 0, 'height' => 0]); },
        ];
        foreach ($cases as $label => $case) {
            try {
                $case();
                $this->fail("$label 应抛 InvalidArgumentException");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('greater than 0', $e->getMessage(), $label);
            }
        }
        $overlay->destroy();
    }

    /** 非法颜色抛 InvalidArgumentException（旧实现静默当纯黑）。 */
    public function testInvalidColorThrows(): void
    {
        foreach (['notacolor', '#GGGGGG', '', '#12345', 'rgb(1,2,3)'] as $color) {
            try {
                (new GdDriver())->create(20, 20)->rectangle(0, 0, 5, 5, ['color' => $color]);
                $this->fail("颜色 '$color' 应抛 InvalidArgumentException");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid color', $e->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid color');
        (new GdDriver())->create(20, 20)->text('X', 0, 10, ['font' => null, 'color' => 'zzz']);
    }

    /** 合法写法都要能过：#RGB / #RRGGBB / #RRGGBBAA / 不带 # / 小写。 */
    public function testValidColorFormsAccepted(): void
    {
        foreach (['#F00', '#FF0000', '#FF000080', 'FF0000', '#ff0000aa'] as $color) {
            $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => $color]);
            $this->assertSame(0xFF0000, imagecolorat($d->getResource(), 5, 5) & 0xFFFFFF, $color);
            $d->destroy();
        }
    }

    /** opacity 越界夹到 [0,1]（旧实现抛 GD ValueError），0-100 写法兼容。 */
    public function testOpacityIsClamped(): void
    {
        foreach ([[5, 0], [2, 0], [-1, 127], [1, 0], [0, 127], [0.5, 63]] as [$opacity, $alpha]) {
            $d = (new GdDriver())->create(20, 20)->rectangle(0, 0, 10, 10, ['color' => '#FF0000', 'opacity' => $opacity]);
            $this->assertSame($alpha, (imagecolorat($d->getResource(), 5, 5) >> 24) & 0x7F, "opacity=$opacity");
            $d->destroy();
        }
    }

    /** load 非图片内容不产生 PHP Notice/Warning，直接抛 RuntimeException。 */
    public function testLoadNonImageEmitsNoPhpNotice(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'poster-guard-');
        file_put_contents($path, 'not an image at all');

        $messages = [];
        set_error_handler(function (int $no, string $message) use (&$messages) {
            $messages[] = $message;
            return true;
        });
        try {
            (new GdDriver())->load($path);
            $this->fail('应抛 RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot read image', $e->getMessage());
        } finally {
            restore_error_handler();
            unlink($path);
        }
        $this->assertSame([], $messages, 'load 非图片时不应有 PHP Notice/Warning：' . implode(' | ', $messages));
    }

    /** destroy() 后所有操作抛 RuntimeException，getSize 不再返回旧尺寸。 */
    public function testAllOperationsThrowAfterDestroy(): void
    {
        $driver = new GdDriver();
        $driver->create(10, 10);
        $driver->destroy();
        $this->assertNull($driver->getResource());

        $overlay = new GdDriver();
        $overlay->create(4, 4);
        $path = sys_get_temp_dir() . '/poster-destroyed-' . uniqid() . '.png';

        $calls = [
            'getSize' => function () use ($driver) { return $driver->getSize(); },
            'save' => function () use ($driver, $path) { return $driver->save($path, 'png'); },
            'output' => function () use ($driver) { return $driver->output('png'); },
            'resize' => function () use ($driver) { return $driver->resize(5, 5); },
            'crop' => function () use ($driver) { return $driver->crop(0, 0, 5, 5); },
            'rotate' => function () use ($driver) { return $driver->rotate(90); },
            'circle' => function () use ($driver) { return $driver->circle(5); },
            'blur' => function () use ($driver) { return $driver->blur(1); },
            'sharpen' => function () use ($driver) { return $driver->sharpen(1.0); },
            'pixelate' => function () use ($driver) { return $driver->pixelate(2); },
            'text' => function () use ($driver) { return $driver->text('x', 0, 0, ['font' => null]); },
            'image' => function () use ($driver, $overlay) { return $driver->image($overlay, 0, 0); },
            'rectangle' => function () use ($driver) { return $driver->rectangle(0, 0, 2, 2); },
            'ellipse' => function () use ($driver) { return $driver->ellipse(1, 1, 1, 1); },
            'filledArc' => function () use ($driver) { return $driver->filledArc(1, 1, 2, 2, 0, 90); },
            'line' => function () use ($driver) { return $driver->line(0, 0, 1, 1); },
        ];
        foreach ($calls as $label => $call) {
            try {
                $call();
                $this->fail("destroy() 后 $label 应抛 RuntimeException");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('No image resource', $e->getMessage(), $label);
            }
        }
        $this->assertSame(['width' => 4, 'height' => 4], $overlay->getSize(), '其他实例不受影响');
        $this->assertFileDoesNotExist($path);
        $overlay->destroy();
    }

    /** destroy() 可重复调用，且 __destruct 不报错。 */
    public function testDestroyIsIdempotent(): void
    {
        $driver = (new GdDriver())->create(10, 10);
        $driver->destroy();
        $driver->destroy();
        $this->assertNull($driver->getResource());
    }

    /** jpg 与 jpeg 的 data URL MIME 都是 image/jpeg（image/jpg 不是注册 MIME）。 */
    public function testJpegMimeTypeIsImageJpeg(): void
    {
        $driver = (new GdDriver())->create(12, 7);
        foreach (['jpg', 'jpeg', 'JPG'] as $format) {
            $this->assertStringStartsWith('data:image/jpeg;base64,', $driver->output($format), $format);
        }
        $this->assertStringStartsWith('data:image/png;base64,', $driver->output('png'));
        $this->assertStringStartsWith('data:image/gif;base64,', $driver->output('gif'));
        if (gd_info()['WebP Support'] ?? false) {
            $this->assertStringStartsWith('data:image/webp;base64,', $driver->output('webp'));
        }
    }

    /** PNG 默认压缩级别读 poster.png_compression（旧实现把 quality=90 当级别 0，体积大 25 倍）。 */
    public function testPngCompressionComesFromConfig(): void
    {
        $driver = $this->noisy(200);
        $path0 = sys_get_temp_dir() . '/poster-png0-' . uniqid() . '.png';
        $path9 = sys_get_temp_dir() . '/poster-png9-' . uniqid() . '.png';

        PosterConfig::merge(['poster' => ['png_compression' => 0]]);
        $driver->save($path0, 'png');
        PosterConfig::merge(['poster' => ['png_compression' => 9]]);
        $driver->save($path9, 'png');

        $this->assertGreaterThan(3 * filesize($path9), filesize($path0), 'png_compression 未生效');
        unlink($path0);
        unlink($path9);
    }

    /** 显式 quality 仍映射到 PNG 级别：100 -> 0（最大）、0 -> 9（最小）。 */
    public function testPngExplicitQualityMapsToCompressionLevel(): void
    {
        $driver = $this->noisy(200);
        $best = sys_get_temp_dir() . '/poster-pngq100-' . uniqid() . '.png';
        $smallest = sys_get_temp_dir() . '/poster-pngq0-' . uniqid() . '.png';
        $driver->save($best, 'png', 100);
        $driver->save($smallest, 'png', 0);
        $this->assertGreaterThan(filesize($smallest), filesize($best));
        unlink($best);
        unlink($smallest);
    }

    /** JPEG 未显式传 quality 时读 image.quality（旧实现写死 90）。 */
    public function testJpegDefaultQualityComesFromConfig(): void
    {
        $driver = $this->noisy(200);
        $low = sys_get_temp_dir() . '/poster-jpg10-' . uniqid() . '.jpg';
        $high = sys_get_temp_dir() . '/poster-jpg95-' . uniqid() . '.jpg';

        PosterConfig::merge(['image' => ['quality' => 10]]);
        $driver->save($low, 'jpg');
        PosterConfig::merge(['image' => ['quality' => 95]]);
        $driver->save($high, 'jpg');

        $this->assertLessThan(filesize($high), filesize($low), 'image.quality 未生效');
        $this->assertGreaterThan(0, filesize($low));
        unlink($low);
        unlink($high);
    }

    /** 生成噪声图（PNG 压缩级别差异才明显）。 */
    private function noisy(int $size): GdDriver
    {
        $driver = (new GdDriver())->create($size, $size);
        for ($i = 0; $i < $size; $i++) {
            $driver->rectangle(0, $i, $size, 1, ['color' => sprintf('#%02X%02X%02X', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))]);
        }
        return $driver;
    }

    /** @return int 私有静态方法的返回值 */
    private static function reflect(string $method): int
    {
        $reflection = new \ReflectionMethod(GdDriver::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke(null);
    }
}
