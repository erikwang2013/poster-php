<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\ChartElement;
use PHPUnit\Framework\TestCase;

class ChartElementTest extends TestCase
{
    /** 验证 bar 图空数据直接返回、零绘制调用 */
    public function testBarWithEmptyDataRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('rectangle');
        $canvas->expects($this->never())->method('line');
        $canvas->expects($this->never())->method('text');
        (new ChartElement(['type' => 'bar', 'data' => []]))->render($canvas);
    }

    /** 验证 line 图不足两个点时直接返回 */
    public function testLineWithSinglePointRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('ellipse');
        $canvas->expects($this->never())->method('line');
        (new ChartElement(['type' => 'line', 'data' => [[ 'value' => 5]]]))->render($canvas);
    }

    /** 验证 pie 图总值非正时直接返回 */
    public function testPieWithZeroTotalRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('filledArc');
        (new ChartElement(['type' => 'pie', 'data' => [['value' => 0], ['value' => 0]]]))->render($canvas);
    }

    /** 验证 bar 图：2 根柱 + 2 条轴线 + 2 值标签 + 2 轴标签 */
    public function testBarChartDrawCounts(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('rectangle');
        $canvas->expects($this->exactly(2))->method('line');
        $canvas->expects($this->exactly(4))->method('text');
        (new ChartElement(['type' => 'bar', 'data' => [
            ['label' => 'A', 'value' => 10],
            ['label' => 'B', 'value' => 20],
        ]]))->render($canvas);
    }

    /** 验证 bar 图接受标量数值项（非数组） */
    public function testBarChartAcceptsScalarValues(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('rectangle');
        $canvas->expects($this->exactly(2))->method('text');
        (new ChartElement(['type' => 'bar', 'data' => [5, 10]]))->render($canvas);
    }

    /** 验证 line 图：3 点 3 圆点 + 2 轴线 + 4 网格线 + 2 连线 + 3 标签 */
    public function testLineChartDrawCounts(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(3))->method('ellipse');
        $canvas->expects($this->exactly(8))->method('line');
        $canvas->expects($this->exactly(3))->method('text');
        (new ChartElement(['type' => 'line', 'data' => [
            ['label' => 'Jan', 'value' => 10],
            ['label' => 'Feb', 'value' => 25],
            ['label' => 'Mar', 'value' => 15],
        ]]))->render($canvas);
    }

    /** 验证 pie 图：3 项数据画 3 个扇区 + 3 个标签 */
    public function testPieChartDrawCounts(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(3))->method('filledArc');
        $canvas->expects($this->exactly(3))->method('text');
        (new ChartElement(['type' => 'pie', 'data' => [
            ['label' => 'A', 'value' => 30],
            ['label' => 'B', 'value' => 60],
            ['label' => 'C', 'value' => 10],
        ]]))->render($canvas);
    }

    /** 验证未知图表类型（'histogram'/'donut'/'BAR'/空串）抛 InvalidArgumentException，不再静默画柱状图 */
    public function testUnknownTypeThrows(): void
    {
        foreach (['histogram', 'donut', 'BAR', ''] as $type) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('rectangle');
            try {
                (new ChartElement(['type' => $type, 'data' => [1, 2]]))->render($canvas);
                $this->fail('未知图表类型应抛异常：' . var_export($type, true));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Unknown chart type', $e->getMessage());
                $this->assertStringContainsString('bar, pie, line', $e->getMessage());
            }
        }
    }

    /** 验证 colors 为空数组时回落默认调色板（此前会 DivisionByZeroError） */
    public function testEmptyColorsFallsBackToDefaultPalette(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $colors = [];
        $canvas->expects($this->exactly(2))->method('rectangle')->with(
            $this->anything(), $this->anything(), $this->anything(), $this->anything(),
            $this->callback(function (array $o) use (&$colors) {
                $colors[] = $o['color'];
                return true;
            })
        );
        (new ChartElement(['type' => 'bar', 'data' => [10, 20], 'colors' => []]))->render($canvas);
        $this->assertSame(['#FF6B6B', '#4ECDC4'], $colors);
    }

    /** 验证 pie 图 colors 为空数组同样回落默认调色板 */
    public function testEmptyColorsFallsBackForPie(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $colors = [];
        $canvas->expects($this->exactly(2))->method('filledArc')->with(
            $this->anything(), $this->anything(), $this->anything(), $this->anything(),
            $this->anything(), $this->anything(),
            $this->callback(function (array $o) use (&$colors) {
                $colors[] = $o['color'];
                return true;
            })
        );
        (new ChartElement(['type' => 'pie', 'data' => [1, 1], 'colors' => []]))->render($canvas);
        $this->assertSame(['#FF6B6B', '#4ECDC4'], $colors);
    }

    /** 验证非数组 colors（字符串）也回落默认调色板 */
    public function testNonArrayColorsFallsBack(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('ellipse'); // line 图 2 个数据点各取 palette()[0]
        (new ChartElement(['type' => 'line', 'data' => [1, 2], 'colors' => '#FF0000']))->render($canvas);
    }

    /** 验证自定义 colors 仍按序循环使用 */
    public function testCustomColorsAreUsedInOrder(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $colors = [];
        $canvas->expects($this->exactly(3))->method('rectangle')->with(
            $this->anything(), $this->anything(), $this->anything(), $this->anything(),
            $this->callback(function (array $o) use (&$colors) {
                $colors[] = $o['color'];
                return true;
            })
        );
        (new ChartElement(['type' => 'bar', 'data' => [1, 2, 3], 'colors' => ['#111111', '#222222']]))->render($canvas);
        $this->assertSame(['#111111', '#222222', '#111111'], $colors);
    }

    /** 验证 width/height <= 0 抛 InvalidArgumentException 而不是画出畸形图表 */
    public function testInvalidDimensionsThrow(): void
    {
        foreach ([['width' => 0], ['height' => -5]] as $bad) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('rectangle');
            try {
                (new ChartElement(['type' => 'bar', 'data' => [1, 2]] + $bad))->render($canvas);
                $this->fail('非法尺寸应抛异常：' . json_encode($bad));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be greater than 0', $e->getMessage());
            }
        }
    }

    /** 验证 resolve() 递归替换 data 里的 label 等占位符 */
    public function testResolveRecursesIntoData(): void
    {
        $el = new ChartElement(['type' => 'bar', 'data' => [
            ['label' => '{{month}}', 'value' => 10],
        ]]);
        $el->resolve(['month' => '5月']);
        $this->assertSame('5月', $el->toArray()['data'][0]['label']);
    }
}
