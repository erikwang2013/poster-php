<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

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

        // 小背景图时 min 可能 > max，钳制到合法区间
        $xMin = 50;
        $xMax = max($xMin, $this->width - $this->puzzleWidth - $xMin);
        $yMin = 20;
        $yMax = max($yMin, $this->height - $this->puzzleHeight - $yMin);
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
