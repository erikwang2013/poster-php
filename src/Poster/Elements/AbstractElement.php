<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

abstract class AbstractElement implements ElementInterface
{
    protected array $options = [];

    /** 需要做占位符替换的选项键，子类按需声明 */
    protected array $resolveKeys = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return ['type' => static::class, 'options' => $this->options];
    }

    public function resolve(array $variables): static
    {
        foreach ($this->resolveKeys as $key) {
            if (isset($this->options[$key])) {
                $this->options[$key] = $this->resolvePlaceholders($this->options[$key], $variables);
            }
        }
        return $this;
    }

    protected function resolvePlaceholders(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($variables) {
            return $variables[$m[1]] ?? $m[0];
        }, $text);
    }
}
