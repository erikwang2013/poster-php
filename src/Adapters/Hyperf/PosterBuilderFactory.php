<?php

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterBuilderFactory
{
    public function __invoke(): PosterBuilder
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new PosterBuilder(
            DriverFactory::create(PosterConfig::get('image.driver'))
        );
    }
}
