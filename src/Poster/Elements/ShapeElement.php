<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ShapeElement extends AbstractElement
{
    protected array $resolveKeys = ['color'];

    public function render(ImageDriverInterface $canvas): void
    {
        $shape = $this->options['shape'] ?? 'rect';

        if ($shape === 'circle') {
            $cx = intval($this->options['cx'] ?? $this->options['x'] ?? 0);
            $cy = intval($this->options['cy'] ?? $this->options['y'] ?? 0);
            $radius = $this->circleRadius();
            $canvas->ellipse($cx, $cy, $radius, $radius, $this->options);
        } else {
            $x = intval($this->options['x'] ?? 0);
            $y = intval($this->options['y'] ?? 0);
            $w = $this->positive(intval($this->options['width'] ?? 100), 'width');
            $h = $this->positive(intval($this->options['height'] ?? 100), 'height');
            $canvas->rectangle($x, $y, $w, $h, $this->options);
        }
    }

    /**
     * 半径来源：radius > size > width/2（文档用 width/height 描述圆形外接框）> 默认 50。
     * 显式给出的值 <= 0 交给 positive() 报错，不再默默使用默认半径。
     */
    private function circleRadius(): int
    {
        foreach (['radius', 'size'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return $this->positive(intval($this->options[$key]), $key);
            }
        }
        if (array_key_exists('width', $this->options)) {
            return $this->positive(intdiv(intval($this->options['width']), 2), 'width');
        }
        return 50;
    }
}
