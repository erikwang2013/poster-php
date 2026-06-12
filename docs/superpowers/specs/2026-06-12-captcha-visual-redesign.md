# Captcha Visual Redesign

**Date:** 2026-06-12
**Status:** approved

## Problem

`AbstractCaptcha::createBackground()` generates a plain light-colored rectangle with 50 random 2px dots — visually cheap and pixelated. All three captcha types (Click, Slider, Rotate) inherit this background.

## Goal

Beautiful captcha images. Default backgrounds provided out of the box, user-configurable.

## Design

### Background selection priority

1. User-set single image path (`setBackground($path)`) — highest priority
2. Configured `captcha.background_dir` directory — random pick from `*.jpg|png|gif|webp`
3. Procedural generation (always available, no setup required)

### Procedural background generation

Three styles, picked at random each time:

| Style | Palette | Decorations | Noise |
|-------|---------|-------------|-------|
| `minimal` | Soft gradients (light blue/purple/gray) | Few low-opacity large circles, geometric lines | Sparse fine dots |
| `vibrant` | Bright gradients (blue-purple, pink-red, cyan-green) | Various-size circles, triangles | Medium density |
| `natural` | Warm tones (cream, beige, light brown) | Irregular color blocks simulating paper texture | Dense micro-dots |

Gradient simulated via horizontal 1px rectangles with interpolated colors (~50 strips).

### Config additions (`config/poster.php`)

```php
'captcha' => [
    // ... existing keys ...
    'background_dir' => null,        // path to directory with background images
    'background_styles' => ['minimal', 'vibrant', 'natural'],
],
```

### Per-type improvements

**SliderCaptcha:**
- Puzzle gap: rounded rectangle, dashed border
- Puzzle piece: rounded corners, drop shadow, semi-transparent white fill
- Arrow icon on piece indicating slide direction

**ClickCaptcha:**
- Target marker: large semi-transparent circle + bright border ring + order number
- Text label: rounded pill background (semi-transparent white) + text

**RotateCaptcha:**
- Benefits from background improvements alone
- Small rotation direction indicator added at image corner

### Files to change

- `config/poster.php` — add `background_dir`, `background_styles`
- `src/Captcha/AbstractCaptcha.php` — rewrite `createBackground()`, add procedural generation
- `src/Captcha/SliderCaptcha.php` — rounded corners, shadow, arrow icon
- `src/Captcha/ClickCaptcha.php` — improved target markers, pill labels

### No changes

- `ImageDriverInterface` and drivers — no new methods needed
- `CaptchaManager`, `CaptchaFactory`, `CaptchaInterface` — no API changes
- `RotateCaptcha` — background improvement is sufficient

### Constraints

- Works with both GD and Imagick drivers
- No new dependencies
- Uses existing `rectangle()`, `ellipse()`, `line()` primitives only
