<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\EmojiElement;
use PHPUnit\Framework\TestCase;

class EmojiElementTest extends TestCase
{
    /** 验证 emoji 为空且无 codepoint 时不渲染 */
    public function testEmptyEmojiRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new EmojiElement())->render($canvas);
    }

    /** 验证 int 类型 codepoint 转为对应 emoji 字符并渲染 */
    public function testIntCodepointRendersCharacter(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('😀', 0, 0, $this->anything());
        (new EmojiElement(['codepoint' => 0x1F600]))->render($canvas);
    }

    /** 验证字符串 codepoint 的三种格式（U+、裸十六进制、0x）均能转换 */
    public function testStringCodepointFormats(): void
    {
        foreach (['U+1F600', '1F600', '0x1F600'] as $cp) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('text')->with('😀', 0, 0, $this->anything());
            (new EmojiElement(['codepoint' => $cp]))->render($canvas);
        }
    }

    /** 验证超出 Unicode 范围的 codepoint（mb_chr 返回 false）不渲染 */
    public function testOutOfRangeCodepointRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new EmojiElement(['codepoint' => 'FFFFFFFF']))->render($canvas);
    }

    /** 验证非法十六进制 codepoint（如 'ZZZ'）不渲染，避免 hexdec=0 产生 NUL 字节 */
    public function testInvalidHexCodepointRendersNothing(): void
    {
        foreach (['ZZZ', 'U+ZZZ', '0xGG'] as $cp) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('text');
            (new EmojiElement(['codepoint' => $cp]))->render($canvas);
        }
    }

    /** 验证直接传 emoji 字符时渲染一次（有无 emoji 字体均走 text） */
    public function testEmojiCharacterRendersOnce(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('😀', 10, 20, $this->anything());
        (new EmojiElement(['emoji' => '😀', 'x' => 10, 'y' => 20]))->render($canvas);
    }

    /** 验证 resolve() 替换 emoji 占位符 */
    public function testResolveReplacesEmojiPlaceholder(): void
    {
        $el = new EmojiElement(['emoji' => '{{e}}']);
        $el->resolve(['e' => '😀']);
        $this->assertSame('😀', $el->toArray()['options']['emoji']);
    }
}
