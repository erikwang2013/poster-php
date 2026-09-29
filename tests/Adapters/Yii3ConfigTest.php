<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Yii3 适配层中「不依赖 Yii3」的部分。config/params.php 与 config/di.php
 * 本身只是数组 + 闭包，可以脱离 yiisoft/config 直接跑，所以这里真跑真断言。
 *
 * 需要真 Yii3 的部分（config-plugin 的实际合并、容器解析闭包、从容器取 PSR-16 池）
 * 未做自动化测试 —— CI 里没有 Yii3。
 */

namespace Erikwang2013\Poster\Tests\Adapters;

use Erikwang2013\Poster\Adapters\Yii3\CaptchaManagerFactory;
use Erikwang2013\Poster\Adapters\Yii3\PosterBuilderFactory;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\FileStorage;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

class Yii3ConfigTest extends TestCase
{
    /** 覆盖层的命名空间键，params.php 与 di.php 必须一致 */
    private const NS = 'erikwang2013/poster-php';

    protected function tearDown(): void
    {
        PosterConfig::reset();
        StorageFactory::reset();
        StorageFactory::setPsr16Pool(null);
    }

    private static function configFile(string $name): string
    {
        return dirname(__DIR__, 2) . '/src/Adapters/Yii3/config/' . $name;
    }

    /**
     * 按 yiisoft/config 的方式包含 di.php：$params 是被包含文件作用域里的变量。
     * （di.php 里的 $params 正是靠这个作用域拿到的，不是全局变量。）
     */
    private function loadDi(array $params = []): array
    {
        return (static function (string $file, array $params): array {
            return require $file;
        })(self::configFile('di.php'), $params);
    }

    /** params.php 只提供空覆盖层：默认值的唯一来源是 config/poster.php */
    public function testParamsIsAnEmptyOverrideLayer(): void
    {
        $params = require self::configFile('params.php');

        $this->assertIsArray($params);
        $this->assertArrayHasKey(self::NS, $params);
        $this->assertSame([], $params[self::NS], '默认值不得抄进 params.php，否则字体路径/词表会出现双份真相');
    }

    /** 不传 params 时用包内 config/poster.php 的默认值 */
    public function testEmptyParamsKeepPackageDefaults(): void
    {
        $this->loadDi();

        $this->assertSame(300, PosterConfig::get('captcha.ttl'));
        $this->assertSame('random', PosterConfig::get('captcha.default_type'));
    }

    /** params 覆盖层真的落到 PosterConfig，且不误伤未覆盖的键 */
    public function testParamsOverrideReachesPosterConfig(): void
    {
        $this->loadDi([self::NS => ['captcha' => ['ttl' => 1234, 'storage' => 'file']]]);

        $this->assertSame(1234, PosterConfig::get('captcha.ttl'));
        $this->assertSame('file', PosterConfig::get('captcha.storage'));
        $this->assertSame(3, PosterConfig::get('captcha.max_attempts'), '未覆盖的键应保持包默认值');
    }

    /**
     * 回归护栏：yiisoft/di 只管理共享实例，而图像驱动持有当前画布
     * （GdDriver::$resource / ImagickDriver::$imagick）、PosterBuilder 累积 width/add。
     * 注册进容器 = 常驻进程下跨请求串状态，只能放无状态工厂。
     */
    public function testContainerRegistersOnlyStatelessServices(): void
    {
        $defs = $this->loadDi();

        foreach ([ImageDriverInterface::class, CaptchaManager::class, PosterBuilder::class] as $stateful) {
            $this->assertArrayNotHasKey($stateful, $defs, "$stateful 有状态，不得进容器");
        }

        $this->assertEqualsCanonicalizing(
            [StorageInterface::class, CaptchaManagerFactory::class, PosterBuilderFactory::class],
            array_keys($defs)
        );
    }

    /** 工厂每次产出独立实例（这正是它们替代容器的理由） */
    public function testFactoriesYieldFreshInstances(): void
    {
        $builders = new PosterBuilderFactory();
        $this->assertInstanceOf(PosterBuilder::class, $builders());
        $this->assertNotSame($builders(), $builders(), 'builder 有状态，必须每次新建');

        $managers = new CaptchaManagerFactory(new FileStorage(sys_get_temp_dir()));
        $this->assertInstanceOf(CaptchaManager::class, $managers());
        $this->assertNotSame($managers(), $managers(), 'manager 持有驱动（即画布），必须每次新建');
    }

    /** composer.json 声明的 config-plugin 路径必须真实存在（拼错会静默失效，Yii3 装包后毫无报错） */
    public function testComposerDeclaresExistingConfigFiles(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
        $plugin = $composer['extra']['config-plugin'] ?? [];

        $this->assertNotSame([], $plugin, 'composer.json 缺少 extra.config-plugin');

        foreach ($plugin as $group => $file) {
            $this->assertFileExists($root . '/' . $file, "extra.config-plugin.$group 指向的文件不存在");
        }
    }
}
