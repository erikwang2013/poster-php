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

    /** 验证 label 在二维码下方居中，且 y 用实际渲染尺寸而非请求 size（本例 198 != 200） */
    public function testLabelRendersTextBelowCentered(): void
    {
        $renderSize = $this->actualQrSize('https://erik.xyz/page', 200);
        $this->assertNotSame(200, $renderSize, '该用例需要实际渲染尺寸 != 请求尺寸才有区分度');
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('image');
        $canvas->expects($this->once())->method('text')->with(
            'Scan me', 100, $renderSize + 20,
            $this->callback(fn(array $o) => $o['align'] === 'center'
                && $o['size'] === 14
                && $o['color'] === '#999999')
        );
        (new QrcodeElement([
            'content' => 'https://erik.xyz/page', 'size' => 200, 'x' => 0, 'y' => 0,
            'label' => 'Scan me', 'label_size' => 14, 'label_color' => '#999999',
        ]))->render($canvas);
    }

    /** 验证 label 的 x 以码中心为基准（带 x 偏移时同样居中） */
    public function testLabelIsCenteredOnQrCodeWithOffset(): void
    {
        $renderSize = $this->actualQrSize('https://erik.xyz/page', 180);
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->once())->method('text')->with(
            '扫码查看详情', 275 + 90, 60 + $renderSize + 20, $this->anything()
        );
        (new QrcodeElement([
            'content' => 'https://erik.xyz/page', 'size' => 180, 'x' => 275, 'y' => 60,
            'label' => '扫码查看详情',
        ]))->render($canvas);
    }

    /** 验证非法 size（<=0）抛 InvalidArgumentException */
    public function testInvalidSizeThrows(): void
    {
        $canvas = $this->createMock(ImageDriverInterface::class);
        $canvas->expects($this->never())->method('image');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('size');
        (new QrcodeElement(['content' => 'x', 'size' => 0]))->render($canvas);
    }

    /** 实际渲染尺寸（QrcodeGenerator 会把 size 量化到模块数 × 缩放） */
    private function actualQrSize(string $content, int $size): int
    {
        $gen = new \Erikwang2013\Poster\Qrcode\QrcodeGenerator();
        $gd = $gen->setText($content)->setSize($size)->setErrorLevel('H')->render();
        $actual = imagesx($gd);
        imagedestroy($gd);
        return $actual;
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
        $this->assertSame('https://e.xyz/7', $el->toArray()['content']);
    }
}
