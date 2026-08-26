<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\ImageElement;
use PHPUnit\Framework\TestCase;

class ImageElementTest extends TestCase
{
    /** 验证不存在的 src 直接跳过渲染、驱动零调用 */
    public function testNonExistentSourceRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('image');
        $canvas->expects($this->never())->method('load');
        (new ImageElement(['src' => '/no/such/file.png']))->render($canvas);
    }

    /** 验证真实图片文件被加载并合成到画布一次 */
    public function testRendersRealImageFile(): void
    {
        $path = sys_get_temp_dir() . '/img-el-' . uniqid() . '.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $path);
        imagedestroy($img);
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('image')->with(
                $this->isInstanceOf(ImageDriverInterface::class),
                3, 4,
                $this->callback(fn(array $o) => $o['src'] === $path && $o['width'] === 100)
            );
            (new ImageElement(['src' => $path, 'x' => 3, 'y' => 4, 'width' => 100]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 resolve() 替换 src 占位符 */
    public function testResolveReplacesSrcPlaceholder(): void
    {
        $el = new ImageElement(['src' => '/img/{{id}}.png']);
        $el->resolve(['id' => '42']);
        $this->assertSame('/img/42.png', $el->toArray()['options']['src']);
    }
}
