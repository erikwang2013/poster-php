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

    /** 验证空白格用 dimColor 而非 cellBg（2026-05：5 个前置空格 + 6 个后置空格 = 11） */
    public function testEmptyCellsUseDimColor(): void
    {
        $counts = $this->rectangleColors([
            'year' => 2026, 'month' => 5, 'startDay' => 0,
            'cellBg' => '#FFFFFF', 'dimColor' => '#00FF00',
        ]);
        $this->assertSame(11, $counts['#00FF00'] ?? 0, '空白格应使用 dimColor');
        $this->assertSame(31, $counts['#FFFFFF'] ?? 0, '有值格应保持 cellBg');
    }

    /** 验证 dimColor 缺省为 #CCCCCC，自定义值同样生效 */
    public function testDimColorIsConfigurable(): void
    {
        $default = $this->rectangleColors(['year' => 2026, 'month' => 5, 'startDay' => 0]);
        $this->assertSame(11, $default['#CCCCCC'] ?? 0);

        $custom = $this->rectangleColors([
            'year' => 2026, 'month' => 5, 'startDay' => 0, 'dimColor' => '#123456',
        ]);
        $this->assertSame(11, $custom['#123456'] ?? 0);
    }

    /** 验证 resolve() 替换 title 与 highlights 文案占位符 */
    public function testResolveReplacesTitleAndHighlights(): void
    {
        $el = new CalendarElement([
            'year' => 2026, 'month' => 5,
            'title' => '{{who}}的月历',
            'highlights' => ['2026-05-16' => '{{event}}'],
        ]);
        $el->resolve(['who' => '小明', 'event' => '生日']);
        $arr = $el->toArray();
        $this->assertSame('小明的月历', $arr['title']);
        $this->assertSame('生日', $arr['highlights']['2026-05-16']);
        $this->assertArrayHasKey('2026-05-16', $arr['highlights']); // 日期键不被替换
    }

    /** 按颜色统计 rectangle 调用次数 */
    private function rectangleColors(array $options): array
    {
        $counts = [];
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->atLeastOnce())->method('rectangle')->with(
            $this->anything(), $this->anything(), $this->anything(), $this->anything(),
            $this->callback(function (array $o) use (&$counts) {
                $counts[$o['color']] = ($counts[$o['color']] ?? 0) + 1;
                return true;
            })
        );
        (new CalendarElement($options))->render($canvas);
        return $counts;
    }
}
