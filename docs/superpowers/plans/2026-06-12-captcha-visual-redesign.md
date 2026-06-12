# Captcha Visual Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace pixelated captcha backgrounds with beautiful procedural gradients + optional background images; improve all three captcha types visually.

**Architecture:** Procedural background generation in AbstractCaptcha with three style presets (minimal/vibrant/natural), simulated gradients via 1px rectangle strips using existing driver primitives. No driver interface changes.

**Tech Stack:** PHP 8.x, GD/Imagick drivers (existing), PHPUnit tests

---

### Task 1: Add background config keys

**Files:**
- Modify: `config/poster.php`

- [ ] **Step 1: Add `background_dir` and `background_styles` to captcha config**

In `config/poster.php`, after the `'default_difficulty' => 'medium',` line, insert:

```php
// 默认背景图目录（放 png/jpg/gif/webp），随机选用
// null = 使用程序化生成
// Background image directory; null = procedural generation
'background_dir' => null,

// 程序化背景风格 / Procedural background styles
// Available: 'minimal', 'vibrant', 'natural'
'background_styles' => ['minimal', 'vibrant', 'natural'],
```

- [ ] **Step 2: Commit**

```bash
git add config/poster.php
git commit -m "feat: add captcha background_dir and background_styles config keys"
```

---

### Task 2: Rewrite AbstractCaptcha procedural background

**Files:**
- Modify: `src/Captcha/AbstractCaptcha.php`

- [ ] **Step 1: Replace `createBackground()` method**

Replace the existing `createBackground()` with the new version that has three-tier priority: user image → configured directory → procedural generation:

```php
protected function createBackground(): ImageDriverInterface
{
    $bg = $this->imageDriver->clone();

    // 1. User-set background image
    if ($this->backgroundPath !== null && is_file($this->backgroundPath)) {
        $bg->load($this->backgroundPath);
        $size = $bg->getSize();
        $this->width = $size['width'];
        $this->height = $size['height'];
        return $bg;
    }

    // 2. Configured background directory
    $bgDir = PosterConfig::get('captcha.background_dir');
    if ($bgDir && is_dir($bgDir)) {
        $files = glob($bgDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!empty($files)) {
            $bg->load($files[array_rand($files)]);
            $size = $bg->getSize();
            $this->width = $size['width'];
            $this->height = $size['height'];
            return $bg;
        }
    }

    // 3. Procedural generation
    $styles = PosterConfig::get('captcha.background_styles', ['minimal', 'vibrant', 'natural']);
    $style = $styles[array_rand($styles)];
    $bg->create($this->width, $this->height);
    $this->generateProceduralBackground($bg, $style);

    return $bg;
}
```

- [ ] **Step 2: Add `generateProceduralBackground()` and palette methods**

Add after `createBackground()`:

```php
private function generateProceduralBackground(ImageDriverInterface $bg, string $style): void
{
    $this->generateGradient($bg, $style);
    $this->generateDecorations($bg, $style);
    $this->generateNoise($bg, $style);
}

private function palettesForStyle(string $style): array
{
    return match ($style) {
        'minimal' => [
            ['#e8eaf6', '#c5cae9'],
            ['#e0f2f1', '#b2dfdb'],
            ['#f3e5f5', '#e1bee7'],
            ['#eceff1', '#cfd8dc'],
            ['#e8f5e9', '#c8e6c9'],
        ],
        'vibrant' => [
            ['#667eea', '#764ba2'],
            ['#f093fb', '#f5576c'],
            ['#4facfe', '#00f2fe'],
            ['#43e97b', '#38f9d7'],
            ['#fa709a', '#fee140'],
            ['#a18cd1', '#fbc2eb'],
        ],
        'natural' => [
            ['#f5f0e8', '#e8dcc8'],
            ['#faf0e6', '#f5deb3'],
            ['#f0ebe3', '#d9cdb3'],
            ['#fef9ef', '#f5e6c8'],
            ['#f7f2e9', '#e6d5c3'],
        ],
    };
}
```

