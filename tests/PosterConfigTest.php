<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests;

use Erikwang2013\Poster\PosterConfig;
use PHPUnit\Framework\TestCase;

/**
 * PosterConfig 测试：get/merge/reset/load 语义（存储模块依赖其配置读取）。
 */
class PosterConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 测试 get() 能读取嵌套配置值 */
    public function testGetNestedValue(): void
    {
        $this->assertSame(300, PosterConfig::get('captcha.ttl'));
        $this->assertSame(90, PosterConfig::get('image.quality'));
    }

    /** 测试 get() 缺失键返回默认值 */
    public function testGetMissingKeyReturnsDefault(): void
    {
        $this->assertNull(PosterConfig::get('no.such.key'));
        $this->assertSame('fallback', PosterConfig::get('no.such.key', 'fallback'));
    }

    /** 测试 get() 部分路径缺失时返回默认值 */
    public function testGetPartialMissingPathReturnsDefault(): void
    {
        $this->assertSame('d', PosterConfig::get('captcha.tolerance.noop', 'd'));
    }

    /** 测试 merge() 覆盖深层配置且保留其他键 */
    public function testMergeOverridesDeepAndKeepsOthers(): void
    {
        PosterConfig::merge(['captcha' => ['ttl' => 60]]);
        $this->assertSame(60, PosterConfig::get('captcha.ttl'));
        $this->assertSame('auto', PosterConfig::get('image.driver'));
        $this->assertSame(3, PosterConfig::get('captcha.max_attempts'));
    }

    /** 测试 reset() 清空缓存后 get() 重新读取默认配置 */
    public function testResetReloadsConfig(): void
    {
        PosterConfig::merge(['captcha' => ['ttl' => 60]]);
        $this->assertSame(60, PosterConfig::get('captcha.ttl'));
        PosterConfig::reset();
        $this->assertSame(300, PosterConfig::get('captcha.ttl'));
    }

    /** 测试 load() 支持加载自定义配置文件并返回其内容 */
    public function testLoadCustomPath(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'poster-config-');
        file_put_contents($file, '<?php return ["custom" => ["value" => 42]];');
        try {
            $config = PosterConfig::load($file);
            $this->assertSame(42, $config['custom']['value']);
        } finally {
            @unlink($file);
        }
    }

    /** 测试显式 load() 的路径持久生效：后续 get() 不被默认配置覆盖 */
    public function testLoadCustomPathPersistsAcrossGets(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'poster-config-');
        file_put_contents($file, '<?php return ["custom" => ["value" => 42]];');
        try {
            PosterConfig::load($file);
            $this->assertSame(42, PosterConfig::get('custom.value'));
            $this->assertSame(42, PosterConfig::get('custom.value'));
            $this->assertSame('fallback', PosterConfig::get('custom.missing', 'fallback'));
        } finally {
            @unlink($file);
        }
    }

    /** 测试临时目录模拟项目结构时，项目级 config/poster.php 能被发现并加载 */
    public function testFindProjectConfigFindsProjectLevelConfig(): void
    {
        $tmp = sys_get_temp_dir() . '/poster-project-' . uniqid();
        mkdir($tmp . '/config', 0777, true);
        file_put_contents($tmp . '/config/poster.php', '<?php return ["project" => ["flag" => true]];');
        $origCwd = getcwd();
        try {
            chdir($tmp);
            $config = PosterConfig::load();
            $this->assertTrue($config['project']['flag'] ?? false);
        } finally {
            chdir($origCwd);
            @unlink($tmp . '/config/poster.php');
            @rmdir($tmp . '/config');
            @rmdir($tmp);
        }
    }
}
