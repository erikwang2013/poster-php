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
    /** 验证 toArray() 返回元素类名与完整 options */
    public function testToArrayContainsTypeAndOptions(): void
    {
        $el = new TextElement(['x' => 1, 'text' => 'a']);
        $arr = $el->toArray();
        $this->assertSame(TextElement::class, $arr['type']);
        $this->assertSame(['x' => 1, 'text' => 'a'], $arr['options']);
    }

    /** 验证 toArray() 对空 options 返回空数组 */
    public function testToArrayWithEmptyOptions(): void
    {
        $el = new TextElement();
        $this->assertSame(['type' => TextElement::class, 'options' => []], $el->toArray());
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
        };
    }
}
