<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Laravel 适配层中「不依赖 Laravel」的部分（CaptchaImage / CaptchaVerifier / 响应头）。
 * 放在 tests/Storage/ 下是因为这些类只碰存储与驱动；
 * 真正需要框架的 CaptchaImageController::show()、Rules\CaptchaRule、ServiceProvider 未做自动化测试（CI 不含 Laravel）。
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Adapters\Laravel\CaptchaImage;
use Erikwang2013\Poster\Adapters\Laravel\CaptchaImageController;
use Erikwang2013\Poster\Adapters\Laravel\CaptchaVerifier;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class LaravelAdapterCaptchaTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;
    private CaptchaImage $image;
    private CaptchaVerifier $verifier;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-laravel-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
        $manager = new CaptchaManager(new GdDriver(), $this->storage);
        $this->image = new CaptchaImage($this->storage, $manager, 60);
        $this->verifier = new CaptchaVerifier($this->storage, $manager);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        @rmdir($this->tempDir);
        @unlink(sys_get_temp_dir() . '/poster-captcha-lock-' . md5($this->tempDir) . '.lock');
    }

    /** 测试 generate() 返回 URL（不是 base64），并把 PNG 字节缓存进同 key 的存储条目 */
    public function testGenerateReturnsUrlAndCachesPng(): void
    {
        $captcha = $this->image->generate('click', ['difficulty' => 'easy']);

        $this->assertArrayHasKey('key', $captcha);
        $this->assertSame('click', $captcha['type']);
        $this->assertSame('/captcha/' . $captcha['key'], $captcha['url']);
        $this->assertArrayNotHasKey('image', $captcha, '返回值不再携带 base64');

        $png = $this->image->png($captcha['key']);
        $this->assertIsString($png);
        $this->assertSame("\x89PNG", substr($png, 0, 4), '必须是 PNG 字节流');
        $this->assertArrayHasKey('png', $this->storage->get($captcha['key']));
    }

    /** 测试 png() 对未知/非本适配器写入的 key 返回 null */
    public function testPngForUnknownKeyReturnsNull(): void
    {
        $this->assertNull($this->image->png('nope'));

        $this->storage->set('plain', ['type' => 'click'], 60);
        $this->assertNull($this->image->png('plain'), '没有 png 字段的条目不应被当成图片');
    }

    /** 测试无 Laravel 路由时 URL 回落到约定路径 */
    public function testUrlFallsBackToConventionPath(): void
    {
        $this->assertSame('/captcha/abc', CaptchaImage::url('abc'));
        $this->assertSame('/img/captcha/abc', CaptchaImage::url('abc', '/img/captcha/'));
    }

    /** 测试响应头：image/png + no-store（验证码图片不允许被任何环节缓存） */
    public function testImageResponseHeaders(): void
    {
        $headers = CaptchaImageController::headers("\x89PNG\r\n\x1a\n12345");

        $this->assertSame('image/png', $headers['Content-Type']);
        $this->assertSame('13', $headers['Content-Length']);
        $this->assertStringContainsString('no-store', $headers['Cache-Control']);
        $this->assertStringContainsString('no-cache', $headers['Cache-Control']);
    }

    /** 测试 CaptchaVerifier 自己从存储里取 type：答错 false（计数 +1 且 key 保留）、答对 true（key 作废） */
    public function testVerifierResolvesTypeFromStorage(): void
    {
        $captcha = $this->image->generate('click', ['difficulty' => 'easy']);
        $answer = array_map(
            fn($t) => [$t['x'], $t['y']],
            $this->storage->get($captcha['key'])['targets']
        );

        $this->assertFalse($this->verifier->verify($captcha['key'], [[0, 0]]));
        $this->assertSame(1, $this->storage->get($captcha['key'])['attempts'], '答错应累计尝试次数');
        $this->assertTrue($this->verifier->verify($captcha['key'], $answer));
        $this->assertNull($this->storage->get($captcha['key']), '答对后 key 立即作废');
        $this->assertNull($this->image->png($captcha['key']), '图片随 key 一起清掉');
    }

    /** 测试未知 key / 类型不匹配都返回 false，不抛异常 */
    public function testVerifierRejectsUnknownKeyAndWrongType(): void
    {
        $this->assertFalse($this->verifier->verify('missing', 'x'));

        $captcha = $this->image->generate('rotate');
        $this->assertFalse($this->verifier->verify($captcha['key'], '不是角度'));
    }
}
