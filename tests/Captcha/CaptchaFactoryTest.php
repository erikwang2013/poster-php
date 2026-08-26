<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaFactory;
use Erikwang2013\Poster\Captcha\CaptchaInterface;
use Erikwang2013\Poster\Captcha\ClickCaptcha;
use Erikwang2013\Poster\Captcha\RotateCaptcha;
use Erikwang2013\Poster\Captcha\SliderCaptcha;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CaptchaFactoryTest extends TestCase
{
    private ImageDriverInterface $driver;
    private StorageInterface $storage;

    protected function setUp(): void
    {
        $this->driver = $this->createMock(ImageDriverInterface::class);
        $this->storage = $this->createMock(StorageInterface::class);
    }

    public static function typeProvider(): array
    {
        return [
            'click'  => ['click', ClickCaptcha::class],
            'rotate' => ['rotate', RotateCaptcha::class],
            'slider' => ['slider', SliderCaptcha::class],
        ];
    }

    /** 测试：工厂对每种类型返回对应实现类，且符合 CaptchaInterface 契约
     * @dataProvider typeProvider
     */
    public function testCreateReturnsCorrectImplementation(string $type, string $expectedClass): void
    {
        $captcha = CaptchaFactory::create($type, $this->driver, $this->storage);
        $this->assertInstanceOf($expectedClass, $captcha);
        $this->assertInstanceOf(CaptchaInterface::class, $captcha);
    }

    /** 测试：random 类型返回 click/rotate/slider 三者之一 */
    public function testCreateRandomReturnsOneOfThreeTypes(): void
    {
        $allowed = [ClickCaptcha::class, RotateCaptcha::class, SliderCaptcha::class];
        for ($i = 0; $i < 20; $i++) {
            $captcha = CaptchaFactory::create('random', $this->driver, $this->storage);
            $this->assertContains($captcha::class, $allowed);
        }
    }

    /** 测试：未知类型抛出 InvalidArgumentException，且消息包含类型名 */
    public function testCreateWithUnknownTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bogus');
        CaptchaFactory::create('bogus', $this->driver, $this->storage);
    }

    /** 测试：空字符串类型同样视为未知类型抛出异常 */
    public function testCreateWithEmptyTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CaptchaFactory::create('', $this->driver, $this->storage);
    }
}
