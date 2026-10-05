<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

interface ImageDriverInterface
{
    public function load(string $path): self;

    public function create(int $width, int $height): self;

    public function resize(int $width, int $height): self;

    public function rotate(float $angle, string $bgColor = '#000000'): self;

    public function circle(int $diameter): self;

    public function crop(int $x, int $y, int $width, int $height): self;

    public function text(string $text, int $x, int $y, array $options = []): self;

    public function image(self $overlay, int $x, int $y, array $options = []): self;

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): self;

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): self;

    public function filledArc(int $cx, int $cy, int $w, int $h, int $startAngle, int $endAngle, array $options = []): self;

    /**
     * 填充/描边任意多边形。$points 为 [[x, y], ...] 顶点列表（自动闭合）。
     * $options: color（支持 #RRGGBBAA）、filled（默认 true）。
     */
    public function polygon(array $points, array $options = []): self;

    /**
     * 按掩膜裁剪：掩膜不透明处的原像素保留，透明处置空。掩膜尺寸内逐像素生效，
     * 超出目标画布的部分忽略。掩膜与目标可以是不同驱动（内部转换）。
     */
    public function mask(self $mask): self;

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): self;

    public function blur(int $radius = 1): self;

    public function sharpen(float $amount = 1.0): self;

    public function pixelate(int $blockSize = 3): self;

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool;

    public function output(string $format = 'jpg', int $quality = 90): string;

    public function getSize(): array;

    public function getResource(): mixed;

    public function clone(): self;

    public function destroy(): void;
}
