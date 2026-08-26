<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Qrcode;

use Erikwang2013\Poster\Qrcode\QrcodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QrcodeTest extends TestCase
{
    public function testGenerateReturnsGdImageWithCorrectDimensions(): void
    {
        $qr = new QrcodeGenerator();
        $qr->setText('https://erik.xyz');
        $qr->setSize(200);
        $image = $qr->render();
        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertGreaterThanOrEqual(100, imagesx($image));
        $this->assertGreaterThanOrEqual(100, imagesy($image));
        imagedestroy($image);
    }

    public function testOutputReturnsNonEmptyPngData(): void
    {
        $qr = new QrcodeGenerator();
        $qr->setText('Hello World');
        $qr->setSize(150);
        $image = $qr->render();
        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        $this->assertIsString($pngData);
        $this->assertGreaterThan(500, strlen($pngData));
        imagedestroy($image);
    }

    public function testSmallSizeDoesNotCrash(): void
    {
        $qr = new QrcodeGenerator();
        $qr->setText('x')->setSize(21);
        $image = $qr->render();
        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertGreaterThan(0, imagesx($image));
        imagedestroy($image);
    }

    /** 验证空文本渲染抛出 InvalidArgumentException。 */
    public function testRenderThrowsOnEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('QR code text cannot be empty');
        (new QrcodeGenerator())->render();
    }

    /** 验证文本 "0" 可正常生成二维码（不被 empty() 误判为空）。 */
    public function testTextZeroRendersSuccessfully(): void
    {
        $img = (new QrcodeGenerator())->setText('0')->render();
        $this->assertInstanceOf(\GdImage::class, $img);
        $this->assertGreaterThan(0, imagesx($img));
        imagedestroy($img);
    }

    /** 验证掩码只作用于数据区：finder/timing 等功能图形不被反转（版本 1：21 模块，margin=2，size=25 → scale=1）。 */
    public function testMaskDoesNotInvertFunctionPatterns(): void
    {
        $img = (new QrcodeGenerator())->setText('x')->setSize(25)->render();
        // 模块 (r,c) 对应像素 (c+2, r+2)
        $this->assertSame(0x000000, imagecolorat($img, 2, 2) & 0xFFFFFF, 'top-left finder corner must stay dark');
        $this->assertSame(0x000000, imagecolorat($img, 16, 2) & 0xFFFFFF, 'top-right finder corner must stay dark');
        $this->assertSame(0x000000, imagecolorat($img, 2, 16) & 0xFFFFFF, 'bottom-left finder corner must stay dark');
        $this->assertSame(0x000000, imagecolorat($img, 10, 8) & 0xFFFFFF, 'timing pattern must stay dark');
        imagedestroy($img);
    }

    /** 验证超大数据量抛出 InvalidArgumentException。 */
    public function testOversizedDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Data too large');
        (new QrcodeGenerator())->setText(str_repeat('A', 6000))->render();
    }

    /** 验证 setSize 下限钳制：21 模块 + 默认边距 2*2 = 25。 */
    public function testSizeClampedToMinimum(): void
    {
        $img = (new QrcodeGenerator())->setText('x')->setSize(5)->render();
        $this->assertSame(25, imagesx($img));
        imagedestroy($img);
    }

    /** 验证 margin 影响输出尺寸（29 = 21 模块 + 8 边距）。 */
    public function testMarginAffectsOutputSize(): void
    {
        $img = (new QrcodeGenerator())->setText('x')->setSize(42)->setMargin(4)->render();
        $this->assertSame(29, imagesx($img));
        imagedestroy($img);
    }

    /** 验证负 margin 钳制为 0 后输出 42x42。 */
    public function testNegativeMarginClampedToZero(): void
    {
        $img = (new QrcodeGenerator())->setText('x')->setMargin(-3)->setSize(42)->render();
        $this->assertSame(42, imagesx($img));
        imagedestroy($img);
    }

    /** 验证非法纠错级别回退到 H，且与显式 H 输出完全一致。 */
    public function testInvalidErrorLevelFallsBackToHigh(): void
    {
        $a = (new QrcodeGenerator())->setText('hello')->setErrorLevel('X')->render();
        $b = (new QrcodeGenerator())->setText('hello')->setErrorLevel('H')->render();
        ob_start();
        imagepng($a);
        $pa = ob_get_clean();
        ob_start();
        imagepng($b);
        $pb = ob_get_clean();
        $this->assertSame($pa, $pb);
        imagedestroy($a);
        imagedestroy($b);
    }

    /** 验证大小写混合的纠错级别均可渲染。 */
    public function testAllErrorLevelsRender(): void
    {
        foreach (['L', 'M', 'Q', 'H', 'l', 'h'] as $lvl) {
            $img = (new QrcodeGenerator())->setText('test data')->setErrorLevel($lvl)->render();
            $this->assertInstanceOf(\GdImage::class, $img);
            imagedestroy($img);
        }
    }

    /** 验证前景/背景颜色生效：边角为背景色，且存在前景色像素。 */
    public function testForegroundAndBackgroundColorsApplied(): void
    {
        $img = (new QrcodeGenerator())
            ->setText('x')->setSize(50)->setMargin(5)
            ->setForeground(0x112233)->setBackground(0xAABBCC)
            ->render();
        $this->assertSame(0xAABBCC, imagecolorat($img, 0, 0) & 0xFFFFFF);
        $found = false;
        for ($y = 0; $y < 31 && !$found; $y++) {
            for ($x = 0; $x < 31; $x++) {
                if ((imagecolorat($img, $x, $y) & 0xFFFFFF) === 0x112233) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, '图像中应存在前景色像素');
        imagedestroy($img);
    }

    /** 验证相同参数两次渲染输出完全一致（确定性）。 */
    public function testRenderIsDeterministic(): void
    {
        $render = function (): string {
            $img = (new QrcodeGenerator())->setText('hello world')->setSize(120)->render();
            ob_start();
            imagepng($img);
            $data = ob_get_clean();
            imagedestroy($img);
            return $data;
        };
        $this->assertSame($render(), $render());
    }

    /** 验证大文本按容量选择更高版本，输出不超出设定尺寸。 */
    public function testLargeTextStaysWithinSize(): void
    {
        $img = (new QrcodeGenerator())->setText(str_repeat('A', 2000))->setSize(300)->render();
        $this->assertGreaterThan(21, imagesx($img));
        $this->assertLessThanOrEqual(300, imagesx($img));
        imagedestroy($img);
    }

    /** 验证 setter 返回自身支持链式调用。 */
    public function testSettersReturnStaticForChaining(): void
    {
        $qr = new QrcodeGenerator();
        $this->assertSame($qr, $qr->setText('x'));
        $this->assertSame($qr, $qr->setSize(100));
        $this->assertSame($qr, $qr->setMargin(1));
        $this->assertSame($qr, $qr->setErrorLevel('M'));
        $this->assertSame($qr, $qr->setForeground(0));
        $this->assertSame($qr, $qr->setBackground(0xFFFFFF));
    }
}
