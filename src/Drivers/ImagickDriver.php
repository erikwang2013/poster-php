<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

use Erikwang2013\Poster\PosterConfig;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use InvalidArgumentException;
use RuntimeException;

class ImagickDriver implements ImageDriverInterface
{
    use TextTrait;

    private const MEMORY_LIMIT = 256 * 1024 * 1024;
    private const MAP_LIMIT = 512 * 1024 * 1024;
    private const PIXELS_LIMIT = 40000000;

    private ?Imagick $resource = null;

    public function __construct()
    {
        // 缺少扩展时立即失败，避免构造出空壳驱动、延后到首次调用才报 "Class Imagick not found"
        if (!extension_loaded('imagick') || !class_exists(Imagick::class)) {
            throw new RuntimeException('ImagickDriver requires the imagick extension; use GdDriver or image.driver=gd instead');
        }
        if (defined('Imagick::RESOURCETYPE_MEMORY')) {
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MEMORY_LIMIT);
        }
        if (defined('Imagick::RESOURCETYPE_MAP')) {
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAP_LIMIT);
        }
        if (defined('Imagick::RESOURCETYPE_PIXELS')) {
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_PIXELS, self::PIXELS_LIMIT);
        }
    }

    public function load(string $path): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("File not found: $path");
        }
        try {
            $this->resource = new Imagick($path);
        } catch (\ImagickException $e) {
            // 与 GdDriver 对齐：坏文件统一抛 RuntimeException
            throw new RuntimeException("Cannot read image: $path ({$e->getMessage()})", 0, $e);
        }
        return $this;
    }

    public function create(int $width, int $height): static
    {
        $this->guardSize($width, $height);
        $this->resource = new Imagick();
        $this->resource->newImage($width, $height, new ImagickPixel('transparent'));
        $this->resource->setImageFormat('png');
        return $this;
    }

    public function resize(int $width, int $height): static
    {
        $this->guardSize($width, $height);
        $this->requireImage()->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        return $this;
    }

    public function rotate(float $angle, string $bgColor = '#000000'): static
    {
        $pixel = $bgColor === 'transparent'
            ? new ImagickPixel('transparent')
            : new ImagickPixel($bgColor);
        $this->requireImage()->rotateImage($pixel, -$angle);
        return $this;
    }

    public function circle(int $diameter): static
    {
        $this->guardSize($diameter, $diameter);
        $image = $this->requireImage();
        $image->resizeImage($diameter, $diameter, Imagick::FILTER_LANCZOS, 1);

        $mask = new Imagick();
        $mask->newImage($diameter, $diameter, new ImagickPixel('transparent'));
        $mask->setImageFormat('png');

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->circle($diameter / 2, $diameter / 2, $diameter / 2, 0);
        $mask->drawImage($draw);
        $draw->clear();

        // DSTIN = 保留圆内的原像素（含原有 alpha），圆外置透明；
        // 与 GdDriver 的 circle()/image(radius=直径/2) 语义一致（旧代码用 COPYOPACITY 会把圆内 alpha 一并抹平）。
        // 注：本机未安装 imagick 扩展，此处为静态修改，未经实测。
        $image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $mask->clear();

        return $this;
    }

    public function crop(int $x, int $y, int $width, int $height): static
    {
        $this->guardSize($width, $height);
        $image = $this->requireImage();
        $image->cropImage($width, $height, $x, $y);
        $image->setImagePage(0, 0, 0, 0);
        return $this;
    }

    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $this->requireImage();
        // 未显式传 font 时用配置的默认字体（image.font）；显式传 null 可退回 Imagick 默认字体
        $fontFile   = array_key_exists('font', $options) ? $options['font'] : PosterConfig::get('image.font');
        $size       = $options['size'] ?? 16;
        $color      = $options['color'] ?? '#000000';
        $maxWidth   = $options['maxWidth'] ?? 0;
        $align      = $options['align'] ?? 'left';
        $lineHeight = $options['lineHeight'] ?? intval($size * 1.5);
        $angle      = $options['angle'] ?? 0;

        $draw = new ImagickDraw();
        // ImagickPixel 直接支持 8 位色 #RRGGBBAA，与 GdDriver 的 allocColor() 语义一致
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setFontSize($size);
        if ($fontFile && is_file($fontFile)) {
            $draw->setFont($fontFile);
        }
        $draw->setTextAlignment(match ($align) {
            'center' => Imagick::ALIGN_CENTER,
            'right'  => Imagick::ALIGN_RIGHT,
            default  => Imagick::ALIGN_LEFT,
        });

        if ($maxWidth > 0 && $fontFile) {
            // 与 GdDriver 共用 TextTrait 的换行实现（token 级测量 + 宽度缓存）
            $lines = $this->wrapText($text, intval($maxWidth), function (string $token) use ($draw) {
                $metrics = $this->resource->queryFontMetrics($draw, $token);
                return isset($metrics['textWidth']) ? intval(ceil($metrics['textWidth'])) : null;
            });
        } else {
            $lines = explode("\n", $text);
        }

        foreach ($lines as $i => $line) {
            $this->resource->annotateImage($draw, $x, $y + $i * $lineHeight, $angle, $line);
        }

        $draw->clear();
        return $this;
    }

    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static
    {
        $this->requireImage();
        $ov = $overlay->getResource();
        if ($ov instanceof \Imagick) {
            $ov = clone $ov;
        } elseif ($ov instanceof \GdImage) {
            ob_start();
            imagepng($ov);
            $ov = new Imagick();
            $ov->readImageBlob(ob_get_clean());
        } else {
            throw new RuntimeException('Unsupported overlay resource: expected GdImage or Imagick');
        }
        $destW = intval($options['width'] ?? $ov->getImageWidth());
        $destH = intval($options['height'] ?? $ov->getImageHeight());
        $this->guardSize($destW, $destH);

        // 先缩到目标尺寸再圆角/投影：radius 因此是「目标像素」，与 GdDriver 一致
        $ov->resizeImage($destW, $destH, Imagick::FILTER_LANCZOS, 1);

        if (($options['radius'] ?? 0) > 0) {
            $ov->roundCorners($options['radius'], $options['radius']);
        }

        if (isset($options['shadow'])) {
            $s = $options['shadow'];
            // shadowImage 的 opacity 是 0-100；这里同时接受 0-1 分数写法（与 GdDriver 的 shadowOpacityToAlpha 一致）
            $opacity = floatval($s['opacity'] ?? 50);
            if ($opacity <= 1) {
                $opacity *= 100;
            }
            $shadow = clone $ov;
            $shadow->setImageBackgroundColor(new ImagickPixel($s['color'] ?? '#00000033'));
            $shadow->shadowImage(max(0, min(100, $opacity)), $s['blur'] ?? 8, $s['offsetX'] ?? 4, $s['offsetY'] ?? 4);
            $this->resource->compositeImage($shadow, Imagick::COMPOSITE_OVER, $x, $y);
            $shadow->clear();
        }

        $this->resource->compositeImage($ov, Imagick::COMPOSITE_OVER, $x, $y);
        $ov->clear();
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): static
    {
        $this->requireImage();
        $draw = new ImagickDraw();
        $color = $options['color'] ?? '#FFFFFF';

        if (isset($options['opacity'])) {
            // opacity 夹到 [0,1]，与 GdDriver 一致
            $draw->setFillOpacity(max(0.0, min(1.0, floatval($options['opacity']))));
        } else {
            $draw->setFillColor(new ImagickPixel($color));
        }

        if (!($options['filled'] ?? true)) {
            $draw->setFillOpacity(0);
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeWidth(intval($options['strokeWidth'] ?? 1));
        }

        $radius = intval($options['radius'] ?? 0);
        if ($radius > 0) {
            $draw->roundRectangle($x, $y, $x + $width - 1, $y + $height - 1, $radius, $radius);
        } else {
            $draw->rectangle($x, $y, $x + $width - 1, $y + $height - 1);
        }

        $this->resource->drawImage($draw);
        $draw->clear();
        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): static
    {
        $this->requireImage();
        $draw = new ImagickDraw();
        $color = $options['color'] ?? '#FFFFFF';
        $draw->setFillColor(new ImagickPixel($color));

        if (!($options['filled'] ?? true)) {
            $draw->setFillOpacity(0);
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeWidth(1);
        }

        $draw->ellipse($cx, $cy, $rx, $ry, 0, 360);
        $this->resource->drawImage($draw);
        $draw->clear();
        return $this;
    }

    public function filledArc(int $cx, int $cy, int $w, int $h, int $startAngle, int $endAngle, array $options = []): static
    {
        $this->requireImage();
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel($options['color'] ?? '#FFFFFF'));

        $rx = $w / 2;
        $ry = $h / 2;
        $sx = $cx + $rx * cos(deg2rad($startAngle));
        $sy = $cy + $ry * sin(deg2rad($startAngle));
        $ex = $cx + $rx * cos(deg2rad($endAngle));
        $ey = $cy + $ry * sin(deg2rad($endAngle));

        $draw->pathStart();
        $draw->pathMoveToAbsolute($cx, $cy);
        $draw->pathLineToAbsolute($sx, $sy);
        $draw->pathEllipticArcAbsolute($rx, $ry, 0, false, true, $ex, $ey);
        $draw->pathClose();
        $draw->pathFinish();

        $this->resource->drawImage($draw);
        $draw->clear();
        return $this;
    }

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): static
    {
        $this->requireImage();
        $draw = new ImagickDraw();
        $draw->setStrokeColor(new ImagickPixel($options['color'] ?? '#000000'));
        $draw->setStrokeWidth(max(1, intval($options['width'] ?? 1)));
        $draw->line($x1, $y1, $x2, $y2);
        $this->resource->drawImage($draw);
        $draw->clear();
        return $this;
    }

    public function blur(int $radius = 1): static
    {
        $image = $this->requireImage();
        if ($radius < 1) {
            return $this;
        }
        $image->blurImage($radius, max(1, $radius * 0.5));
        return $this;
    }

    public function sharpen(float $amount = 1.0): static
    {
        $this->requireImage()->sharpenImage(0, $amount);
        return $this;
    }

    public function pixelate(int $blockSize = 3): static
    {
        $image = $this->requireImage();
        $w = $image->getImageWidth();
        $h = $image->getImageHeight();
        $bs = max(1, $blockSize);
        $image->scaleImage(max(1, intval($w / $bs)), max(1, intval($h / $bs)));
        $image->scaleImage($w, $h);
        return $this;
    }

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool
    {
        $image = $this->requireImage();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $format = strtolower($format);
        $encoded = self::encodeParam($format, $quality, func_num_args() >= 3);
        $prev = $image->getImageFormat();
        $image->setImageFormat($format);
        if (in_array($format, ['jpg', 'jpeg'])) {
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality($encoded);
        } elseif ($format === 'png') {
            // poster.png_compression（0-9）在 Imagick 侧对应 ZIP 压缩级别
            $image->setImageCompression(Imagick::COMPRESSION_ZIP);
            $image->setImageCompressionQuality($encoded);
            // 强制 RGBA 输出，理由同 output()：'png' 默认 24 位会把透明写成不透明
            $image->setImageFormat('png32');
        }
        $result = $image->writeImage($path);
        $image->setImageFormat($prev);
        return $result;
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        $image = $this->requireImage();
        $format = strtolower($format);
        $encoded = self::encodeParam($format, $quality, func_num_args() >= 3);
        $prev = $image->getImageFormat();
        $image->setImageFormat($format);
        if (in_array($format, ['jpg', 'jpeg'])) {
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality($encoded);
        } elseif ($format === 'png') {
            $image->setImageCompression(Imagick::COMPRESSION_ZIP);
            $image->setImageCompressionQuality($encoded);
            // 强制 RGBA 输出：'png' 默认写 24 位不带 alpha，透明区域落盘后变成不透明，
            // 圆角/圆形头像、透明水印在 GD/浏览器侧会变成黑块（Imagick 自己读回却是透明的，
            // 所以该问题只在跨库消费时暴露）。
            $image->setImageFormat('png32');
        }
        $data = $image->getImageBlob();
        $image->setImageFormat($prev);
        return 'data:' . self::mimeType($format) . ';base64,' . base64_encode($data);
    }

    public function getSize(): array
    {
        $image = $this->requireImage();
        return [
            'width'  => $image->getImageWidth(),
            'height' => $image->getImageHeight(),
        ];
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function clone(): static
    {
        $driver = new self();
        if ($this->resource !== null) {
            $driver->resource = clone $this->resource;
        }
        return $driver;
    }

    public function destroy(): void
    {
        if ($this->resource !== null) {
            // Imagick::destroy() 已废弃，用 clear() 释放底层资源并置空，避免 destroy() 后仍返回旧尺寸
            $this->resource->clear();
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * 与 GdDriver 一致的入参守卫（尺寸为正 + 像素预算）。
     * 注：本机未安装 imagick 扩展，此处为静态修改，未经实测。
     */
    private function guardSize(int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException("Width and height must be greater than 0, got {$width}x{$height}");
        }
        if ($width * $height > self::PIXELS_LIMIT) {
            throw new InvalidArgumentException(
                "Image too large: {$width}x{$height} (max " . self::PIXELS_LIMIT . ' pixels)'
            );
        }
    }

    /** 与 GdDriver 一致：destroy() 后所有操作给出明确异常。 */
    private function requireImage(): Imagick
    {
        if ($this->resource === null) {
            throw new RuntimeException('No image resource: call load() or create() first (the resource was destroyed)');
        }
        return $this->resource;
    }

    /**
     * save()/output() 的编码参数（与 GdDriver 同一套语义）：
     * PNG 用 0-9 级别（未显式传 quality 时读 poster.png_compression），其余用 0-100 质量（读 image.quality）。
     */
    private static function encodeParam(string $format, int $quality, bool $explicit): int
    {
        if ($format === 'png') {
            return $explicit
                ? max(0, min(9, (int) round((100 - max(0, min(100, $quality))) * 9 / 100)))
                : max(0, min(9, intval(PosterConfig::get('poster.png_compression', 6))));
        }
        $quality = $explicit ? max(0, min(100, $quality)) : intval(PosterConfig::get('image.quality', 90));
        return max(0, min(100, $quality));
    }

    /** jpg/jpeg 以及回落到 JPEG 编码的未知格式都是 image/jpeg。 */
    private static function mimeType(string $format): string
    {
        return match ($format) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
