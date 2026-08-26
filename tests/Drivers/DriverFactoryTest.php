<?php

/**
 * DriverFactory 路由逻辑测试：auto/gd/imagick/未知驱动的选择与 imagick 缺失时的行为。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Drivers\ImagickDriver;
use PHPUnit\Framework\TestCase;

class DriverFactoryTest extends TestCase
{
    /** 验证 create(null) 走 auto 分支，返回可用的 ImageDriverInterface 实现。 */
    public function testCreateWithNullUsesAutoRouting(): void
    {
        $this->assertInstanceOf(ImageDriverInterface::class, DriverFactory::create(null));
    }

    /** 验证未安装 imagick 时 auto 路由返回 GdDriver。 */
    public function testAutoReturnsGdDriverWhenImagickUnavailable(): void
    {
        if (DriverFactory::isImagickAvailable()) {
            $this->markTestSkipped('本环境已安装 imagick，无法验证缺失分支');
        }
        $this->assertInstanceOf(GdDriver::class, DriverFactory::create('auto'));
    }

    /** 验证已安装 imagick 时 auto 路由返回 ImagickDriver。 */
    public function testAutoReturnsImagickDriverWhenAvailable(): void
    {
        if (!DriverFactory::isImagickAvailable()) {
            $this->markTestSkipped('未安装 imagick，本分支在本环境无法执行');
        }
        $this->assertInstanceOf(ImagickDriver::class, DriverFactory::create('auto'));
    }

    /** 验证显式指定 gd 返回 GdDriver。 */
    public function testCreateExplicitGd(): void
    {
        $this->assertInstanceOf(GdDriver::class, DriverFactory::create('gd'));
    }

    /** 验证显式指定 imagick 返回 ImagickDriver。 */
    public function testCreateExplicitImagick(): void
    {
        if (!DriverFactory::isImagickAvailable()) {
            $this->markTestSkipped('未安装 imagick，本分支在本环境无法执行');
        }
        $this->assertInstanceOf(ImagickDriver::class, DriverFactory::create('imagick'));
    }

    /** 验证未安装 imagick 时显式指定 imagick 会抛出 Error（类无法解析），而不是静默降级。 */
    public function testCreateImagickWithoutExtensionThrowsError(): void
    {
        if (DriverFactory::isImagickAvailable()) {
            $this->markTestSkipped('本环境已安装 imagick，无法验证缺失分支');
        }
        $this->expectException(\Error::class);
        DriverFactory::create('imagick');
    }

    /** 验证未知驱动名按工厂设计回退到 GdDriver。 */
    public function testCreateUnknownDriverFallsBackToGd(): void
    {
        $this->assertInstanceOf(GdDriver::class, DriverFactory::create('bmp'));
    }

    /** 验证 isImagickAvailable 返回布尔值，且与扩展加载状态一致。 */
    public function testIsImagickAvailableReflectsEnvironment(): void
    {
        $this->assertIsBool(DriverFactory::isImagickAvailable());
        if (!extension_loaded('imagick')) {
            $this->assertFalse(DriverFactory::isImagickAvailable());
        }
    }
}
