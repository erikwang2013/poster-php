<?php

/**
 * 接口契约测试：验证两个 Driver 完整实现 ImageDriverInterface。
 * 注意：仅做类加载/方法存在性检查，不实例化 ImagickDriver，
 * 因此未安装 imagick 扩展时本文件也能正常运行。
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Drivers\ImagickDriver;
use PHPUnit\Framework\TestCase;

class DriverContractTest extends TestCase
{
    /** 验证 GdDriver 实现接口的全部方法（含返回类型声明的可加载性由 PHP 协变规则保证）。 */
    public function testGdDriverImplementsAllInterfaceMethods(): void
    {
        $this->assertInstanceOf(ImageDriverInterface::class, new GdDriver());
        foreach ($this->interfaceMethods() as $method) {
            $this->assertTrue(method_exists(GdDriver::class, $method), "GdDriver 缺少接口方法 $method");
        }
    }

    /** 验证 ImagickDriver 声明实现接口的全部方法（不实例化，无需扩展即可运行）。 */
    public function testImagickDriverImplementsAllInterfaceMethods(): void
    {
        $this->assertTrue(is_subclass_of(ImagickDriver::class, ImageDriverInterface::class));
        foreach ($this->interfaceMethods() as $method) {
            $this->assertTrue(method_exists(ImagickDriver::class, $method), "ImagickDriver 缺少接口方法 $method");
        }
    }

    /** 验证两个 Driver 均未被标记为 final，可被扩展（与接口兼容性相关）。 */
    public function testBothDriversAreExtensible(): void
    {
        $this->assertFalse((new \ReflectionClass(GdDriver::class))->isFinal());
        $this->assertFalse((new \ReflectionClass(ImagickDriver::class))->isFinal());
    }

    /** 获取接口声明的全部方法名列表。 */
    private function interfaceMethods(): array
    {
        return array_map(
            fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(ImageDriverInterface::class))->getMethods()
        );
    }
}
