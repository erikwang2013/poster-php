<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * FileStorage 并发读探针：由 FileStorageConcurrencyTest 用 proc_open 启动的独立进程，
 * 在另一个进程持续 incrementAttempts()（原地重写文件）的同时疯狂 get()，
 * 把「读到 null / 字段缺失」的次数打到 stdout。
 *
 * argv: [autoload.php, storage dir, key, 运行秒数]
 * stdout: {"ok":N,"bad":N}
 */

require $argv[1];

$storage = new Erikwang2013\Poster\Storage\FileStorage($argv[2]);
$key = $argv[3];
$deadline = microtime(true) + (float) $argv[4];

$ok = 0;
$bad = 0;

while (microtime(true) < $deadline) {
    $data = $storage->get($key);
    if ($data !== null
        && ($data['type'] ?? null) === 'click'
        && ($data['targets'] ?? null) === ['a', 'b']
    ) {
        $ok++;
    } else {
        $bad++;
    }
}

echo json_encode(['ok' => $ok, 'bad' => $bad]);
