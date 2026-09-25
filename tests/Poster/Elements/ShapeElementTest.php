<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\ShapeElement;
use PHPUnit\Framework\TestCase;

class ShapeElementTest extends TestCase
{
    /** 验证默认 rect 为 (0,0) 100x100 */
    public function testDefaultRect(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('rectangle')->with(0, 0, 100, 100, []);
        (new ShapeElement())->render($canvas);
    }

    /** 验证自定义矩形坐标尺寸与选项透传 */
    public function testCustomRect(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('rectangle')->with(
            1, 2, 30, 40,
            $this->callback(fn(array $o) => $o['color'] === '#000000' && $o['filled'] === true)
        );
        (new ShapeElement(['x' => 1, 'y' => 2, 'width' => 30, 'height' => 40, 'color' => '#000000', 'filled' => true]))
            ->render($canvas);
    }

    /** 验证 circle 使用 cx/cy/radius 且半径同时作为宽高传给 ellipse */
    public function testCircleUsesCxCyRadius(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(10, 20, 5, 5, $this->anything());
        (new ShapeElement(['shape' => 'circle', 'cx' => 10, 'cy' => 20, 'radius' => 5]))->render($canvas);
    }

    /** 验证 circle 未给 radius 时回退到 size */
    public function testCircleFallsBackToSize(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(1, 2, 8, 8, $this->anything());
        (new ShapeElement(['shape' => 'circle', 'x' => 1, 'y' => 2, 'size' => 8]))->render($canvas);
    }

    /** 验证 circle 未给 cx/cy 时回退到 x/y */
    public function testCircleFallsBackToXY(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(5, 6, 3, 3, $this->anything());
        (new ShapeElement(['shape' => 'circle', 'x' => 5, 'y' => 6, 'radius' => 3]))->render($canvas);
    }

    /** 验证 circle 未给 radius/size 时按文档以 width/height 作为外接框（半径 = width/2） */
    public function testCircleFallsBackToHalfWidth(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(100, 100, 40, 40, $this->anything());
        (new ShapeElement([
            'shape' => 'circle', 'x' => 100, 'y' => 100, 'width' => 80, 'height' => 80,
        ]))->render($canvas);
    }

    /** 验证 radius 优先级高于 width/2 */
    public function testRadiusWinsOverWidth(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(0, 0, 7, 7, $this->anything());
        (new ShapeElement(['shape' => 'circle', 'width' => 80, 'radius' => 7]))->render($canvas);
    }

    /** 验证 opacity 选项原样传给 ellipse（驱动层消费，元素层不吞参数） */
    public function testCirclePassesOpacityToDriver(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('ellipse')->with(
            0, 0, 50, 50,
            $this->callback(fn(array $o) => $o['opacity'] === 0.5 && $o['color'] === '#4ECDC4')
        );
        (new ShapeElement(['shape' => 'circle', 'opacity' => 0.5, 'color' => '#4ECDC4']))->render($canvas);
    }

    /** 验证非法尺寸（radius/size/width <= 0）抛 InvalidArgumentException */
    public function testInvalidCircleDimensionsThrow(): void
    {
        foreach ([['radius' => 0], ['size' => -3], ['width' => 0]] as $bad) {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('ellipse');
            try {
                (new ShapeElement(['shape' => 'circle'] + $bad))->render($canvas);
                $this->fail('非法半径应抛异常：' . json_encode($bad));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be greater than 0', $e->getMessage());
            }
        }
    }

    /** 验证矩形非法宽高抛 InvalidArgumentException */
    public function testInvalidRectDimensionsThrow(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('rectangle');
        $this->expectException(\InvalidArgumentException::class);
        (new ShapeElement(['shape' => 'rect', 'width' => 0, 'height' => 100]))->render($canvas);
    }

    /** 验证 resolve() 替换 color 占位符 */
    public function testResolveReplacesColorPlaceholder(): void
    {
        $el = new ShapeElement(['shape' => 'rect', 'color' => '{{brand}}']);
        $el->resolve(['brand' => '#FF6B6B']);
        $this->assertSame('#FF6B6B', $el->toArray()['color']);
    }
}
