<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz>
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\TrajectoryVerifier;
use Erikwang2013\Poster\PosterConfig;
use PHPUnit\Framework\TestCase;

class TrajectoryVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        PosterConfig::reset();
    }

    private function enable(array $overrides = []): TrajectoryVerifier
    {
        PosterConfig::merge(['captcha' => ['trajectory' => $overrides + ['enabled' => true]]]);
        return new TrajectoryVerifier();
    }

    /** 测试：默认关闭（误杀触屏/无障碍设备风险高），此时任何输入都放行 */
    public function testDisabledByDefaultAcceptsLegacyPayload(): void
    {
        $verifier = new TrajectoryVerifier();
        $this->assertFalse(TrajectoryVerifier::isEnabled());
        $this->assertTrue($verifier->verify(123));
        $this->assertTrue($verifier->verify(['angle' => 30]));
        $this->assertTrue($verifier->verify(null));
    }

    /** 测试：开启后缺少轨迹（旧前端只传数值）一律拒绝 */
    public function testEnabledRejectsMissingTrail(): void
    {
        $verifier = $this->enable();
        $this->assertFalse($verifier->verify(100));
        $this->assertFalse($verifier->verify(['x' => 100]));
        $this->assertFalse($verifier->verify(['x' => 100, 'trail' => [], 'duration' => 1000]));
        $this->assertFalse($verifier->verify(['x' => 100, 'trail' => [[0, 0, 0]], 'duration' => 1000]));
    }

    /** 测试：采样点不足、耗时越界、轨迹元素畸形均拒绝 */
    public function testMinPointsDurationWindowAndMalformedPoints(): void
    {
        $verifier = $this->enable(['min_points' => 4, 'min_duration' => 300, 'max_duration' => 5000]);
        $human = [[0, 0, 0], [30, 3, 400], [31, 4, 700], [100, 9, 1100]];

        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 900, 'trail' => array_slice($human, 0, 3)]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 200, 'trail' => $human]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 9000, 'trail' => $human]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 'abc', 'trail' => $human]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 900, 'trail' => [[0, 0, 0], 'oops', [31, 4, 700], [100, 9, 1100]]]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 900, 'trail' => [[0, 0, 0], [30, [3], 400], [31, 4, 700], [100, 9, 1100]]]));
    }

    /** 测试：人手轨迹（起步/收尾慢、中段快）通过 */
    public function testHumanLikeTrailsPass(): void
    {
        $verifier = $this->enable();
        // 典型加减速
        $this->assertTrue($verifier->verify(['x' => 100, 'duration' => 900, 'trail' => [
            [0, 0, 0], [4, 2, 150], [18, 5, 300], [45, 9, 450], [76, 12, 600], [94, 13, 750], [100, 14, 900],
        ]]));
        // 途中犹豫停顿
        $this->assertTrue($verifier->verify(['x' => 100, 'duration' => 1200, 'trail' => [
            [0, 0, 0], [30, 3, 180], [31, 4, 400], [33, 3, 620], [70, 8, 800], [100, 9, 1200],
        ]]));
    }

    /** 测试：脚本式匀速直线轨迹（R²≈1）被拒，等步长带抖动也拒 */
    public function testLinearScriptTrailsAreRejected(): void
    {
        $verifier = $this->enable();
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 800, 'trail' => [
            [0, 0, 0], [25, 0, 200], [50, 0, 400], [75, 0, 600], [100, 0, 800],
        ]]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 800, 'trail' => [
            [0, 0, 0], [26, 1, 200], [49, -1, 400], [76, 1, 600], [100, 0, 800],
        ]]));
    }

    /** 测试：无位移 / 时间戳全相同等退化轨迹按机器处理 */
    public function testDegenerateTrailsAreRejected(): void
    {
        $verifier = $this->enable();
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 800, 'trail' => [
            [50, 50, 0], [50, 50, 200], [50, 50, 400], [50, 50, 800],
        ]]));
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 800, 'trail' => [
            [0, 0, 300], [30, 3, 300], [60, 6, 300], [90, 9, 300],
        ]]));
    }

    /** 测试：旋转验证码用 angle 字段，轨迹校验对 slider/rotate 通用 */
    public function testRotatePayloadWithTrail(): void
    {
        $verifier = $this->enable();
        $this->assertTrue($verifier->verify(['angle' => 120, 'duration' => 1000, 'trail' => [
            [0, 0, 0], [5, 1, 200], [9, 2, 400], [40, 6, 600], [85, 9, 800], [100, 10, 1000],
        ]]));
    }

    /** 测试：阈值可配置（max_linearity 调低后允许更接近直线的轨迹） */
    public function testThresholdsComeFromConfig(): void
    {
        $verifier = $this->enable(['max_linearity' => 0.5, 'min_points' => 10]);
        $this->assertFalse($verifier->verify(['x' => 100, 'duration' => 900, 'trail' => [
            [0, 0, 0], [4, 2, 150], [18, 5, 300], [45, 9, 450], [76, 12, 600], [94, 13, 750], [100, 14, 900],
        ]]), '点数不足 10 应拒绝');
    }
}
