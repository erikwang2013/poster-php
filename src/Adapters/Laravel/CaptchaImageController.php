<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

/**
 * 验证码图片端点：直接吐 PNG 字节流，取代把 base64 塞进 HTML 的做法。
 *
 * ⚠️ 需要应用侧注册路由（本仓库 CI 不含 Laravel，未做自动化测试）：
 *   // routes/web.php
 *   Route::get('/captcha/{key}', [CaptchaImageController::class, 'show'])->name('poster.captcha.image');
 * 或把 config/poster.php 之外的同名配置打开，由 CaptchaServiceProvider 自动注册：
 *   config(['poster.captcha.route.enabled' => true, 'poster.captcha.route.path' => '/captcha/{key}']);
 *
 * 响应头由 headers() 给出（纯函数，已被 tests/Storage 覆盖）：image/png + no-store，避免中间层缓存。
 */
class CaptchaImageController
{
    private CaptchaImage $image;

    public function __construct(?CaptchaImage $image = null)
    {
        $this->image = $image ?? new CaptchaImage();
    }

    /** 路由目标：GET /captcha/{key} */
    public function show(string $key)
    {
        $png = $this->image->png($key);
        if ($png === null) {
            abort(404);
        }
        return response($png, 200, self::headers($png));
    }

    /** 图片响应头：验证码一次性使用，任何缓存（浏览器/代理/CDN）都不该留下副本 */
    public static function headers(string $png): array
    {
        return [
            'Content-Type'   => 'image/png',
            'Content-Length' => (string) strlen($png),
            'Cache-Control'  => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'         => 'no-cache',
        ];
    }
}