- [ ] **Step 3: Add `generateGradient()` method**

```php
private function generateGradient(ImageDriverInterface $bg, string $style): void
{
    $palettes = $this->palettesForStyle($style);
    $palette = $palettes[array_rand($palettes)];

    $steps = 60;
    for ($i = 0; $i < $steps; $i++) {
        $t = $i / ($steps - 1);
        $color = $this->interpolateColor($palette[0], $palette[1], $t);
        $y = intval($i * $this->height / $steps);
        $nextY = intval(($i + 1) * $this->height / $steps);
        $h = $nextY - $y;
        $bg->rectangle(0, $y, $this->width, $h, ['color' => $color, 'filled' => true]);
    }
}

private function interpolateColor(string $c1, string $c2, float $t): string
{
    $r1 = hexdec(substr($c1, 1, 2));
    $g1 = hexdec(substr($c1, 3, 2));
    $b1 = hexdec(substr($c1, 5, 2));
    $r2 = hexdec(substr($c2, 1, 2));
    $g2 = hexdec(substr($c2, 3, 2));
    $b2 = hexdec(substr($c2, 5, 2));
    return sprintf('#%02X%02X%02X',
        intval($r1 + ($r2 - $r1) * $t),
        intval($g1 + ($g2 - $g1) * $t),
        intval($b1 + ($b2 - $b1) * $t)
    );
}
```

- [ ] **Step 4: Add `generateDecorations()` and style-specific decoration methods**

```php
private function generateDecorations(ImageDriverInterface $bg, string $style): void
{
    match ($style) {
        'minimal' => $this->decorateMinimal($bg),
        'vibrant' => $this->decorateVibrant($bg),
        'natural' => $this->decorateNatural($bg),
    };
}

private function decorateMinimal(ImageDriverInterface $bg): void
{
    for ($i = 0; $i < mt_rand(2, 3); $i++) {
        $x = mt_rand(-40, $this->width + 40);
        $y = mt_rand(-40, $this->height + 40);
        $r = mt_rand(60, 140);
        $bg->ellipse($x, $y, $r, $r, ['color' => '#FFFFFF66', 'filled' => true]);
    }
    for ($i = 0; $i < mt_rand(1, 2); $i++) {
        $x1 = mt_rand(0, $this->width);
        $y1 = mt_rand(0, $this->height);
        $x2 = $x1 + mt_rand(-120, 120);
        $y2 = $y1 + mt_rand(-80, 80);
        $bg->line($x1, $y1, $x2, $y2, ['color' => '#FFFFFF55', 'width' => mt_rand(2, 4)]);
    }
}

private function decorateVibrant(ImageDriverInterface $bg): void
{
    for ($i = 0; $i < mt_rand(10, 18); $i++) {
        $x = mt_rand(0, $this->width);
        $y = mt_rand(0, $this->height);
        $r = mt_rand(10, 70);
        $color = $this->randomColor();
        $bg->ellipse($x, $y, $r, $r, ['color' => $color . '2A', 'filled' => true]);
    }
    for ($i = 0; $i < mt_rand(3, 6); $i++) {
        $x = mt_rand(0, $this->width);
        $y = mt_rand(0, $this->height);
        $r = mt_rand(15, 45);
        $color = $this->randomColor();
        $bg->ellipse($x, $y, $r, $r, ['color' => $color . '55', 'filled' => false]);
    }
}

private function decorateNatural(ImageDriverInterface $bg): void
{
    for ($i = 0; $i < mt_rand(6, 12); $i++) {
        $x = mt_rand(0, $this->width - 50);
        $y = mt_rand(0, $this->height - 30);
        $w = mt_rand(30, 90);
        $h = mt_rand(15, 45);
        $color = $this->randomLightColor();
        $bg->rectangle($x, $y, $w, $h, ['color' => $color . '2E', 'filled' => true]);
    }
}
```

