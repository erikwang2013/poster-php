<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\QrcodeElement;
use PHPUnit\Framework\TestCase;

class QrcodeElementTest extends TestCase
{
    /** 验证空 content 不渲染、零调用 */
    public function testEmptyContentRendersNothing(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('image');
        $canvas->expects($this->never())->method('text');
        (new QrcodeElement())->render($canvas);
    }

    /** 验证真实二维码生成并合成到画布一次（纯 PHP 渲染，无网络） */
    public function testRendersRealQrcode(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('image')->with(
            $this->isInstanceOf(ImageDriverInterface::class), 5, 6, $this->anything()
        );
        $canvas->expects($this->never())->method('text');
        (new QrcodeElement(['content' => 'https://erik.xyz', 'x' => 5, 'y' => 6, 'size' => 120]))->render($canvas);
    }

    /** 验证 label 选项在二维码下方额外渲染一次文本 */
    public function testLabelRendersTextBelow(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('image');
        $canvas->expects($this->once())->method('text')->with('Scan me', 0, 220, $this->anything());
        (new QrcodeElement([
            'content' => 'x', 'size' => 200, 'x' => 0, 'y' => 0,
            'label' => 'Scan me', 'label_size' => 14, 'label_color' => '#999999',
        ]))->render($canvas);
    }

    /** 验证 logo 选项存在时渲染不抛异常且画布合成一次 */
    public function testLogoOptionRenders(): void
    {
        $logo = sys_get_temp_dir() . '/qr-logo-' . uniqid() . '.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $logo);
        imagedestroy($img);
        try {
            $canvas = $this->createMock(ImageDriverInterface::class);
            $canvas->expects($this->once())->method('image');
            (new QrcodeElement(['content' => 'x', 'size' => 100, 'logo' => $logo]))->render($canvas);
        } finally {
            unlink($logo);
        }
    }

    /** 验证 resolve() 替换 content 占位符 */
    public function testResolveReplacesContentPlaceholder(): void
    {
        $el = new QrcodeElement(['content' => 'https://e.xyz/{{id}}']);
        $el->resolve(['id' => '7']);
        $this->assertSame('https://e.xyz/7', $el->toArray()['options']['content']);
    }
}
