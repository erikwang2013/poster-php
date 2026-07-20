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

        // Work at high resolution for quality, then downsample to output size
        $workSize = 300;
        $outputSize = 30;

        $bg->resize($workSize, $workSize);
        $bg->circle($workSize);

        $this->actualAngle = mt_rand(intval($this->minAngle), intval($this->maxAngle));
        $bg->rotate($this->actualAngle, 'transparent');

        $bg->resize($outputSize, $outputSize);

        $this->store([
            'angle'     => $this->actualAngle,
            'orig_size' => ['width' => $outputSize, 'height' => $outputSize],
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
