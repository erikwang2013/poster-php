<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

class CaptchaManager
{
    private ImageDriverInterface $imageDriver;
    private StorageInterface $storage;
    private ?CaptchaInterface $currentCaptcha = null;
    /** @var callable|null 身份解析器：返回限流所依据的身份串（登录用户可用 uid），默认 session_id/IP */
    private $identityResolver;
    private RateLimiter $rateLimiter;
    private TrajectoryVerifier $trajectoryVerifier;

    public function __construct(
        ImageDriverInterface $imageDriver,
        StorageInterface $storage,
        ?callable $identityResolver = null
    ) {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
        $this->identityResolver = $identityResolver;
        $this->rateLimiter = new RateLimiter($storage);
        $this->trajectoryVerifier = new TrajectoryVerifier();
    }

    public function create(string $type): CaptchaInterface
    {
        $this->currentCaptcha = CaptchaFactory::create($type, $this->imageDriver, $this->storage);
        return $this->currentCaptcha;
    }

    public function verify(string $key, array $data): bool
    {
        // 跨 key 的窗口限流先行：单 key 计数挡不住「每次换新 key 再猜一次」
        if (!$this->rateLimiter->allow($this->resolveIdentity())) {
            return false;
        }

        $stored = $this->storage->get($key);
        if ($stored === null) {
            return false;
        }

        // 先自增再判定：以存储的原子自增返回值为本次尝试序号，
        // 「读计数 → 校验 → 累加」之间的并发插入不再能放大放行次数。
        // 成功时会删 key，语义与旧的「大于等于才拦」等价。
        $maxAttempts = PosterConfig::get('captcha.max_attempts', 3);
        $attempts = $this->storage->incrementAttempts($key);
        if ($attempts <= 0) {
            // 自增不可用（键在读取后过期/被并发删除、内容损坏、存储写失败）：
            // 各实现均以 0 表示该状态，绝不是「第 1 次」。
            // 此时拿不到可信的序号，若退回读取快照就又回到并发可放大的老路，故直接失败关闭。
            return false;
        }
        if ($attempts > $maxAttempts) {
            $this->storage->del($key);
            return false;
        }

        $type = $data['type'] ?? '';
        $userData = $data['data'] ?? null;
        $result = $this->check($type, $stored, $userData);

        if ($result) {
            $this->storage->del($key);
        }

        return $result;
    }

    /** 限流身份：默认 session_id → REMOTE_ADDR → cli；多实例部署应注入 uid 之类的稳定身份 */
    private function resolveIdentity(): string
    {
        if ($this->identityResolver !== null) {
            $identity = call_user_func($this->identityResolver);
            if (is_string($identity) && $identity !== '') {
                return $identity;
            }
        }

        return session_id() ?: ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }

    private function check(string $type, array $stored, mixed $userData): bool
    {
        if ($type !== ($stored['type'] ?? '')) {
            return false;
        }

        $tolerance = PosterConfig::get('captcha.tolerance', ['click' => 18, 'rotate' => 5, 'slider' => 4]);

        return match ($type) {
            'click'  => $this->checkClick($stored, $userData, $tolerance['click']),
            'rotate' => $this->checkRotate($stored, $userData, $tolerance['rotate']),
            'slider' => $this->checkSlider($stored, $userData, $tolerance['slider']),
            default  => false,
        };
    }

    private function checkClick(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_array($userData) || !isset($stored['targets']) || !is_array($stored['targets']) || $stored['targets'] === []) {
            return false;
        }
        if (count($userData) !== count($stored['targets'])) {
            return false;
        }
        foreach ($stored['targets'] as $i => $target) {
            $point = $userData[$i] ?? null;
            // 非数值坐标（含 [[[1,2],[3,4]]] 这类畸形嵌套）一律判失败：
            // 交给算术运算会抛 TypeError（500），且畸形输入不该换来未捕获异常
            if (!is_array($point)
                || !is_numeric($point[0] ?? null)
                || !is_numeric($point[1] ?? null)
                || !is_numeric($target['x'] ?? null)
                || !is_numeric($target['y'] ?? null)) {
                return false;
            }
            $dx = floatval($point[0]) - floatval($target['x']);
            $dy = floatval($point[1]) - floatval($target['y']);
            if (sqrt($dx * $dx + $dy * $dy) > $tolerance) {
                return false;
            }
        }
        return true;
    }

    private function checkRotate(array $stored, mixed $userData, int $tolerance): bool
    {
        $angle = $this->answerValue($userData, 'angle');
        if ($angle === null || !isset($stored['angle']) || !$this->trajectoryVerifier->verify($userData)) {
            return false;
        }
        $angle = fmod($angle, 360);
        if ($angle < 0) {
            $angle += 360;
        }
        $actual = floatval($stored['angle']);
        $diff = abs($angle - $actual);
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff <= $tolerance;
    }

    private function checkSlider(array $stored, mixed $userData, int $tolerance): bool
    {
        $x = $this->answerValue($userData, 'x');
        if ($x === null || !isset($stored['x']) || !$this->trajectoryVerifier->verify($userData)) {
            return false;
        }
        return abs($x - floatval($stored['x'])) <= $tolerance;
    }

    /**
     * 取用户提交的答案数值：老前端直接传数值，新前端传 ['x'|'angle' => .., 'trail' => ..]。
     * 取不到（缺字段/非数值）返回 null。
     */
    private function answerValue(mixed $userData, string $field): ?float
    {
        if (is_numeric($userData)) {
            return floatval($userData);
        }
        if (is_array($userData) && is_numeric($userData[$field] ?? null)) {
            return floatval($userData[$field]);
        }
        return null;
    }
}
