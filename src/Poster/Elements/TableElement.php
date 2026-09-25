<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class TableElement extends AbstractElement
{
    /** rows 为二维数组，递归替换到每个单元格 */
    protected array $resolveKeys = ['header', 'headers', 'rows'];

    public function render(ImageDriverInterface $canvas): void
    {
        // 选项键同时接受文档中的 camelCase（header / columns / headerBg …）
        // 与历史 snake_case（headers / col_widths / header_bg …）
        $headers = $this->options['header'] ?? $this->options['headers'] ?? [];
        $rows = $this->options['rows'] ?? [];
        if (empty($headers) || empty($rows)) return;

        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);
        $colWidths = $this->options['columns'] ?? $this->options['col_widths'] ?? [];
        $headerHeight = intval($this->options['headerHeight'] ?? $this->options['header_height'] ?? 40);
        $rowHeight = intval($this->options['rowHeight'] ?? $this->options['row_height'] ?? 35);
        $pad = intval($this->options['cellPadding'] ?? $this->options['cell_padding'] ?? 10);
        $headerBg = $this->options['headerBg'] ?? $this->options['header_bg'] ?? '#F5F5F5';
        $rowBg = $this->options['rowBg'] ?? [];   // 文档写法：['#FFFFFF', '#F5F5F5'] 对应第一行 / 第二行
        $evenBg = $rowBg[0] ?? $this->options['even_bg'] ?? '#FAFAFA';
        $oddBg = $rowBg[1] ?? $this->options['odd_bg'] ?? '#FFFFFF';
        $fontSize = intval($this->options['fontSize'] ?? $this->options['font_size'] ?? 14);
        $font = $this->font();
        $headerColor = $this->options['headerColor'] ?? $this->options['header_color'] ?? '#333333';
        $rowColor = $this->options['rowColor'] ?? $this->options['row_color'] ?? '#666666';
        $borderColor = $this->options['borderColor'] ?? $this->options['border_color'] ?? '#EEEEEE';
        $alignments = $this->options['alignments'] ?? [];

        if (empty($colWidths)) {
            $colW = intval(($this->options['width'] ?? 600) / count($headers));
            $colWidths = array_fill(0, count($headers), $colW);
        }

        $totalWidth = array_sum($colWidths);

        // Calculate column X positions
        $colXs = [$x];
        foreach ($colWidths as $i => $w) {
            $colXs[$i + 1] = $colXs[$i] + $w;
        }

        // Draw header background
        $canvas->rectangle($x, $y, $totalWidth, $headerHeight, ['color' => $headerBg, 'filled' => true]);

        // Draw header text
        foreach ($headers as $i => $header) {
            $align = $alignments[$i] ?? 'left';
            $cx = match ($align) {
                'center' => $colXs[$i] + intval($colWidths[$i] / 2),
                'right'  => $colXs[$i] + $colWidths[$i] - $pad,
                default  => $colXs[$i] + $pad,
            };
            $canvas->text((string)$header, $cx, $this->baseline($y, $headerHeight, $fontSize), [
                'size' => $fontSize, 'color' => $headerColor, 'font' => $font, 'align' => $align,
            ]);
        }

        // Draw rows with zebra stripes
        $currentY = $y + $headerHeight;
        foreach ($rows as $ri => $row) {
            $bg = $ri % 2 === 0 ? $evenBg : $oddBg;
            $canvas->rectangle($x, $currentY, $totalWidth, $rowHeight, ['color' => $bg, 'filled' => true]);

            foreach ($row as $ci => $cell) {
                if (!isset($colWidths[$ci])) continue;
                $align = $alignments[$ci] ?? 'left';
                $cx = match ($align) {
                    'center' => $colXs[$ci] + intval($colWidths[$ci] / 2),
                    'right'  => $colXs[$ci] + $colWidths[$ci] - $pad,
                    default  => $colXs[$ci] + $pad,
                };
                $canvas->text((string)$cell, $cx, $this->baseline($currentY, $rowHeight, $fontSize), [
                    'size' => $fontSize, 'color' => $rowColor, 'font' => $font, 'align' => $align,
                ]);
            }

            // Draw row bottom border
            $canvas->line($x, $currentY + $rowHeight - 1, $x + $totalWidth - 1, $currentY + $rowHeight - 1, ['color' => $borderColor]);

            $currentY += $rowHeight;
        }
    }

    /** 文本基线：imagettftext 按基线绘制，需下移半个字高才是视觉居中 */
    private function baseline(int $top, int $height, int $fontSize): int
    {
        return $top + intval(($height + $fontSize * 0.72) / 2);
    }
}
