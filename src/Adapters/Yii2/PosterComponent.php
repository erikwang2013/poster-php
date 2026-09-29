<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Yii2;

use Yii;
use yii\base\Component;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;

/**
 * Yii2 应用组件。
 *
 *     // config/web.php
 *     'components' => [
 *         'poster' => ['class' => Erikwang2013\Poster\Adapters\Yii2\PosterComponent::class],
 *     ],
 *
 *     Yii::$app->poster->captcha->create('click')->generate();   // 经 getCaptcha() 魔术访问
 *     Yii::$app->poster->builder->width(750)->save('poster.jpg');
 *
 * 配置读应用的 config/poster.php（composer require 时 Installer 已发布），
 * 读不到则回落包内默认值。
 */
class PosterComponent extends Component
{
    public function init(): void
    {
        parent::init();

        // 用 @app 而不是 cwd 定位配置：php-fpm 下 cwd 常是 web/ 或 public/，
        // PosterConfig::findProjectConfig() 从 cwd 往上找会漏掉应用真正的 config/poster.php，
        // 静默回落包内默认值 —— 用户改了配置却不生效。
        $appConfig = Yii::getAlias('@app/config/poster.php', false);
        PosterConfig::load(is_string($appConfig) && is_file($appConfig) ? $appConfig : null);
    }

    /** 验证码管理器：本身无状态，可安全共享 */
    public function getCaptcha(): CaptchaManager
    {
        return new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
    }

    /**
     * 海报构建器：有状态（width()/height()/add() 会累积），每次调用返回新实例。
     * 正因如此本组件不把它暴露成应用单例，Bootstrap 也不往容器里注册它。
     */
    public function getBuilder(): PosterBuilder
    {
        return new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver')));
    }
}
