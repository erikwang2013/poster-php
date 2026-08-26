<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\PosterConfig;
use PHPUnit\Framework\TestCase;

class PosterConfigTest extends TestCase
{
    protected function setUp(): void
    {
        PosterConfig::reset();
    }

    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 验证 load() 默认加载包自带 config/poster.php 且结构完整 */
    public function testLoadReturnsPackageConfig(): void
    {
        $config = PosterConfig::load();
        $this->assertIsArray($config);
        $this->assertSame(750, $config['poster']['default_width']);
        $this->assertSame('auto', $config['image']['driver']);
    }

    /** 验证 get() 点分语法能取到配置各层的标量值 */
    public function testGetWithDotNotation(): void
    {
        $this->assertSame(750, PosterConfig::get('poster.default_width'));
        $this->assertSame(1334, PosterConfig::get('poster.default_height'));
        $this->assertSame(90, PosterConfig::get('poster.jpeg_quality'));
        $this->assertSame(6, PosterConfig::get('poster.png_compression'));
        $this->assertSame('auto', PosterConfig::get('image.driver'));
        $this->assertSame('Alibaba-PuHuiTi-Regular.ttf', basename(PosterConfig::get('poster.font')));
    }

    /** 验证 get() 能返回嵌套数组值（captcha.tolerance 整体） */
    public function testGetReturnsNestedArrayValue(): void
    {
        $this->assertSame(
            ['click' => 18, 'rotate' => 5, 'slider' => 4],
            PosterConfig::get('captcha.tolerance')
        );
    }

    /** 验证 get() 对不存在的键返回默认值（含深层键） */
    public function testGetMissingKeyReturnsDefault(): void
    {
        $this->assertNull(PosterConfig::get('poster.not_exist'));
        $this->assertSame('fallback', PosterConfig::get('poster.not_exist', 'fallback'));
        $this->assertSame('fb', PosterConfig::get('a.b.c.d', 'fb'));
    }

    /** 验证 merge() 递归合并覆盖，且未覆盖的兄弟键保留 */
    public function testMergeOverridesRecursively(): void
    {
        $merged = PosterConfig::merge(['poster' => ['default_width' => 800]]);
        $this->assertSame(800, $merged['poster']['default_width']);
        $this->assertSame(1334, $merged['poster']['default_height']);
        $this->assertSame(800, PosterConfig::get('poster.default_width'));
    }

    /** 验证 merge() 后 get() 读到的是合并后的值 */
    public function testGetAfterMergeReflectsOverrides(): void
    {
        PosterConfig::merge(['poster' => ['jpeg_quality' => 70]]);
        $this->assertSame(70, PosterConfig::get('poster.jpeg_quality'));
        $this->assertSame(750, PosterConfig::get('poster.default_width'));
    }

    /** 验证 load() 支持显式路径加载自定义配置文件 */
    public function testLoadWithExplicitPath(): void
    {
        $path = sys_get_temp_dir() . '/poster-config-' . uniqid() . '.php';
        file_put_contents($path, '<?php return ["foo" => ["bar" => 42]];');
        try {
            $config = PosterConfig::load($path);
            $this->assertSame(42, $config['foo']['bar']);
        } finally {
            unlink($path);
            PosterConfig::reset();
        }
    }

    /** 验证 reset() 清空缓存后重新读取的是原始配置 */
    public function testResetClearsCache(): void
    {
        PosterConfig::merge(['poster' => ['default_width' => 999]]);
        PosterConfig::reset();
        $this->assertSame(750, PosterConfig::get('poster.default_width'));
    }
}
