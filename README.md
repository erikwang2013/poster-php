# poster-php

PHP 图片验证码与海报生成工具包 —— 框架无关核心 + Laravel / ThinkPHP / Webman / Hyperf 适配。

[English Documentation](README_EN.md)

## 功能

### 验证码（三种方式）

| 类型 | 说明 |
|------|------|
| 点击验证 `click` | 用户按顺序点击图片上的目标文字 |
| 旋转验证 `rotate` | 用户拖动滑块将图片旋转回正确角度 |
| 滑块验证 `slider` | 用户拖动拼图块到缺口位置 |

### 海报生成

链式 Builder API，支持 8 种元素：

| 元素 | 方法 | 说明 |
|------|------|------|
| 文字 | `addText()` | 自动换行，对齐，多行 |
| 图片 | `addImage()` | 缩放裁剪，圆角，阴影 |
| 头像 | `addAvatar()` | 圆形裁剪，边框 |
| 二维码 | `addQrcode()` | 纯 PHP 生成，中心 Logo，底部文案 |
| 形状 | `addShape()` | 矩形/圆形/圆角，填充/描边 |
| 分割线 | `addLine()` | 颜色，宽度 |
| 水印 | `addWatermark()` | 平铺文字，角度，间距 |
| 表格 | `addTable()` | 表头，斑马纹，列宽 |

## 安装

```bash
composer require erikwang2013/poster-php
```

系统要求：PHP >= 8.0，GD 扩展。

可选扩展：
- `ext-imagick`：ImageMagick 图像驱动（性能更好，功能更强）
- `ext-redis`：Redis 验证码存储（分布式部署）

## 快速开始

### 验证码

```php
// 生成点击验证码
$captcha = captcha_create('click', ['difficulty' => 'easy']);
// 返回：['key' => 'xxx', 'image' => 'base64...', 'extra' => ['targets' => [...]]]

// 验证
$pass = captcha_verify($captcha['key'], 'click', [[120, 80], [200, 150]]);
```

### 海报

```php
poster_create(750, 1334)
    ->background('#FFFFFF')
    ->addText('新品首发', ['x' => 80, 'y' => 100, 'size' => 48, 'color' => '#333'])
    ->addQrcode('https://example.com', ['x' => 275, 'y' => 1050, 'size' => 200])
    ->save('/path/to/poster.jpg');
```

## 框架集成

### Laravel

安装后自动发现 ServiceProvider。使用 Facade：

```php
use Erikwang2013\Poster\Adapters\Laravel\Facades\Captcha;
use Erikwang2013\Poster\Adapters\Laravel\Facades\Poster;

$result = Captcha::create('click')->generate();
Poster::width(750)->height(1334)->background('#FFF')->save('poster.jpg');
```

发布配置文件：

```bash
php artisan vendor:publish --tag=poster-config
```

### ThinkPHP

在 `config/console.php` 和 `config/web.php` 中注册服务：

```php
return [
    'services' => [
        Erikwang2013\Poster\Adapters\ThinkPHP\CaptchaService::class,
        Erikwang2013\Poster\Adapters\ThinkPHP\PosterService::class,
    ],
];
```

使用 Facade：

```php
use Erikwang2013\Poster\Adapters\ThinkPHP\Facades\Captcha;
$result = Captcha::create('click')->generate();
```

### Webman

在 `config/bootstrap.php` 中注册：

```php
return [
    Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin::class,
    Erikwang2013\Poster\Adapters\Webman\PosterPlugin::class,
];
```

### Hyperf

在 `config/autoload/dependencies.php` 或通过 ConfigProvider 自动注册。

## 配置

配置文件 `config/poster.php` 包含所有可配置项：

```php
return [
    'image' => [
        'driver'  => 'auto',    // 'auto' | 'gd' | 'imagick'
        'quality' => 90,        // JPEG 质量 0-100
        'font'    => null,      // 默认字体路径，null=包自带字体
    ],
    'captcha' => [
        'storage'           => 'auto',   // 'auto' | 'file' | 'session' | 'redis'
        'ttl'               => 300,       // 有效期（秒）
        'max_attempts'      => 3,         // 最大验证次数
        'default_difficulty' => 'medium', // 默认难度
        'tolerance' => [
            'click'  => 18,   // 点击容差（像素半径）
            'rotate' => 5,    // 旋转容差（角度）
            'slider' => 4,    // 滑块容差（像素）
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

可通过 `.env` 文件覆盖（各框架自行适配）。

## 目录结构

```
src/
├── Captcha/        # 验证码模块
│   ├── CaptchaInterface.php
│   ├── AbstractCaptcha.php
│   ├── ClickCaptcha.php
│   ├── RotateCaptcha.php
│   ├── SliderCaptcha.php
│   ├── CaptchaFactory.php
│   └── CaptchaManager.php
├── Poster/         # 海报模块
│   ├── PosterBuilder.php
│   ├── PosterTemplate.php
│   └── Elements/   # 8 种渲染元素
├── Drivers/        # 图像驱动（GD / ImageMagick）
├── Qrcode/         # 纯 PHP 二维码生成器
├── Storage/        # 验证数据存储（File / Session / Redis）
└── Adapters/       # 框架适配层
    ├── Laravel/
    ├── ThinkPHP/
    ├── Webman/
    └── Hyperf/
```

## License

MIT License — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
