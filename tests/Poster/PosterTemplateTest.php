<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Poster\Elements\ArtisticTextElement;
use Erikwang2013\Poster\Poster\Elements\ChartElement;
use Erikwang2013\Poster\Poster\Elements\EmojiElement;
use Erikwang2013\Poster\Poster\Elements\TextElement;
use Erikwang2013\Poster\Poster\PosterTemplate;
use PHPUnit\Framework\TestCase;

class PosterTemplateTest extends TestCase
{
    /** 验证构造函数设置宽高、getter 原样返回 */
    public function testConstructorAndGetters(): void
    {
        $t = new PosterTemplate(400, 600);
        $this->assertSame(400, $t->getWidth());
        $this->assertSame(600, $t->getHeight());
    }

    /** 验证 fromConfig 空数组时宽高回落到默认值 750/1334 */
    public function testFromConfigDefaults(): void
    {
        $t = PosterTemplate::fromConfig([]);
        $this->assertSame(750, $t->getWidth());
        $this->assertSame(1334, $t->getHeight());
    }

    /** 验证 fromConfig 能携带元素定义并 build 出实例 */
    public function testFromConfigWithElements(): void
    {
        $t = PosterTemplate::fromConfig([
            'width' => 100, 'height' => 200,
            'elements' => [['type' => 'text', 'text' => 'Hi']],
        ]);
        $els = $t->build();
        $this->assertCount(1, $els);
        $this->assertInstanceOf(TextElement::class, $els[0]);
    }

    /** 验证 fromJson 正确解析合法 JSON */
    public function testFromJsonParsesValidJson(): void
    {
        $json = json_encode(['width' => 300, 'height' => 400, 'elements' => [['type' => 'line']]]);
        $t = PosterTemplate::fromJson($json);
        $this->assertSame(300, $t->getWidth());
        $this->assertSame(400, $t->getHeight());
        $this->assertCount(1, $t->build());
    }

    /** 验证 fromJson 对非法 JSON（解析失败为 null）回落到默认值 */
    public function testFromJsonInvalidJsonFallsBackToDefaults(): void
    {
        $t = PosterTemplate::fromJson('not json at all');
        $this->assertSame(750, $t->getWidth());
        $this->assertSame(1334, $t->getHeight());
        $this->assertCount(0, $t->build());
    }

    /** 验证 fromJson 对合法但非对象的标量 JSON 抛带可读消息的 InvalidArgumentException */
    public function testFromJsonScalarJsonThrowsInvalidArgumentException(): void
    {
        foreach (['123', '"str"', 'true'] as $scalar) {
            try {
                PosterTemplate::fromJson($scalar);
                $this->fail('标量 JSON 应抛出 InvalidArgumentException，实际未抛：' . $scalar);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('expected an object', $e->getMessage());
            }
        }
    }

    /** 验证 build() 能把全部 15 种 type（含 artistic-text 别名）映射为对应元素 */
    public function testBuildMapsAllElementTypes(): void
    {
        $defs = [];
        foreach ([
            'text', 'image', 'qrcode', 'avatar', 'shape', 'line', 'watermark', 'table',
            'chart', 'calendar', 'artistictext', 'artistic-text', 'emoji', 'icon', 'emoticon',
        ] as $type) {
            $defs[] = ['type' => $type];
        }
        $els = (new PosterTemplate(100, 100, $defs))->build();
        $this->assertCount(15, $els);
        $this->assertInstanceOf(ArtisticTextElement::class, $els[10]);
        $this->assertInstanceOf(ArtisticTextElement::class, $els[11]);
        $this->assertInstanceOf(EmojiElement::class, $els[12]);
        $this->assertInstanceOf(ChartElement::class, $els[8]);
    }

    /** 验证 build() 跳过未知 type 与缺失 type 的定义 */
    public function testBuildSkipsUnknownAndMissingType(): void
    {
        $t = new PosterTemplate(100, 100, [
            ['type' => 'unknown'],
            ['x' => 1],
            ['type' => 'text', 'text' => 'ok'],
        ]);
        $els = $t->build();
        $this->assertCount(1, $els);
        $this->assertInstanceOf(TextElement::class, $els[0]);
    }

    /** 验证 build() 会用传入变量解析元素占位符 */
    public function testBuildResolvesPlaceholders(): void
    {
        $t = new PosterTemplate(100, 100, [
            ['type' => 'text', 'text' => 'Hello {{name}}'],
        ]);
        $els = $t->build(['name' => 'World']);
        $this->assertSame('Hello World', $els[0]->toArray()['options']['text']);
    }

    /** 验证 toArray/toJson 序列化与反序列化往返一致 */
    public function testToArrayAndToJsonRoundTrip(): void
    {
        $t = new PosterTemplate(200, 300, [['type' => 'text', 'text' => '中文']]);
        $arr = $t->toArray();
        $this->assertSame(200, $arr['width']);
        $this->assertSame(300, $arr['height']);
        $decoded = json_decode($t->toJson(), true);
        $this->assertSame($arr, $decoded);
    }
}
