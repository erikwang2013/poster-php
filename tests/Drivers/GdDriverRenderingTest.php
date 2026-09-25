<?php

/**
 * GdDriver 渲染语义与性能回归测试：
 * 圆角/阴影在目标尺寸生效（radius = 目标像素）、circle 真圆且不再逐像素回写、
 * 大半径 blur 不叠加全幅高斯、文字/阴影的 8 位色 alpha、两驱动共用的换行实现。
 *
 * 时间断言用「相对比值 + 宽松绝对下限」，避免 CI 机器/负载差异导致抖动，
 * 但足以拦住被优化掉前的实现（旧实现慢 10~50 倍或直接 OOM）。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use PHPUnit\Framework\TestCase;

class GdDriverRenderingTest extends TestCase
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    /** radius 是目标像素：源图尺寸不同、落到同一目标尺寸时形状必须完全一致。 */
    public function testRadiusIsMeasuredInTargetPixels(): void
    {
        $this->assertSame(
            $this->topRowAlphaSignature(100, 30),
            $this->topRowAlphaSignature(1000, 30),
            'radius 必须按目标像素生效，形状不能随源图尺寸变化'
        );
    }

    /** radius 确实按目标像素缩放（radius=0 与 radius=30 形状不同，且角被切、中心保留）。 */
    public function testImageRadiusCutsCornersOnTargetCanvas(): void
    {
        $plain = $this->topRowAlphaSignature(400, 0);
        $rounded = $this->topRowAlphaSignature(400, 30);
        $this->assertNotSame($plain, $rounded);
        $this->assertSame(0, strspn($plain, '0'), 'radius=0 时首行不应有被擦掉的像素');
        $this->assertGreaterThan(0, strspn($rounded, '0'), 'radius=30 时首行左端应被擦掉');

        $canvas = (new GdDriver())->create(100, 100);
        $overlay = (new GdDriver())->create(400, 400)->rectangle(0, 0, 400, 400, ['color' => '#FF6600']);
        $canvas->image($overlay, 0, 0, ['width' => 100, 'height' => 100, 'radius' => 30]);
        $this->assertSame(127, self::alphaAt($canvas->getResource(), 0, 0));
        $this->assertSame(0, self::alphaAt($canvas->getResource(), 50, 50));
    }

    /** 大源图 + 小目标 + 圆角：旧实现在源尺寸整幅逐像素（3000×4000 在 512M 也 OOM），现在必须毫秒级完成。 */
    public function testLargeSourceWithRadiusCompletesQuickly(): void
    {
        $path = sys_get_temp_dir() . '/poster-bigsrc-' . uniqid() . '.jpg';
        $big = imagecreatetruecolor(2000, 2500);
        imagefill($big, 0, 0, imagecolorallocate($big, 30, 120, 200));
        imagejpeg($big, $path, 80);
        imagedestroy($big);

        $t0 = microtime(true);
        $canvas = (new GdDriver())->create(400, 400);
        $overlay = (new GdDriver())->load($path);
        $canvas->image($overlay, 10, 10, ['width' => 200, 'height' => 200, 'radius' => 12]);
        $elapsed = (microtime(true) - $t0) * 1000;

        $this->assertSame(127, self::alphaAt($canvas->getResource(), 10, 10), '圆角应在目标尺寸擦除');
        $this->assertSame(0, self::alphaAt($canvas->getResource(), 110, 110));
        $this->assertLessThan(5000, $elapsed, '大源图圆角必须在目标尺寸做，耗时 ' . round($elapsed) . 'ms');

        $canvas->destroy();
        $overlay->destroy();
        unlink($path);
    }

    /** circle 生成真圆：不透明像素占比 ≈ π/4（旧实现是圆角方块，实测 98%+ 不透明）。 */
    public function testCircleOpaqueFractionApproximatesPiOverFour(): void
    {
        $d = (new GdDriver())->create(200, 200)->rectangle(0, 0, 200, 200, ['color' => '#3366CC'])->circle(200);
        $img = $d->getResource();
        $opaque = 0;
        for ($y = 0; $y < 200; $y++) {
            for ($x = 0; $x < 200; $x++) {
                if (self::alphaAt($img, $x, $y) < 64) {
                    $opaque++;
                }
            }
        }
        $fraction = $opaque / 40000;
        $this->assertGreaterThan(M_PI / 4 - 0.02, $fraction, "真圆不透明占比应≈78.5%，实测 " . round($fraction * 100, 2) . '%');
        $this->assertLessThan(M_PI / 4 + 0.02, $fraction);
    }

    /** circle 保留内部 alpha：透明画布转圆后中心仍透明（不把圆内像素抹成不透明）。 */
    public function testCircleKeepsInteriorAlpha(): void
    {
        $d = (new GdDriver())->create(40, 40)->circle(40);
        $this->assertSame(127, self::alphaAt($d->getResource(), 20, 20));
        $this->assertSame(127, self::alphaAt($d->getResource(), 0, 0));
    }

    /** circle 的成本应与等价的 image(radius=直径/2) 同量级（旧实现逐像素回写，慢 30~50 倍）。 */
    public function testCircleCostIsComparableToRoundedImage(): void
    {
        $base = (new GdDriver())->create(400, 400)->rectangle(0, 0, 400, 400, ['color' => '#3366CC']);

        $t0 = microtime(true);
        $circled = $base->clone();
        $circled->circle(400);
        $circleMs = (microtime(true) - $t0) * 1000;

        $overlay = (new GdDriver())->create(400, 400)->rectangle(0, 0, 400, 400, ['color' => '#3366CC']);
        $t0 = microtime(true);
        $rounded = $base->clone();
        $rounded->image($overlay, 0, 0, ['width' => 400, 'height' => 400, 'radius' => 200]);
        $roundedMs = (microtime(true) - $t0) * 1000;

        $this->assertLessThan(
            max(60, $roundedMs * 4),
            $circleMs,
            sprintf('circle(400)=%.1fms 不应远慢于 image(radius=200)=%.1fms', $circleMs, $roundedMs)
        );
    }

    /** blur 大半径走「降采样 → 模糊 → 升采样」，不得显著慢于小半径（旧实现叠加 10 次全幅高斯）。 */
    public function testLargeBlurRadiusIsNotSlowerThanSmallRadius(): void
    {
        $small = (new GdDriver())->create(400, 400)->rectangle(0, 0, 400, 400, ['color' => '#3366CC']);
        $t0 = microtime(true);
        $small->blur(2);
        $smallMs = (microtime(true) - $t0) * 1000;

        $large = (new GdDriver())->create(400, 400)->rectangle(0, 0, 400, 400, ['color' => '#3366CC']);
        $t0 = microtime(true);
        $large->blur(10);
        $largeMs = (microtime(true) - $t0) * 1000;

        $this->assertLessThan(
            max(60, $smallMs * 3),
            $largeMs,
            sprintf('blur(10)=%.1fms 不应远慢于 blur(2)=%.1fms', $largeMs, $smallMs)
        );

        // 模糊确实发生：硬边界被插值出多个中间色
        $edge = (new GdDriver())->create(60, 60);
        $edge->rectangle(0, 0, 30, 60, ['color' => '#FFFFFF']);
        $edge->blur(6);
        $colors = [];
        for ($x = 0; $x < 60; $x++) {
            $colors[imagecolorat($edge->getResource(), $x, 30) & 0xFFFFFF] = true;
        }
        $this->assertGreaterThan(2, count($colors));
    }

    /** blur(0) 是 no-op，不改变像素。 */
    public function testBlurZeroIsNoop(): void
    {
        $d = (new GdDriver())->create(40, 40)->rectangle(0, 0, 40, 40, ['color' => '#3366CC']);
        $before = imagecolorat($d->getResource(), 20, 20);
        $d->blur(0);
        $this->assertSame($before, imagecolorat($d->getResource(), 20, 20));
    }

    /** 文字 8 位色（#RRGGBBAA）的 alpha 必须生效（旧实现经 hexToRgb 丢掉 alpha）。 */
    public function testTextColorSupportsEightDigitHexAlpha(): void
    {
        $semi = (new GdDriver())->create(60, 20);
        $semi->text('W', 0, 12, ['font' => null, 'size' => 4, 'color' => '#FF000080']);
        $this->assertSame([63], $this->distinctAlphas($semi->getResource(), 60, 20));

        $opaque = (new GdDriver())->create(60, 20);
        $opaque->text('W', 0, 12, ['font' => null, 'size' => 4, 'color' => '#FF0000']);
        $this->assertSame([0], $this->distinctAlphas($opaque->getResource(), 60, 20));
    }

    /** TTF 文字同样支持 8 位色 alpha（笔画内部为纯色 alpha=63）。 */
    public function testTextTtfSupportsEightDigitHexAlpha(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('系统无 TTF 字体可用');
        }
        $d = (new GdDriver())->create(200, 80);
        $d->text('W', 0, 60, ['font' => self::FONT, 'size' => 48, 'color' => '#FF000080']);
        $this->assertContains(63, $this->distinctAlphas($d->getResource(), 200, 80));
    }

    /** shadow.opacity 生效（0-1 与 0-100 两种写法），旧实现固定 alpha=0 完全忽略该选项。 */
    public function testShadowReadsOpacityOption(): void
    {
        $this->assertSame(0, $this->shadowAlpha([]), '默认不带 opacity 时阴影不透明（保持旧行为）');
        $this->assertSame(127, $this->shadowAlpha(['opacity' => 0]), 'opacity=0 阴影必须完全不可见');
        $this->assertSame(0, $this->shadowAlpha(['opacity' => 1]));
        $this->assertSame(63, $this->shadowAlpha(['opacity' => 0.5]));
        $this->assertSame(63, $this->shadowAlpha(['opacity' => 50]), '0-100 百分比写法等价于 0.5');
    }

    /** 阴影边缘必须是渐变（软边）：GD 的 GAUSSIAN_BLUR 不动 alpha，旧实现因此 blur 完全无效、永远是硬边。 */
    public function testShadowEdgeIsSoftWhenBlurred(): void
    {
        $canvas = (new GdDriver())->create(200, 120);
        $overlay = (new GdDriver())->create(60, 60)->rectangle(0, 0, 60, 60, ['color' => '#FF0000']);
        $canvas->image($overlay, 40, 40, ['shadow' => [
            'color' => '#000000', 'opacity' => 1, 'offsetX' => 10, 'offsetY' => 10, 'blur' => 20,
        ]]);

        // 沿阴影右缘横向扫描，统计中间灰阶（既非全透明也非全不透明）的像素数
        $resource = $canvas->getResource();
        $gradient = 0;
        for ($x = 100; $x < 145; $x++) {
            $c = imagecolorat($resource, $x, 80);
            $alpha = ($c >> 24) & 0x7F;
            if ($alpha > 5 && $alpha < 122) {
                $gradient++;
            }
        }
        $this->assertGreaterThanOrEqual(4, $gradient, 'blur>0 时阴影边缘应出现多级 alpha 过渡（软边）');

        $canvas->destroy();
        $overlay->destroy();
    }

    /** 换行契约（两驱动共用 TextTrait）：每行宽度不超 maxWidth，且不丢字符。 */
    public function testWrapTextKeepsLinesWithinMaxWidth(): void
    {
        $text = str_repeat('中文字符换行测试内容', 20); // 200 个 CJK token
        $measure = function (string $token) {
            return 10 * mb_strlen($token);
        };
        $lines = $this->wrap($text, 100, $measure);
        $this->assertGreaterThanOrEqual(20, count($lines));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(100, $measure($line));
        }
        $this->assertSame($text, implode('', $lines));
    }

    /** 拉丁文按词断行：行宽不超限，且除空白外的字符全部保留。 */
    public function testWrapTextBreaksLatinAtWordBoundaries(): void
    {
        $text = str_repeat('lorem ipsum dolor sit amet ', 20);
        $measure = function (string $token) {
            return 10 * mb_strlen($token);
        };
        $lines = $this->wrap($text, 120, $measure);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(120, $measure($line));
        }
        $this->assertSame(str_replace(' ', '', $text), str_replace(' ', '', implode('', $lines)));
    }

    /** token 级测量：测量次数必须是 O(tokens)，而不是旧实现逐前缀重测的 O(tokens²)。 */
    public function testWrapTextMeasureCallsAreLinear(): void
    {
        $calls = 0;
        $measure = function (string $token) use (&$calls) {
            $calls++;
            return 10 * mb_strlen($token);
        };
        $this->wrap(str_repeat('中文字符换行测试内容', 20), 100, $measure);
        $this->assertLessThanOrEqual(400, $calls, "200 token 的文本测量了 {$calls} 次（旧实现 >2000 次）");
    }

    /** 真实字体引擎下同样满足「每行实测宽度 ≤ maxWidth」。 */
    public function testWrapTextWithRealFontRespectsMaxWidth(): void
    {
        if (!is_file(self::FONT)) {
            $this->markTestSkipped('系统无 TTF 字体可用');
        }
        foreach ([[24, 200, str_repeat('这是一段用于测试自动换行性能的中文正文内容，需要处理换行与对齐。', 6)],
                  [16, 100, 'This is a long text that must wrap onto multiple lines and keep every line within the width budget'],
                  [20, 150, '混合 mixed 文本 with English words 和中文混排']] as $case) {
            [$size, $maxWidth, $text] = $case;
            $measure = function (string $token) use ($size) {
                $box = @imagettfbbox($size, 0, self::FONT, $token);
                return $box === false ? null : $box[2] - $box[0];
            };
            $lines = $this->wrap($text, $maxWidth, $measure);
            $this->assertNotEmpty($lines);
            foreach ($lines as $line) {
                $box = @imagettfbbox($size, 0, self::FONT, $line);
                $this->assertLessThanOrEqual($maxWidth, $box[2] - $box[0], "size={$size} 行超宽：" . mb_substr($line, 0, 12));
            }
            $this->assertSame(
                str_replace([' ', "\t"], '', $text),
                str_replace([' ', "\t"], '', implode('', $lines))
            );
        }
    }

    /** 用 sourceSize×sourceSize 的源图缩到 100×100、圆角 radius，返回首行 alpha 签名。 */
    private function topRowAlphaSignature(int $sourceSize, int $radius): string
    {
        $overlay = (new GdDriver())->create($sourceSize, $sourceSize)
            ->rectangle(0, 0, $sourceSize, $sourceSize, ['color' => '#FF6600']);
        $canvas = (new GdDriver())->create(100, 100);
        $canvas->image($overlay, 0, 0, ['width' => 100, 'height' => 100, 'radius' => $radius]);
        $signature = '';
        for ($x = 0; $x < 100; $x++) {
            $signature .= self::alphaAt($canvas->getResource(), $x, 0) < 64 ? '1' : '0';
        }
        $overlay->destroy();
        $canvas->destroy();
        return $signature;
    }

    /** 采样阴影处像素的 alpha：覆盖层 60×60 放在 (40,40)，阴影偏移 (10,10)，取 (105,105)。 */
    private function shadowAlpha(array $shadow): int
    {
        $canvas = (new GdDriver())->create(200, 200);
        $overlay = (new GdDriver())->create(60, 60)->rectangle(0, 0, 60, 60, ['color' => '#FF0000']);
        $canvas->image($overlay, 40, 40, ['shadow' => $shadow + [
            'color' => '#000000', 'offsetX' => 30, 'offsetY' => 30, 'blur' => 4,
        ]]);
        // 采样「只有阴影、没有覆盖层」的区域：覆盖层 40..100，阴影 70..130，
        // 取 (115,115) 距两边边缘都 ≥15px，落在这块纯阴影区；
        // 该处 alpha 只由 opacity 决定，不受模糊渐变影响（边缘像素不能用来断言）。
        $alpha = self::alphaAt($canvas->getResource(), 115, 115);
        $canvas->destroy();
        $overlay->destroy();
        return $alpha;
    }

    /** @return int[] 区域内出现的所有 alpha（去重升序） */
    private function distinctAlphas(\GdImage $img, int $width, int $height): array
    {
        $alphas = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = self::alphaAt($img, $x, $y);
                if ($alpha < 127) {
                    $alphas[$alpha] = true;
                }
            }
        }
        $alphas = array_keys($alphas);
        sort($alphas);
        return $alphas;
    }

    /** 调用驱动的（protected）共享换行实现。 */
    private function wrap(string $text, int $maxWidth, callable $measure): array
    {
        $method = new \ReflectionMethod(GdDriver::class, 'wrapText');
        $method->setAccessible(true);
        return $method->invoke(new GdDriver(), $text, $maxWidth, $measure);
    }

    private static function alphaAt(\GdImage $img, int $x, int $y): int
    {
        return (imagecolorat($img, $x, $y) >> 24) & 0x7F;
    }
}
