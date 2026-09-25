<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\PosterConfig;

/**
 * 行为轨迹校验（slider / rotate）。
 *
 * data 支持两种形态：
 *  - 旧前端：直接是数值（滑块 x / 旋转角度）
 *  - 新前端：['x' => .., 'angle' => .., 'trail' => [[x, y, t], ...], 'duration' => 毫秒]
 *
 * captcha.trajectory.enabled 默认关闭，务必保持关闭后再灰度：
 * 触屏/手写笔设备、无鼠标环境、无障碍工具、远程桌面、低采样率浏览器的轨迹
 * 与「人」的统计特征差异很大，开启后误杀真实用户的风险高。开启时旧前端（只传数值）
 * 一律判失败，属于有意的破坏性变更。
 *
 * 判定条件（需全部满足）：
 *  1. 采样点数 >= min_points
 *  2. duration（毫秒）落在 [min_duration, max_duration]
 *  3. 线性度 <= max_linearity：轨迹在拖动方向上的位移投影 vs 时间做最小二乘拟合，
 *     取 R²。脚本按直线匀速插值拖动时 R² ≈ 1；人手的加减速与抖动会显著拉低 R²。
 */
class TrajectoryVerifier
{
    /** 配置是否开启轨迹校验 */
    public static function isEnabled(): bool
    {
        return (bool) PosterConfig::get('captcha.trajectory.enabled', false);
    }

    /**
     * 判定本次交互是否「像人」。未开启轨迹校验时恒为 true（保持旧前端兼容）。
     */
    public function verify(mixed $data): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        // 开启后必须带轨迹：只传数值的旧前端无法证明是人在操作
        if (!is_array($data) || !is_array($data['trail'] ?? null) || !is_numeric($data['duration'] ?? null)) {
            return false;
        }

        $points = [];
        foreach ($data['trail'] as $point) {
            if (!is_array($point)
                || !is_numeric($point[0] ?? null)
                || !is_numeric($point[1] ?? null)
                || !is_numeric($point[2] ?? null)) {
                return false;
            }
            $points[] = [floatval($point[0]), floatval($point[1]), floatval($point[2])];
        }

        $duration = floatval($data['duration']);
        if (count($points) < intval(PosterConfig::get('captcha.trajectory.min_points', 4))
            || $duration < floatval(PosterConfig::get('captcha.trajectory.min_duration', 300))
            || $duration > floatval(PosterConfig::get('captcha.trajectory.max_duration', 5000))) {
            return false;
        }

        return self::linearity($points) <= floatval(PosterConfig::get('captcha.trajectory.max_linearity', 0.99));
    }

    /**
     * 线性度 = 最小二乘拟合的 R²（位移投影 vs 时间），越大越像机器。
     * 退化情形（全程没位移 / 时间戳全相同 / 位移无方差）返回 1.0，按机器处理。
     */
    private static function linearity(array $points): float
    {
        $first = $points[0];
        $last = $points[count($points) - 1];
        $dx = $last[0] - $first[0];
        $dy = $last[1] - $first[1];
        $length = sqrt($dx * $dx + $dy * $dy);
        if ($length <= 0.0) {
            return 1.0;
        }

        $count = count($points);
        $sumT = 0.0;
        $sumS = 0.0;
        $sumTT = 0.0;
        $sumTS = 0.0;
        $orthogonal = [];
        foreach ($points as $point) {
            // 投影到弦方向：旋转轨迹的位移可能在 y 上，垂直方向的抖动不计入时间线性度
            $s = (($point[0] - $first[0]) * $dx + ($point[1] - $first[1]) * $dy) / $length;
            $t = $point[2];
            $orthogonal[] = [$t, $s];
            $sumT += $t;
            $sumS += $s;
            $sumTT += $t * $t;
            $sumTS += $t * $s;
        }

        $denominator = $count * $sumTT - $sumT * $sumT;
        if (abs($denominator) < 1e-9) {
            return 1.0;
        }
        $slope = ($count * $sumTS - $sumT * $sumS) / $denominator;
        $intercept = ($sumS - $slope * $sumT) / $count;
        $mean = $sumS / $count;

        $residual = 0.0;
        $total = 0.0;
        foreach ($orthogonal as $entry) {
            $predicted = $slope * $entry[0] + $intercept;
            $residual += ($entry[1] - $predicted) ** 2;
            $total += ($entry[1] - $mean) ** 2;
        }
        if ($total < 1e-9) {
            return 1.0;
        }

        return 1.0 - $residual / $total;
    }
}
