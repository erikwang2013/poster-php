<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class AvatarElement extends AbstractElement
{
    protected array $resolveKeys = ['src'];

    /**
     * 头像：缺省圆形裁剪（与文档「圆形裁剪，边框」一致），'circle' => false 得到方图。
     * border 为边框颜色、borderWidth 为边框宽度（默认 2px）。
     */
    public function render(ImageDriverInterface $canvas): void
    {
        $img = $this->loadImage($this->options['src'] ?? '');
        if ($img === null) return;

        $size = $this->positive(intval($this->options['size'] ?? 80), 'size');
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);
        $img->resize($size, $size);

        $circle = (bool) ($this->options['circle'] ?? true);

        $options = $this->options;
        if ($circle) {
            $options['radius'] = intval($size / 2);
        }

        $this->drawBorder($canvas, $x, $y, $size, $circle);

        $canvas->image($img, $x, $y, $options);
        $img->destroy();
    }

    /**
     * 边框：先在底层画一圈比头像大 borderWidth 的实心形状，头像盖上去即留出等宽边框。
     * 圆形头像用圆环（四角保持透明），方图用矩形框。
     */
    private function drawBorder(ImageDriverInterface $canvas, int $x, int $y, int $size, bool $circle): void
    {
        $color = $this->options['border'] ?? null;
        if (!is_string($color) || $color === '') return;

        $width = max(0, intval($this->options['borderWidth'] ?? 2));
        if ($width === 0) return;

        $options = ['color' => $color, 'filled' => true];
        if ($circle) {
            $radius = intval($size / 2);
            $canvas->ellipse($x + $radius, $y + $radius, $radius + $width, $radius + $width, $options);
        } else {
            $canvas->rectangle($x - $width, $y - $width, $size + $width * 2, $size + $width * 2, $options);
        }
    }
}
