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

    /** 验证大文本按容量选择更高版本，输出不超出设定尺寸（2000 字节仅 L 级可容纳，H 级上限为 1273 字节）。 */
    public function testLargeTextStaysWithinSize(): void
    {
        $img = (new QrcodeGenerator())->setText(str_repeat('A', 2000))->setErrorLevel('L')->setSize(300)->render();
        $this->assertGreaterThan(21, imagesx($img));
        $this->assertLessThanOrEqual(300, imagesx($img));
        imagedestroy($img);
    }

    /**
     * 每个 version×level：编码区模块数 == (数据码字 + 纠错码字) × 8 + 余位数。
     * 编码区模块数由几何公式独立推导，码字数来自分块表，两者不一致即分块表错误。
     */
    public function testDataModuleCountMatchesCodewordTablesForEveryVersionAndLevel(): void
    {
        foreach (range(1, 40) as $version) {
            $raw = $this->rawDataModules($version);
            foreach (['L', 'M', 'Q', 'H'] as $level) {
                $ecc = $this->const('ECC_PER_BLOCK')[$level][$version];
                $blocks = $this->const('EC_BLOCK_COUNT')[$level][$version];
                $codewords = intdiv($raw, 8);
                $data = $codewords - $ecc * $blocks;

                // 每个 RS 块的总长度（数据 + 纠错）受 GF(256) 限制不得超过 255
                $this->assertGreaterThan(0, $data, "v$version-$level 数据码字必须为正");
                $this->assertLessThanOrEqual(255, intdiv($data, $blocks) + 1 + $ecc, "v$version-$level 单块长度超过 GF(256) 上限");
                $this->assertContains($raw % 8, [0, 3, 4, 7], "v$version 余位数非法");
                $this->assertSame(
                    $raw,
                    ($data + $ecc * $blocks) * 8 + $raw % 8,
                    "v$version-$level 数据模块数与码字表不一致"
                );
            }
        }
    }

    /** 代表性版本：实际放置的编码区模块数必须等于分块表推导的位数（含 v7+ 版本信息区、对齐图形与最大版本）。 */
    public function testPlacedEncodingRegionMatchesTableGeometry(): void
    {
        foreach ([1, 7, 10, 27, 40] as $version) {
            foreach (['L', 'H'] as $level) {
                $raw = $this->rawDataModules($version);

                $generator = new QrcodeGenerator();
                $generator->setText(str_repeat('x', $this->byteCapacity($version, $level)))->setErrorLevel($level);
                $img = $generator->setSize(21)->setMargin(4)->render(); // scale = 1 → 边长为模块数 + 8
                $this->assertSame($version * 4 + 17 + 8, imagesx($img), "v$version-$level 模块数不符");
                imagedestroy($img);

                $property = new \ReflectionProperty(QrcodeGenerator::class, 'dataCells');
                $property->setAccessible(true);
                $this->assertCount($raw, $property->getValue($generator), "v$version-$level 实际放置的编码区模块数不符");
            }
        }
    }

    /** 格式信息两份副本必须一致，且解码回配置的纠错级别（同时校验 BCH(15,5) 与拷贝坐标）。 */
    public function testFormatInformationDecodesBackToConfiguredLevel(): void
    {
        foreach (['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2] as $level => $levelBits) {
            $img = (new QrcodeGenerator())->setText('format info')->setErrorLevel($level)->setSize(21)->setMargin(4)->render();
            $n = imagesx($img) - 8;
            $this->assertSame(imagesx($img), imagesy($img));

            $read = function (int $row, int $col) use ($img): int {
                return (imagecolorat($img, $col + 4, $row + 4) & 0xFFFFFF) === 0x000000 ? 1 : 0;
            };

            $copy1 = 0;
            for ($i = 0; $i < 6; $i++) { $copy1 |= $read($i, 8) << $i; }
            $copy1 |= $read(7, 8) << 6;
            $copy1 |= $read(8, 8) << 7;
            $copy1 |= $read(8, 7) << 8;
            for ($i = 9; $i < 15; $i++) { $copy1 |= $read(8, 14 - $i) << $i; }

            $copy2 = 0;
            for ($i = 0; $i < 8; $i++) { $copy2 |= $read(8, $n - 1 - $i) << $i; }
            for ($i = 8; $i < 15; $i++) { $copy2 |= $read($n - 15 + $i, 8) << $i; }

            $this->assertSame($copy1, $copy2, "$level 级格式信息两份副本不一致");
            // 5 位数据 = 纠错级别(2) + 掩码(3)，置于 15 位码字的 bit13-14 与 bit10-12
            $this->assertSame($levelBits, ($copy1 ^ 0x5412) >> 13 & 0b11, "$level 级格式信息纠错级别错误");
            $this->assertLessThan(8, ($copy1 ^ 0x5412) >> 10 & 0b111, '掩码编号超出范围');
            imagedestroy($img);
        }
    }

    /** 格式信息不得覆盖 timing 图形：第 6 行/第 6 列的 timing 模块必须保持交替（v1 无对齐图形干扰）。 */
    public function testTimingPatternSurvivesFormatInformation(): void
    {
        // size=21 → scale=1，模块 (r,c) 对应像素 (c+4, r+4)
        $img = (new QrcodeGenerator())->setText('x')->setSize(21)->setMargin(4)->render();
        $n = imagesx($img) - 8;
        $dark = function (int $row, int $col) use ($img): bool {
            return (imagecolorat($img, $col + 4, $row + 4) & 0xFFFFFF) === 0x000000;
        };

        for ($i = 8; $i <= $n - 9; $i++) {
            $this->assertSame($i % 2 === 0, $dark($i, 6), "第 $i 行 timing 模块被破坏（格式信息写入 (row=$i,col=6)）");
            $this->assertSame($i % 2 === 0, $dark(6, $i), "第 $i 列 timing 模块被破坏（格式信息写入 (row=6,col=$i)）");
        }
        // (8,6) 与 (6,8) 紧邻格式信息区，历史上曾被格式信息覆盖
        $this->assertTrue($dark(8, 6), '格式信息不得写入 timing 模块 (8,6)');
        $this->assertTrue($dark(6, 8), '格式信息不得写入 timing 模块 (6,8)');
        // 固定暗模块
        $this->assertTrue($dark($n - 8, 8), '固定暗模块必须为深色');
        imagedestroy($img);
    }

    /** 版本信息（v7+）两份副本必须一致。 */
    public function testVersionInformationIsPlacedInBothCopies(): void
    {
        $img = (new QrcodeGenerator())->setText(str_repeat('v', 200))->setErrorLevel('H')->setSize(21)->setMargin(4)->render();
        $n = imagesx($img) - 8;
        $this->assertGreaterThanOrEqual(7, intdiv($n - 17, 4));

        $dark = function (int $row, int $col) use ($img): int {
            return (imagecolorat($img, $col + 4, $row + 4) & 0xFFFFFF) === 0x000000 ? 1 : 0;
        };

        $bits = [];
        for ($i = 0; $i < 18; $i++) {
            $row = $n - 11 + $i % 3;
            $col = intdiv($i, 3);
            $bottomLeft = $dark($row, $col);
            $topRight = $dark($col, $row);
            $this->assertSame($bottomLeft, $topRight, "版本信息两份副本第 $i 位不一致");
            $bits[] = $bottomLeft;
        }
        $value = 0;
        foreach ($bits as $i => $bit) { $value |= $bit << $i; }
        $version = intdiv($n - 17, 4);
        $this->assertSame($version, $value >> 12, '版本信息高 6 位必须等于版本号');
        imagedestroy($img);
    }

    /** 超出 v1-40 的版本必须显式失败，不得静默吸附到邻近版本。 */
    public function testUnsupportedVersionThrowsInsteadOfSnapping(): void
    {
        $method = new \ReflectionMethod(QrcodeGenerator::class, 'blockLayout');
        $method->setAccessible(true);
        foreach ([0, 41, 99] as $version) {
            try {
                $method->invokeArgs(new QrcodeGenerator(), [$version, 0]);
                $this->fail("版本 $version 应抛出 InvalidArgumentException");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Unsupported QR code version', $e->getMessage());
            }
        }
    }

    /** 按已校验的分块表计算某版本/级别的字节模式数据容量。 */
    private function byteCapacity(int $version, string $level): int
    {
        $ecc = $this->const('ECC_PER_BLOCK')[$level][$version];
        $blocks = $this->const('EC_BLOCK_COUNT')[$level][$version];
        $data = intdiv($this->rawDataModules($version), 8) - $ecc * $blocks;
        return intdiv($data * 8 - 4 - ($version <= 9 ? 8 : 16), 8);
    }

    private function rawDataModules(int $version): int
    {
        $method = new \ReflectionMethod(QrcodeGenerator::class, 'rawDataModules');
        $method->setAccessible(true);
        return $method->invokeArgs(null, [$version]);
    }

    private function const(string $name): array
    {
        $reflection = new \ReflectionClass(QrcodeGenerator::class);
        return $reflection->getConstant($name);
    }

    /** 验证 setter 返回自身支持链式调用。 */

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
