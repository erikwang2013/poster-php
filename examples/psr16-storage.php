<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * PSR-16 存储示例：把任意具备 get/set/delete 的缓存池接进来当验证码存储。
 * - 本包不依赖 psr/simple-cache，池是「鸭子类型」，下面这个 30 行的内存池就能跑；
 * - Laravel 用户直接把 Cache::store() 传进去（它就是 PSR-16）；
 * - 也可以用任意 PSR-16 实现（Symfony Cache 的 Psr16Adapter 等）。
 */

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\Psr16Storage;
use Erikwang2013\Poster\Storage\StorageFactory;

/** 演示用内存池：只实现 Psr16Storage 用到的 get/set/delete */
final class DemoArrayPool
{
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->items[$key] ?? null;
        if ($entry === null || $entry['expire_at'] < time()) {
            unset($this->items[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, $ttl = null): bool
    {
        if (is_int($ttl) && $ttl <= 0) {
            unset($this->items[$key]);          // PSR-16：非正 TTL = 立即过期
            return true;
        }
        $this->items[$key] = [
            'value'     => $value,
            'expire_at' => is_int($ttl) ? time() + $ttl : PHP_INT_MAX,   // null = 池默认（这里设为不过期）
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }
}

$pool = new DemoArrayPool();

// 1) 注入池 → 用 'cache' 驱动（未注入时 create('cache') 会抛异常，不会静默降级）
StorageFactory::setPsr16Pool($pool);
$storage = StorageFactory::create('cache');
echo "驱动      : " . get_class($storage) . "\n";
echo "池        : " . get_class($pool) . "\n";

// 2) 生成 + 校验：CaptchaManager 只认 StorageInterface，不关心后端是谁
$manager = new CaptchaManager(DriverFactory::create(), $storage);
$result = $manager->create('slider')->setDifficulty('easy')->generate();
$stored = $storage->get($result['key']);
echo "key       : {$result['key']}\n";
echo "已存储    : " . json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$answer = ['type' => 'slider', 'data' => $stored['x']];
echo "答错      : " . var_export($manager->verify($result['key'], ['type' => 'slider', 'data' => $stored['x'] + 100]), true) . "\n";
echo "答对      : " . var_export($manager->verify($result['key'], $answer), true) . "\n";

// 3) 也可以直接构造（跳过工厂），自定义前缀
$direct = new Psr16Storage($pool, 'acme:captcha:');
$direct->set('k1', ['type' => 'click'], 60);
echo "直接构造  : attempts=" . $direct->incrementAttempts('k1') . "\n";
echo "⚠️  incrementAttempts() 为读改写，非原子：池不支持原子自增时并发计数可能低估；需要精确计数请用 redis 驱动\n";

echo "示例执行完毕\n";
