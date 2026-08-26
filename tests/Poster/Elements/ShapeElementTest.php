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
}
