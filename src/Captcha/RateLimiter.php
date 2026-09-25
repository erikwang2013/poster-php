<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageInterface;

/**
 * 会话级固定窗口限流（跨 key 生效）。
 *
 * 单 key 的 captcha.max_attempts 只按 key 计数，挡不住「每次先领新 key 再猜一次」的盲猜：
 * slider 固定猜某个 x、rotate 固定猜某个角度，命中率 = 命中区间 / 参数空间，与 key 数无关。
 * 这里按「身份」（构造函数注入的解析器，默认 session_id() → REMOTE_ADDR → cli）
 * 在窗口内计数，超出 captcha.rate_limit.max 即拒绝。
 *
 * 计数键固定为 'rate:' . md5(identity)，窗口序号写进记录，窗口滚动即重新计数；
 * TTL 取窗口长度，过期由存储自动回收。计数取自 incrementAttempts() 的原子自增返回值。
 */
class RateLimiter
{
    public const KEY_PREFIX = 'rate:';

    private StorageInterface $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * 记录一次校验尝试。
     *
     * @return bool false = 本窗口内已超限，调用方应直接判失败。
     *              不抛异常：异常会向攻击者暴露「已被限流」这一状态。
     *
     * captcha.rate_limit.max <= 0 或 window <= 0 视为关闭限流（全量放行）。
     */
    public function allow(string $identity): bool
    {
        $max = intval(PosterConfig::get('captcha.rate_limit.max', 30));
        $window = intval(PosterConfig::get('captcha.rate_limit.window', 60));
        if ($max <= 0 || $window <= 0) {
            return true;
        }

        $key = self::KEY_PREFIX . md5($identity);
        $windowIndex = intdiv(time(), $window);
        $record = $this->storage->get($key);
        if ($record === null || intval($record['window'] ?? -1) !== $windowIndex) {
            $this->storage->set($key, ['window' => $windowIndex, 'attempts' => 0], $window);
        }

        $count = $this->storage->incrementAttempts($key);
        if ($count <= 0) {
            // 自增不可用（键已过期/写入失败，各存储实现均以 0 表示，绝不是「首次」）：
            // 拿不到计数就无法执行限流，此时必须失败关闭而不是当作第 1 次放行
            return false;
        }

        return $count <= $max;
    }
}
