# poster-php

PHP image captcha & poster generation toolkit — framework-agnostic core with Laravel / ThinkPHP / Webman / Hyperf adapters.

[中文文档](README.md)

## Features

### Captcha (3 types)

| Type | Description |
|------|-------------|
| Click `click` | User clicks target characters on image in order |
| Rotate `rotate` | User drags slider to rotate image back to correct orientation |
| Slider `slider` | User drags puzzle piece into the gap |

### Poster Generation

Fluent Builder API with 8 element types:

| Element | Method | Description |
|---------|--------|-------------|
| Text | `addText()` | Auto line-wrap, alignment, multi-line |
| Image | `addImage()` | Resize, crop modes, border-radius, shadow |
| Avatar | `addAvatar()` | Circle crop, border |
| QR Code | `addQrcode()` | Pure PHP generation, center logo, label |
| Shape | `addShape()` | Rectangle/circle/rounded-rect, fill/stroke |
| Line | `addLine()` | Color, width |
| Watermark | `addWatermark()` | Tiled text, configurable angle and spacing |
| Table | `addTable()` | Header row, zebra stripes, column widths |

## Installation

```bash
composer require erikwang2013/poster-php
```

Requirements: PHP >= 8.0, GD extension.

Optional extensions:
- `ext-imagick`: ImageMagick driver (better performance)
- `ext-redis`: Redis captcha storage (distributed deployments)

## Quick Start

### Captcha

```php
// Generate click captcha
$captcha = captcha_create('click', ['difficulty' => 'easy']);
// Returns: ['key' => 'xxx', 'image' => 'base64...', 'extra' => ['targets' => [...]]]

// Verify
$pass = captcha_verify($captcha['key'], 'click', [[120, 80], [200, 150]]);
```

### Poster

```php
poster_create(750, 1334)
    ->background('#FFFFFF')
    ->addText('New Arrival', ['x' => 80, 'y' => 100, 'size' => 48, 'color' => '#333'])
    ->addQrcode('https://example.com', ['x' => 275, 'y' => 1050, 'size' => 200])
    ->save('/path/to/poster.jpg');
```

## Framework Integration

### Laravel

Auto-discovered via composer. Use Facades:

```php
use Erikwang2013\Poster\Adapters\Laravel\Facades\Captcha;
use Erikwang2013\Poster\Adapters\Laravel\Facades\Poster;

$result = Captcha::create('click')->generate();
Poster::width(750)->height(1334)->background('#FFF')->save('poster.jpg');
```

Publish config:

```bash
php artisan vendor:publish --tag=poster-config
```

### ThinkPHP

Register services in `config/console.php` and `config/web.php`:

```php
return [
    'services' => [
        Erikwang2013\Poster\Adapters\ThinkPHP\CaptchaService::class,
        Erikwang2013\Poster\Adapters\ThinkPHP\PosterService::class,
    ],
];
```

### Webman

Register in `config/bootstrap.php`:

```php
return [
    Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin::class,
    Erikwang2013\Poster\Adapters\Webman\PosterPlugin::class,
];
```

### Hyperf

Registered automatically via ConfigProvider in `config/autoload/dependencies.php`.

## Configuration

Default config at `config/poster.php`. Override via `.env` or framework config:

```php
return [
    'image' => [
        'driver'  => 'auto',    // 'auto' | 'gd' | 'imagick'
        'quality' => 90,        // JPEG quality 0-100
        'font'    => null,      // Custom font path, null=bundled font
    ],
    'captcha' => [
        'storage'           => 'auto',   // 'auto' | 'file' | 'session' | 'redis'
        'ttl'               => 300,       // Expiry in seconds
        'max_attempts'      => 3,         // Max verification attempts per key
        'default_difficulty' => 'medium', // 'easy' | 'medium' | 'hard'
        'tolerance' => [
            'click'  => 18,   // Pixel radius
            'rotate' => 5,    // Degrees
            'slider' => 4,    // Pixels
        ],
    ],
    'poster' => [
        'default_width'  => 750,
        'default_height' => 1334,
        'jpeg_quality'   => 90,
        'png_compression' => 6,
    ],
];
```

## Directory Structure

```
src/
├── Captcha/        # Captcha module
│   ├── CaptchaInterface.php
│   ├── AbstractCaptcha.php
│   ├── ClickCaptcha.php
│   ├── RotateCaptcha.php
│   ├── SliderCaptcha.php
│   ├── CaptchaFactory.php
│   └── CaptchaManager.php
├── Poster/         # Poster module
│   ├── PosterBuilder.php
│   ├── PosterTemplate.php
│   └── Elements/   # 8 rendering elements
├── Drivers/        # Image drivers (GD / ImageMagick)
├── Qrcode/         # Pure PHP QR code generator
├── Storage/        # Captcha storage (File / Session / Redis)
└── Adapters/       # Framework adapters
    ├── Laravel/
    ├── ThinkPHP/
    ├── Webman/
    └── Hyperf/
```

## License

MIT License — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
