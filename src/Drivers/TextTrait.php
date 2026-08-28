<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

trait TextTrait
{
    protected function splitText(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
}
