<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Laravel 适配示例：图片 HTTP 端点（GET /captcha/{key}）+ 表单校验规则。
 *
 * 本仓库 CI 不含 Laravel，因此这里不 require 任何 Laravel 类：
 *   ① 上半部分是框架无关、直接能跑的演示（生成 → URL/PNG → 校验）；
 *   ② 下半部分是应用侧接线（注释形式），照抄进 Laravel 项目即可。
 */

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Poster\Adapters\Laravel\CaptchaImage;
use Erikwang2013\Poster\Adapters\Laravel\CaptchaImageController;
use Erikwang2013\Poster\Adapters\Laravel\CaptchaVerifier;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\FileStorage;

// ── ① 框架无关演示 ──────────────────────────────────────────────
$storage = new FileStorage(sys_get_temp_dir() . '/poster-example-laravel');
$manager = new CaptchaManager(DriverFactory::create(), $storage);
$image = new CaptchaImage($storage, $manager);

$captcha = $image->generate('click', ['difficulty' => 'easy']);
echo "key        : {$captcha['key']}\n";
echo "type       : {$captcha['type']}\n";
echo "图片 URL   : {$captcha['url']}   （<img src=\"...\"> 即可，页面里不再出现 base64）\n";

$png = (string) $image->png($captcha['key']);
echo "PNG 字节数 : " . strlen($png) . "\n";
echo "响应头     : " . json_encode(CaptchaImageController::headers($png), JSON_UNESCAPED_SLASHES) . "\n";
echo "文件头     : " . bin2hex(substr($png, 0, 8)) . "（89504e47 = PNG 魔数）\n";

// 校验：答案数据照旧放在存储里，CaptchaVerifier 自己读 type，调用方不用传
$targets = $storage->get($captcha['key'])['targets'];
$answer = array_map(fn($t) => [$t['x'], $t['y']], $targets);
$verifier = new CaptchaVerifier($storage, $manager);
echo "错误答案   : " . var_export($verifier->verify($captcha['key'], [[0, 0]]), true) . "（计数 +1，key 保留可重试）\n";
echo "正确答案   : " . var_export($verifier->verify($captcha['key'], $answer), true) . "（成功后 key 立即作废）\n";

/* ── ② 应用侧接线（Laravel，复制进项目） ─────────────────────────

routes/web.php
    use Erikwang2013\Poster\Adapters\Laravel\CaptchaImageController;

    Route::get('/captcha/{key}', [CaptchaImageController::class, 'show'])->name('poster.captcha.image');
    // 也可以交给 provider 自动注册（config/poster.php 之外的运行时配置）：
    // config(['poster.captcha.route.enabled' => true, 'poster.captcha.route.path' => '/captcha/{key}']);

控制器里生成（把 url 交给视图，不要交 base64）
    use Erikwang2013\Poster\Adapters\Laravel\CaptchaImage;

    public function login(CaptchaImage $image)
    {
        return view('login', ['captcha' => $image->generate('click', ['difficulty' => 'medium'])]);
    }

Blade 模板
    <img src="{{ $captcha['url'] }}" alt="验证码" onclick="this.src='{{ $captcha['url'] }}?t='+Date.now()">
    <input type="hidden" name="captcha_key" value="{{ $captcha['key'] }}">

提交校验（两种写法等价）
    use Erikwang2013\Poster\Adapters\Laravel\Rules\CaptchaRule;

    $key = (string) $request->input('captcha_key');
    $request->validate([
        'captcha_answer' => ['required', new CaptchaRule($key)],   // 对象写法，可注入自定义 CaptchaVerifier
        // 'captcha_answer' => ['required', 'captcha:'.$key],      // 字符串写法，provider 已注册同名扩展
    ]);
    // 用户答案直接透传：rotate 角度 / slider x / click [[x, y], ...]

存储用框架缓存（Laravel >= 8 的 Cache::store() 本身即 PSR-16）
    // config/poster.php: 'captcha' => ['storage' => 'cache', 'cache' => ['store' => 'redis']]
    // provider 会自动执行：StorageFactory::setPsr16Pool(Cache::store(config('poster.captcha.cache.store')));
    // ⚠️ 缓存池的 incrementAttempts() 是读改写、非原子，并发下尝试次数可能少算；
    //    需要精确计数就把 storage 换成 'redis'（原子 INCR）。

注意：路由与 provider 由应用侧装配，本仓库 CI 不含 Laravel，上述接线未做自动化测试。
──────────────────────────────────────────────────────────────── */
