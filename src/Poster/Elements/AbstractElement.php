<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;

abstract class AbstractElement implements ElementInterface
{
    protected array $options = [];

    /** 需要做占位符替换的选项键（值可为字符串或嵌套数组），子类按需声明 */
    protected array $resolveKeys = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * 导出为模板结构：短类型名 + 拍平 options（不再嵌 options 层），
     * 因此 PosterTemplate::fromConfig([... 'elements' => [$el->toArray()]]) 可原样还原。
     */
    public function toArray(): array
    {
        $options = $this->options;
        unset($options['type']); // 模板定义里的 'type' 是元素类型名，以注册表的短名为准

        return array_merge(['type' => ElementRegistry::typeFor(static::class) ?? static::class], $options);
    }

    public function resolve(array $variables): static
    {
        foreach ($this->resolveKeys as $key) {
            if (array_key_exists($key, $this->options)) {
                $this->options[$key] = $this->resolveValue($this->options[$key], $variables);
            }
        }
        return $this;
    }

    /**
     * 递归替换占位符：字符串走 {{var}} 替换，数组逐项递归（键保持不变），其它类型原样返回。
     * 表格 rows、图表 data 这类嵌套结构因此也能吃到模板变量。
     */
    protected function resolveValue(mixed $value, array $variables): mixed
    {
        if (is_string($value)) {
            return $this->resolvePlaceholders($value, $variables);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolveValue($v, $variables);
            }
            return $value;
        }
        return $value;
    }

    /**
     * 字符串占位符替换；变量名支持 Unicode（含中文），未提供或非标量的变量原样保留。
     * PCRE 失败（如非法 UTF-8）时返回原文。
     */
    protected function resolvePlaceholders(string $text, array $variables): string
    {
        $result = preg_replace_callback('/\{\{\s*([\p{L}\p{N}_]+)\s*\}\}/u', function (array $m) use ($variables) {
            $value = $variables[$m[1]] ?? null;
            return is_scalar($value) ? (string) $value : $m[0];
        }, $text);

        return $result ?? $text;
    }

    /**
     * 尺寸兜底：size/width/height/radius 之类 <= 0 时抛明确异常，
     * 不让非法值落进 GD 变成原生 ValueError（imagecreatetruecolor 等）。
     */
    protected function positive(int $value, string $key): int
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                static::class . ' option "' . $key . '" must be greater than 0, got ' . $value
            );
        }
        return $value;
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
