<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel\Rules;

use Closure;
use Erikwang2013\Poster\Adapters\Laravel\CaptchaVerifier;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 验证码表单校验规则（Laravel >= 10；Laravel 9 及以下把 implements 换成 Illuminate\Contracts\Validation\Rule）。
 *
 * 两种写法等价：
 *   1) 对象：'captcha_answer' => ['required', new CaptchaRule($request->input('captcha_key'))]
 *   2) 字符串（CaptchaServiceProvider 已注册同名扩展）：'captcha_answer' => ['required', 'captcha:'.$key]
 *      字符串写法的 key 不能含逗号（本包生成的 key 是 32 位十六进制，安全）。
 *
 * 用户答案直接透传：rotate 传角度、slider 传 x、click 传 [[x, y], ...]。
 * ⚠️ 需要应用侧注册路由/服务提供者；本仓库 CI 不含 Laravel，未做自动化测试。
 */
class CaptchaRule implements ValidationRule
{
    public const MESSAGE = '验证码不正确或已过期，请重新获取';

    private string $key;
    private ?CaptchaVerifier $verifier;

    public function __construct(string $key, ?CaptchaVerifier $verifier = null)
    {
        $this->key = $key;
        $this->verifier = $verifier;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail(static::MESSAGE);
        }
    }

    /** 便于直接调用（Laravel 走 validate()，两者结果一致） */
    public function passes(string $attribute, mixed $value): bool
    {
        return ($this->verifier ?? new CaptchaVerifier())->verify($this->key, $value);
    }

    public function message(): string
    {
        return static::MESSAGE;
    }
}
