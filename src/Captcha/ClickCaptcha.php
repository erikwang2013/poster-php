<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;


class ClickCaptcha extends AbstractCaptcha
{
    /** 目标类型：'text' 文字（默认）| 'icon' 用图元现画的矢量图形 */
    private const TARGET_TYPES = ['text', 'icon'];

    /** 图标形状：全部由驱动图元（ellipse/rectangle/line/filledArc）现画，不依赖图片素材 */
    private const SHAPES = ['circle', 'ring', 'square', 'rounded', 'bar', 'cross', 'x', 'chevron', 'semicircle', 'wedge', 'asterisk'];

    /** 提示缩略图用中性色：若用画布上的随机色，thumb 就把目标颜色告诉了攻击者，颜色分离又能还原坐标 */
    private const THUMB_COLOR = '#37474F';

    private string $targetType = 'text';
    private ?array $words = null;
    protected int $targetCount;
    private int $iconSize = 22;

    public function setWords(array $words): static
    {
        $this->words = $words;
        return $this;
    }

    public function setTargetType(string $type): static
    {
        $this->targetType = $type;
        return $this;
    }

    protected function getType(): string
    {
        return 'click';
    }

    public function generate(): array
    {
        if (!in_array($this->targetType, self::TARGET_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported click target type "%s"; supported: %s',
                $this->targetType,
                implode(', ', self::TARGET_TYPES)
            ));
        }
        $this->generateKey();
        $bg = $this->createBackground();
        if ($this->difficulty === 'easy') {
            $this->targetCount = 2;
        } elseif ($this->difficulty === 'hard') {
            $this->targetCount = 4;
        } else {
            $this->targetCount = 3;
        }

        $targets = $this->placeTargets();
        $fontFile = PosterConfig::get('image.font')
            ?? dirname(__DIR__, 2) . '/src/fonts/Alibaba-PuHuiTi-Regular.ttf';

        foreach ($targets as $i => $target) {
            // 色相随机 + 整体随机旋转：旧实现是恒定 #FF4444 正立绘制，按颜色分离即可精确还原坐标
            $color = $this->randomTargetColor();
            $angle = random_int(-15, 15);
            $targets[$i]['color'] = $color;
            $targets[$i]['angle'] = $angle;

            if ($this->targetType === 'icon') {
                $icon = $this->renderIcon($target['text'], $color . $this->randomAlpha(), $angle);
                $size = $icon->getSize();
                $bg->image($icon, $target['x'] - intdiv($size['width'], 2), $target['y'] - intdiv($size['height'], 2));
                $icon->destroy();
            } else {
                $bg->text($target['text'], $target['x'], $target['y'] + 6, [
                    'size'  => 16,
                    'color' => $color,
                    'font'  => $fontFile,
                    'align' => 'center',
                    'angle' => $angle,
                ]);
            }
        }

