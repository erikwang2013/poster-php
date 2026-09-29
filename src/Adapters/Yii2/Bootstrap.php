<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Yii2;

use Yii;
use yii\base\BootstrapInterface;
use Erikwang2013\Poster\Captcha\CaptchaManager;

/**
 * 可选：把 CaptchaManager 注册进 Yii::$container，控制器即可构造注入。
 *
 *     // config/web.php
 *     'bootstrap'  => ['poster'],
 *     'components' => ['poster' => ['class' => PosterComponent::class]],
 *
 *     class CaptchaController extends Controller
 *     {
 *         public function __construct($id, $module, private CaptchaManager $captcha, $config = [])
 *         {
 *             parent::__construct($id, $module, $config);
 *         }
 *     }
 *
 * 不注册 PosterBuilder：它有状态（width/height/add 会累积），
 * 进了容器就成了共享单例，同一次请求里两处取用会互相串状态。
 * 需要它时用 Yii::$app->poster->builder。
 */
class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        // 闭包只在真正被注入时才解析 'poster' 组件：
        // 没配该组件时不应把 bootstrap 阶段也拖挂掉。
        Yii::$container->set(CaptchaManager::class, static function () use ($app): CaptchaManager {
            return $app->get('poster')->getCaptcha();
        });
    }
}
