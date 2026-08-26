<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\ArtisticTextElement;
use PHPUnit\Framework\TestCase;

class ArtisticTextElementTest extends TestCase
{
    private const FONT = __DIR__ . '/../../../src/fonts/Alibaba-PuHuiTi-Regular.ttf';

    /** 验证 stroke 样式：strokeWidth=1 时画 8 次描边 + 1 次主体共 9 次 text */
    public function testStrokeStyleDrawsOutlineAndCenter(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(9))->method('text');
        (new ArtisticTextElement(['text' => 'STROKE', 'style' => 'stroke', 'strokeWidth' => 1]))
            ->render($canvas);
    }

    /** 验证默认样式即 stroke（未显式传 style 时行为一致） */
    public function testDefaultStyleIsStroke(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(9))->method('text');
        (new ArtisticTextElement(['text' => 'X']))->render($canvas);
    }

    /** 验证 shadow 样式：1 次阴影 + 1 次主体 */
    public function testShadowStyleDrawsShadowAndMain(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('text');
        (new ArtisticTextElement(['text' => 'X', 'style' => 'shadow']))->render($canvas);
    }

    /** 验证 neon 样式：3 层光晕 + 1 次白色主体 */
    public function testNeonStyleDrawsGlowLayers(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(4))->method('text');
        (new ArtisticTextElement(['text' => 'X', 'style' => 'neon']))->render($canvas);
    }

    /** 验证 gradient 样式无字体文件时回退为单次普通 text */
    public function testGradientWithoutFontFallsBackToPlainText(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text');
        (new ArtisticTextElement(['text' => 'X', 'style' => 'gradient']))->render($canvas);
    }

    /** 验证 gradient 样式带真实字体时走位图遮罩合成（image 一次、text 不调用） */
    public function testGradientWithRealFontCompositesMask(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('缺少测试字体文件');
        }
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        $canvas->expects($this->once())->method('image');
        (new ArtisticTextElement([
            'text' => 'GRAD', 'style' => 'gradient', 'font' => self::FONT,
            'color' => '#FF0000', 'color2' => '#0000FF',
        ]))->render($canvas);
    }

    /** 验证未知样式走默认分支只画一次 text */
    public function testUnknownStyleDrawsOnce(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text');
        (new ArtisticTextElement(['text' => 'X', 'style' => 'weird']))->render($canvas);
    }

    /** 验证 resolve() 替换 text 键占位符 */
    public function testResolveReplacesPlaceholders(): void
    {
        $el = new ArtisticTextElement(['text' => '{{w}}!', 'style' => 'neon']);
        $el->resolve(['w' => 'Hey']);
        $this->assertSame('Hey!', $el->toArray()['options']['text']);
    }
}
