<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class RotateCaptcha extends AbstractCaptcha
{
    private float $minAngle = 30;
    private float $maxAngle = 330;
    private float $actualAngle = 0;

    public function setAngleRange(float $min, float $max): static
    {
        $this->minAngle = max(1, $min);
        $this->maxAngle = min(359, $max);
        return $this;
    }

    protected function getType(): string
    {
        return 'rotate';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        $bg->resize(30, 30);
        $bg->circle(30);

        $this->actualAngle = mt_rand(intval($this->minAngle), intval($this->maxAngle));
        $bg->rotate($this->actualAngle, 'transparent');

        $this->store([
            'angle'     => $this->actualAngle,
            'orig_size' => ['width' => 30, 'height' => 30],
        ]);

        $image = $bg->output('png');
        $bg->destroy();

        return [
            'key'   => $this->key,
            'type'  => 'rotate',
            'image' => $image,
            'extra' => [],
        ];
    }
}
