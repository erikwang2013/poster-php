<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

abstract class AbstractCaptcha implements CaptchaInterface
{
    protected ImageDriverInterface $imageDriver;
    protected StorageInterface $storage;
    protected string $difficulty = 'medium';
    protected ?string $backgroundPath = null;
    protected string $key = '';
    protected int $width = 300;
    protected int $height = 200;

    public function __construct(ImageDriverInterface $imageDriver, StorageInterface $storage)
    {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function setBackground(?string $imagePath): static
    {
        $this->backgroundPath = $imagePath;
        return $this;
    }

    protected function createBackground(): ImageDriverInterface
    {
        $bg = $this->imageDriver->clone();

        if ($this->backgroundPath !== null && is_file($this->backgroundPath)) {
            $bg->load($this->backgroundPath);
            $size = $bg->getSize();
            $this->width = $size['width'];
            $this->height = $size['height'];
            return $bg;
        }

        $bgDir = PosterConfig::get('captcha.background_dir');
        if ($bgDir && is_dir($bgDir)) {
            $files = glob($bgDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            if (!empty($files)) {
                $bg->load($files[array_rand($files)]);
                $size = $bg->getSize();
                $this->width = $size['width'];
                $this->height = $size['height'];
                return $bg;
            }
        }

        $styles = PosterConfig::get('captcha.background_styles', ['minimal', 'vibrant', 'natural']);
        $style = $styles[array_rand($styles)];
        $bg->create($this->width, $this->height);
        $this->generateProceduralBackground($bg, $style);

        return $bg;
    }

    private function generateProceduralBackground(ImageDriverInterface $bg, string $style): void
    {
        $this->generateGradient($bg, $style);
        $this->generateDecorations($bg, $style);
        $this->generateNoise($bg, $style);
    }

    private function palettesForStyle(string $style): array
    {
        return match ($style) {
            'minimal' => [
                ['#e8eaf6', '#c5cae9'],
                ['#e0f2f1', '#b2dfdb'],
                ['#f3e5f5', '#e1bee7'],
                ['#eceff1', '#cfd8dc'],
                ['#e8f5e9', '#c8e6c9'],
            ],
            'vibrant' => [
                ['#667eea', '#764ba2'],
                ['#f093fb', '#f5576c'],
                ['#4facfe', '#00f2fe'],
                ['#43e97b', '#38f9d7'],
                ['#fa709a', '#fee140'],
                ['#a18cd1', '#fbc2eb'],
            ],
            'natural' => [
                ['#f5f0e8', '#e8dcc8'],
                ['#faf0e6', '#f5deb3'],
                ['#f0ebe3', '#d9cdb3'],
                ['#fef9ef', '#f5e6c8'],
                ['#f7f2e9', '#e6d5c3'],
            ],
        };
    }

    private function generateGradient(ImageDriverInterface $bg, string $style): void
    {
        $palettes = $this->palettesForStyle($style);
        $palette = $palettes[array_rand($palettes)];

        $steps = 60;
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / ($steps - 1);
            $color = $this->interpolateColor($palette[0], $palette[1], $t);
            $y = intval($i * $this->height / $steps);
            $nextY = intval(($i + 1) * $this->height / $steps);
            $h = $nextY - $y;
            $bg->rectangle(0, $y, $this->width, $h, ['color' => $color, 'filled' => true]);
        }
    }

    private function interpolateColor(string $c1, string $c2, float $t): string
    {
        $r1 = hexdec(substr($c1, 1, 2));
        $g1 = hexdec(substr($c1, 3, 2));
        $b1 = hexdec(substr($c1, 5, 2));
        $r2 = hexdec(substr($c2, 1, 2));
        $g2 = hexdec(substr($c2, 3, 2));
        $b2 = hexdec(substr($c2, 5, 2));
        return sprintf('#%02X%02X%02X',
            intval($r1 + ($r2 - $r1) * $t),
            intval($g1 + ($g2 - $g1) * $t),
            intval($b1 + ($b2 - $b1) * $t)
        );
    }

    private function generateDecorations(ImageDriverInterface $bg, string $style): void
    {
        match ($style) {
            'minimal' => $this->decorateMinimal($bg),
            'vibrant' => $this->decorateVibrant($bg),
            'natural' => $this->decorateNatural($bg),
        };
    }

    private function decorateMinimal(ImageDriverInterface $bg): void
    {
        for ($i = 0; $i < mt_rand(2, 3); $i++) {
            $x = mt_rand(-40, $this->width + 40);
            $y = mt_rand(-40, $this->height + 40);
            $r = mt_rand(60, 140);
            $bg->ellipse($x, $y, $r, $r, ['color' => '#FFFFFF66', 'filled' => true]);
        }
        for ($i = 0; $i < mt_rand(1, 2); $i++) {
            $x1 = mt_rand(0, $this->width);
            $y1 = mt_rand(0, $this->height);
            $x2 = $x1 + mt_rand(-120, 120);
            $y2 = $y1 + mt_rand(-80, 80);
            $bg->line($x1, $y1, $x2, $y2, ['color' => '#FFFFFF55', 'width' => mt_rand(2, 4)]);
        }
    }

    private function decorateVibrant(ImageDriverInterface $bg): void
    {
        for ($i = 0; $i < mt_rand(10, 18); $i++) {
            $x = mt_rand(0, $this->width);
            $y = mt_rand(0, $this->height);
            $r = mt_rand(10, 70);
            $color = $this->randomColor();
            $bg->ellipse($x, $y, $r, $r, ['color' => $color . '2A', 'filled' => true]);
        }
        for ($i = 0; $i < mt_rand(3, 6); $i++) {
            $x = mt_rand(0, $this->width);
            $y = mt_rand(0, $this->height);
            $r = mt_rand(15, 45);
            $color = $this->randomColor();
            $bg->ellipse($x, $y, $r, $r, ['color' => $color . '55', 'filled' => false]);
        }
    }

    private function decorateNatural(ImageDriverInterface $bg): void
    {
        for ($i = 0; $i < mt_rand(6, 12); $i++) {
            $x = mt_rand(0, $this->width - 50);
            $y = mt_rand(0, $this->height - 30);
            $w = mt_rand(30, 90);
            $h = mt_rand(15, 45);
            $color = $this->randomLightColor();
            $bg->rectangle($x, $y, $w, $h, ['color' => $color . '2E', 'filled' => true]);
        }
    }

    private function generateNoise(ImageDriverInterface $bg, string $style): void
    {
        $count = match ($style) {
            'minimal' => mt_rand(20, 40),
            'vibrant' => mt_rand(50, 90),
            'natural' => mt_rand(100, 180),
        };
        $dotSize = match ($style) {
            'minimal' => 1,
            'vibrant' => mt_rand(1, 2),
            'natural' => 1,
        };
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand(0, $this->width - 1);
            $y = mt_rand(0, $this->height - 1);
            $color = $this->randomColor();
            $bg->ellipse($x, $y, $dotSize, $dotSize, ['color' => $color . '1E', 'filled' => true]);
        }
    }

    protected function generateKey(): string
    {
        $this->key = bin2hex(random_bytes(16));
        return $this->key;
    }

    protected function randomColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
    }

    protected function randomLightColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
    }

    protected function store(array $answerData): void
    {
        $this->storage->set($this->key, array_merge($answerData, [
            'type'       => $this->getType(),
            'attempts'   => 0,
            'created_at' => time(),
        ]), PosterConfig::get('captcha.ttl', 300));
    }

    abstract protected function getType(): string;
}
