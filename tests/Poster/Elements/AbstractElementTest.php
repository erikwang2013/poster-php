<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\Elements\AbstractElement;
use Erikwang2013\Poster\Poster\Elements\TextElement;
use PHPUnit\Framework\TestCase;

class AbstractElementTest extends TestCase
{
    /** 验证 toArray() 输出短类型名 + 拍平的 options（可直接喂回模板系统） */
    public function testToArrayContainsShortTypeAndFlattenedOptions(): void
    {
        $el = new TextElement(['x' => 1, 'text' => 'a']);
        $this->assertSame(['type' => 'text', 'x' => 1, 'text' => 'a'], $el->toArray());
    }

    /** 验证 toArray() 对空 options 只返回类型名 */
    public function testToArrayWithEmptyOptions(): void
    {
        $el = new TextElement();
        $this->assertSame(['type' => 'text'], $el->toArray());
    }

    /** 验证未注册的元素类回落成类名（保证 toArray() 不会丢类型信息） */
    public function testToArrayFallsBackToClassNameForUnregisteredElement(): void
    {
        $el = $this->exposedElement();
        $this->assertStringContainsString('@anonymous', $el->toArray()['type']);
    }

    /** 验证 resolvePlaceholders 替换已知变量（含重复出现与非字符串值） */
    public function testResolvePlaceholdersReplacesKnownVariables(): void
    {
        $el = $this->exposedElement();
        $this->assertSame(
            'Hi Bob Bob 42',
            $el->exposed('Hi {{name}} {{name}} {{n}}', ['name' => 'Bob', 'n' => 42])
        );
    }

    /** 验证 resolvePlaceholders 对未提供的变量原样保留 */
    public function testResolvePlaceholdersLeavesUnknownVariables(): void
    {
        $el = $this->exposedElement();
        $this->assertSame('x {{ missing }} y', $el->exposed('x {{ missing }} y', []));
    }

    /** 验证 resolvePlaceholders 对无占位符文本不做任何改动 */
    public function testResolvePlaceholdersPlainText(): void
    {
        $el = $this->exposedElement();
        $this->assertSame('no braces here', $el->exposed('no braces here', ['name' => 'x']));
    }

    /** 验证 resolvePlaceholders 兼容带空格占位符 {{ name }}，同时保持 {{name}} 无空格形式 */
    public function testResolvePlaceholdersAllowsSpaces(): void
    {
        $el = $this->exposedElement();
        $this->assertSame('Hi Bob', $el->exposed('Hi {{ name }}', ['name' => 'Bob']));
        $this->assertSame('Hi Bob', $el->exposed('Hi {{name}}', ['name' => 'Bob']));
        $this->assertSame('Hi Bob', $el->exposed('Hi {{  name  }}', ['name' => 'Bob']));
    }

    /** 验证变量名支持中文（正则带 /u 且允许 Unicode 字母） */
    public function testResolvePlaceholdersSupportsChineseVariableNames(): void
    {
        $el = $this->exposedElement();
        $this->assertSame('新品首发', $el->exposed('{{标题}}', ['标题' => '新品首发']));
        $this->assertSame('A/B', $el->exposed('{{ 甲 }}/{{乙}}', ['甲' => 'A', '乙' => 'B']));
    }

    /** 验证数组值不再被转成 "Array"，占位符原样保留 */
    public function testResolvePlaceholdersKeepsNonScalarVariables(): void
    {
        $el = $this->exposedElement();
        $this->assertSame('{{tags}}', $el->exposed('{{tags}}', ['tags' => ['a', 'b']]));
    }

    /** 验证 resolveValue() 递归替换嵌套数组（表格行 / 图表数据）且不改数组键 */
    public function testResolveValueRecursesIntoNestedArrays(): void
    {
        $el = $this->exposedElement();
        $this->assertSame(
            [
                'header' => ['H', 'B'],
                'rows'   => [['A', '{{b}}'], ['2', ['deep' => 'C']]],
                'value'  => 10,
                'nil'    => null,
            ],
            $el->exposedValue([
                'header' => ['{{h}}', 'B'],
                'rows'   => [['{{a}}', '{{b}}'], ['2', ['deep' => '{{c}}']]],
                'value'  => 10,
                'nil'    => null,
            ], ['h' => 'H', 'a' => 'A', 'c' => 'C'])
        );
    }

    /** 验证 resolvePlaceholders 对非法 UTF-8 不抛错、不改动原文 */
    public function testResolvePlaceholdersHandlesInvalidUtf8(): void
    {
        $el = $this->exposedElement();
        $broken = "bad\xB1{{name}}";
        $this->assertSame($broken, $el->exposed($broken, ['name' => 'x']));
    }

    /** 验证带声明 resolveKeys 的元素会递归解析数组值 */
    public function testResolveAppliesDeclaredKeysRecursively(): void
    {
        $el = new class (['rows' => [['{{a}}', '{{b}}']], 'x' => '{{a}}', 'ignored' => '{{a}}']) extends AbstractElement {
            protected array $resolveKeys = ['rows'];

            public function render(ImageDriverInterface $canvas): void
            {
            }
        };
        $el->resolve(['a' => '1', 'b' => '2']);
        $this->assertSame([['1', '2']], $el->toArray()['rows']);
        $this->assertSame('{{a}}', $el->toArray()['x']);           // 未声明解析的键保持原样
        $this->assertSame('{{a}}', $el->toArray()['ignored']);
    }

    private function exposedElement(): AbstractElement
    {
        return new class () extends AbstractElement {
            public function render(ImageDriverInterface $canvas): void
            {
            }

            public function exposed(string $text, array $variables): string
            {
                return $this->resolvePlaceholders($text, $variables);
            }

            public function exposedValue(mixed $value, array $variables): mixed
            {
                return $this->resolveValue($value, $variables);
            }
        };
    }
}
