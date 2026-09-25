<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Poster\Elements\ArtisticTextElement;
use Erikwang2013\Poster\Poster\Elements\ChartElement;
use Erikwang2013\Poster\Poster\Elements\ElementRegistry;
use Erikwang2013\Poster\Poster\Elements\EmojiElement;
use Erikwang2013\Poster\Poster\Elements\LineElement;
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

    /** 验证 fromJson 对非法 JSON 抛异常（此前静默回落默认模板 → 渲染出空白海报） */
    public function testFromJsonInvalidJsonThrows(): void
    {
        foreach (['not json at all', '{"width": 750,', '', 'null'] as $bad) {
            try {
                PosterTemplate::fromJson($bad);
                $this->fail('非法 JSON 应抛 InvalidArgumentException：' . $bad);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid JSON', $e->getMessage());
            }
        }
    }

    /** 验证 JSON 解析错误信息带 json_last_error_msg 内容 */
    public function testFromJsonReportsJsonError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Syntax error');
        PosterTemplate::fromJson('{"width": }');
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

    /** 验证 build() 对未知 type 抛异常并列出已知类型（此前静默丢弃 → 0 元素无警告） */
    public function testBuildThrowsOnUnknownType(): void
    {
        $t = new PosterTemplate(100, 100, [['type' => 'typo']]);
        try {
            $t->build();
            $this->fail('未知 type 应抛 InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown element type "typo"', $e->getMessage());
            $this->assertStringContainsString('text', $e->getMessage());
        }
    }

    /** 验证 build() 对缺失/非法 type 键抛异常 */
    public function testBuildThrowsOnMissingType(): void
    {
        foreach ([[['x' => 1]], [['type' => null]], [['type' => '']], ['not-an-array']] as $defs) {
            try {
                (new PosterTemplate(100, 100, $defs))->build();
                $this->fail('缺失 type 应抛 InvalidArgumentException：' . json_encode($defs));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('"type" key', $e->getMessage());
            }
        }
    }

    /** 验证类型映射与 ElementRegistry 单点注册保持一致 */
    public function testTypeMapComesFromRegistry(): void
    {
        $defs = array_map(static fn ($type) => ['type' => $type], ElementRegistry::types());
        $els = (new PosterTemplate(100, 100, $defs))->build();
        $this->assertCount(count(ElementRegistry::TYPES), $els);
        foreach ($els as $i => $el) {
            $this->assertInstanceOf(ElementRegistry::TYPES[$defs[$i]['type']], $el);
        }
    }

    /** 验证 build() 支持追加已有元素（存在于列表之前，供 Builder::replaceElements(false) 用） */
    public function testBuildAppendsToExistingElements(): void
    {
        $existing = new TextElement(['text' => 'hand-written']);
        $els = (new PosterTemplate(100, 100, [['type' => 'line']]))->build([], [$existing]);
        $this->assertCount(2, $els);
        $this->assertSame($existing, $els[0]);
        $this->assertInstanceOf(LineElement::class, $els[1]);
    }

    /** 验证 ElementRegistry 查表与报错 */
    public function testElementRegistryLookup(): void
    {
        $this->assertTrue(ElementRegistry::has('text'));
        $this->assertSame(TextElement::class, ElementRegistry::classFor('text'));
        $this->assertSame('artistictext', ElementRegistry::typeFor(ArtisticTextElement::class)); // 别名取注册顺序里第一个
        $this->assertNull(ElementRegistry::typeFor(TextElement::class . 'X'));
        $this->assertInstanceOf(TextElement::class, ElementRegistry::create('text', ['text' => 'x']));
        $this->expectException(\InvalidArgumentException::class);
        ElementRegistry::create('nope');
    }

    /** 验证 build() 会用传入变量解析元素占位符 */
    public function testBuildResolvesPlaceholders(): void
    {
        $t = new PosterTemplate(100, 100, [
            ['type' => 'text', 'text' => 'Hello {{name}}'],
        ]);
        $els = $t->build(['name' => 'World']);
        $this->assertSame('Hello World', $els[0]->toArray()['text']);
    }

    /** 验证元素 toArray()（短名 + 拍平）能被 fromConfig() 原样还原并再次导出 */
    public function testElementArrayRoundTripsThroughFromConfig(): void
    {
        $defs = [
            ['type' => 'text', 'text' => 'Hi {{n}}', 'x' => 1],
            ['type' => 'table', 'header' => ['A'], 'rows' => [['1']]],
            ['type' => 'chart', 'chart' => 'pie', 'data' => [['label' => 'A', 'value' => 1]]],
            ['type' => 'artistic-text', 'text' => 'Art'],
        ];
        $template = PosterTemplate::fromConfig(['width' => 300, 'height' => 300, 'elements' => $defs]);

        $again = [];
        foreach ($template->build(['n' => 'there']) as $el) {
            $again[] = $el->toArray();
        }

        $this->assertSame(['text', 'table', 'chart', 'artistictext'], array_column($again, 'type'));
        $this->assertSame('Hi there', $again[0]['text']);
        $this->assertSame(['A'], $again[1]['header']);
        $this->assertSame('pie', $again[2]['chart']);

        // 还原结构可再次喂回模板系统（幂等）
        $this->assertSame(
            ['text', 'table', 'chart', 'artistictext'],
            array_column(PosterTemplate::fromConfig(['elements' => $again])->toArray()['elements'], 'type')
        );
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
