<?php

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaManagerFactory
{
    public function __invoke(): CaptchaManager
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
    }
}
