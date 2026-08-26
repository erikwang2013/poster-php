<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\AbstractCaptcha;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 用匿名子类暴露 AbstractCaptcha 的 protected 方法，测试基类公共逻辑。
 */
class AbstractCaptchaTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-abstract-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.{png,json}', GLOB_BRACE));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    /** 构造一个可暴露 protected 方法的匿名测试子类 */
    private function makeCaptcha(ImageDriverInterface $driver, StorageInterface $storage): AbstractCaptcha
    {
        return new class ($driver, $storage) extends AbstractCaptcha {
            public function getType(): string
            {
                return 'dummy';
            }

            public function generate(): array
            {
                return [];
            }

            public function exposeGenerateKey(): string
            {
                return $this->generateKey();
            }

            public function exposeStore(array $data): void
            {
                $this->store($data);
            }

            public function exposeCreateBackground(): ImageDriverInterface
            {
                return $this->createBackground();
            }

            public function getBackgroundPath(): ?string
            {
                return $this->backgroundPath;
            }

            public function exposeDimensions(): array
            {
                return [$this->width, $this->height];
            }
        };
    }

    /** 测试：setDifficulty/setBackground 返回自身支持链式调用，且状态持久化 */
    public function testSettersAreFluentAndPersist(): void
    {
        $captcha = $this->makeCaptcha(
            $this->createMock(ImageDriverInterface::class),
            $this->createMock(StorageInterface::class)
        );
        $this->assertSame($captcha, $captcha->setDifficulty('easy'));
        $this->assertSame($captcha, $captcha->setBackground('/tmp/foo.png'));
        $this->assertSame('/tmp/foo.png', $captcha->getBackgroundPath());
        $this->assertSame($captcha, $captcha->setBackground(null));
        $this->assertNull($captcha->getBackgroundPath());
    }

    /** 测试：generateKey 生成 32 位十六进制随机 key，且两次调用不重复 */
    public function testGenerateKeyReturnsUnique32HexChars(): void
    {
        $captcha = $this->makeCaptcha(
            $this->createMock(ImageDriverInterface::class),
            $this->createMock(StorageInterface::class)
        );
        $k1 = $captcha->exposeGenerateKey();
        $k2 = $captcha->exposeGenerateKey();
        $this->assertSame(32, strlen($k1));
        $this->assertTrue(ctype_xdigit($k1));
        $this->assertNotSame($k1, $k2);
    }

    /** 测试：store 合并 type/attempts/created_at 元数据，默认 TTL 300 */
    public function testStoreMergesMetadataWithDefaultTtl(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $captcha = $this->makeCaptcha($this->createMock(ImageDriverInterface::class), $storage);
        $key = $captcha->exposeGenerateKey();

        $storage->expects($this->once())->method('set')->with(
            $key,
            $this->callback(function (array $data): bool {
                return $data['answer'] === 42
                    && $data['type'] === 'dummy'
                    && $data['attempts'] === 0
                    && abs($data['created_at'] - time()) <= 1;
            }),
            300
        );
        $captcha->exposeStore(['answer' => 42]);
    }

    /** 测试：store 使用配置中的 captcha.ttl 作为过期时间 */
    public function testStoreUsesConfiguredTtl(): void
    {
        PosterConfig::merge(['captcha' => ['ttl' => 60]]);
        $storage = $this->createMock(StorageInterface::class);
        $captcha = $this->makeCaptcha($this->createMock(ImageDriverInterface::class), $storage);
        $captcha->exposeGenerateKey();
        $storage->expects($this->once())->method('set')->with($this->anything(), $this->anything(), 60);
        $captcha->exposeStore(['answer' => 1]);
    }

    /** 测试：指定有效背景路径时加载图片，并按其尺寸更新宽高 */
    public function testCreateBackgroundWithCustomPathLoadsImage(): void
    {
        $img = imagecreatetruecolor(120, 90);
        imagefill($img, 0, 0, imagecolorallocate($img, 10, 20, 30));
        $path = $this->tempDir . '/bg.png';
        imagepng($img, $path);
        imagedestroy($img);

        $captcha = $this->makeCaptcha(new GdDriver(), $this->createMock(StorageInterface::class));
        $captcha->setBackground($path);
        $captcha->exposeCreateBackground();
        $this->assertSame([120, 90], $captcha->exposeDimensions());
    }

    /** 测试：背景路径不存在时回退程序化生成，宽高保持默认 300x200 */
    public function testCreateBackgroundWithMissingPathFallsBackToProcedural(): void
    {
        PosterConfig::merge(['captcha' => ['background_dir' => null]]);
        $driver = $this->createMock(ImageDriverInterface::class);
        $driver->expects($this->once())->method('clone')->willReturnSelf();
        $driver->expects($this->once())->method('create')->with(300, 200);
        $driver->expects($this->never())->method('load');
        $driver->expects($this->never())->method('resize');

        $captcha = $this->makeCaptcha($driver, $this->createMock(StorageInterface::class));
        $captcha->setBackground($this->tempDir . '/not-exists.png');
        $captcha->exposeCreateBackground();
        $this->assertSame([300, 200], $captcha->exposeDimensions());
    }

    /** 测试：配置 background_dir 时随机加载目录内图片并 resize 到默认尺寸 */
    public function testCreateBackgroundUsesConfiguredDirectory(): void
    {
        $img = imagecreatetruecolor(50, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        imagepng($img, $this->tempDir . '/a.png');
        imagedestroy($img);
        PosterConfig::merge(['captcha' => ['background_dir' => $this->tempDir]]);

        $driver = $this->createMock(ImageDriverInterface::class);
        $driver->expects($this->once())->method('clone')->willReturnSelf();
        $driver->expects($this->once())->method('load')->with($this->stringContains('a.png'));
        $driver->expects($this->once())->method('resize')->with(300, 200);
        $driver->expects($this->never())->method('create');

        $captcha = $this->makeCaptcha($driver, $this->createMock(StorageInterface::class));
        $captcha->exposeCreateBackground();
    }

    /** 测试：配置了未知背景风格时抛出带合法取值列表的 InvalidArgumentException */
    public function testUnknownBackgroundStyleThrowsInvalidArgumentException(): void
    {
        // array_replace_recursive 按索引合并，需覆盖全部 3 个样式位
        PosterConfig::merge([
            'captcha' => ['background_dir' => null, 'background_styles' => ['bogus', 'bogus', 'bogus']],
        ]);
        $captcha = $this->makeCaptcha(new GdDriver(), $this->createMock(StorageInterface::class));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minimal, vibrant, natural');
        $captcha->exposeCreateBackground();
    }
}
