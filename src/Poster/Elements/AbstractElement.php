<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;

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

    /**
     * 文本字体：未显式指定 font 选项时用配置的 poster.font，显式传 null 则退回驱动内置位图字体。
     * 自行拼装 text() 选项的元素（表格 / 日历 / 二维码文案 / 艺术字）应走这里，
     * 否则中英文会落到 GD 内置位图字体而渲染错乱。
     */
    protected function font(): ?string
    {
        return array_key_exists('font', $this->options)
            ? $this->options['font']
            : PosterConfig::get('poster.font');
    }

    /**
     * 加载图片文件；文件缺失时回退到 poster.placeholder 配置的占位图
     * （留空即保持原行为：跳过缺失图片）。占位图也不存在时返回 null。
     */
    protected function loadImage(string $src): ?ImageDriverInterface
    {
        if (!is_file($src)) {
            $src = (string) PosterConfig::get('poster.placeholder', '');
        }

        return is_file($src) ? DriverFactory::create()->load($src) : null;
    }
}