        $this->drawOverlayNoise($bg, $targets);
        $this->store(['targets' => $targets]);
        $image = $bg->output('png');
        $bg->destroy();
        return [
            'key'   => $this->key,
            'type'  => 'click',
            'image' => $image,
            'extra' => [
                'texts' => $this->hints($targets),
            ],
        ];
    }

    /**
     * 目标布点：中心两两间距 >= 2×容差（任意点最多落进一个目标的容差圆），边距随画布缩放。
     *
     * 旧实现边距固定 40px，画布一缩小可放置区间就退化成单点（60×60 时三个目标全在 (40,41)），
     * 盲提交一个坐标即可通过。这里：槽位网格 + 整体随机平移保证间距恒定，画布装不下就明确拒绝。
     */
    private function placeTargets(): array
    {
        $tolerance = floatval(PosterConfig::get('captcha.tolerance.click', 18));
        $spacing = max(24.0, 2 * $tolerance);
        $pad = max(12, intdiv(min($this->width, $this->height), 5));

        $cols = intval(ceil(sqrt($this->targetCount)));
        $rows = intval(ceil($this->targetCount / $cols));
        $jitter = $spacing / 3;
        $regionW = $this->width - 2 * $pad;
        $regionH = $this->height - 3 * $pad;
        if ($regionW < ($cols - 1) * $spacing + $jitter || $regionH < ($rows - 1) * $spacing + $jitter) {
            throw new InvalidArgumentException(sprintf(
                'Canvas %dx%d is too small for %d click targets (min spacing %.0fpx): enlarge to about %s or use a lower difficulty',
                $this->width,
                $this->height,
                $this->targetCount,
                $spacing,
                $this->minimumCanvas($cols, $rows, $spacing)
            ));
        }

        $slots = [];
        for ($c = 0; $c < $cols; $c++) {
            for ($r = 0; $r < $rows; $r++) {
                $slots[] = [$c, $r];
            }
        }
        shuffle($slots);

        $offsetX = $pad + random_int(0, intval(round($regionW - ($cols - 1) * $spacing)));
        $offsetY = $pad + random_int(0, intval(round($regionH - ($rows - 1) * $spacing)));

        $pool = $this->targetPool();
        $targets = [];
        for ($i = 0; $i < $this->targetCount; $i++) {
            $targets[] = [
                'x'     => intval(round($offsetX + $slots[$i][0] * $spacing)),
                'y'     => intval(round($offsetY + $slots[$i][1] * $spacing)),
                'text'  => $pool[$i],
                'order' => $i + 1,
            ];
        }
        return $targets;
    }

    /** 目标文案池：text 用词表（沿用旧兜底逻辑），icon 用形状名；不足时循环取，保证个数正确 */
    private function targetPool(): array
    {
        $pool = $this->targetType === 'icon'
            ? self::SHAPES
            // ?: 而非 ??：setWords([]) 时空数组会走到兜底，避免 count(0) 取模除零
            : ($this->words
                ?: PosterConfig::get('captcha.click_words')
                ?: match ($this->difficulty) {
                    'easy' => ['云', '风'],
                    'hard' => ['星', '雨', '山', '火'],
                    default => ['云', '风', '山'],
                });
        $pool = array_values($pool);
        shuffle($pool);

        $picked = [];
        for ($i = 0; $i < $this->targetCount; $i++) {
            $picked[] = $pool[$i % count($pool)];
        }
        return $picked;
    }

    /** 报给调用方的最小画布尺寸（正方形估算，仅用于错误提示）：解 pad/边距两式取上界 */
    private function minimumCanvas(int $cols, int $rows, float $spacing): string
    {
        $needW = ($cols - 1) * $spacing + $spacing / 3;
        $needH = ($rows - 1) * $spacing + $spacing / 3;
        $side = max(60, intval(ceil(5 * max($needW / 3, $needH / 2))));
        return $side . 'x' . $side;
    }

    /** 前端提示项：icon 模式给缩略图，形状与画布一致（同一渲染路径），颜色中性不泄露画布配色 */
    private function hints(array $targets): array
    {
        $hints = [];
        foreach ($targets as $target) {
            $item = ['text' => $target['text'], 'order' => $target['order']];
            if ($this->targetType === 'icon') {
                $icon = $this->renderIcon($target['text'], self::THUMB_COLOR, $target['angle']);
                $item['thumb'] = $icon->output('png');
                $icon->destroy();
            }
            $hints[] = $item;
        }
        return $hints;
    }

    /** 渲染单个图标：透明小画布 + 整体旋转，画布与缩略图共用，保证形状、角度一致 */
    private function renderIcon(string $shape, string $color, int $angle): ImageDriverInterface
    {
        $icon = $this->imageDriver->clone();
        $icon->create($this->iconSize + 6, $this->iconSize + 6);   // 留出旋转后的外接尺寸
        $size = $icon->getSize();
        $this->drawShape($icon, $shape, intdiv($size['width'], 2), intdiv($size['height'], 2), $this->iconSize, $color);
        if ($angle !== 0) {
            $icon->rotate($angle, 'transparent');
        }
        return $icon;
    }

    /** 图元画出的形状，$size 为外接尺寸，中心为 ($cx, $cy) */
    private function drawShape(ImageDriverInterface $canvas, string $shape, int $cx, int $cy, int $size, string $color): void
    {
        $r = intdiv($size, 2);
        $stroke = max(2, intdiv($size, 8));
        $line = ['color' => $color, 'width' => $stroke];
        $filled = ['color' => $color, 'filled' => true];

        switch ($shape) {
            case 'circle':
                $canvas->ellipse($cx, $cy, $r, $r, $filled);
                break;
            case 'ring':
                for ($i = 0; $i < $stroke; $i++) {
                    $canvas->ellipse($cx, $cy, $r - $i, $r - $i, ['color' => $color, 'filled' => false]);
                }
                break;
            case 'square':
                $canvas->rectangle($cx - $r, $cy - $r, $size, $size, $filled);
                break;
            case 'rounded':
                $canvas->rectangle($cx - $r, $cy - $r, $size, $size, $filled + ['radius' => $stroke * 2]);
                break;
            case 'bar':
                $canvas->rectangle($cx - $r, $cy - intdiv($size, 6), $size, max(4, intdiv($size, 3)), $filled);
                break;
            case 'cross':
                $canvas->rectangle($cx - $r, $cy - intdiv($stroke, 2), $size, $stroke, $filled);
                $canvas->rectangle($cx - intdiv($stroke, 2), $cy - $r, $stroke, $size, $filled);
                break;
            case 'x':
                $canvas->line($cx - $r, $cy - $r, $cx + $r, $cy + $r, $line);
                $canvas->line($cx + $r, $cy - $r, $cx - $r, $cy + $r, $line);
                break;
            case 'chevron':
                $canvas->line($cx - $r, $cy - $r, $cx + $r, $cy, $line);
                $canvas->line($cx + $r, $cy, $cx - $r, $cy + $r, $line);
                break;
            case 'semicircle':
                $canvas->filledArc($cx, $cy, $size, $size, 180, 360, ['color' => $color]);
                break;
            case 'wedge':
                $canvas->filledArc($cx, $cy, $size, $size, 40, 140, ['color' => $color]);
                break;
            case 'asterisk':
                for ($i = 0; $i < 3; $i++) {
                    $radian = deg2rad(90 + 60 * $i);
                    $canvas->line(
                        $cx - intval(round($r * cos($radian))),
                        $cy - intval(round($r * sin($radian))),
                        $cx + intval(round($r * cos($radian))),
                        $cy + intval(round($r * sin($radian))),
                        $line
                    );
                }
                break;
            default:
                $canvas->ellipse($cx, $cy, $r, $r, $filled);
        }
    }

    /** 目标之上的补噪点：打断「按颜色阈值分离目标 → 直接读出坐标」的还原路径 */
    private function drawOverlayNoise(ImageDriverInterface $bg, array $targets): void
    {
        foreach ($targets as $target) {
            for ($i = 0; $i < 4; $i++) {
                $bg->ellipse(
                    $target['x'] + random_int(-9, 9),
                    $target['y'] + random_int(-9, 9),
                    random_int(1, 2),
                    random_int(1, 2),
                    ['color' => $this->randomColor() . '55', 'filled' => true]
                );
            }
        }
        // 全图再撒一层：只覆盖目标附近反而等于标出目标位置
        for ($i = 0; $i < 12; $i++) {
            $bg->ellipse(
                random_int(0, max(0, $this->width - 1)),
                random_int(0, max(0, $this->height - 1)),
                1,
                1,
                ['color' => $this->randomColor() . '3C', 'filled' => true]
            );
        }
    }

    /** 随机色相/明度：恒定色会被按颜色分离精确还原坐标 */
    private function randomTargetColor(): string
    {
        $hue = random_int(0, 359) / 60.0;
        $saturation = random_int(65, 100) / 100;
        $value = random_int(45, 75) / 100;
        $sector = intval(floor($hue)) % 6;
        $fraction = $hue - floor($hue);
        $p = $value * (1 - $saturation);
        $q = $value * (1 - $saturation * $fraction);
        $t = $value * (1 - $saturation * (1 - $fraction));

        [$r, $g, $b] = match ($sector) {
            1 => [$q, $value, $p],
            2 => [$p, $value, $t],
            3 => [$p, $q, $value],
            4 => [$t, $p, $value],
            5 => [$value, $p, $q],
            default => [$value, $t, $p],
        };
        return sprintf('#%02X%02X%02X', intval($r * 255), intval($g * 255), intval($b * 255));
    }

    /** 随机透明度（8 位十六进制颜色后缀）：图元支持 alpha，文字不支持，故只用于图标目标 */
    private function randomAlpha(): string
    {
        return sprintf('%02X', random_int(0xB0, 0xF0));
    }
}
