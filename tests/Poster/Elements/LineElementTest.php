<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\LineElement;
use PHPUnit\Framework\TestCase;

class LineElementTest extends TestCase
{
    /** 验证无选项时默认 (0,0) 到 (100,0) 且 options 透传 */
    public function testDefaults(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('line')->with(0, 0, 100, 0, []);
        (new LineElement())->render($canvas);
    }

    /** 验证只给 x/y 时 x2/y2 回退到 x/y */
    public function testXYFallbackToX2Y2(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('line')->with(0, 0, 50, 20, $this->anything());
        (new LineElement(['x' => 50, 'y' => 20]))->render($canvas);
    }

    /** 验证字符串坐标经 intval 强转且其余选项原样透传 */
    public function testStringCoordinatesCoercedAndOptionsPassed(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('line')->with(
            1, 2, 3, -4,
            $this->callback(fn(array $o) => $o['color'] === '#FF0000' && $o['width'] === 2)
        );
        (new LineElement(['x1' => '1', 'y1' => '2', 'x2' => '3.9', 'y2' => '-4', 'color' => '#FF0000', 'width' => 2]))
            ->render($canvas);
    }
}
