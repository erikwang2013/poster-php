<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\CalendarElement;
use PHPUnit\Framework\TestCase;

class CalendarElementTest extends TestCase
{
    /** 验证默认参数渲染至少产生文字与矩形绘制、不抛异常 */
    public function testRenderWithDefaultsDraws(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('text');
        $canvas->expects($this->atLeastOnce())->method('rectangle');
        (new CalendarElement(['year' => 2026, 'month' => 5]))->render($canvas);
    }

    /** 验证 startDay=1（周一开头）与 highlights 数组形式正常渲染 */
    public function testRenderWithMondayStartAndArrayHighlights(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('text');
        (new CalendarElement([
            'year' => 2026, 'month' => 5, 'startDay' => 1,
            'highlights' => ['2026-05-16' => ['text' => 'F', 'bg' => '#FFEAA7']],
        ]))->render($canvas);
    }

    /** 验证 highlights 字符串形式（日期 => 文案）正常渲染 */
    public function testRenderWithStringHighlights(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('text');
        (new CalendarElement([
            'year' => 2026, 'month' => 5,
            'highlights' => ['2026-05-16' => '今天'],
        ]))->render($canvas);
    }

    /** 验证越界月份（13）被 PHP date 归一化后仍正常渲染 */
    public function testRenderWithInvalidMonthNormalizes(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('text');
        (new CalendarElement(['year' => 2026, 'month' => 13]))->render($canvas);
    }

    /** 验证自定义 title 与颜色选项不改变绘制行为 */
    public function testRenderWithCustomOptions(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('rectangle');
        (new CalendarElement([
            'year' => 2026, 'month' => 2, 'title' => 'My Cal',
            'cellSize' => 30, 'headerBg' => '#111111', 'headerColor' => '#FFFFFF',
            'todayBg' => '#00FF00', 'textColor' => '#000000',
        ]))->render($canvas);
    }
}
