<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster;

use Erikwang2013\Poster\Poster\Elements\ElementRegistry;

class PosterTemplate
{
    private int $width;
    private int $height;
    private array $elementDefs = [];

    public function __construct(int $width, int $height, array $elements = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->elementDefs = $elements;
    }

    public static function fromConfig(array $config): self
    {
        return new self($config['width'] ?? 750, $config['height'] ?? 1334, $config['elements'] ?? []);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        // 解析失败必须报错：静默回退默认模板会渲染出一张空白海报
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'Invalid JSON: expected an object, got ' . get_debug_type($data)
            );
        }
        return self::fromConfig($data);
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    /**
     * 构建元素实例（类型映射查 ElementRegistry，未知 type 直接抛错而不是静默丢弃）。
     *
     * @param array $variables 占位符变量
     * @param array $existing  追加模式：已有元素排在前（见 PosterBuilder::replaceElements()）
     * @throws \InvalidArgumentException 未知 type / type 缺失
     */
    public function build(array $variables = [], array $existing = []): array
    {
        $elements = $existing;
        foreach ($this->elementDefs as $i => $def) {
            if (!is_array($def) || !isset($def['type']) || !is_string($def['type']) || $def['type'] === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Template element #%d is missing a valid "type" key. Known types: %s',
                    $i, implode(', ', ElementRegistry::types())
                ));
            }
            $element = ElementRegistry::create($def['type'], $def);
            if (method_exists($element, 'resolve')) $element->resolve($variables);
            $elements[] = $element;
        }
        return $elements;
    }

    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height, 'elements' => $this->elementDefs];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
