<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

class StorageFactory
{
    public static function create(?string $driver = null): StorageInterface
    {
        $driver ??= 'auto';

        if ($driver === 'auto') {
            if (extension_loaded('redis')) {
                return new RedisStorage();
            }
            if (session_status() === PHP_SESSION_ACTIVE || headers_sent() === false) {
                return new SessionStorage();
            }
            return new FileStorage();
        }

        return match ($driver) {
            'redis' => new RedisStorage(),
            'session' => new SessionStorage(),
            'file' => new FileStorage(),
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }
}
