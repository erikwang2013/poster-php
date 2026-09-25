<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Captcha\ClickCaptcha;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClickCaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private FileStorage $storage;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-click-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
        $this->manager = new CaptchaManager(new GdDriver(), $this->storage);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        rmdir($this->tempDir);
        PosterConfig::reset();
    }

    private function generate(string $difficulty = 'medium'): array
    {
        return $this->manager->create('click')->setDifficulty($difficulty)->generate();
    }

    /** 测试：setWords 的自定义词会被写入点击目标，顺序从 1 递增 */
    public function testSetWordsCustomWordsUsedInTargets(): void
    {
        $result = $this->manager->create('click')
            ->setWords(['甲', '乙'])
            ->setDifficulty('easy')
            ->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(2, $targets);
        foreach ($targets as $i => $t) {
            $this->assertContains($t['text'], ['甲', '乙']);
            $this->assertSame($i + 1, $t['order']);
        }
    }

    /** 测试：setWords([]) 空数组时回退到配置的 click_words */
    public function testEmptyWordsFallsBackToConfiguredWords(): void
    {
        PosterConfig::merge(['captcha' => ['click_words' => ['子', '丑']]]);
        $result = $this->manager->create('click')->setWords([])->setDifficulty('easy')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(2, $targets);
        foreach ($targets as $t) {
            $this->assertContains($t['text'], ['子', '丑']);
        }
    }

    /** 测试：不同难度对应不同目标数量（easy=2 / medium=3 / hard=4）
     * 数据内联为循环而非 @dataProvider：doc-comment 元数据在 PHPUnit 11 已废弃，
     * 而 PHP 8.0 只能配 PHPUnit 9（不支持 attributes），内联两者都兼容。
     */
    public function testTargetCountByDifficulty(): void
    {
        foreach (['easy' => 2, 'medium' => 3, 'hard' => 4] as $difficulty => $expected) {
            $result = $this->generate($difficulty);
            $targets = $this->storage->get($result['key'])['targets'];
            $this->assertCount($expected, $targets, "难度 {$difficulty} 应生成 {$expected} 个目标");
        }
    }

    /** 测试：默认难度为 medium，生成 3 个目标 */
    public function testDefaultDifficultyIsMedium(): void
    {
        $result = $this->manager->create('click')->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(3, $targets);
    }

    /** 测试：extra.texts 与存储目标一一对应，包含 text 与 order */
    public function testExtraTextsMatchStoredTargets(): void
    {
        $result = $this->generate();
        $targets = $this->storage->get($result['key'])['targets'];
        $this->assertCount(count($targets), $result['extra']['texts']);
        foreach ($result['extra']['texts'] as $i => $item) {
            $this->assertSame($targets[$i]['text'], $item['text']);
            $this->assertSame($targets[$i]['order'], $item['order']);
        }
    }

    /** 测试：setTargetType 与 setWords 均为链式方法（返回自身） */
    public function testAdditionalSettersAreFluent(): void
    {
        $captcha = $this->manager->create('click');
        $this->assertInstanceOf(ClickCaptcha::class, $captcha->setTargetType('text'));
        $this->assertInstanceOf(ClickCaptcha::class, $captcha->setWords(['一']));
    }

    /** 测试：默认 targetType 为 text 可正常生成；不支持的类型在 generate 时抛异常并列出合法取值 */
    public function testTargetTypeValidatedOnGenerate(): void
    {
        $result = $this->manager->create('click')->generate();
        $this->assertSame('click', $result['type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('text');
        $this->manager->create('click')->setTargetType('image')->generate();
    }
}
