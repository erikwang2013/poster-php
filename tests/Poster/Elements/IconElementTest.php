<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\IconElement;
use PHPUnit\Framework\TestCase;

class IconElementTest extends TestCase
{
    private const FONT = __DIR__ . '/../../../src/fonts/Alibaba-PuHuiTi-Regular.ttf';

    /** 验证 icon 与 codepoint 均为空时不渲染 */
    public function testEmptyIconRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new IconElement())->render($canvas);
    }

    /** 验证未知 icon 无 codepoint 时直接返回、不渲染 */
    public function testUnknownIconRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new IconElement(['icon' => 'nope-icon']))->render($canvas);
    }

    /** 验证已知 icon 但无图标字体时回退渲染 "[icon]" 占位文本 */
    public function testKnownIconWithoutFontRendersPlaceholder(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('[heart]', 0, 0, $this->anything());
        (new IconElement(['icon' => 'heart']))->render($canvas);
    }

    /** 验证已知 icon 且字体文件存在时渲染真实字形字符 */
    public function testKnownIconWithFontRendersGlyph(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('缺少测试字体文件');
        }
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with(
            mb_chr(0xF004, 'UTF-8'), 0, 0,
            $this->callback(fn(array $o) => $o['font'] === self::FONT)
        );
        (new IconElement(['icon' => 'heart', 'font' => self::FONT]))->render($canvas);
    }

    /** 验证 codepoint 优先于 icon：支持 \u{XXXX}、U+XXXX 与裸十六进制格式 */
    public function testCodepointOverridesIcon(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('缺少测试字体文件');
        }
        foreach (['\\u{F005}', 'U+F005', 'F005'] as $cp) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('text')->with(
                mb_chr(0xF005, 'UTF-8'), 0, 0, $this->anything()
            );
            (new IconElement(['icon' => 'heart', 'codepoint' => $cp, 'font' => self::FONT]))
                ->render($canvas);
        }
    }
}
