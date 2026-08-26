<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\WatermarkElement;
use PHPUnit\Framework\TestCase;

class WatermarkElementTest extends TestCase
{
    /** 验证空文本直接返回、零调用 */
    public function testEmptyTextRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('text');
        (new WatermarkElement())->render($canvas);
    }

    /** 验证画布尺寸为空时直接返回、零调用 */
    public function testEmptyCanvasSizeRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('getSize')->willReturn([]);
        $canvas->expects($this->never())->method('text');
        (new WatermarkElement(['text' => 'wm']))->render($canvas);
    }

    /** 验证按默认间距 150x100 在 300x200 画布上平铺 2x2 共 4 次且角度 -30 生效 */
    public function testTilesAcrossCanvasWithAngle(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('getSize')->willReturn(['width' => 300, 'height' => 200]);
        $canvas->expects($this->exactly(4))->method('text')->with(
            'wm', $this->anything(), $this->anything(),
            $this->callback(fn(array $o) => $o['angle'] === -30.0)
        );
        (new WatermarkElement(['text' => 'wm']))->render($canvas);
    }

    /** 验证 spacing 简写同时作用于两个方向且坐标按步进取值 */
    public function testSpacingShorthandAndCoordinates(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('getSize')->willReturn(['width' => 400, 'height' => 300]);
        $positions = [];
        $idx = 0;
        $canvas->expects($this->exactly(4))->method('text')->with(
            'wm',
            $this->callback(function ($x) use (&$positions, &$idx) {
                $positions[$idx][0] = $x;
                return true;
            }),
            $this->callback(function ($y) use (&$positions, &$idx) {
                $positions[$idx][1] = $y;
                $idx++;
                return true;
            }),
            $this->anything()
        )->willReturnSelf();
        (new WatermarkElement(['text' => 'wm', 'spacing' => 200]))->render($canvas);
        $this->assertSame([[0, 0], [0, 200], [200, 0], [200, 200]], $positions);
    }

    /** 验证单独 spacing_x/spacing_y 优先于 spacing 简写 */
    public function testPerAxisSpacingOverridesShorthand(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('getSize')->willReturn(['width' => 300, 'height' => 100]);
        $canvas->expects($this->exactly(2))->method('text'); // 300/150=2, 100/100=1 → 2 个
        (new WatermarkElement(['text' => 'wm', 'spacing' => 200, 'spacing_x' => 150, 'spacing_y' => 100]))
            ->render($canvas);
    }

    /** 验证 resolve() 替换 text 占位符 */
    public function testResolveReplacesTextPlaceholder(): void
    {
        $el = new WatermarkElement(['text' => '© {{year}}']);
        $el->resolve(['year' => 2026]);
        $this->assertSame('© 2026', $el->toArray()['options']['text']);
    }
}
