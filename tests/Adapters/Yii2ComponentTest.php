<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Yii2 适配层。CI 里没有 Yii2，所以文件末尾用最小 stub 顶替
 * yii\base\Component / yii\base\BootstrapInterface / Yii —— 只实现本组件真正
 * 用到的那点契约（构造时调 init()、魔术 getter、getAlias/setAlias、容器 set），
 * Component 的行为/事件等一概不参与，也就不在这里假装验证。
 *
 * stub 全部带 class_exists 守卫：装了真 Yii2 就以真的为准，测试对真 Yii2 同样成立。
 * 需要真 Yii2 的部分（应用配置里注册组件、Yii::$container 真正参与控制器装配）
 * 未做自动化测试。
 */

namespace Erikwang2013\Poster\Tests\Adapters {

    use Erikwang2013\Poster\Adapters\Yii2\Bootstrap;
    use Erikwang2013\Poster\Adapters\Yii2\PosterComponent;
    use Erikwang2013\Poster\Captcha\CaptchaManager;
    use Erikwang2013\Poster\Poster\PosterBuilder;
    use Erikwang2013\Poster\PosterConfig;
    use Erikwang2013\Poster\Storage\StorageFactory;
    use PHPUnit\Framework\TestCase;

    class Yii2ComponentTest extends TestCase
    {
        private string $appDir;

        protected function setUp(): void
        {
            $this->appDir = sys_get_temp_dir() . '/poster-yii2-' . uniqid();
            mkdir($this->appDir . '/config', 0755, true);

            \Yii::setAlias('@app', $this->appDir);
            \Yii::$container = new ContainerStub();
        }

        protected function tearDown(): void
        {
            @unlink($this->appDir . '/config/poster.php');
            @rmdir($this->appDir . '/config');
            @rmdir($this->appDir);

            PosterConfig::reset();
            StorageFactory::reset();
            StorageFactory::setPsr16Pool(null);
        }

        private function writeAppConfig(string $body): void
        {
            file_put_contents($this->appDir . '/config/poster.php', "<?php return $body;");
        }

        /** 应用 config/poster.php 经 @app 定位后被加载 */
        public function testInitLoadsAppConfigViaAlias(): void
        {
            $this->writeAppConfig("['captcha' => ['ttl' => 777]]");

            new PosterComponent();

            $this->assertSame(777, PosterConfig::get('captcha.ttl'));
        }

        /** 应用没放配置时回落包内默认值，而不是崩掉 */
        public function testInitFallsBackToPackageDefaults(): void
        {
            new PosterComponent();

            $this->assertSame(300, PosterConfig::get('captcha.ttl'));
        }

        /** 两个 getter 都可用：魔术属性（Yii2 的 Component::__get）与方法调用 */
        public function testCaptchaIsReachableBothWays(): void
        {
            $component = new PosterComponent();

            $this->assertInstanceOf(CaptchaManager::class, $component->captcha, '经 Yii2 魔术 getter 访问');
            $this->assertInstanceOf(CaptchaManager::class, $component->getCaptcha());
        }

        /** builder 有状态，必须每次给新实例——否则同一次请求里两处取用会互相串元素 */
        public function testBuilderIsFreshOnEveryAccess(): void
        {
            $component = new PosterComponent();

            $this->assertInstanceOf(PosterBuilder::class, $component->getBuilder());
            $this->assertNotSame($component->getBuilder(), $component->getBuilder());
        }

        /** Bootstrap 只注册 CaptchaManager；注册后容器闭包能取出真实例 */
        public function testBootstrapRegistersCaptchaManager(): void
        {
            $component = new PosterComponent();
            $container = \Yii::$container;

            (new Bootstrap())->bootstrap(new AppStub($component));

            $this->assertTrue($container->has(CaptchaManager::class));
            $this->assertArrayNotHasKey(
                PosterBuilder::class,
                $container->definitions,
                'PosterBuilder 有状态，不得进容器'
            );

            $definition = $container->definitions[CaptchaManager::class];
            $this->assertInstanceOf(CaptchaManager::class, $definition());
        }
    }

    /** Yii::$container 的最小替身：只记 set()/has()，够断言用 */
    class ContainerStub
    {
        public array $definitions = [];

        public function set(string $class, $definition = []): void
        {
            $this->definitions[$class] = $definition;
        }

        public function has(string $class): bool
        {
            return isset($this->definitions[$class]);
        }
    }

    /** yii\base\Application 的最小替身：Bootstrap 只用到 get() */
    class AppStub
    {
        public function __construct(private PosterComponent $component)
        {
        }

        public function get(string $id): PosterComponent
        {
            return $this->component;
        }
    }
}

namespace yii\base {

    if (!class_exists(Component::class)) {
        class Component
        {
            public function __construct(array $config = [])
            {
                $this->init();
            }

            public function init(): void
            {
            }

            public function __get(string $name)
            {
                $getter = 'get' . $name;
                if (method_exists($this, $getter)) {
                    return $this->$getter();
                }
                throw new \Exception("Unknown property: $name");
            }
        }
    }

    if (!interface_exists(BootstrapInterface::class)) {
        interface BootstrapInterface
        {
            public function bootstrap($app);
        }
    }
}

namespace {

    if (!class_exists('Yii')) {
        class Yii
        {
            /** @var object|null */
            public static $container;

            /** @var array<string, string> */
            private static array $aliases = [];

            public static function setAlias(string $alias, string $path): void
            {
                self::$aliases[$alias] = rtrim($path, '/');
            }

            public static function getAlias(string $alias, bool $throwException = true)
            {
                foreach (self::$aliases as $name => $path) {
                    if (str_starts_with($alias, $name)) {
                        return $path . substr($alias, strlen($name));
                    }
                }
                if ($throwException) {
                    throw new \Exception("Invalid alias: $alias");
                }
                return false;
            }
        }
    }
}
