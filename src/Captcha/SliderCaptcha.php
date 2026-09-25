<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use InvalidArgumentException;

class SliderCaptcha extends AbstractCaptcha
{
    private int $puzzleWidth = 50;
    private int $puzzleHeight = 50;

    protected function getType(): string
    {
        return 'slider';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

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

        // Extract puzzle piece from background (before drawing gap)
        $piece = $bg->clone();
        $piece->crop($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight);

        // Draw gap — dark semi-transparent rectangle, no border
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#00000040',
            'filled' => true,
        ]);

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

        $this->store(['x' => $puzzleX, 'y' => $puzzleY]);

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
}
