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

    protected function getType(): string { return 'click'; }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        if ($this->difficulty === 'easy') {
            $this->targetCount = 2;
        } elseif ($this->difficulty === 'hard') {
            $this->targetCount = 4;
        }

        $targets = $this->placeTargets();
        $fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';
        if (!is_file($fontFile)) {
            $fontFile = '/usr/share/fonts/fonts-gb/GB_ST_GB18030.ttf';
        }

        foreach ($targets as $target) {
            $color = '#FF4444';
            $bg->text($target['text'], $target['x'], $target['y'] + 6, [
                'size' => 16, 'color' => $color,
                'font' => $fontFile, 'align' => 'center',
            ]);
        }

        $this->store(['targets' => $targets]);
        $image = $bg->output('png');
        $bg->destroy();
        return ['key' => $this->key, 'type' => 'click', 'image' => $image, 'extra' => ['targets' => $targets]];
    }

    private function placeTargets(): array
    {
        $targets = [];
        $margin = 40;
        $words = $this->words
            ?? PosterConfig::get('captcha.click_words')
            ?? match ($this->difficulty) {
                'easy' => ['云', '风'],
                'hard' => ['星', '雨', '山', '火'],
                default => ['云', '风', '山'],
            };
        for ($i = 0; $i < $this->targetCount; $i++) {
            $x = mt_rand($margin, $this->width - $margin);
            $y = mt_rand($margin, $this->height - $margin - 40);
            $word = $words[$i % count($words)];
            $targets[] = ['x' => $x, 'y' => $y, 'text' => $word, 'order' => $i + 1];
        }
        return $targets;
    }
}
