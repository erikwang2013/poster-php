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

        // Draw gap on background — rounded rectangle
        $gapRadius = 6;
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#00000018',
            'filled' => true,
            'radius' => $gapRadius,
        ]);
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => '#00000040',
            'filled'      => false,
            'radius'      => $gapRadius,
            'strokeWidth' => 2,
        ]);

        // Create puzzle piece — with shadow and rounded corners
        $pad = 8;
        $pieceW = $this->puzzleWidth + $pad;
        $pieceH = $this->puzzleHeight + $pad;
        $piece = $this->imageDriver->clone();
        $piece->create($pieceW, $pieceH);

        // Shadow
        $piece->rectangle($pad, $pad, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#00000022',
            'filled' => true,
            'radius' => $gapRadius,
        ]);

        // Piece body
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#FFFFFFF0',
            'filled' => true,
            'radius' => $gapRadius,
        ]);

        // Piece border
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => '#888888',
            'filled'      => false,
            'radius'      => $gapRadius,
            'strokeWidth' => 2,
        ]);

        // Direction arrow
        $cx = intval($this->puzzleWidth / 2);
        $cy = intval($this->puzzleHeight / 2);
        $fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';
        $piece->text('»', $cx, $cy + 6, [
            'size'  => 18,
            'color' => '#999999',
            'font'  => is_file($fontFile) ? $fontFile : null,
            'align' => 'center',
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
                'puzzle'    => $pzImage,
                'puzzle_w'  => $this->puzzleWidth,
                'puzzle_h'  => $this->puzzleHeight,
            ],
        ];
    }
}
