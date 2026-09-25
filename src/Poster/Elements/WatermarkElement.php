<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use InvalidArgumentException;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class WatermarkElement extends AbstractElement
{
    protected array $resolveKeys = ['text'];

    public function render(ImageDriverInterface $canvas): void
    {
        $text = $this->options['text'] ?? '';
        if (empty($text)) return;

        $canvasSize = $canvas->getSize();
        if (empty($canvasSize['width']) || empty($canvasSize['height'])) return;

        // spacing <= 0 会让两层 for 的步进为 0 而永久循环（曾可被模板 JSON 触发打满 CPU），故直接报错。
        $spacingX = $this->positive(
            intval($this->options['spacing_x'] ?? $this->options['spacing'] ?? 150), 'spacing_x'
        );
        $spacingY = $this->positive(
            intval($this->options['spacing_y'] ?? $this->options['spacing'] ?? 100), 'spacing_y'
        );

        // 再挡一层：间距过小会在画布上铺出天量文本（750×1334 配 spacing=1 是 100 万次绘制），
        // 同样是可远程触发的 CPU 打满，故给瓦片数设上限。
        $tiles = (int) ceil($canvasSize['width'] / $spacingX) * (int) ceil($canvasSize['height'] / $spacingY);
        if ($tiles > 20000) {
            throw new InvalidArgumentException(sprintf(
                'Watermark spacing too small: %dx%d would draw %d tiles on a %dx%d canvas (max 20000)',
                $spacingX, $spacingY, $tiles, $canvasSize['width'], $canvasSize['height']
            ));
        }

        $angle = floatval($this->options['angle'] ?? -30);

        $textOptions = $this->options;
        $textOptions['angle'] = $angle;

        for ($x = 0; $x < $canvasSize['width']; $x += $spacingX) {
            for ($y = 0; $y < $canvasSize['height']; $y += $spacingY) {
                $canvas->text($text, $x, $y, $textOptions);
            }
        }
    }

}
