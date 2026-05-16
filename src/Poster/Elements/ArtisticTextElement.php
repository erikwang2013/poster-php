<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ArtisticTextElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text  = $this->options['text'] ?? $this->options['content'] ?? '';
        $x     = intval($this->options['x'] ?? 0);
        $y     = intval($this->options['y'] ?? 0);
        $size  = intval($this->options['size'] ?? 48);
        $font  = $this->options['font'] ?? null;
        $style = $this->options['style'] ?? 'stroke';

        $angle    = intval($this->options['angle'] ?? 0);
        $color    = $this->options['color'] ?? '#333333';
        $maxWidth = intval($this->options['maxWidth'] ?? 0);
        $align    = $this->options['align'] ?? 'left';

        $baseOpts = [
            'font'   => $font,
            'angle'  => $angle,
            'align'  => $align,
            'maxWidth' => $maxWidth,
        ];

        switch ($style) {
            case 'stroke':
                // Text outline/stroke effect
                $strokeColor = $this->options['strokeColor'] ?? '#000000';
                $strokeWidth = intval($this->options['strokeWidth'] ?? 1);
                for ($ox = -$strokeWidth; $ox <= $strokeWidth; $ox++) {
                    for ($oy = -$strokeWidth; $oy <= $strokeWidth; $oy++) {
                        if ($ox === 0 && $oy === 0) continue;
                        $canvas->text($text, $x + $ox, $y + $oy, $baseOpts + [
                            'size' => $size, 'color' => $strokeColor,
                        ]);
                    }
                }
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                break;

            case 'shadow':
                // Drop shadow effect
                $shadowColor   = $this->options['shadowColor'] ?? '#00000033';
                $shadowOffsetX = intval($this->options['shadowOffsetX'] ?? 3);
                $shadowOffsetY = intval($this->options['shadowOffsetY'] ?? 3);
                $canvas->text($text, $x + $shadowOffsetX, $y + $shadowOffsetY, $baseOpts + [
                    'size' => $size, 'color' => $shadowColor,
                ]);
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                break;

            case 'gradient':
                // Vertical gradient (top color to bottom color)
                $color2  = $this->options['color2'] ?? '#FF6B6B';
                $r1 = hexdec(substr($color, 1, 2)); $g1 = hexdec(substr($color, 3, 2)); $b1 = hexdec(substr($color, 5, 2));
                $r2 = hexdec(substr($color2, 1, 2)); $g2 = hexdec(substr($color2, 3, 2)); $b2 = hexdec(substr($color2, 5, 2));
                $steps = 8;
                for ($i = 0; $i < $steps; $i++) {
                    $ratio = $i / ($steps - 1);
                    $c = sprintf('#%02X%02X%02X', intval($r1 + ($r2-$r1)*$ratio), intval($g1 + ($g2-$g1)*$ratio), intval($b1 + ($b2-$b1)*$ratio));
                    $canvas->text($text, $x, $y + $i, $baseOpts + ['size' => $size, 'color' => $c]);
                }
                break;

            case 'neon':
                // Neon glow effect (multiple blurred layers)
                $glowColor = $this->options['glowColor'] ?? $color;
                for ($i = 3; $i >= 1; $i--) {
                    $alpha = dechex(20 * $i);
                    $canvas->text($text, $x, $y, $baseOpts + [
                        'size' => $size + $i * 2, 'color' => $glowColor . $alpha,
                    ]);
                }
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => '#FFFFFF']);
                break;

            default:
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
        }
    }

    public function resolve(array $variables): static
    {
        $key = isset($this->options['text']) ? 'text' : 'content';
        if (isset($this->options[$key])) {
            $this->options[$key] = $this->resolvePlaceholders($this->options[$key], $variables);
        }
        return $this;
    }
}
