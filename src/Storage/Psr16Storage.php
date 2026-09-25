<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;

/**
 * PSR-16（simple-cache）存储适配，鸭子类型：接受任何具备 get() / set() / delete() 的对象
 * （PSR-16 池、Laravel 的 Cache::store()、其它框架的 PSR-16 包装器）。
 *
 * 不 require psr/simple-cache，扩展与缓存池都是可选的，包本身不因此产生硬依赖。
 *
 * ⚠️ 非原子：incrementAttempts() 是「读-改-写」实现，缓存池不提供原子自增时，
 * 并发提交可能互相覆盖，导致尝试次数被低估（失败次数少算，即重试次数可能多于 max_attempts）。
 * 需要精确计数请用 redis 驱动（其用 INCR 原子计数）。
 *
 * 过期以池的 TTL 为准，payload 内的 expire_at 只作兜底读取与「写回时保留剩余 TTL」用。
 */
class Psr16Storage implements StorageInterface
{
    private object $pool;
    private string $prefix;

    public function __construct(object $pool, ?string $prefix = null)
    {
        if (!method_exists($pool, 'get') || !method_exists($pool, 'set') || !method_exists($pool, 'delete')) {
            throw new InvalidArgumentException(
                'PSR-16 pool must expose get() / set() / delete(), got ' . get_class($pool)
            );
        }
        $this->pool = $pool;
        $this->prefix = $prefix ?? PosterConfig::get('captcha.cache.prefix', 'poster:captcha:');
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return false;
        }
        // PSR-16 的 $ttl 为「秒」（0/负数表示立即过期，由池决定语义）
        return (bool) $this->pool->set($this->prefix . $key, $encoded, $ttl);
    }

    public function get(string $key): ?array
    {
        $payload = $this->decode($this->pool->get($this->prefix . $key));
        if ($payload === null || !isset($payload['data']) || !is_array($payload['data'])) {
            return null;
        }
        if (isset($payload['expire_at']) && $payload['expire_at'] < time()) {
            $this->pool->delete($this->prefix . $key);
            return null;
        }
        return array_merge($payload['data'], ['attempts' => $payload['attempts'] ?? 0]);
    }

    public function del(string $key): bool
    {
        return (bool) $this->pool->delete($this->prefix . $key);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        // 读改写（非原子，见类注释）：写回时按 expire_at 保留剩余 TTL，避免计数把整体有效期延长
        $payload = $this->decode($this->pool->get($this->prefix . $key));
        if ($payload === null || !isset($payload['data']) || !is_array($payload['data'])) {
            return 0;
        }
        $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return 0;
        }
        $ttl = isset($payload['expire_at']) ? intval($payload['expire_at']) - time() : 300;
        return $this->pool->set($this->prefix . $key, $encoded, max(1, $ttl)) ? $payload['attempts'] : 0;
    }

    private function decode(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }
        $payload = json_decode($raw, true);
        return is_array($payload) ? $payload : null;
    }
}