- [ ] **Step 5: Add `generateNoise()` method**

```php
private function generateNoise(ImageDriverInterface $bg, string $style): void
{
    $count = match ($style) {
        'minimal' => mt_rand(20, 40),
        'vibrant' => mt_rand(50, 90),
        'natural' => mt_rand(100, 180),
    };
    $dotSize = match ($style) {
        'minimal' => 1,
        'vibrant' => mt_rand(1, 2),
        'natural' => 1,
    };
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, $this->width - 1);
        $y = mt_rand(0, $this->height - 1);
        $color = $this->randomColor();
        $bg->ellipse($x, $y, $dotSize, $dotSize, ['color' => $color . '1E', 'filled' => true]);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Captcha/AbstractCaptcha.php
git commit -m "feat: rewrite captcha background with procedural gradient generation"
```

---

### Task 3: Improve SliderCaptcha visuals

**Files:**
- Modify: `src/Captcha/SliderCaptcha.php`

- [ ] **Step 1: Replace the gap and puzzle piece drawing in `generate()`**

Replace the entire gap-drawing and piece-creation section in `generate()` (from the gap comment to the `store()` call):

```php
// Draw gap on background — rounded rectangle
$gapRadius = 6;
$bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
    'color'  => '#00000018',
    'filled' => true,
    'radius' => $gapRadius,
]);
$bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
    'color'       => '#00000040',
    'filled'      => false,
    'radius'      => $gapRadius,
    'strokeWidth' => 2,
]);

// Create puzzle piece — with shadow and rounded corners
$pad = 8;
$pieceW = $this->puzzleWidth + $pad;
$pieceH = $this->puzzleHeight + $pad;
$piece = $this->imageDriver->clone();
$piece->create($pieceW, $pieceH);

// Shadow
$piece->rectangle($pad, $pad, $this->puzzleWidth, $this->puzzleHeight, [
    'color'  => '#00000022',
    'filled' => true,
    'radius' => $gapRadius,
]);

// Piece body
$piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
    'color'  => '#FFFFFFF0',
    'filled' => true,
    'radius' => $gapRadius,
]);

// Piece border
$piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
    'color'       => '#888888',
    'filled'      => false,
    'radius'      => $gapRadius,
    'strokeWidth' => 2,
]);

// Direction arrow
$cx = intval($this->puzzleWidth / 2);
$cy = intval($this->puzzleHeight / 2);
$fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';
$piece->text('»', $cx, $cy + 6, [
    'size'  => 18,
    'color' => '#999999',
    'font'  => is_file($fontFile) ? $fontFile : null,
    'align' => 'center',
]);
```

- [ ] **Step 2: Commit**

```bash
git add src/Captcha/SliderCaptcha.php
git commit -m "feat: improve slider captcha visuals with rounded corners and shadow"
```

---

### Task 4: Improve ClickCaptcha visuals

**Files:**
- Modify: `src/Captcha/ClickCaptcha.php`

- [ ] **Step 1: Replace target and label drawing in `generate()`**

Replace the `foreach ($targets as $target)` loop body:

```php
foreach ($targets as $target) {
    // Outer ring
    $bg->ellipse($target['x'], $target['y'], 28, 28, [
        'color'  => '#FF6B6B',
        'filled' => false,
    ]);
    // Inner highlight
    $bg->ellipse($target['x'], $target['y'], 24, 24, [
        'color'  => '#FF6B6B22',
        'filled' => true,
    ]);
    // Order number
    $bg->text((string)$target['order'], $target['x'], $target['y'] + 6, [
        'size'  => 16,
        'color' => '#FF6B6B',
        'font'  => is_file($fontFile) ? $fontFile : null,
        'align' => 'center',
    ]);

    // Pill label background
    $labelText = $target['order'] . '.' . $target['text'];
    $labelY = min($target['y'] + 38, $this->height - 14);
    $pillW = mb_strlen($labelText) * 13 + 16;
    $pillX = $target['x'] - intval($pillW / 2);
    $bg->rectangle($pillX, $labelY - 12, $pillW, 24, [
        'color'  => '#FFFFFFD0',
        'filled' => true,
        'radius' => 12,
    ]);
    // Label text
    $bg->text($labelText, $target['x'], $labelY + 6, [
        'size'  => 14,
        'color' => '#333333',
        'font'  => is_file($fontFile) ? $fontFile : null,
        'align' => 'center',
    ]);
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Captcha/ClickCaptcha.php
git commit -m "feat: improve click captcha visuals with ring markers and pill labels"
```

