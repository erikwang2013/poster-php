<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use DateInterval;

/**
 * PSR-16 鸭子类型替身：本仓库不引入 psr/simple-cache，测试用这个内存池
 * 验证 Psr16Storage 只依赖 get()/set()/delete() 三个方法。
 *
 * 语义对齐 PSR-16：$ttl 为秒（DateInterval 也接受）、非正 TTL 表示立即过期、未命中返回 $default。
 */
class ArrayPsr16Pool
{
    /** @var array<string, array{value: mixed, expire_at: ?int}> */
    public array $items = [];

    /** @var array<string, mixed> 每次 set() 收到的 $ttl，便于断言「写回保留剩余 TTL」 */
    public array $setTtls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->items[$key])) {
            return $default;
        }
        $entry = $this->items[$key];
        if ($entry['expire_at'] !== null && $entry['expire_at'] < time()) {
            unset($this->items[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->setTtls[$key] = $ttl;
        if ($ttl instanceof DateInterval) {
            $ttl = (new \DateTimeImmutable())->add($ttl)->getTimestamp() - time();
        }
        if ($ttl !== null && $ttl <= 0) {
            unset($this->items[$key]);
            return true;
        }
        $this->items[$key] = ['value' => $value, 'expire_at' => $ttl === null ? null : time() + $ttl];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
