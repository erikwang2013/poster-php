<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\PosterConfig;


class ClickCaptcha extends AbstractCaptcha
{
    private string $targetType = 'text';
    private ?array $words = null;
    protected int $targetCount;

    public function setWords(array $words): static
    {
        $this->words = $words;
        return $this;
    }

    public function setTargetType(string $type): static
    {
        $this->targetType = $type;
        return $this;
    }

    protected function getType(): string
    {
        return 'click';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();
        if ($this->difficulty === 'easy') {
            $this->targetCount = 2;
        } elseif ($this->difficulty === 'hard') {
            $this->targetCount = 4;
        } else {
            $this->targetCount = 3;
        }

        $targets = $this->placeTargets();
        $fontFile = PosterConfig::get('image.font')
            ?? dirname(__DIR__, 2) . '/src/fonts/Alibaba-PuHuiTi-Regular.ttf';

        foreach ($targets as $target) {
            $color = '#FF4444';
            $bg->text($target['text'], $target['x'], $target['y'] + 6, [
                'size' => 16,
                'color' => $color,
                'font' => $fontFile,
                'align' => 'center',
            ]);
        }

        $this->store(['targets' => $targets]);
        $image = $bg->output('png');
        $bg->destroy();
        return [
            'key'   => $this->key,
            'type'  => 'click',
            'image' => $image,
            'extra' => [
                'texts' => array_map(fn($t) => ['text' => $t['text'], 'order' => $t['order']], $targets),
            ],
        ];
    }

    private function placeTargets(): array
    {
        $targets = [];
        $margin = 40;
        // ?: 而非 ??：setWords([]) 时空数组会走到兜底，避免 count(0) 取模除零
        $words = $this->words
            ?: PosterConfig::get('captcha.click_words')
            ?: match ($this->difficulty) {
                'easy' => ['云', '风'],
                'hard' => ['星', '雨', '山', '火'],
                default => ['云', '风', '山'],
            };
        for ($i = 0; $i < $this->targetCount; $i++) {
            $xMin = max(1, $margin);
            $xMax = max($xMin + 1, $this->width - $margin);
            $yMin = max(1, $margin);
            $yMax = max($yMin + 1, $this->height - $margin - 40);
            $x = random_int($xMin, $xMax);
            $y = random_int($yMin, $yMax);
            $word = $words[$i % count($words)];
            $targets[] = ['x' => $x, 'y' => $y, 'text' => $word, 'order' => $i + 1];
        }
        return $targets;
    }
}
