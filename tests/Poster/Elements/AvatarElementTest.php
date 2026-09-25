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

    /** 验证显式 circle 选项把 radius 设成 size/2 传入合成选项 */
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

    /** 验证缺省即圆形（对齐 README「圆形裁剪」），无需显式传 circle */
    public function testCircleIsTheDefault(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('image')->with(
                $this->anything(), 0, 0,
                $this->callback(fn(array $o) => $o['radius'] === 40)
            );
            (new AvatarElement(['src' => $path, 'size' => 80]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 circle=false 时按方图渲染且不带 radius，也不画圆环 */
    public function testSquareAvatarRendersOnce(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('ellipse');
            $canvas->expects($this->once())->method('image')->with(
                $this->anything(), 5, 6,
                $this->callback(fn(array $o) => !array_key_exists('radius', $o))
            );
            (new AvatarElement(['src' => $path, 'x' => 5, 'y' => 6, 'circle' => false]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 border 生效：先画一圈比头像大 borderWidth 的实心圆，再叠头像 */
    public function testBorderDrawsRingBehindAvatar(): void
    {
        $path = $this->tempPng();
        try {
            $order = [];
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('ellipse')->with(
                140, 100, 63, 63,
                $this->callback(fn(array $o) => $o['color'] === '#FF6B6B' && $o['filled'] === true)
            )->willReturnCallback(function () use (&$order, $canvas) {
                $order[] = 'border';
                return $canvas;
            });
            $canvas->expects($this->once())->method('image')->willReturnCallback(function () use (&$order, $canvas) {
                $order[] = 'avatar';
                return $canvas;
            });
            (new AvatarElement([
                'src' => $path, 'x' => 80, 'y' => 40, 'size' => 120,
                'border' => '#FF6B6B', 'borderWidth' => 3,
            ]))->render($canvas);
            $this->assertSame(['border', 'avatar'], $order);
        } finally {
            unlink($path);
        }
    }

    /** 验证方图边框用矩形框，且尺寸为 size + 2*borderWidth（默认 2px） */
    public function testSquareBorderDrawsFrame(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('rectangle')->with(
                8, 18, 104, 104,
                $this->callback(fn(array $o) => $o['color'] === '#4ECDC4')
            );
            (new AvatarElement([
                'src' => $path, 'x' => 10, 'y' => 20, 'size' => 100,
                'circle' => false, 'border' => '#4ECDC4',
            ]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 borderWidth=0 时不画边框；无 border 时同样不画 */
    public function testNoBorderMeansNoBackdrop(): void
    {
        $path = $this->tempPng();
        try {
            foreach ([[], ['border' => '#FF6B6B', 'borderWidth' => 0]] as $extra) {
                $canvas = $this->createMock(ImageDriverInterface::class);
                $canvas->expects($this->never())->method('ellipse');
                $canvas->expects($this->never())->method('rectangle');
                (new AvatarElement(['src' => $path] + $extra))->render($canvas);
            }
        } finally {
            unlink($path);
        }
    }

    /** 验证非法 size（<=0）抛 InvalidArgumentException，不再落到 GD 的 ValueError */
    public function testInvalidSizeThrowsInsteadOfGdValueError(): void
    {
        $path = $this->tempPng();
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->never())->method('image');
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('size');
            (new AvatarElement(['src' => $path, 'size' => -10]))->render($canvas);
        } finally {
            unlink($path);
        }
    }

    /** 验证 resolve() 替换 src 占位符 */
    public function testResolveReplacesSrcPlaceholder(): void
    {
        $el = new AvatarElement(['src' => '/a/{{u}}.png']);
        $el->resolve(['u' => 'x']);
        $this->assertSame('/a/x.png', $el->toArray()['src']);
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
