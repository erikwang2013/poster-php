<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\TableElement;
use PHPUnit\Framework\TestCase;

class TableElementTest extends TestCase
{
    /** 验证 headers 为空时直接返回、零绘制 */
    public function testEmptyHeadersRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('rectangle');
        $canvas->expects($this->never())->method('text');
        $canvas->expects($this->never())->method('line');
        (new TableElement(['headers' => [], 'rows' => [['a']]]))->render($canvas);
    }

    /** 验证 rows 为空时直接返回、零绘制 */
    public function testEmptyRowsRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('rectangle');
        (new TableElement(['headers' => ['A'], 'rows' => []]))->render($canvas);
    }

    /** 验证 2 列 2 行：1 表头块 + 2 行斑马块 + 2 头文本 + 4 单元格文本 + 2 行底边框 */
    public function testHeaderAndZebraRowsDrawCounts(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(3))->method('rectangle');
        $canvas->expects($this->exactly(6))->method('text');
        $canvas->expects($this->exactly(2))->method('line');
        (new TableElement([
            'headers' => ['Name', 'Age'],
            'rows' => [['A', 1], ['B', 2]],
        ]))->render($canvas);
    }

    /** 验证自定义列宽与 center/left/right 对齐生效且渲染成功 */
    public function testCustomColWidthsAndAlignments(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('rectangle'); // 表头 + 1 行
        $aligns = [];
        $idx = 0;
        $canvas->expects($this->exactly(6))->method('text')->with(
            $this->anything(), $this->anything(), $this->anything(),
            $this->callback(function (array $o) use (&$aligns, &$idx) {
                $aligns[$idx++] = $o['align'] ?? 'left';
                return true;
            })
        )->willReturnSelf();
        (new TableElement([
            'headers' => ['A', 'B', 'C'],
            'rows' => [['1', '2', '3']],
            'col_widths' => [100, 100, 100],
            'alignments' => ['center', 'left', 'right'],
        ]))->render($canvas);
        $this->assertSame(['center', 'left', 'right', 'center', 'left', 'right'], $aligns);
    }

    /** 验证行内单元格少于列数时缺失列被跳过不报错 */
    public function testMissingCellsAreSkipped(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(3))->method('text'); // 2 头 + 1 个存在的单元格
        (new TableElement([
            'headers' => ['A', 'B'],
            'rows' => [['only-one']],
        ]))->render($canvas);
    }
}
