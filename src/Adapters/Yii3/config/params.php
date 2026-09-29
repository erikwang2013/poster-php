<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * Yii3 参数：覆盖 config/poster.php 里的任意键，键名与 config/poster.php 完全一致。
 *
 *     // config/params.php
 *     return [
 *         'erikwang2013/poster-php' => [
 *             'image'   => ['driver' => 'imagick'],
 *             'captcha' => ['storage' => 'cache', 'ttl' => 600],
 *         ],
 *     ];
 *
 * 默认空数组：默认值只有 config/poster.php 一个来源。
 * 在这里再抄一份（字体路径、click_words 词表……）就会出现双份真相，
 * 改一处漏一处。合并逻辑见 config/di.php。
 */

return [
    'erikwang2013/poster-php' => [],
];
