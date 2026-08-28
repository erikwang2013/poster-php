<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ChartElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $type  = $this->options['type'] ?? 'bar';
        $data  = $this->options['data'] ?? [];
        $x     = intval($this->options['x'] ?? 0);
        $y     = intval($this->options['y'] ?? 0);
        $w     = intval($this->options['width'] ?? 600);
        $h     = intval($this->options['height'] ?? 400);

        match ($type) {
            'pie'  => $this->drawPie($canvas, $data, $x, $y, $w, $h),
            'line' => $this->drawLineChart($canvas, $data, $x, $y, $w, $h),
            default => $this->drawBar($canvas, $data, $x, $y, $w, $h),
        };
    }

    private function drawBar(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors  = $this->options['colors'] ?? ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'];
        $padding = intval($this->options['padding'] ?? 40);
        $count   = count($data);
        if ($count === 0) return;

        $barW    = intval(($w - $padding * 2) / $count - 10);
        $maxVal  = max(array_map(fn($v) => $this->value($v), $data)) ?: 1;
        $chartH  = $h - $padding * 2;
        $axisY   = $y + $h - $padding;

        // Y-axis
        $canvas->line($x + $padding, $y + $padding, $x + $padding, $axisY, ['color' => '#CCCCCC']);
        // X-axis
        $canvas->line($x + $padding, $axisY, $x + $w - $padding, $axisY, ['color' => '#CCCCCC']);

        for ($i = 0; $i < $count; $i++) {
            $item  = $data[$i];
            $label = $this->label($item);
            $val   = $this->value($item);
            $barH  = intval(($val / $maxVal) * $chartH);
            $bx    = $x + $padding + $i * intval(($w - $padding * 2) / $count) + 5;
            $by    = $axisY - $barH;
            $color = $colors[$i % count($colors)];

            $canvas->rectangle($bx, $by, $barW, $barH, ['color' => $color, 'filled' => true]);

            // Value label
            $canvas->text((string)$val, $bx + intval($barW / 2), $by - 5, [
                'size' => 12, 'color' => '#333333', 'align' => 'center',
            ]);
            // Axis label
            if ($label !== '') {
                $canvas->text($label, $bx + intval($barW / 2), $axisY + 18, [
                    'size' => 11, 'color' => '#666666', 'align' => 'center',
                ]);
            }
        }
    }

    private function drawLineChart(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors  = $this->options['colors'] ?? ['#FF6B6B'];
        $lineColor = $colors[0];
        $padding = intval($this->options['padding'] ?? 40);
        $count   = count($data);
        if ($count < 2) return;

        $maxVal  = max(array_map(fn($v) => $this->value($v), $data)) ?: 1;
        $chartH  = $h - $padding * 2;
        $chartW  = $w - $padding * 2;
        $axisY   = $y + $h - $padding;
        $stepX   = intval($chartW / ($count - 1));

        // Axes
        $canvas->line($x + $padding, $y + $padding, $x + $padding, $axisY, ['color' => '#CCCCCC']);
        $canvas->line($x + $padding, $axisY, $x + $w - $padding, $axisY, ['color' => '#CCCCCC']);

        // Grid lines
        for ($g = 1; $g <= 4; $g++) {
            $gy = $axisY - intval(($g / 4) * $chartH);
            $canvas->line($x + $padding, $gy, $x + $w - $padding, $gy, ['color' => '#EEEEEE']);
        }

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $item = $data[$i];
            $val  = $this->value($item);
            $px   = $x + $padding + $i * $stepX;
            $py   = $axisY - intval(($val / $maxVal) * $chartH);
            $points[] = [$px, $py];

            // Dot
            $canvas->ellipse($px, $py, 4, 4, ['color' => $lineColor, 'filled' => true]);

            $label = $this->label($item);
            if ($label !== '') {
                $canvas->text($label, $px, $axisY + 18, ['size' => 11, 'color' => '#666666', 'align' => 'center']);
            }
        }

        // Connect points
        for ($i = 1; $i < count($points); $i++) {
            $canvas->line($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1], [
                'color' => $lineColor, 'width' => 2,
            ]);
        }
    }

    private function drawPie(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors = $this->options['colors'] ?? ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'];
        $total  = array_sum(array_map(fn($v) => $this->value($v), $data));
        if ($total <= 0) return;

        $cx     = $x + intval($w / 2);
        $cy     = $y + intval($h / 2);
        $radius = intval(min($w, $h) / 2) - 10;
        $start  = -90;

        $assigned = 0;
        $count = count($data);

        foreach ($data as $idx => $item) {
            $val   = $this->value($item);
            $label = $this->label($item);

            if ($idx === $count - 1) {
                $slice = 360 - $assigned;
            } else {
                $slice = intval(round(($val / $total) * 360));
            }
            $assigned += $slice;
            if ($slice <= 0) continue;

            $color = $colors[$idx % count($colors)];
            $canvas->filledArc($cx, $cy, $radius * 2, $radius * 2, $start, $start + $slice, ['color' => $color]);

            $midAng = deg2rad($start + $slice / 2);
            $lx = $cx + intval(cos($midAng) * ($radius + 25));
            $ly = $cy + intval(sin($midAng) * ($radius + 25));
            if ($label !== '') {
                $canvas->text($label, $lx, $ly, ['size' => 10, 'color' => '#333333', 'align' => 'center']);
            }

            $start += $slice;
        }
    }

    private function value(mixed $item): int|float|string
    {
        return is_array($item) ? ($item['value'] ?? 0) : $item;
    }

    private function label(mixed $item): string
    {
        return is_array($item) ? ($item['label'] ?? '') : '';
    }
}