---

### Task 5: Run tests and verify

**Files:**
- Existing: `tests/Captcha/CaptchaTest.php`

- [ ] **Step 1: Run existing test suite**

```bash
cd /home/wwwroot/erikwang2013/poster-php && vendor/bin/phpunit tests/Captcha/CaptchaTest.php --colors=always
```

Expected: All 9 tests pass.

- [ ] **Step 2: Add background config tests**

Append to `tests/Captcha/CaptchaTest.php` before the closing `}`:

```php
public function testCaptchaBackgroundUsesProceduralGenerationByDefault(): void
{
    \Erikwang2013\Poster\PosterConfig::merge([
        'captcha' => ['background_dir' => null],
    ]);
    $result = $this->manager->create('click')->generate();
    $this->assertStringStartsWith('data:image/png;base64,', $result['image']);
    $this->assertNotEmpty($result['key']);
    \Erikwang2013\Poster\PosterConfig::reset();
}

public function testCaptchaBackgroundRespectsCustomPathViaSetBackground(): void
{
    $testImg = imagecreatetruecolor(100, 80);
    imagefill($testImg, 0, 0, imagecolorallocate($testImg, 200, 100, 50));
    $testPath = $this->tempDir . '/test-bg.png';
    imagepng($testImg, $testPath);
    imagedestroy($testImg);

    $result = $this->manager->create('click')
        ->setBackground($testPath)
        ->generate();
    $this->assertNotEmpty($result['key']);
    unlink($testPath);
    \Erikwang2013\Poster\PosterConfig::reset();
}

public function testSliderCaptchaPieceHasImprovedVisuals(): void
{
    $result = $this->manager->create('slider')->generate();
    $this->assertArrayHasKey('puzzle', $result['extra']);
    $this->assertNotEmpty($result['extra']['puzzle']);
}
```

- [ ] **Step 3: Run all tests**

```bash
vendor/bin/phpunit tests/Captcha/CaptchaTest.php --colors=always
```

Expected: All 12 tests pass.

- [ ] **Step 4: Run visual smoke test**

```bash
php -r '
require "vendor/autoload.php";
$m = new \Erikwang2013\Poster\Captcha\CaptchaManager(
    \Erikwang2013\Poster\Drivers\DriverFactory::create(),
    new \Erikwang2013\Poster\Storage\FileStorage()
);
$r = $m->create("slider")->generate();
echo "Key: " . $r["key"] . "\n";
echo "Image: " . substr($r["image"], 0, 80) . "...\n";
echo "Puzzle: " . substr($r["extra"]["puzzle"], 0, 80) . "...\n";
echo "OK - slider generated\n";
$r2 = $m->create("click")->setDifficulty("easy")->generate();
echo "Click targets: " . count($r2["extra"]["targets"]) . "\n";
echo "OK - click generated\n";
$r3 = $m->create("rotate")->generate();
echo "OK - rotate generated\n";
echo "All smoke tests passed\n";
'
```

Expected: All three types generate without errors, output valid base64 data URIs.

- [ ] **Step 5: Commit**

```bash
git add tests/Captcha/CaptchaTest.php
git commit -m "test: add captcha background and visual improvement tests"
```
