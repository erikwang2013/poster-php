<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Poster\PosterTemplate;
use Erikwang2013\Poster\PosterConfig;
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
        $this->assertSame($builder, $builder->addPet(['x' => 1, 'y' => 2, 'width' => 100]));
    }

    /** 验证 addPet() 使用随包分发的吉祥物图片，且该文件真实存在 */
    public function testAddPetUsesBundledMascot(): void
    {
        $path = PosterBuilder::petPath();
        $this->assertFileExists($path);

        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('create')->with(100, 100);
        $driver->expects($this->once())->method('image')->with(
            $this->isInstanceOf(ImageDriverInterface::class), 10, 20,
            $this->callback(fn(array $o) => $o['src'] === $path && $o['width'] === 60)
        );
        (new PosterBuilder($driver))->width(100)->height(100)->addPet(['x' => 10, 'y' => 20, 'width' => 60])->output('png');
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

    /** 验证 save() 按扩展名推断格式（此前恒写 jpg），未知扩展名回落 jpg */
    public function testSaveInfersFormatFromExtension(): void
    {
        $cases = [
            '/tmp/a.jpg'    => 'jpg',
            '/tmp/a.jpeg'   => 'jpeg',
            '/tmp/a.PNG'    => 'png',
            '/tmp/a.webp'   => 'webp',
            '/tmp/a.gif'    => 'gif',
            '/tmp/a'        => 'jpg',
            '/tmp/a.bmp'    => 'jpg',
        ];
        foreach ($cases as $path => $expected) {
            $driver = $this->mockDriver();
            $driver->expects($this->once())->method('save')->with($path, $expected, 90)->willReturn(true);
            $builder = new PosterBuilder($driver);
            $builder->width(10)->height(10);
            $this->assertTrue($builder->save($path), "save($path) 应返回 true");
        }
    }

    /** 验证 save() 未传 quality 时读配置 poster.jpeg_quality（此前硬编码 90） */
    public function testSaveUsesConfiguredJpegQuality(): void
    {
        PosterConfig::merge(['poster' => ['jpeg_quality' => 55]]);
        try {
            $driver = $this->mockDriver();
            $driver->expects($this->once())->method('save')->with($this->anything(), 'jpg', 55)->willReturn(true);
            (new PosterBuilder($driver))->width(10)->height(10)->save('/tmp/q.jpg');
        } finally {
            PosterConfig::reset();
        }
    }

    /** 验证 add() 走注册表，未知类型抛 InvalidArgumentException */
    public function testAddUsesRegistryAndRejectsUnknownType(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('text')->with('via add', 0, 0, $this->anything());
        $builder = new PosterBuilder($driver);
        $this->assertSame($builder, $builder->add('text', ['text' => 'via add']));
        $builder->width(10)->height(10)->output('png');

        $this->expectException(\InvalidArgumentException::class);
        (new PosterBuilder($this->mockDriver()))->add('nope');
    }

    /** 验证 toArray() 导出模板结构，fromConfig() 往返一致 */
    public function testToArrayRoundTripsThroughTemplate(): void
    {
        $builder = new PosterBuilder($this->mockDriver());
        $builder->width(320)->height(480)
            ->addText('标题', ['x' => 10, 'y' => 20])
            ->addShape('rect', ['width' => 100, 'height' => 50, 'color' => '#FF6B6B'])
            ->addChart('pie', [['label' => 'A', 'value' => 1]], ['x' => 1])
            ->add('line', ['x1' => 0, 'y1' => 0, 'x2' => 5, 'y2' => 5]);

        $exported = $builder->toArray();
        $this->assertSame(320, $exported['width']);
        $this->assertSame(480, $exported['height']);
        $this->assertSame(
            ['text', 'shape', 'chart', 'line'],
            array_column($exported['elements'], 'type')
        );

        $template = PosterTemplate::fromConfig($exported);
        $this->assertSame(320, $template->getWidth());
        $this->assertSame(480, $template->getHeight());
        $this->assertSame($exported, $template->toArray());

        // 还原出的元素再导出，结构保持一致（真正的往返）
        $again = [];
        foreach ($template->build() as $el) {
            $again[] = $el->toArray();
        }
        $this->assertSame($exported['elements'], $again);
    }

    /** 验证模板下的 toArray() 导出模板元素（未渲染时也能导出） */
    public function testToArrayWithTemplateExportsTemplateElements(): void
    {
        $template = new PosterTemplate(500, 600, [['type' => 'text', 'text' => 'T']]);
        $builder = new PosterBuilder($this->mockDriver());
        $builder->useTemplate($template);
        $exported = $builder->toArray();
        $this->assertSame(500, $exported['width']);
        $this->assertSame(600, $exported['height']);
        $this->assertSame([['type' => 'text', 'text' => 'T']], $exported['elements']);
    }

    /** 验证 replaceElements(false) 为追加语义：手写元素 + 模板元素都渲染 */
    public function testReplaceElementsFalseKeepsHandWrittenElements(): void
    {
        $texts = [];
        $driver = $this->mockDriver();
        $driver->expects($this->exactly(2))->method('text')->willReturnCallback(
            function (string $text, int $x, int $y, array $o) use (&$texts, $driver) {
                $texts[] = $text;
                return $driver;
            }
        );
        $template = new PosterTemplate(100, 100, [['type' => 'text', 'text' => 'from-template']]);
        (new PosterBuilder($driver))
            ->useTemplate($template)
            ->replaceElements(false)
            ->addText('hand-written')
            ->output('png');

        $this->assertSame(['hand-written', 'from-template'], $texts);
    }

    /** 验证缺省（替换语义）仍然吞掉手写元素，保持既有行为 */
    public function testTemplateReplacesHandWrittenElementsByDefault(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())->method('text')->with('from-template', 0, 0, $this->anything());
        $template = new PosterTemplate(100, 100, [['type' => 'text', 'text' => 'from-template']]);
        (new PosterBuilder($driver))
            ->useTemplate($template)
            ->addText('hand-written')
            ->output('png');
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
