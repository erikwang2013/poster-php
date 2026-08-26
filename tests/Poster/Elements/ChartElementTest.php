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

    /** 验证未知图表类型回退为 bar 图 */
    public function testUnknownTypeFallsBackToBar(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->exactly(2))->method('rectangle');
        (new ChartElement(['type' => 'histogram', 'data' => [1, 2]]))->render($canvas);
    }
}
