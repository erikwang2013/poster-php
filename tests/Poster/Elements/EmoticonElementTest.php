<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\EmoticonElement;
use PHPUnit\Framework\TestCase;

class EmoticonElementTest extends TestCase
{
    /** 验证 expressions() 返回全部内置表情 key */
    public function testExpressionsListsAllKeys(): void
    {
        $exprs = EmoticonElement::expressions();
        $this->assertContains('happy', $exprs);
        $this->assertContains('lenny', $exprs);
        $this->assertContains('tableflip', $exprs);
        $this->assertCount(12, $exprs);
    }

    /** 验证已知 expression 渲染对应颜文字 */
    public function testKnownExpressionRendersKaomoji(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('(｡•̀ᴗ-)✧', 0, 0, $this->anything());
        (new EmoticonElement(['expression' => 'happy']))->render($canvas);
    }

    /** 验证未知 expression 且无 text 时不渲染 */
    public function testUnknownExpressionRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new EmoticonElement(['expression' => 'nope']))->render($canvas);
    }

    /** 验证 text 选项优先于 expression */
    public function testTextTakesPrecedenceOverExpression(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with('custom', 0, 0, $this->anything());
        (new EmoticonElement(['text' => 'custom', 'expression' => 'happy']))->render($canvas);
    }

    /** 验证 font 仅在文件存在时才传入渲染选项 */
    public function testFontOnlyAppliedWhenFileExists(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with(
            'x', 0, 0, $this->callback(fn(array $o) => !isset($o['font']))
        );
        (new EmoticonElement(['text' => 'x', 'font' => '/no/such/font.ttf']))->render($canvas);
    }

    /** 验证 resolve() 替换 text 占位符 */
    public function testResolveReplacesTextPlaceholder(): void
    {
        $el = new EmoticonElement(['text' => '({{w}})']);
        $el->resolve(['w' => 'ok']);
        $this->assertSame('(ok)', $el->toArray()['options']['text']);
    }
}
