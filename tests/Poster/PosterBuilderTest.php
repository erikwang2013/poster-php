<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Poster\PosterTemplate;
use PHPUnit\Framework\TestCase;

class PosterBuilderTest extends TestCase
{
    private function mockDriver(): ImageDriverInterface
    {
        return $this->createMock(ImageDriverInterface::class);
    }

    private function tempImage(): string
    {
        $path = sys_get_temp_dir() . '/poster-builder-bg-' . uniqid() . '.png';
        $img = imagecreatetruecolor(20, 20);
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    /** 验证 width/height 链式调用返回自身且生效 */
    public function testWidthHeightAreChainable(): void
    {
        $builder = new PosterBuilder($this->mockDriver());
        $this->assertSame($builder, $builder->width(300));
        $this->assertSame($builder, $builder->height(400));
    }

    /** 验证全部 add* 方法链式返回自身 */
    public function testAllAddMethodsAreChainable(): void
    {
        $builder = new PosterBuilder($this->mockDriver());
        $this->assertSame($builder, $builder->addText('t'));
        $this->assertSame($builder, $builder->addImage('x.png'));
        $this->assertSame($builder, $builder->addQrcode('x'));
        $this->assertSame($builder, $builder->addAvatar('x.png'));
        $this->assertSame($builder, $builder->addShape('rect'));
        $this->assertSame($builder, $builder->addLine([]));
        $this->assertSame($builder, $builder->addWatermark('w'));
        $this->assertSame($builder, $builder->addTable([]));
        $this->assertSame($builder, $builder->addChart('bar', []));
        $this->assertSame($builder, $builder->addCalendar([]));
        $this->assertSame($builder, $builder->addArtisticText('t', 'stroke'));
        $this->assertSame($builder, $builder->addEmoji('😀'));
        $this->assertSame($builder, $builder->addIcon('heart'));
        $this->assertSame($builder, $builder->addEmoticon('happy'));
    }

    /** 验证未设宽高时渲染使用配置默认值 750x1334 */
    public function testDefaultDimensionsFromConfig(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('create')->with(750, 1334);
        $builder = new PosterBuilder($driver);
        $builder->output('png');
    }

    /** 验证 background() 识别 # 前缀六位十六进制颜色并铺满画布 */
    public function testBackgroundRecognizesHexColor(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('rectangle')->with(
            0, 0, 100, 100,
            $this->callback(fn(array $o) => $o['color'] === '#FF0000' && $o['filled'] === true)
        );
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->background('#FF0000')->output('png');
    }

    /** 验证 background() 对无 # 的三位十六进制（如 F00）同样识别 */
    public function testBackgroundRecognizesShortHexWithoutHash(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('rectangle')->with(
            0, 0, 100, 100,
            $this->callback(fn(array $o) => $o['color'] === 'F00')
        );
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->background('F00')->output('png');
    }

    /** 验证 background() 识别存在的图片路径并作为背景图合成 */
    public function testBackgroundRecognizesImageFile(): void
    {
        $path = $this->tempImage();
        try {
            $driver = $this->mockDriver();
            $driver->expects($this->once())->method('image');
            $builder = new PosterBuilder($driver);
            $builder->width(100)->height(100)->background($path)->output('png');
        } finally {
            unlink($path);
        }
    }

    /** 验证 background() 传入非法值（非颜色非文件）时被忽略、回退白色背景 */
    public function testBackgroundIgnoresInvalidValue(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('rectangle')->with(
            0, 0, 100, 100,
            $this->callback(fn(array $o) => $o['color'] === '#FFFFFF')
        );
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->background('!!not a color!!')->output('png');
    }

    /** 验证 vertical 渐变按高度分带画 167 个色带（1334/8） */
    public function testBackgroundGradientVerticalBands(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->exactly(167))->method('rectangle');
        $builder = new PosterBuilder($driver);
        $builder->backgroundGradient('#000000', '#FFFFFF', 'vertical')->output('png');
    }

    /** 验证 horizontal 渐变按宽度分带画 94 个色带（750/8） */
    public function testBackgroundGradientHorizontalBands(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->exactly(94))->method('rectangle');
        $builder = new PosterBuilder($driver);
        $builder->backgroundGradient('#000000', '#FFFFFF', 'horizontal')->output('png');
    }

    /** 验证 useTemplate 后画布尺寸与元素均来自模板 */
    public function testTemplateOverridesDimensionsAndElements(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('create')->with(500, 500);
        $driver->expects($this->once())->method('text')->with('T', 0, 0, $this->anything());
        $template = new PosterTemplate(500, 500, [['type' => 'text', 'text' => 'T']]);
        $builder = new PosterBuilder($driver);
        $builder->useTemplate($template)->output('png');
    }

    /** 验证 with() 变量会传入模板元素的占位符解析 */
    public function testWithVariablesResolveInTemplateElements(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('text')->with('Hi Alice', 0, 0, $this->anything());
        $template = new PosterTemplate(100, 100, [['type' => 'text', 'text' => 'Hi {{name}}']]);
        $builder = new PosterBuilder($driver);
        $builder->useTemplate($template)->with(['name' => 'Alice'])->output('png');
    }

    /** 验证 render 幂等：save 后 output 不会二次渲染 */
    public function testRenderHappensOnlyOnce(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('create');
        $driver->expects($this->once())->method('rectangle');
        $driver->expects($this->once())->method('text');
        $driver->expects($this->once())->method('save')->willReturn(true);
        $driver->expects($this->once())->method('output')->willReturn('data:image/png;base64,');
        $path = sys_get_temp_dir() . '/poster-builder-once-' . uniqid() . '.jpg';
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->background('#FFFFFF')->addText('x');
        $this->assertTrue($builder->save($path, 80));
        $builder->output('png');
        @unlink($path);
    }

    /** 验证 save() 把质量参数透传给驱动 */
    public function testSavePassesQualityToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('save')->with(
            $this->anything(), 'jpg', 75
        )->willReturn(true);
        $builder = new PosterBuilder($driver);
        $builder->width(10)->height(10);
        $this->assertTrue($builder->save('/tmp/any.jpg', 75));
    }

    /** 验证 addLine 的 x/y 简写会落到 x2/y2 默认值 */
    public function testAddLineXFallbackToX2(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('line')->with(0, 0, 50, 20, $this->anything());
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->addLine(['x' => 50, 'y' => 20])->output('png');
    }

    /** 验证 destroy() 调用驱动释放资源不抛异常 */
    public function testDestroyCallsDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('destroy');
        $builder = new PosterBuilder($driver);
        $builder->destroy();
    }
}
