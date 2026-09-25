<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use InvalidArgumentException;

/**
 * 元素类型注册表：模板系统与 Builder 唯一的「类型名 → 元素类」映射点。
 * 新增元素只需往 TYPES 里加一行，PosterTemplate::build() / PosterBuilder::add() 自动可用。
 */
class ElementRegistry
{
    /** 类型名 => 元素类；'artistic-text' 为文档里使用的别名 */
    public const TYPES = [
        'text'          => TextElement::class,
        'image'         => ImageElement::class,
        'qrcode'        => QrcodeElement::class,
        'avatar'        => AvatarElement::class,
        'shape'         => ShapeElement::class,
        'line'          => LineElement::class,
        'watermark'     => WatermarkElement::class,
        'table'         => TableElement::class,
        'chart'         => ChartElement::class,
        'calendar'      => CalendarElement::class,
        'artistictext'  => ArtisticTextElement::class,
        'artistic-text' => ArtisticTextElement::class,
        'emoji'         => EmojiElement::class,
        'icon'          => IconElement::class,
        'emoticon'      => EmoticonElement::class,
    ];

    /** 已知类型名列表（错误提示 / 文档用） */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public static function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** @throws InvalidArgumentException 未知类型 */
    public static function classFor(string $type): string
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException(
                'Unknown element type "' . $type . '". Known types: ' . implode(', ', self::types())
            );
        }
        return self::TYPES[$type];
    }

    /** @throws InvalidArgumentException 未知类型 */
    public static function create(string $type, array $options = []): ElementInterface
    {
        $class = self::classFor($type);
        return new $class($options);
    }

    /** 元素类 → 短类型名（toArray() 用）；未注册返回 null */
    public static function typeFor(string $class): ?string
    {
        return array_search($class, self::TYPES, true) ?: null;
    }
}
