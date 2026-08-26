<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * 用 mock Storage 隔离验证逻辑，逐项断言 Manager 的校验/过期/错误分支。
 */
class CaptchaManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    /** 构造一个 get 返回指定存储内容的 Manager，返回 [manager, storageMock] */
    private function managerWith(?array $stored): array
    {
        $driver = $this->createMock(ImageDriverInterface::class);
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->willReturn($stored);
        return [new CaptchaManager($driver, $storage), $storage];
    }

    private function storedClick(): array
    {
        return ['type' => 'click', 'attempts' => 0, 'targets' => [['x' => 100, 'y' => 100], ['x' => 200, 'y' => 200]]];
    }

    private function storedRotate(int $angle = 30): array
    {
        return ['type' => 'rotate', 'attempts' => 0, 'angle' => $angle];
    }

    private function storedSlider(int $x = 100): array
    {
        return ['type' => 'slider', 'attempts' => 0, 'x' => $x];
    }

    /** 测试：key 不存在时 verify 返回 false，且不会调用 del */
    public function testVerifyWithUnknownKeyReturnsFalse(): void
    {
        [$manager, $storage] = $this->managerWith(null);
        $storage->expects($this->never())->method('del');
        $this->assertFalse($manager->verify('missing-key', ['type' => 'click', 'data' => []]));
    }

    /** 测试：点击验证码坐标正确时通过，并删除 key（一次性） */
    public function testVerifyClickWithCorrectDataDeletesKey(): void
    {
        [$manager, $storage] = $this->managerWith($this->storedClick());
        $storage->expects($this->once())->method('del')->with('k1');
        $this->assertTrue($manager->verify('k1', [
            'type' => 'click',
            'data' => [[100, 100], [200, 200]],
        ]));
    }

    /** 测试：点击数量不匹配时失败，并累计一次尝试次数 */
    public function testVerifyClickWithWrongCountFailsAndIncrements(): void
    {
        [$manager, $storage] = $this->managerWith($this->storedClick());
        $storage->expects($this->once())->method('incrementAttempts')->with('k1');
        $this->assertFalse($manager->verify('k1', ['type' => 'click', 'data' => [[100, 100]]]));
    }

    /** 测试：点击坐标恰好在容忍半径边界（=18）时通过，超出 1 像素失败 */
    public function testVerifyClickToleranceBoundary(): void
    {
        [$m1, $s1] = $this->managerWith(['type' => 'click', 'attempts' => 0, 'targets' => [['x' => 100, 'y' => 100]]]);
        $this->assertTrue($m1->verify('k', ['type' => 'click', 'data' => [[118, 100]]]));

        [$m2, $s2] = $this->managerWith(['type' => 'click', 'attempts' => 0, 'targets' => [['x' => 100, 'y' => 100]]]);
        $s2->expects($this->once())->method('incrementAttempts');
        $this->assertFalse($m2->verify('k', ['type' => 'click', 'data' => [[119, 100]]]));
    }

    /** 测试：点击数据非数组或坐标缺失时失败 */
    public function testVerifyClickWithMalformedDataFails(): void
    {
        [$m1] = $this->managerWith($this->storedClick());
        $this->assertFalse($m1->verify('k', ['type' => 'click', 'data' => 'not-an-array']));

        [$m2] = $this->managerWith($this->storedClick());
        $this->assertFalse($m2->verify('k', ['type' => 'click', 'data' => [[100]]]));

        [$m3] = $this->managerWith($this->storedClick());
        $this->assertFalse($m3->verify('k', ['type' => 'click']));
    }

    /** 测试：存储中 targets 缺失时点击验证失败 */
    public function testVerifyClickWithMissingTargetsFails(): void
    {
        [$manager] = $this->managerWith(['type' => 'click', 'attempts' => 0]);
        $this->assertFalse($manager->verify('k', ['type' => 'click', 'data' => []]));
    }

    /** 测试：存储与提交类型不一致时失败，且该次尝试会计入次数 */
    public function testVerifyTypeMismatchFailsAndCountsAsAttempt(): void
    {
        [$manager, $storage] = $this->managerWith($this->storedClick());
        $storage->expects($this->once())->method('incrementAttempts');
        $this->assertFalse($manager->verify('k', ['type' => 'slider', 'data' => 100]));
    }

    /** 测试：未知类型直接失败并累计次数 */
    public function testVerifyUnknownTypeFails(): void
    {
        [$manager, $storage] = $this->managerWith($this->storedClick());
        $storage->expects($this->once())->method('incrementAttempts');
        $this->assertFalse($manager->verify('k', ['type' => 'captcha', 'data' => []]));
    }

    /** 测试：达到最大尝试次数后直接失败并删除 key，不再执行校验 */
    public function testVerifyBlocksAtMaxAttemptsAndDeletesKey(): void
    {
        [$manager, $storage] = $this->managerWith(['type' => 'click', 'attempts' => 3, 'targets' => [['x' => 100, 'y' => 100]]]);
        $storage->expects($this->once())->method('del')->with('k');
        $storage->expects($this->never())->method('incrementAttempts');
        $this->assertFalse($manager->verify('k', ['type' => 'click', 'data' => [[100, 100]]]));
    }

    /** 测试：未达最大次数时仍执行校验，正确数据可通过 */
    public function testVerifyBelowMaxAttemptsStillChecks(): void
    {
        [$manager, $storage] = $this->managerWith(['type' => 'click', 'attempts' => 2, 'targets' => [['x' => 100, 'y' => 100]]]);
        $storage->expects($this->once())->method('del');
        $this->assertTrue($manager->verify('k', ['type' => 'click', 'data' => [[100, 100]]]));
    }

    /** 测试：旋转角度 ±360 取模后等价，可验证通过 */
    public function testVerifyRotateWrapsAround360(): void
    {
        foreach ([30, 390, -330] as $input) {
            [$manager] = $this->managerWith($this->storedRotate(30));
            $this->assertTrue($manager->verify('k', ['type' => 'rotate', 'data' => $input]));
        }
    }

    /** 测试：旋转角度恰好差 5 度（容忍边界）通过，差 6 度失败 */
    public function testVerifyRotateToleranceBoundary(): void
    {
        [$m1] = $this->managerWith($this->storedRotate(30));
        $this->assertTrue($m1->verify('k', ['type' => 'rotate', 'data' => 35]));

        [$m2] = $this->managerWith($this->storedRotate(30));
        $this->assertFalse($m2->verify('k', ['type' => 'rotate', 'data' => 36]));
    }

    /** 测试：非数字角度或存储缺 angle 时旋转验证失败 */
    public function testVerifyRotateWithMalformedDataFails(): void
    {
        [$m1] = $this->managerWith($this->storedRotate(30));
        $this->assertFalse($m1->verify('k', ['type' => 'rotate', 'data' => 'abc']));

        [$m2] = $this->managerWith(['type' => 'rotate', 'attempts' => 0]);
        $this->assertFalse($m2->verify('k', ['type' => 'rotate', 'data' => 30]));
    }

    /** 测试：滑块 x 差 4 像素（容忍边界）通过，差 5 像素失败；数字字符串可通过 */
    public function testVerifySliderToleranceBoundaryAndNumericString(): void
    {
        [$m1] = $this->managerWith($this->storedSlider(100));
        $this->assertTrue($m1->verify('k', ['type' => 'slider', 'data' => 104]));

        [$m2] = $this->managerWith($this->storedSlider(100));
        $this->assertFalse($m2->verify('k', ['type' => 'slider', 'data' => 105]));

        [$m3] = $this->managerWith($this->storedSlider(100));
        $this->assertTrue($m3->verify('k', ['type' => 'slider', 'data' => '100']));
    }

    /** 测试：滑块数据非数字或存储缺 x 时验证失败 */
    public function testVerifySliderWithMalformedDataFails(): void
    {
        [$m1] = $this->managerWith($this->storedSlider(100));
        $this->assertFalse($m1->verify('k', ['type' => 'slider', 'data' => 'oops']));

        [$m2] = $this->managerWith(['type' => 'slider', 'attempts' => 0]);
        $this->assertFalse($m2->verify('k', ['type' => 'slider', 'data' => 100]));
    }

    /** 测试：容忍度可通过配置覆盖（slider 容忍 100 时大幅偏差可通过） */
    public function testVerifyToleranceOverridableViaConfig(): void
    {
        PosterConfig::merge(['captcha' => ['tolerance' => ['slider' => 100]]]);
        [$manager] = $this->managerWith($this->storedSlider(100));
        $this->assertTrue($manager->verify('k', ['type' => 'slider', 'data' => 199]));
    }

    /** 测试：存储 targets 为空数组时必须失败（校验需非空数据，防止篡改存储绕过） */
    public function testVerifyEmptyTargetsWithEmptyDataFails(): void
    {
        [$manager] = $this->managerWith(['type' => 'click', 'attempts' => 0, 'targets' => []]);
        $this->assertFalse($manager->verify('k', ['type' => 'click', 'data' => []]));
    }
}
