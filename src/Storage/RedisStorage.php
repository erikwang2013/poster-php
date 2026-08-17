<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use Redis;
use RuntimeException;

class RedisStorage implements StorageInterface
{
    private Redis $redis;
    private string $prefix;

    public function __construct(?Redis $redis = null)
    {
        if ($redis !== null) {
            $this->redis = $redis;
        } else {
            if (!extension_loaded('redis') || !class_exists('Redis')) {
                throw new RuntimeException('Redis extension is not loaded');
            }
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
        $this->prefix = PosterConfig::get('captcha.redis.prefix', 'poster:captcha:');
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        $this->redis->setex(
            $this->prefix . $key, $ttl,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        // 独立原子计数键：get+setex 非原子会丢计数，INCR 无此问题
        return $this->redis->setex($this->prefix . $key . ':att', $ttl, 0);
    }

    public function get(string $key): ?array
    {
        $content = $this->redis->get($this->prefix . $key);
        if ($content === false) {
            return null;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return null;
        }
        return array_merge($payload['data'], [
            'attempts' => (int) $this->redis->get($this->prefix . $key . ':att'),
        ]);
    }

    public function del(string $key): bool
    {
        $this->redis->del([$this->prefix . $key, $this->prefix . $key . ':att']);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function incrementAttempts(string $key): int
    {
        $counterKey = $this->prefix . $key . ':att';
        $attempts = $this->redis->incr($counterKey);
        if ($attempts === 1) {
            // 旧版本写入的键没有计数键，首次 INCR 时补上过期时间
            $ttl = $this->redis->ttl($this->prefix . $key);
            if ($ttl > 0) {
                $this->redis->expire($counterKey, $ttl);
            }
        }
        return $attempts;
    }
}
