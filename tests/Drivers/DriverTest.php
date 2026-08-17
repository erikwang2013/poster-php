<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImagickDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DriverTest extends TestCase
{
    public function testCreateReturnsCorrectSize(): void
    {
        $driver = new GdDriver();
        $driver->create(300, 200);
        $size = $driver->getSize();
        $this->assertSame(300, $size['width']);
        $this->assertSame(200, $size['height']);
        $driver->destroy();
    }

    public function testRectangleDrawsOnCanvas(): void
    {
        $driver = new GdDriver();
        $driver->create(100, 100);
        $driver->rectangle(10, 10, 80, 80, ['color' => '#FF0000', 'filled' => true]);
        $resource = $driver->getResource();
        $this->assertNotNull($resource);
        $this->assertInstanceOf(\GdImage::class, $resource);
        $driver->destroy();
    }

    public function testTextDrawsWithoutError(): void
    {
        $driver = new GdDriver();
        $driver->create(200, 100);
        $driver->text('Hello World', 10, 50, ['size' => 16, 'color' => '#000000']);
        $resource = $driver->getResource();
        $this->assertNotNull($resource);
        $driver->destroy();
    }

    public function testSaveCreatesFile(): void
    {
        $driver = new GdDriver();
        $driver->create(50, 50);
        $path = sys_get_temp_dir() . '/poster-test-save-' . uniqid() . '.jpg';
        $result = $driver->save($path);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        unlink($path);
        $driver->destroy();
    }

    public function testOutputReturnsDataUrl(): void
    {
        $driver = new GdDriver();
        $driver->create(50, 50);
        $output = $driver->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $decoded = base64_decode(substr($output, strpos($output, ',') + 1));
        $this->assertNotFalse($decoded);
        $this->assertGreaterThan(100, strlen($decoded));
        $driver->destroy();
    }

    public function testLoadRejectsOversizedImage(): void
    {
        $png = "\x89PNG\r\n\x1a\n";
        $ihdr = 'IHDR' . pack('N2', 10000, 10000) . pack('C5', 8, 6, 0, 0, 0);
        $png .= pack('N', 13) . $ihdr . pack('N', crc32($ihdr));
        $path = tempnam(sys_get_temp_dir(), 'poster-') . '.png';
        file_put_contents($path, $png);
        try {
            $this->expectException(RuntimeException::class);
            (new GdDriver())->load($path);
        } finally {
            unlink($path);
        }
    }

    public function testLoadRejectsCorruptImage(): void
    {
        $png = "\x89PNG\r\n\x1a\n";
        $ihdr = 'IHDR' . pack('N2', 10, 10) . pack('C5', 8, 6, 0, 0, 0);
        $png .= pack('N', 13) . $ihdr . pack('N', crc32($ihdr));
        $idat = 'IDAT' . 'this-is-not-zlib-data';
        $png .= pack('N', 21) . $idat . pack('N', crc32($idat));
        $png .= pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
        $path = tempnam(sys_get_temp_dir(), 'poster-') . '.png';
        file_put_contents($path, $png);
        try {
            $this->expectException(RuntimeException::class);
            (new GdDriver())->load($path);
        } finally {
            unlink($path);
        }
    }

    public function testImageAcceptsImagickOverlay(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick not loaded');
        }
        $canvas = new GdDriver();
        $canvas->create(100, 100);
        $overlay = new ImagickDriver();
        $overlay->create(20, 20);
        $canvas->image($overlay, 10, 10);
        $this->assertNotNull($canvas->getResource());
        $canvas->destroy();
        $overlay->destroy();
    }

    public function testImageAcceptsGdOverlayOnImagick(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick not loaded');
        }
        $canvas = new ImagickDriver();
        $canvas->create(100, 100);
        $overlay = new GdDriver();
        $overlay->create(20, 20);
        $canvas->image($overlay, 10, 10);
        $this->assertNotNull($canvas->getResource());
        $canvas->destroy();
        $overlay->destroy();
    }
}
