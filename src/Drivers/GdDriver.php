<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;
use RuntimeException;

class GdDriver implements ImageDriverInterface
{
    use TextTrait;

    /** memory_limit 不可解析（-1/0）时使用的历史像素阈值 */
    private const MAX_PIXELS = 40000000;

    private $resource;
    private int $width = 0;
    private int $height = 0;

    public function load(string $path): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("File not found: $path");
        }
        // 非图片文件 getimagesize 会先打 Notice，这里静默后统一转成异常
        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException("Cannot read image: $path");
        }
        if ($info[0] * $info[1] > self::maxPixels()) {
            throw new RuntimeException("Image too large: {$info[0]}x{$info[1]} (max " . self::maxPixels() . ' pixels)');
        }
        $this->resource = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => throw new RuntimeException("Unsupported image type: " . $info[2]),
        };
        if ($this->resource === false) {
            throw new RuntimeException("Failed to decode image: $path");
        }
        $this->width  = imagesx($this->resource);
        $this->height = imagesy($this->resource);
        return $this;
    }

    public function create(int $width, int $height): static
    {
        $this->guardSize($width, $height);
        $this->resource = imagecreatetruecolor($width, $height);
        imagealphablending($this->resource, true);
        imagesavealpha($this->resource, true);
        $this->width  = $width;
        $this->height = $height;
        $transparent = imagecolorallocatealpha($this->resource, 0, 0, 0, 127);
        imagefill($this->resource, 0, 0, $transparent);
        return $this;
    }

    public function resize(int $width, int $height): static
    {
        $this->requireImage();
        $this->guardSize($width, $height);
        $new = imagecreatetruecolor($width, $height);
        imagealphablending($new, true);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopyresampled($new, $this->resource, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
        imagedestroy($this->resource);
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function rotate(float $angle, string $bgColor = '#000000'): static
    {
        $this->requireImage();
        if ($bgColor === 'transparent') {
            $bg = imagecolorallocatealpha($this->resource, 0, 0, 0, 127);
        } else {
            $bg = $this->allocColor($bgColor);
        }
        imagealphablending($this->resource, false);
        $rotated = imagerotate($this->resource, -$angle, $bg);
        if ($rotated === false) {
            throw new RuntimeException("Image rotation failed");
        }
        imagedestroy($this->resource);
        $this->resource = $rotated;
        imagealphablending($this->resource, true);
        imagesavealpha($this->resource, true);
        $this->width  = imagesx($this->resource);
        $this->height = imagesy($this->resource);
        return $this;
    }

    public function circle(int $diameter): static
    {
        $this->guardSize($diameter, $diameter);
        $this->requireImage();
        $this->resize($diameter, $diameter);

        // 真圆 = 四角半径取半边长，直接复用圆角路径（旧实现额外要一张 4 倍超采样画布 + 整幅逐像素回写）
        if ($diameter > 1) {
            $rounded = $this->roundCornersGD($this->resource, intdiv($diameter, 2));
            imagedestroy($this->resource);
            $this->resource = $rounded;
        }
        return $this;
    }

    public function crop(int $x, int $y, int $width, int $height): static
    {
        $this->requireImage();
        $this->guardSize($width, $height);
        $new = imagecreatetruecolor($width, $height);
        imagealphablending($new, true);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopy($new, $this->resource, 0, 0, $x, $y, $width, $height);
        imagedestroy($this->resource);
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $this->requireImage();
        // 未显式传 font 时用配置的默认字体（image.font）；显式传 null 可退回 GD 内置位图字体
        $fontFile = array_key_exists('font', $options) ? $options['font'] : PosterConfig::get('image.font');
        $size     = $options['size'] ?? 16;
        $color    = $options['color'] ?? '#000000';
        $angle    = $options['angle'] ?? 0;
        $maxWidth = $options['maxWidth'] ?? 0;
        $align    = $options['align'] ?? 'left';
        $lineHeight = $options['lineHeight'] ?? intval($size * 1.5);

        // 与形状路径同一套取色：8 位色（#RRGGBBAA）的 alpha 在文字上同样生效
        $alloc = $this->allocColor($color);

        if ($fontFile && is_file($fontFile)) {
            $measure = function (string $token) use ($size, $fontFile) {
                $bbox = @imagettfbbox($size, 0, $fontFile, $token);
                return $bbox === false ? null : $bbox[2] - $bbox[0];
            };
            $lines = ($maxWidth > 0) ? $this->wrapText($text, intval($maxWidth), $measure) : explode("\n", $text);
            foreach ($lines as $i => $line) {
                $bbox = @imagettfbbox($size, $angle, $fontFile, $line);
                if ($bbox === false) {
                    continue;
                }
                $lineW = $bbox[2] - $bbox[0];
                $lx = match ($align) {
                    'center' => $x - intval($lineW / 2),
                    'right'  => $x - $lineW,
                    default  => $x,
                };
                imagettftext($this->resource, $size, $angle, $lx, $y + $i * $lineHeight, $alloc, $fontFile, $line);
            }
        } else {
            $lines = ($maxWidth > 0) ? $this->wrapTextBuiltin($text, $maxWidth) : explode("\n", $text);
            foreach ($lines as $i => $line) {
                $lx = match ($align) {
                    'center' => $x - intval(strlen($line) * imagefontwidth(5) / 2),
                    'right'  => $x - intval(strlen($line) * imagefontwidth(5)),
                    default  => $x,
                };
                imagestring($this->resource, 5, $lx, $y + $i * $lineHeight, $line, $alloc);
            }
        }

        return $this;
    }

    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static
    {
        $this->requireImage();
        $ov = $overlay->getResource();
        $owned = false;
        if ($ov instanceof \Imagick) {
            $ov = imagecreatefromstring($ov->getImageBlob());
            if ($ov === false) {
                throw new RuntimeException('Cannot convert Imagick overlay to GD');
            }
            $owned = true;
        }
        if (!$ov instanceof \GdImage) {
            throw new RuntimeException('Unsupported overlay resource: expected GdImage or Imagick');
        }
        $ovW = imagesx($ov);
        $ovH = imagesy($ov);

        $destW = intval($options['width'] ?? $ovW);
        $destH = intval($options['height'] ?? $ovH);
        $this->guardSize($destW, $destH);

        // 先缩到目标尺寸再做圆角/阴影：代价按目标像素算，而不是源分辨率
        // （源图可以是目标的 10-40 倍，旧实现在源分辨率上整幅逐像素回写）
        // radius 语义因此统一为「目标像素」，与 ImagickDriver 一致
        if ($destW !== $ovW || $destH !== $ovH) {
            $scaled = imagecreatetruecolor($destW, $destH);
            imagealphablending($scaled, true);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            imagecopyresampled($scaled, $ov, 0, 0, 0, 0, $destW, $destH, $ovW, $ovH);
            if ($owned) {
                imagedestroy($ov);
            }
            $ov = $scaled;
            $owned = true;
            $ovW = $destW;
            $ovH = $destH;
        }

        if (($options['radius'] ?? 0) > 0) {
            $rounded = $this->roundCornersGD($ov, intval($options['radius']));
            if ($owned) {
                imagedestroy($ov);
            }
            $ov = $rounded;
            $owned = true;
        }

        if (isset($options['shadow'])) {
            $this->drawShadowGD($options['shadow'], $x, $y, $destW, $destH);
        }

        imagecopyresampled($this->resource, $ov, $x, $y, 0, 0, $destW, $destH, $ovW, $ovH);
        if ($owned) {
            imagedestroy($ov);
        }
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): static
    {
        $this->requireImage();
        $color  = $options['color'] ?? '#FFFFFF';
        $radius = intval($options['radius'] ?? 0);
        $filled = $options['filled'] ?? true;

        // opacity 越界夹到 [0,1]（兼容 0-100 写法），不再抛 GD ValueError
        $alloc = $this->allocColor($color, self::opacityToAlpha($options['opacity'] ?? null));

        if ($radius > 0) {
            $this->roundedRectGD($x, $y, $x + $width - 1, $y + $height - 1, $radius, $alloc, $filled);
        } elseif ($filled) {
            imagefilledrectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        } else {
            imagerectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        }

        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): static
    {
        $this->requireImage();
        $color  = $options['color'] ?? '#FFFFFF';
        $filled = $options['filled'] ?? true;
        $alloc  = $this->allocColor($color);

        if ($filled) {
            imagefilledellipse($this->resource, $cx, $cy, $rx * 2, $ry * 2, $alloc);
        } else {
            imageellipse($this->resource, $cx, $cy, $rx * 2, $ry * 2, $alloc);
        }

        return $this;
    }

    public function filledArc(int $cx, int $cy, int $w, int $h, int $startAngle, int $endAngle, array $options = []): static
    {
        $this->requireImage();
        $color = $options['color'] ?? '#FFFFFF';
        $alloc = $this->allocColor($color);
        imagefilledarc($this->resource, $cx, $cy, $w, $h, $startAngle, $endAngle, $alloc, IMG_ARC_PIE);
        return $this;
    }

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): static
    {
        $this->requireImage();
        $color = $options['color'] ?? '#000000';
        $alloc = $this->allocColor($color);
        imagesetthickness($this->resource, max(1, intval($options['width'] ?? 1)));
        imageline($this->resource, $x1, $y1, $x2, $y2, $alloc);
        imagesetthickness($this->resource, 1);
        return $this;
    }

    public function blur(int $radius = 1): static
    {
        $this->requireImage();
        if ($radius < 1) {
            return $this;
        }
        if ($radius <= 2) {
            for ($i = 0; $i < $radius; $i++) {
                imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR);
            }
            return $this;
        }

        // 大半径：1/4 缩放 → 模糊 → 放大（同 drawShadowGD），全幅高斯代价降到约 1/16
        $w = imagesx($this->resource);
        $h = imagesy($this->resource);
        $sw = max(1, intdiv($w, 4));
        $sh = max(1, intdiv($h, 4));
        $small = imagecreatetruecolor($sw, $sh);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $this->resource, 0, 0, 0, 0, $sw, $sh, $w, $h);
        for ($i = 0, $passes = min(8, max(1, intdiv($radius, 2))); $i < $passes; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagealphablending($this->resource, false);
        imagecopyresampled($this->resource, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagealphablending($this->resource, true);
        imagedestroy($small);
        return $this;
    }

    public function sharpen(float $amount = 1.0): static
    {
        $this->requireImage();
        $a = max(0, min(3, $amount));
        $center = $a * 4 + 1;
        $edge = -$a;
        imageconvolution($this->resource, [
            [0, $edge, 0],
            [$edge, $center, $edge],
            [0, $edge, 0],
        ], 1, 0);
        return $this;
    }

    public function pixelate(int $blockSize = 3): static
    {
        $this->requireImage();
        imagefilter($this->resource, IMG_FILTER_PIXELATE, max(1, $blockSize), true);
        return $this;
    }

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool
    {
        $this->requireImage();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $format = strtolower($format);
        // 未显式传 quality（第三个参数）时按配置取默认值：PNG 用 poster.png_compression，其余用 image.quality
        $encoded = self::encodeParam($format, $quality, func_num_args() >= 3);

        return match ($format) {
            'png'  => imagepng($this->resource, $path, $encoded),
            'gif'  => imagegif($this->resource, $path),
            'webp' => imagewebp($this->resource, $path, $encoded),
            default => imagejpeg($this->resource, $path, $encoded),
        };
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        $this->requireImage();
        $format = strtolower($format);
        $encoded = self::encodeParam($format, $quality, func_num_args() >= 3);

        ob_start();
        match ($format) {
            'png'  => imagepng($this->resource, null, $encoded),
            'gif'  => imagegif($this->resource),
            'webp' => imagewebp($this->resource, null, $encoded),
            default => imagejpeg($this->resource, null, $encoded),
        };
        $data = ob_get_clean();
        return 'data:' . self::mimeType($format) . ';base64,' . base64_encode($data);
    }

    public function getSize(): array
    {
        $this->requireImage();
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function setGdResource(\GdImage $gd): void
    {
        $this->destroy();
        $this->resource = $gd;
        $this->width  = imagesx($gd);
        $this->height = imagesy($gd);
    }

    public function clone(): static
    {
        $driver = new self();
        if ($this->resource !== null && $this->width > 0 && $this->height > 0) {
            $driver->create($this->width, $this->height);
            imagecopy($driver->resource, $this->resource, 0, 0, 0, 0, $this->width, $this->height);
        }
        return $driver;
    }

    public function destroy(): void
    {
        if ($this->resource instanceof \GdImage) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
        $this->width = 0;
        $this->height = 0;
    }

    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * 像素守卫：尺寸必须为正，且不超过 memory_limit 能装下的像素数。
     * 入口覆盖 create/resize/crop/circle 与 image() 的目标尺寸，避免不可 catch 的致命 OOM。
     */
    private function guardSize(int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException("Width and height must be greater than 0, got {$width}x{$height}");
        }
        if ($width * $height > self::maxPixels()) {
            throw new InvalidArgumentException(
                "Image too large: {$width}x{$height} exceeds the pixel budget of " . self::maxPixels()
                . " pixels (memory_limit / 4 bytes per pixel)"
            );
        }
    }

    /** 像素预算：按 memory_limit / 4（每像素 4 字节 RGBA）估算；memory_limit 不限时退回历史阈值 40M。 */
    private static function maxPixels(): int
    {
        $limit = self::memoryLimitBytes();
        return $limit > 0 ? intdiv($limit, 4) : self::MAX_PIXELS;
    }

    private static function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $bytes = intval($value);
        return match (strtolower(substr($value, -1))) {
            'g'     => $bytes * 1024 * 1024 * 1024,
            'm'     => $bytes * 1024 * 1024,
            'k'     => $bytes * 1024,
            default => $bytes,
        };
    }

    /** destroy() 后所有操作给出明确异常，而不是让 GD 抛 TypeError/返回 null 尺寸。 */
    private function requireImage(): \GdImage
    {
        if (!$this->resource instanceof \GdImage) {
            throw new RuntimeException('No image resource: call load() or create() first (the resource was destroyed)');
        }
        return $this->resource;
    }

    /**
     * save()/output() 的编码参数：
     * - PNG：0-9 压缩级别。未显式传 quality 时读 poster.png_compression（默认 6）；
     *   显式传的 quality 仍是 0-100，按「质量越高压缩越少」映射到级别。
     * - 其余（jpeg/webp）：quality 0-100，未显式传时读 image.quality（默认 90）。
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

    /** 输出 data URI 的 MIME：jpg/jpeg 以及回落到 JPEG 编码的未知格式都是 image/jpeg。 */
    private static function mimeType(string $format): string
    {
        return match ($format) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /** opacity 越界夹到 [0,1] 后转 GD alpha(0 不透明 / 127 全透明)；未给时按不透明。 */
    private static function opacityToAlpha($opacity): int
    {
        if ($opacity === null) {
            return 0;
        }
        return intval((1 - max(0.0, min(1.0, floatval($opacity)))) * 127);
    }

    /** shadow.opacity 在 Imagick 侧是 0-100（shadowImage 约定），这里 0-1 与 0-100 都接受。 */
    private static function shadowOpacityToAlpha($opacity): int
    {
        if ($opacity === null) {
            return 0;
        }
        $value = floatval($opacity);
        if ($value > 1) {
            $value /= 100;
        }
        return intval((1 - max(0.0, min(1.0, $value))) * 127);
    }

    private function allocColor(string $color, int $alpha = 0): int
    {
        return $this->allocColorOn($this->resource, $color, $alpha);
    }

    /** 在指定画布上取色；8 位色（#RRGGBBAA）自带 alpha，优先于传入的 $alpha。 */
    private function allocColorOn(\GdImage $image, string $color, int $alpha = 0): int
    {
        $rgb = $this->hexToRgb($color);
        if (strlen(ltrim($color, '#')) === 8) {
            $alpha = 127 - intval(hexdec(substr(ltrim($color, '#'), 6, 2)) / 2);
        }
        return imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha)));
    }

    /** 颜色只接受 #RGB / #RRGGBB / #RRGGBBAA，其它形式一律报错（旧实现静默变纯黑）。 */
    private function hexToRgb(string $color): array
    {
        $hex = ltrim($color, '#');
        if (!preg_match('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
            throw new InvalidArgumentException(
                "Invalid color: '$color' (expected #RGB, #RRGGBB or #RRGGBBAA)"
            );
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function wrapTextBuiltin(string $text, int $maxWidth): array
    {
        $charWidth = imagefontwidth(5);
        $maxChars = max(1, intval($maxWidth / $charWidth));
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $start = 0;
            $len = mb_strlen($paragraph);
            while ($start < $len) {
                $lines[] = mb_substr($paragraph, $start, $maxChars);
                $start += $maxChars;
            }
        }
        return $lines ?: [$text];
    }

    /**
     * 圆角：只处理 4 个 radius×radius 的角箱，箱外整幅 imagecopy（C 级）。
     * 角箱内用与旧实现同一段角弧掩码，按掩码 alpha 擦除为透明。
     * 旧实现按源分辨率做整幅双重循环（1200×1600 源 = 192 万次 imagecolorat+imagesetpixel）。
     */
    private function roundCornersGD($image, int $radius)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        // 半径上限为短边一半：4 个角箱互不重叠，同时把循环限制在有意义的范围内
        $radius = min($radius, intdiv(min($w, $h), 2));

        $result = imagecreatetruecolor($w, $h);
        imagealphablending($result, false);
        imagesavealpha($result, true);
        $transparent = imagecolorallocatealpha($result, 0, 0, 0, 127);
        imagefill($result, 0, 0, $transparent);
        imagecopy($result, $image, 0, 0, 0, 0, $w, $h);

        if ($radius < 1) {
            return $result;
        }

        // 角箱掩码 = 旧全幅掩码的左上角弧（圆心 (r-1,r-1)、180°-270°）画在 r×r 画布上
        $mask = imagecreatetruecolor($radius, $radius);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        imagefill($mask, 0, 0, $transparent);
        $opaque = imagecolorallocatealpha($mask, 0, 0, 0, 0);
        imagefilledarc($mask, $radius - 1, $radius - 1, $radius * 2, $radius * 2, 180, 270, $opaque, IMG_ARC_PIE);

        // 逐行擦除：圆外区域在行内是「连续前缀」，二分出第一个圆内像素后整段矩形擦除。
        // 旧实现逐像素 imagecolorat + 最多 4 次 imagesetpixel，是 O(r²) 次 PHP 调用（r=300 时约 37 万次、370ms）。
        for ($y = 0; $y < $radius; $y++) {
            $lo = 0;
            $hi = $radius;
            while ($lo < $hi) {
                $mid = ($lo + $hi) >> 1;
                if (((imagecolorat($mask, $mid, $y) >> 24) & 0x7F) >= 64) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid;
                }
            }
            if ($lo < 1) {
                continue;
            }
            $x2 = $w - $lo;
            $y2 = $h - 1 - $y;
            imagefilledrectangle($result, 0, $y, $lo - 1, $y, $transparent);
            imagefilledrectangle($result, $x2, $y, $w - 1, $y, $transparent);
            imagefilledrectangle($result, 0, $y2, $lo - 1, $y2, $transparent);
            imagefilledrectangle($result, $x2, $y2, $w - 1, $y2, $transparent);
        }
        imagedestroy($mask);
        return $result;
    }

    private function roundedRectGD(int $x1, int $y1, int $x2, int $y2, int $r, int $color, bool $filled): void
    {
        if ($filled) {
            imagefilledrectangle($this->resource, $x1 + $r, $y1, $x2 - $r, $y2, $color);
            imagefilledrectangle($this->resource, $x1, $y1 + $r, $x2, $y2 - $r, $color);
            imagefilledarc($this->resource, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
        } else {
            imagerectangle($this->resource, $x1, $y1, $x2, $y2, $color);
        }
    }

    private function drawShadowGD(array $shadow, int $x, int $y, int $w, int $h): void
    {
        $sColor  = $shadow['color'] ?? '#00000033';
        $offsetX = intval($shadow['offsetX'] ?? 4);
        $offsetY = intval($shadow['offsetY'] ?? 4);
        $blur    = max(0, intval($shadow['blur'] ?? 8));

        $pad = $blur * 2;
        $sw = $w + $pad;
        $sh = $h + $pad;
        $sw2 = max(1, intdiv($sw, 4));
        $sh2 = max(1, intdiv($sh, 4));

        // GD 的 IMG_FILTER_GAUSSIAN_BLUR 只作用于 RGB、完全不动 alpha 通道，
        // 而阴影的形状恰好全在 alpha 上——直接模糊带 alpha 的图形边缘不会变软（旧实现因此 blur 无效，
        // 永远是一块硬边实心方块）。这里改为在不透明的黑白掩膜上模糊，再把亮度映射成 alpha。
        $mask = imagecreatetruecolor($sw2, $sh2);
        imagefill($mask, 0, 0, imagecolorallocate($mask, 0, 0, 0));
        $b2 = max(1, intdiv($blur, 4));
        $w2 = max(1, intdiv($w, 4));
        $h2 = max(1, intdiv($h, 4));
        imagefilledrectangle(
            $mask, $b2, $b2, $b2 + $w2 - 1, $b2 + $h2 - 1,
            imagecolorallocate($mask, 255, 255, 255)
        );
        for ($i = 0, $passes = max(1, min(8, intdiv($blur, 2))); $i < $passes; $i++) {
            imagefilter($mask, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // 亮度 → alpha（在 1/4 尺度上逐像素，成本可忽略）
        $rgb = $this->hexToRgb($sColor);
        $hexColor = ltrim($sColor, '#');
        if (strlen($hexColor) === 8) {
            $baseAlpha = 127 - intval(hexdec(substr($hexColor, 6, 2)) / 2);   // #RRGGBBAA
        } elseif (array_key_exists('opacity', $shadow)) {
            $baseAlpha = self::shadowOpacityToAlpha($shadow['opacity']);
        } else {
            $baseAlpha = self::shadowOpacityToAlpha(null);
        }
        $baseAlpha = max(0, min(127, $baseAlpha));

        $small = imagecreatetruecolor($sw2, $sh2);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        for ($px = 0; $px < $sw2; $px++) {
            for ($py = 0; $py < $sh2; $py++) {
                $lum = (imagecolorat($mask, $px, $py) >> 16) & 0xFF;   // 形状内为白(255)
                if ($lum === 0) {
                    continue;
                }
                $alpha = intval(round(127 - ($lum / 255) * (127 - $baseAlpha)));
                imagesetpixel($small, $px, $py, imagecolorallocatealpha(
                    $small, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha))
                ));
            }
        }
        imagedestroy($mask);

        // 放大回原尺寸：软边由 1/4 → 原尺寸的重采样给出
        $final = imagecreatetruecolor($sw, $sh);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopyresampled($final, $small, 0, 0, 0, 0, $sw, $sh, $sw2, $sh2);
        imagedestroy($small);

        imagecopy(
            $this->resource, $final,
            $x + $offsetX - $blur, $y + $offsetY - $blur,
            0, 0, $sw, $sh
        );
        imagedestroy($final);
    }

}
