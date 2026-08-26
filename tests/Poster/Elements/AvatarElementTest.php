<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\AvatarElement;
use PHPUnit\Framework\TestCase;

class AvatarElementTest extends TestCase
{
    /** 验证不存在的 src 直接跳过渲染 */
    public function testNonExistentSrcRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('image');
        (new AvatarElement(['src' => '/no/such/avatar.png']))->render($canvas);
    }

    /** 验证 circle 选项把 radius 设成 size/2 传入合成选项 */
    public function testCircleOptionSetsRadius(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('image')->with(
                $this->anything(), 0, 0,
                $this->callback(fn(array $o) => $o['radius'] === 40 && $o['circle'] === true)
            );
            (new AvatarElement(['src' => $path, 'size' => 80, 'circle' => true]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证普通头像（非圆形）渲染一次且不带 radius */
    public function testSquareAvatarRendersOnce(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('image')->with(
                $this->anything(), 5, 6,
                $this->callback(fn(array $o) => !array_key_exists('radius', $o))
            );
            (new AvatarElement(['src' => $path, 'x' => 5, 'y' => 6]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 resolve() 替换 src 占位符 */
    public function testResolveReplacesSrcPlaceholder(): void
    {
        $el = new AvatarElement(['src' => '/a/{{u}}.png']);
        $el->resolve(['u' => 'x']);
        $this->assertSame('/a/x.png', $el->toArray()['options']['src']);
    }

    private function tempPng(): string
    {
        $path = sys_get_temp_dir() . '/avatar-el-' . uniqid() . '.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }
}
