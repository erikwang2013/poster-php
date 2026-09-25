<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use RuntimeException;

/**
 * 会话存储。
 *
 * 会话未启动时 $_SESSION 的读写会静默失效（写入丢失、读取恒为 null），
 * 表现为「用户答对也被判 false」，因此这里直接抛 RuntimeException，
 * 而不是返回一个看起来正常、实际不存任何东西的存储。
 */
class SessionStorage implements StorageInterface
{
    private string $prefix = 'poster_captcha';

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $this->assertSessionActive();
        $_SESSION[$this->prefix][$key] = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return true;
    }

    public function get(string $key): ?array
    {
        $this->assertSessionActive();
        if (!isset($_SESSION[$this->prefix][$key])) {
            return null;
        }
        $entry = $_SESSION[$this->prefix][$key];
        if ($entry['expire_at'] < time()) {
            unset($_SESSION[$this->prefix][$key]);
            return null;
        }
        return array_merge($entry['data'], ['attempts' => $entry['attempts'] ?? 0]);
    }

    public function del(string $key): bool
    {
        $this->assertSessionActive();
        unset($_SESSION[$this->prefix][$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        $this->assertSessionActive();
        if (!isset($_SESSION[$this->prefix][$key])) {
            return 0;
        }
        $_SESSION[$this->prefix][$key]['attempts'] = ($_SESSION[$this->prefix][$key]['attempts'] ?? 0) + 1;
        return $_SESSION[$this->prefix][$key]['attempts'];
    }

    private function assertSessionActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'SessionStorage requires an active session: call session_start() first, '
                . 'or set captcha.storage to "file" / "redis" / "cache" in stateless contexts '
                . '(CLI, API, queue workers).'
            );
        }
    }
}
