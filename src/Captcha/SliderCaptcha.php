<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;

class SliderCaptcha extends AbstractCaptcha
{
    private int $puzzleWidth = 50;
    private int $puzzleHeight = 50;
    private ?string $shape = null;

    protected function getType(): string
    {
        return 'slider';
    }

    /**
     * 拼图形状：'square'（矩形缺口）| 'jigsaw'（凹凸拼图）。
     * 未显式设置时取配置 captcha.slider_shape（默认 square）。
     */
    public function setShape(string $shape): static
    {
        $this->shape = $shape;
        return $this;
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        $shape = $this->shape ?? PosterConfig::get('captcha.slider_shape', 'square');
        if (!in_array($shape, ['square', 'jigsaw'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown slider shape "%s"; supported: square, jigsaw',
                $shape
            ));
        }

        if ($this->difficulty === 'hard') {
            $this->puzzleWidth = 40;
            $this->puzzleHeight = 40;
        }

        // 画布过小时缺口会被钳到唯一位置（100×100 背景上 x 恒为 50），盲提交固定值即可通过：
        // 明确拒绝，不做静默退化。要求见下方 min 尺寸。
        $minWidth = 4 * $this->puzzleWidth;
        $minHeight = 2 * $this->puzzleHeight;
        if ($this->width < $minWidth || $this->height < $minHeight) {
            throw new InvalidArgumentException(sprintf(
                'Canvas %dx%d is too small for slider captcha: need at least %dx%d (puzzle %dx%d) so the gap position stays unpredictable',
                $this->width,
                $this->height,
                $minWidth,
                $minHeight,
                $this->puzzleWidth,
                $this->puzzleHeight
            ));
        }

        // 边距随画布缩放；默认 300×200 + 50×50 拼图下等价于旧的固定值 xMin=50 / yMin=20
        $padX = max($this->puzzleWidth, intdiv($this->width, 6));
        $padY = max(intdiv($this->puzzleHeight, 3), intdiv($this->height, 10));
        $xMin = $padX;
        $xMax = max($xMin, $this->width - $this->puzzleWidth - $padX);
        $yMin = $padY;
        $yMax = max($yMin, $this->height - $this->puzzleHeight - $padY);
        $puzzleX = random_int($xMin, $xMax);
        $puzzleY = random_int($yMin, $yMax);

        // 凸出/凹陷半径；缺口外接矩形比本体每边各多 $knob（square 时为 0）。
        // 放置边距恒大于 $knob（padX ≥ 拼图宽、padY ≥ 拼图高/3，均 > 短边/5），凸出不会越出画布
        $knob = $shape === 'jigsaw' ? max(4, intdiv(min($this->puzzleWidth, $this->puzzleHeight), 5)) : 0;

        // Extract puzzle piece from background (before drawing gap)
        $piece = $bg->clone();
        if ($shape === 'jigsaw') {
            // 四边凸/凹随机，破解方无法假设固定轮廓
            $points = $this->jigsawPoints($this->puzzleWidth, $this->puzzleHeight, $knob, [
                random_int(0, 1) === 1,
                random_int(0, 1) === 1,
                random_int(0, 1) === 1,
                random_int(0, 1) === 1,
            ]);

            // 拼图块：裁外接矩形 → 轮廓掩膜裁掉矩形外的部分
            $bw = $this->puzzleWidth + 2 * $knob;
            $bh = $this->puzzleHeight + 2 * $knob;
            $piece->crop($puzzleX - $knob, $puzzleY - $knob, $bw, $bh);
            $mask = $this->imageDriver->clone()->create($bw, $bh);
            $mask->polygon($points, ['color' => '#FFFFFF']);
            $piece->mask($mask);
            $mask->destroy();

            // 缺口：同一轮廓平移到画布坐标（局部坐标下本体左上角在 ($knob, $knob)）
            $bg->polygon(array_map(
                fn (array $p) => [$p[0] + $puzzleX - $knob, $p[1] + $puzzleY - $knob],
                $points
            ), ['color' => '#00000040']);
        } else {
            $piece->crop($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight);

            // Draw gap — dark semi-transparent rectangle, no border
            $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
                'color'  => '#00000040',
                'filled' => true,
            ]);
        }

        // 混淆：全图撒同色同尺寸噪点块，让"找最暗区域"的扫描无法唯一确定 gap 位置
        for ($i = 0; $i < 30; $i++) {
            $bg->ellipse(
                random_int(0, $this->width - 1),
                random_int(0, $this->height - 1),
                random_int(intval($this->puzzleWidth / 3), intval($this->puzzleWidth / 2)),
                random_int(intval($this->puzzleHeight / 3), intval($this->puzzleHeight / 2)),
                ['color' => '#00000040', 'filled' => true]
            );
        }

        // jigsaw 下存的 x/y 是拼图块 PNG（含外扩）的左上角：前端把整块 PNG 放到 (x, y) 即与缺口对齐
        $this->store(['x' => $puzzleX - $knob, 'y' => $puzzleY - $knob]);

        $bgImage = $bg->output('png');
        $pzImage = $piece->output('png');

        $bg->destroy();
        $piece->destroy();

        return [
            'key'   => $this->key,
            'type'  => 'slider',
            'image' => $bgImage,
            'extra' => [
                'puzzle'    => $pzImage,
                'puzzle_w'  => $this->puzzleWidth,
                'puzzle_h'  => $this->puzzleHeight,
            ],
        ];
    }

    /**
     * 凹凸拼图轮廓（局部坐标：外接矩形左上角为 (0,0)，本体位于 (k,k)-(k+w,k+h)）。
     * $tabs: [上, 右, 下, 左]，true = 外凸半圆，false = 内凹半圆。
     * 每边中点一个半径 $k 的半圆；$k = 短边/5 < 边长/2，轮廓闭合且不自交。
     */
    private function jigsawPoints(int $w, int $h, int $k, array $tabs): array
    {
        // [起点, 边中点, 沿边方向, 外法线]，顺时针绕行（图像坐标 y 向下）
        $sides = [
            [[$k, $k],           [$k + $w / 2, $k],         [1, 0],  [0, -1]],
            [[$k + $w, $k],      [$k + $w, $k + $h / 2],    [0, 1],  [1, 0]],
            [[$k + $w, $k + $h], [$k + $w / 2, $k + $h],    [-1, 0], [0, 1]],
            [[$k, $k + $h],      [$k, $k + $h / 2],         [0, -1], [-1, 0]],
        ];

        $points = [];
        $segments = 12;
        foreach ($sides as $i => [$start, $mid, $dir, $normal]) {
            $points[] = $start;
            // t 从 0 到 π：沿 $dir 进入半圆、绕到离开；凸取外法线、凹取反向
            $sign = !empty($tabs[$i]) ? 1 : -1;
            for ($s = 0; $s <= $segments; $s++) {
                $t = M_PI * $s / $segments;
                $points[] = [
                    $mid[0] - $dir[0] * $k * cos($t) + $normal[0] * $sign * $k * sin($t),
                    $mid[1] - $dir[1] * $k * cos($t) + $normal[1] * $sign * $k * sin($t),
                ];
            }
        }
        return $points;
    }
}
