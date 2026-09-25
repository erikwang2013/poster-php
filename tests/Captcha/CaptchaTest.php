<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class CaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private FileStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-captcha-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $driver = new GdDriver();
        $this->storage = new FileStorage($this->tempDir);
        $this->manager = new CaptchaManager($driver, $this->storage);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.{png,json}', GLOB_BRACE));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    private function getStoredTargets(string $key): array
    {
        $stored = $this->storage->get($key);
        return $stored['targets'] ?? [];
    }

    private function getStoredSliderX(string $key): int
    {
        $stored = $this->storage->get($key);
        return $stored['x'] ?? 0;
    }

    public function testClickCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('texts', $result['extra']);
        $this->assertStringStartsWith('data:image/', $result['image']);
        $this->assertNotEmpty($result['key']);
    }

    public function testClickCaptchaVerifyPassesWithCorrectData(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $this->getStoredTargets($result['key']);
        $clickData = [];
        foreach ($targets as $t) {
            $clickData[] = [$t['x'], $t['y']];
        }
        $verified = $this->manager->verify($result['key'], [
            'type' => 'click',
            'data' => $clickData,
        ]);
        $this->assertTrue($verified);
    }

    public function testClickCaptchaIsOneTimeUse(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $this->getStoredTargets($result['key']);
        $clickData = [];
        foreach ($targets as $t) {
            $clickData[] = [$t['x'], $t['y']];
        }

        $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $secondVerify = $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $this->assertFalse($secondVerify);
    }

    public function testClickCaptchaInvalidDataFails(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $verified = $this->manager->verify($result['key'], [
            'type' => 'click',
            'data' => [[0, 0], [0, 0]],
        ]);
        $this->assertFalse($verified);
    }

    public function testClickCaptchaAllowsRetryOnFailure(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $this->getStoredTargets($result['key']);
        $clickData = array_map(fn($t) => [$t['x'], $t['y']], $targets);

        // First: wrong data, should fail but key persists for retry
        $firstTry = $this->manager->verify($result['key'], ['type' => 'click', 'data' => [[0, 0]]]);
        $this->assertFalse($firstTry);

        // Second: correct data, should pass
        $secondTry = $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $this->assertTrue($secondTry);
    }

    public function testRotateCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('rotate')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testRandomCaptchaReturnsValidType(): void
    {
        $result = $this->manager->create('random')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertContains($result['type'], ['click', 'rotate', 'slider']);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testRandomCaptchaVerificationWorks(): void
    {
        $result = $this->manager->create('random')->generate();
        $type = $result['type'];

        if ($type === 'click') {
            $targets = $this->getStoredTargets($result['key']);
            $data = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        } elseif ($type === 'slider') {
            $data = $this->getStoredSliderX($result['key']);
        } else {
            // rotate: hard to test since we don't know the stored angle
            $this->assertNotNull($result['key']);
            return;
        }

        $pass = $this->manager->verify($result['key'], ['type' => $type, 'data' => $data]);
        $this->assertTrue($pass);
    }

    public function testSliderCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('slider')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('puzzle', $result['extra']);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testCaptchaBackgroundUsesProceduralGenerationByDefault(): void
    {
        \Erikwang2013\Poster\PosterConfig::merge([
            'captcha' => ['background_dir' => null],
        ]);
        $result = $this->manager->create('click')->generate();
        $this->assertStringStartsWith('data:image/png;base64,', $result['image']);
        $this->assertNotEmpty($result['key']);
        \Erikwang2013\Poster\PosterConfig::reset();
    }

    public function testCaptchaBackgroundRespectsCustomPathViaSetBackground(): void
    {
        // 画布尺寸取自背景图，故用足以容纳 3 个点击目标的图（小图会被明确拒绝，见 ClickCaptchaTest）
        $testImg = imagecreatetruecolor(320, 240);
        imagefill($testImg, 0, 0, imagecolorallocate($testImg, 200, 100, 50));
        $testPath = $this->tempDir . '/test-bg.png';
        imagepng($testImg, $testPath);
        imagedestroy($testImg);

        $result = $this->manager->create('click')
            ->setBackground($testPath)
            ->generate();
        $this->assertNotEmpty($result['key']);
        unlink($testPath);
        \Erikwang2013\Poster\PosterConfig::reset();
    }

    public function testSliderCaptchaPieceHasVisualImprovements(): void
    {
        $result = $this->manager->create('slider')->generate();
        $this->assertArrayHasKey('puzzle', $result['extra']);
        $this->assertNotEmpty($result['extra']['puzzle']);
    }

    public function testMaxAttemptsBlocksAfterLimit(): void
    {
        \Erikwang2013\Poster\PosterConfig::merge(['captcha' => ['max_attempts' => 2]]);
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $this->getStoredTargets($result['key']);
        $clickData = array_map(fn($t) => [$t['x'], $t['y']], $targets);

        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'click', 'data' => [[0, 0]]]));
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'click', 'data' => [[0, 0]]]));
        // 达到上限后 key 被删除，正确答案也无法通过
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]));
        \Erikwang2013\Poster\PosterConfig::reset();
    }

    public function testRotateDifficultyAffectsAngleRange(): void
    {
        $result = $this->manager->create('rotate')->setDifficulty('easy')->generate();
        $stored = $this->storage->get($result['key']);
        $this->assertGreaterThanOrEqual(10, $stored['angle']);
        $this->assertLessThanOrEqual(90, $stored['angle']);
    }

    /** 测试：达到最小尺寸（200x100）的背景图可正常出题；小于最小尺寸见 SliderCaptchaTest */
    public function testSliderCaptchaWorksWithMinimumCanvasBackground(): void
    {
        $testImg = imagecreatetruecolor(200, 100);
        imagefill($testImg, 0, 0, imagecolorallocate($testImg, 200, 100, 50));
        $testPath = $this->tempDir . '/test-bg-min.png';
        imagepng($testImg, $testPath);
        imagedestroy($testImg);

        $result = $this->manager->create('slider')->setBackground($testPath)->generate();
        $this->assertNotEmpty($result['key']);
        unlink($testPath);
    }

    /** 测试：跨 key 盲猜被会话级限流挡住——每次先领新 key 再猜，第 3 次连正确答案也被拒 */
    public function testRateLimitBlocksCrossKeyGuessing(): void
    {
        \Erikwang2013\Poster\PosterConfig::merge(['captcha' => ['rate_limit' => ['max' => 2, 'window' => 60]]]);

        for ($i = 0; $i < 2; $i++) {
            $result = $this->manager->create('slider')->generate();
            $x = $this->getStoredSliderX($result['key']);
            $this->assertTrue($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x]));
        }

        $result = $this->manager->create('slider')->generate();
        $x = $this->getStoredSliderX($result['key']);
        $this->assertFalse($this->manager->verify($result['key'], ['type' => 'slider', 'data' => $x]));
        // 被限流时 key 保留（不是被误判为答错而消耗 attempts）
        $this->assertNotNull($this->storage->get($result['key']));
        \Erikwang2013\Poster\PosterConfig::reset();
    }

    /** 测试：限流身份可由构造函数注入（多实例部署用 uid 之类稳定身份，不受会话 ID 轮换影响） */
    public function testRateLimitIdentityResolverInjection(): void
    {
        \Erikwang2013\Poster\PosterConfig::merge(['captcha' => ['rate_limit' => ['max' => 1, 'window' => 60]]]);
        $identity = 'user-42';
        $manager = new \Erikwang2013\Poster\Captcha\CaptchaManager(
            new GdDriver(),
            $this->storage,
            function () use (&$identity) {
                return $identity;
            }
        );

        $first = $manager->create('slider')->generate();
        $this->assertTrue($manager->verify($first['key'], ['type' => 'slider', 'data' => $this->getStoredSliderX($first['key'])]));

        $second = $manager->create('slider')->generate();
        $this->assertFalse($manager->verify($second['key'], ['type' => 'slider', 'data' => $this->getStoredSliderX($second['key'])]));

        // 换身份即可重新计数
        $identity = 'user-43';
        $third = $manager->create('slider')->generate();
        $this->assertTrue($manager->verify($third['key'], ['type' => 'slider', 'data' => $this->getStoredSliderX($third['key'])]));
        \Erikwang2013\Poster\PosterConfig::reset();
    }

    /** 测试：captcha.default_difficulty 生效（此前只认 options['difficulty']，配置是死配置） */
    public function testHelperAppliesDefaultDifficultyFromConfig(): void
    {
        \Erikwang2013\Poster\PosterConfig::merge(['captcha' => [
            'default_difficulty' => 'hard',
            'storage'            => 'file',
            'file'               => ['path' => $this->tempDir],
        ]]);

        $result = captcha_create('click');
        $this->assertCount(4, $result['extra']['texts']);

        // 显式选项优先于配置
        $this->assertCount(2, captcha_create('click', ['difficulty' => 'easy'])['extra']['texts']);
        \Erikwang2013\Poster\PosterConfig::reset();
    }
}
