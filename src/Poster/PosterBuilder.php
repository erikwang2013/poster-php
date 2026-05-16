<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Poster\Elements\{
    TextElement, ImageElement, QrcodeElement, AvatarElement,
    ShapeElement, LineElement, WatermarkElement, TableElement
};

class PosterBuilder
{
    private ImageDriverInterface $canvas;
    private int $width;
    private int $height;
    private array $elements = [];
    private ?PosterTemplate $template = null;
    private array $templateVars = [];

    public function __construct(?ImageDriverInterface $driver = null)
    {
        $this->canvas = $driver ?? DriverFactory::create();
    }

    public function width(int $w): static { $this->width = $w; return $this; }
    public function height(int $h): static { $this->height = $h; return $this; }

    public function background(string $colorOrPath): static
    {
        $this->canvas->create($this->width, $this->height);
        if (preg_match('/^#?[0-9a-fA-F]{3,8}$/', $colorOrPath)) {
            $this->canvas->rectangle(0, 0, $this->width, $this->height, ['color' => $colorOrPath, 'filled' => true]);
        } elseif (is_file($colorOrPath)) {
            $bg = DriverFactory::create()->load($colorOrPath);
            $bg->resize($this->width, $this->height);
            $this->canvas->image($bg, 0, 0);
            $bg->destroy();
        }
        return $this;
    }

    public function backgroundGradient(string $color1, string $color2, string $direction = 'vertical'): static
    {
        $this->canvas->create($this->width, $this->height);
        $r1 = hexdec(substr($color1, 1, 2)); $g1 = hexdec(substr($color1, 3, 2)); $b1 = hexdec(substr($color1, 5, 2));
        $r2 = hexdec(substr($color2, 1, 2)); $g2 = hexdec(substr($color2, 3, 2)); $b2 = hexdec(substr($color2, 5, 2));
        $steps = $direction === 'vertical' ? $this->height : $this->width;
        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / max($steps - 1, 1);
            $color = sprintf('#%02X%02X%02X', intval($r1 + ($r2-$r1)*$ratio), intval($g1 + ($g2-$g1)*$ratio), intval($b1 + ($b2-$b1)*$ratio));
            if ($direction === 'vertical') $this->canvas->line(0, $i, $this->width-1, $i, ['color'=>$color]);
            else $this->canvas->line($i, 0, $i, $this->height-1, ['color'=>$color]);
        }
        return $this;
    }

    public function addText(string $text, array $options = []): static { $this->elements[] = new TextElement(array_merge($options, ['text'=>$text])); return $this; }
    public function addImage(string $src, array $options = []): static { $this->elements[] = new ImageElement(array_merge($options, ['src'=>$src])); return $this; }
    public function addQrcode(string $content, array $options = []): static { $this->elements[] = new QrcodeElement(array_merge($options, ['content'=>$content])); return $this; }
    public function addAvatar(string $src, array $options = []): static { $this->elements[] = new AvatarElement(array_merge($options, ['src'=>$src])); return $this; }
    public function addShape(string $shape, array $options = []): static { $this->elements[] = new ShapeElement(array_merge($options, ['shape'=>$shape])); return $this; }
    public function addLine(array $options = []): static { $this->elements[] = new LineElement($options); return $this; }
    public function addWatermark(string $text, array $options = []): static { $this->elements[] = new WatermarkElement(array_merge($options, ['text'=>$text])); return $this; }
    public function addTable(array $options = []): static { $this->elements[] = new TableElement($options); return $this; }
    public function useTemplate(PosterTemplate $template): static { $this->template = $template; return $this; }
    public function with(array $variables): static { $this->templateVars = $variables; return $this; }

    public function save(string $path, int $quality = 90): bool { $this->render(); return $this->canvas->save($path, 'jpg', $quality); }
    public function output(string $format = 'jpg', int $quality = 90): string { $this->render(); return $this->canvas->output($format, $quality); }

    private function render(): void
    {
        if ($this->template !== null) {
            $this->elements = $this->template->build($this->templateVars);
            $this->width = $this->template->getWidth();
            $this->height = $this->template->getHeight();
        }
        if (!isset($this->width)) $this->width = PosterConfig::get('poster.default_width', 750);
        if (!isset($this->height)) $this->height = PosterConfig::get('poster.default_height', 1334);
        foreach ($this->elements as $element) {
            if (method_exists($element, 'resolve')) $element->resolve($this->templateVars);
            $element->render($this->canvas);
        }
    }

    public function destroy(): void { $this->canvas->destroy(); }
}
