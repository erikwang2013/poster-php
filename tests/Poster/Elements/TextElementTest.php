<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\TextElement;
use PHPUnit\Framework\TestCase;

class TextElementTest extends TestCase
{
    /** 验证默认坐标 (0,0) 且完整 options 透传给驱动 text() */
    public function testRenderDefaultsToOrigin(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('Hi', 0, 0, ['text' => 'Hi']);
        (new TextElement(['text' => 'Hi']))->render($canvas);
    }

    /** 验证无 text 键时回退读取 content 键，text 优先于 content */
    public function testRenderContentFallbackAndTextPrecedence(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('Bye', 0, 0, $this->anything());
        (new TextElement(['content' => 'Bye']))->render($canvas);

        $canvas2 = $this->createMock(ImageDriverInterface::class);
        $canvas2->expects($this->once())->method('text')->with('A', 0, 0, $this->anything());
        (new TextElement(['text' => 'A', 'content' => 'B']))->render($canvas2);
    }

    /** 验证字符串/小数坐标经 intval 强转成整数 */
    public function testRenderCoercesCoordinatesToInt(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('t', 12, -5, $this->anything());
        (new TextElement(['text' => 't', 'x' => '12.9', 'y' => '-5']))->render($canvas);
    }

    /** 验证空文本也照常调用 text()（不做静默跳过） */
    public function testRenderWithEmptyTextStillCalls(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('', 0, 0, $this->anything());
        (new TextElement())->render($canvas);
    }

    /** 验证 resolve() 替换 text 键占位符并返回自身 */
    public function testResolveReplacesTextPlaceholders(): void
    {
        $el = new TextElement(['text' => 'Hi {{name}}', 'x' => 5]);
        $this->assertSame($el, $el->resolve(['name' => 'Bob']));
        $this->assertSame('Hi Bob', $el->toArray()['options']['text']);
        $this->assertSame(5, $el->toArray()['options']['x']);
    }

    /** 验证 resolve() 在无 text 键时替换 content 键，未提供变量则保留原样 */
    public function testResolveUsesContentKeyAndKeepsUnknownVars(): void
    {
        $el = new TextElement(['content' => '{{a}}-{{b}}']);
        $el->resolve(['a' => '1']);
        $this->assertSame('1-{{b}}', $el->toArray()['options']['content']);
    }
}
