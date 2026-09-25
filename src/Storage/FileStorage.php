<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use RuntimeException;

/**
 * 文件存储：key → md5(key).json（md5 归一化路径，key 不参与路径拼接，无目录穿越风险）。
 *
 * 并发约定：
 * - 读：fopen + flock(LOCK_SH) + stream_get_contents，不会读到写了一半的内容；
 * - 写：同目录临时文件 + rename() 原子替换，读方永远看到完整旧内容或完整新内容；
 * - incrementAttempts() 是读改写，需要额外串行化（见该方法注释）。
 */
class FileStorage implements StorageInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? PosterConfig::get('captcha.file.path') ?? sys_get_temp_dir() . '/poster-captcha';
        if (file_exists($this->path) && !is_dir($this->path)) {
            throw new RuntimeException("Path is not a directory: {$this->path}");
        }
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException("Cannot create directory: {$this->path}");
        }
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
        return $this->writeAtomic($this->filePath($key), $encoded);
    }

    public function get(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }
        $payload = $this->readPayload($file);
        if ($payload === null) {
            return null;
        }
        if (!isset($payload['expire_at'], $payload['data']) || $payload['expire_at'] < time()) {
            @unlink($file);
            return null;
        }
        return array_merge($payload['data'], ['attempts' => $payload['attempts'] ?? 0]);
    }

    public function del(string $key): bool
    {
        @unlink($this->filePath($key));
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return 0;
        }
        // 读改写必须整体串行：写方用 rename() 替换 inode，锁在数据文件上会随替换失效，
        // 因此用独立锁文件。锁文件放系统临时目录——存储目录里多出的文件会干扰
        // 「只清理 *.json 后 rmdir」的调用方（测试、部署脚本）。
        // ponytail: 每存储目录一把全局锁、仅本机有效；跨主机共享目录请用 redis 驱动（原子 INCR）。
        $lock = @fopen($this->lockPath(), 'c');
        $locked = $lock !== false && flock($lock, LOCK_EX);
        try {
            $payload = $this->readPayload($file);
            if ($payload === null) {
                return 0;
            }
            $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                return 0;
            }
            return $this->writeAtomic($file, $encoded) ? $payload['attempts'] : 0;
        } finally {
            if ($locked) {
                flock($lock, LOCK_UN);
            }
            if ($lock !== false) {
                fclose($lock);
            }
        }
    }

    /** 临时文件 + rename() 原子替换：读方不会看到半截内容 */
    private function writeAtomic(string $file, string $content): bool
    {
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $content) !== strlen($content)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /** 加共享锁读取原始 payload（不解码过期判断，调用方各自处理） */
    private function readPayload(string $file): ?array
    {
        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            return null;
        }
        $locked = flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        if ($locked) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        if ($content === false || $content === '') {
            return null;
        }
        $payload = json_decode($content, true);
        return is_array($payload) ? $payload : null;
    }

    private function filePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.json';
    }

    private function lockPath(): string
    {
        return sys_get_temp_dir() . '/poster-captcha-lock-' . md5($this->path) . '.lock';
    }
}
