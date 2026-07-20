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

        $puzzleX = mt_rand(50, $this->width - $this->puzzleWidth - 50);
        $puzzleY = mt_rand(20, $this->height - $this->puzzleHeight - 20);

        // Extract puzzle piece from background (before drawing gap)
        $piece = $bg->clone();
        $piece->crop($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight);

        // Draw gap — dark semi-transparent rectangle, no border
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#00000040',
            'filled' => true,
        ]);

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
                'x'         => $puzzleX,
                'y'         => $puzzleY,
                'puzzle'    => $pzImage,
                'puzzle_w'  => $this->puzzleWidth,
                'puzzle_h'  => $this->puzzleHeight,
            ],
        ];
    }
}
