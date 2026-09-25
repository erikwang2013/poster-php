<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;

/**
 * 图片端点用的轻量外观：生成验证码时顺手把 PNG 字节缓存进存储（同一个 key 的条目的 'png' 字段，
 * 成功校验时随 del(key) 一起清掉），前端拿 URL 而不是 base64——省掉 33% 膨胀，也可被 HTTP 缓存策略控制。
 *
 * 用法（Laravel >= 10，需应用侧注册路由，见 examples/laravel-captcha.php）：
 *   Route::get('/captcha/{key}', [CaptchaImageController::class, 'show'])->name('poster.captcha.image');
 *
 * 本类自身不依赖任何 Laravel 类（route() 仅在有 Laravel 时才用），可在无框架环境下直接构造。
 */
final class CaptchaImage
{
    private StorageInterface $storage;
    private CaptchaManager $manager;
    private int $ttl;

    public function __construct(?StorageInterface $storage = null, ?CaptchaManager $manager = null, ?int $ttl = null)
    {
        $this->storage = $storage ?? StorageFactory::create(PosterConfig::get('captcha.storage'));
        $this->manager = $manager ?? new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            $this->storage
        );
        $this->ttl = $ttl ?? intval(PosterConfig::get('captcha.ttl', 300));
    }

    /**
     * 生成验证码并返回可直接塞进 <img src> 的信息（不含 base64）。
     *
     * @param array{difficulty?: string, background?: string} $options
     * @return array{key: string, type: string, url: string, extra: array}
     */
    public function generate(string $type = 'click', array $options = []): array
    {
        $captcha = $this->manager->create($type);
        if (isset($options['difficulty'])) {
            $captcha->setDifficulty($options['difficulty']);
        }
        if (isset($options['background'])) {
            $captcha->setBackground($options['background']);
        }
        $result = $captcha->generate();

        $png = self::decodeDataUri($result['image'] ?? '');
        if ($png !== null) {
            // 与答案同一个 key：校验成功时 del(key) 会一并清掉图片，不必单独回收
            $stored = $this->storage->get($result['key']) ?? [];
            $stored['png'] = base64_encode($png);
            $this->storage->set($result['key'], $stored, $this->ttl);
        }

        return [
            'key'   => $result['key'],
            'type'  => $result['type'],
            'url'   => self::url($result['key']),
            'extra' => $result['extra'] ?? [],
        ];
    }

    /** 取回 PNG 字节；key 不存在、已过期或不是本适配器生成的则返回 null */
    public function png(string $key): ?string
    {
        $stored = $this->storage->get($key);
        if (!isset($stored['png']) || !is_string($stored['png'])) {
            return null;
        }
        $png = base64_decode($stored['png'], true);
        return is_string($png) && $png !== '' ? $png : null;
    }

    /** 图片端点 URL：有 Laravel 命名路由就用它，否则回落到约定路径 */
    public static function url(string $key, string $path = '/captcha'): string
    {
        if (function_exists('route')) {
            try {
                return route('poster.captcha.image', ['key' => $key]);
            } catch (\Throwable $e) {
                // 应用没注册命名路由，回落到约定路径
            }
        }
        return rtrim($path, '/') . '/' . rawurlencode($key);
    }

    private static function decodeDataUri(string $dataUri): ?string
    {
        $pos = strpos($dataUri, 'base64,');
        if ($pos === false) {
            return null;
        }
        $decoded = base64_decode(substr($dataUri, $pos + 7), true);
        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
