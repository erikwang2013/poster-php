<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Helpers;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 全局 helpers 函数测试：强制 gd 驱动 + 文件存储（临时目录），不依赖真实 Redis。
 */
class HelpersTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-helper-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        PosterConfig::reset();
        PosterConfig::merge([
            'image'   => ['driver' => 'gd'],
            'captcha' => [
                'storage' => 'file',
                'file'    => ['path' => $this->tempDir],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        PosterConfig::reset();
        array_map('unlink', glob($this->tempDir . '/*.json'));
        @rmdir($this->tempDir);
    }

    /** 测试三个 helpers 函数均已定义 */
    public function testFunctionsExist(): void
    {
        $this->assertTrue(function_exists('captcha_create'));
        $this->assertTrue(function_exists('captcha_verify'));
        $this->assertTrue(function_exists('poster_create'));
    }

    /** 测试 captcha_create() 生成包含 key/type/image/extra 的数组 */
    public function testCaptchaCreateReturnsArray(): void
    {
        $result = captcha_create('click');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertSame('click', $result['type']);
        $this->assertNotEmpty($result['image']);
    }

    /** 测试 captcha_create() 未指定类型时使用配置默认类型 */
    public function testCaptchaCreateUsesDefaultType(): void
    {
        $result = captcha_create();
        $this->assertArrayHasKey('key', $result);
    }

    /** 测试 captcha_create() 的 difficulty 选项会传入验证码（easy 生成 2 个目标） */
    public function testCaptchaCreateWithDifficulty(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $this->assertCount(2, $result['extra']['texts']);
    }

    /** 测试 captcha_create() 未知类型抛出 InvalidArgumentException */
    public function testCaptchaCreateUnknownTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        captcha_create('bogus');
    }

    /** 测试 captcha_verify() 键不存在时返回 false */
    public function testVerifyMissingKeyReturnsFalse(): void
    {
        $this->assertFalse(captcha_verify('no-such-key', 'click', [[1, 1]]));
    }

    /** 测试 captcha_verify() 类型不匹配时返回 false 并累加尝试次数 */
    public function testVerifyWrongTypeIncrementsAttempts(): void
    {
        $storage = \Erikwang2013\Poster\Storage\StorageFactory::create('file');
        $storage->set('k1', ['type' => 'click', 'targets' => [['x' => 100, 'y' => 100]]], 60);

        $this->assertFalse(captcha_verify('k1', 'rotate', 45));
        $this->assertSame(1, $storage->get('k1')['attempts']);
    }

    /** 测试 captcha_verify() 答案正确时返回 true 并删除键 */
    public function testVerifyCorrectAnswerDeletesKey(): void
    {
        $storage = \Erikwang2013\Poster\Storage\StorageFactory::create('file');
        $storage->set('k1', ['type' => 'click', 'targets' => [['x' => 100, 'y' => 100]]], 60);

        $this->assertTrue(captcha_verify('k1', 'click', [[100, 100]]));
        $this->assertNull($storage->get('k1'));
    }

    /** 测试 captcha_verify() 超过最大尝试次数时返回 false 并删除键 */
    public function testVerifyOverMaxAttemptsReturnsFalse(): void
    {
        $storage = \Erikwang2013\Poster\Storage\StorageFactory::create('file');
        $storage->set('k1', ['type' => 'click', 'targets' => [['x' => 100, 'y' => 100]], 'attempts' => 3], 60);

        $this->assertFalse(captcha_verify('k1', 'click', [[100, 100]]));
        $this->assertNull($storage->get('k1'));
    }

    /** 测试 poster_create() 返回 PosterBuilder 且宽高生效 */
    public function testPosterCreateAppliesWidthHeight(): void
    {
        $builder = poster_create(300, 400);
        $this->assertInstanceOf(PosterBuilder::class, $builder);

        $ref = new \ReflectionClass($builder);
        $width = $ref->getProperty('width');
        $height = $ref->getProperty('height');
        $this->assertSame(300, $width->getValue($builder));
        $this->assertSame(400, $height->getValue($builder));
    }

    /** 测试 poster_create() 不传宽高时不设置画布尺寸（属性保持未初始化） */
    public function testPosterCreateWithoutDimensions(): void
    {
        $builder = poster_create();
        $this->assertInstanceOf(PosterBuilder::class, $builder);
        $ref = new \ReflectionClass($builder);
        $this->assertFalse($ref->getProperty('width')->isInitialized($builder));
    }
}
