<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

/**
 * 两个驱动共用的文本切分 + 换行实现（GD 用 imagettfbbox、Imagick 用 queryFontMetrics）。
 */
trait TextTrait
{
    protected function splitText(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    /**
     * token 级测量换行：CJK 逐字、拉丁按词，token 宽度算一次并缓存，断点用累加宽度判断。
     * 旧实现每追加一个 token 就要测一次「整行 + 新 token」，字符串越测越长（O(n²) 字体引擎工作量）。
     *
     * @param callable $measure fn(string $token): ?int token 宽度；null 表示不可渲染（跳过）
     */
    protected function wrapText(string $text, int $maxWidth, callable $measure): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            foreach ($this->wrapParagraph($paragraph, $maxWidth, $measure) as $line) {
                $lines[] = $line;
            }
        }
        return $lines ?: explode("\n", $text);
    }

    /**
     * 单段落换行：先按 token 累加宽度断行，再用整行实测校正行尾。
     * 累加宽度与整行实测会有偏差（字距/连字/逐字取整），校正保证每行实测宽度不超 maxWidth。
     *
     * @return string[]
     */
    private function wrapParagraph(string $paragraph, int $maxWidth, callable $measure): array
    {
        $cache = [];
        $lines = [];
        $current = [];
        $currentWidth = 0;

        foreach ($this->splitText($paragraph) as $token) {
            if ($token === '') {
                continue;
            }
            if (!array_key_exists($token, $cache)) {
                $cache[$token] = $measure($token);
            }
            $tokenWidth = $cache[$token];
            if ($tokenWidth === null) {
                continue;
            }
            if ($current !== [] && $currentWidth + $tokenWidth > $maxWidth) {
                $carry = [];
                $exact = $measure(implode('', $current));
                while (count($current) > 1 && $exact !== null && $exact > $maxWidth) {
                    array_unshift($carry, array_pop($current));
                    $exact = $measure(implode('', $current));
                }
                $lines[] = implode('', $current);
                $current = $carry;
                $currentWidth = 0;
                foreach ($current as $carried) {
                    $currentWidth += $cache[$carried] ?? 0;
                }
            }
            $current[] = $token;
            $currentWidth += $tokenWidth;
        }
        // 收尾：末行没有后续 token 触发断行，同样要按整行实测校正
        while ($current !== []) {
            $carry = [];
            $exact = $measure(implode('', $current));
            while (count($current) > 1 && $exact !== null && $exact > $maxWidth) {
                array_unshift($carry, array_pop($current));
                $exact = $measure(implode('', $current));
            }
            $lines[] = implode('', $current);
            $current = $carry;
        }

        return $lines;
    }
}
