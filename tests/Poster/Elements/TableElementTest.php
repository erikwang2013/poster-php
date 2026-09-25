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

    /** 验证 README 示例（camelCase：header/columns/headerBg/rowBg/fontSize/cellPadding）可正常绘制 */
    public function testReadmeStyleCamelCaseOptionsRender(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(4))->method('rectangle'); // 表头 + 3 行
        $canvas->expects($this->exactly(12))->method('text')->willReturnSelf();
        $canvas->expects($this->exactly(3))->method('line');
        (new TableElement([
            'x' => 50, 'y' => 800, 'width' => 650, 'columns' => [150, 350, 150],
            'header' => ['序号', '项目', '价格'],
            'rows' => [['1', '商品A', '¥99'], ['2', '商品B', '¥199'], ['3', '商品C', '¥299']],
            'headerBg' => '#333333', 'headerColor' => '#FFFFFF',
            'rowBg' => ['#FFFFFF', '#F5F5F5'], 'rowColor' => '#333333',
            'fontSize' => 24, 'cellPadding' => 10,
        ]))->render($canvas);
    }

    /** 验证 rows 存在时 header 与 headers 两种写法等价（历史兼容） */
    public function testHeaderKeyAcceptsBothSpellings(): void
    {
        foreach (['header', 'headers'] as $key) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->exactly(2))->method('rectangle');
            $canvas->expects($this->exactly(4))->method('text')->willReturnSelf();
            (new TableElement([$key => ['A', 'B'], 'rows' => [['1', '2']]]))->render($canvas);
        }
    }

    /** 验证 resolve() 递归替换 header 与 rows（嵌套二维数组）里的占位符 */
    public function testResolveRecursesIntoHeadersAndRows(): void
    {
        $el = new TableElement([
            'header' => ['{{c1}}', '金额'],
            'rows' => [['{{item}}', '{{price}}'], ['B', 2]],
        ]);
        $el->resolve(['c1' => '项目', 'item' => '商品A', 'price' => '¥99']);
        $arr = $el->toArray();
        $this->assertSame(['项目', '金额'], $arr['header']);
        $this->assertSame([['商品A', '¥99'], ['B', 2]], $arr['rows']);
    }

    /** 验证单元格文本基线落在行内（imagettftext 按基线绘制，不能把 y 当顶部） */
    public function testCellBaselineStaysInsideRow(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $baselines = [];
        $canvas->expects($this->exactly(2))->method('text')->with(
            $this->anything(), $this->anything(), $this->callback(function ($y) use (&$baselines) {
                $baselines[] = $y;
                return true;
            }),
            $this->anything()
        )->willReturnSelf();
        (new TableElement([
            'x' => 0, 'y' => 100, 'header' => ['H'], 'rows' => [['R']],
            'headerHeight' => 40, 'rowHeight' => 35, 'fontSize' => 20,
        ]))->render($canvas);
        // 表头 100~140 内、数据行 140~175 内，且位于各自单元格下半部
        $this->assertGreaterThan(110, $baselines[0]);
        $this->assertLessThan(140, $baselines[0]);
        $this->assertGreaterThan(150, $baselines[1]);
        $this->assertLessThan(175, $baselines[1]);
    }
}
