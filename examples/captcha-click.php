<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

require __DIR__ . '/../vendor/autoload.php';

$manager = new Erikwang2013\Poster\Captcha\CaptchaManager(
    Erikwang2013\Poster\Drivers\DriverFactory::create(),
    new Erikwang2013\Poster\Storage\FileStorage()
);
$result = $manager->create('click')->setDifficulty('easy')->generate();

echo "Key: " . $result['key'] . "\n";
echo "Image: " . substr($result['image'], 0, 60) . "...\n";
echo "Texts:\n";
foreach ($result['extra']['texts'] as $t) {
    echo "  Order {$t['order']}: \"{$t['text']}\"\n";
}
