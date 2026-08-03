# poster-php 代码审查报告

**最新审查日期**: 2026-08-03  
**上次审查**: 2026-08-02  
**测试结果**: 33/33 通过，70 断言，0 失败，0 warning  
**PHP**: 8.3.7 (cli) | **扩展**: gd, mbstring, json, curl (imagick 未安装, redis 未安装)

---

## 总体评分

| 维度 | 评分 | 变化 |
|------|------|------|
| 代码质量 | B+ | — |
| 测试覆盖 | B | — |
| 生态完整度 | C+ | — |
| 安全性 | A- | — |
| 性能 | B | — |
| 文档 | A- | — |

---

## 一、Bug

### BUG-1 🆕 示例脚本键名错误 (P1)

**文件**: `examples/captcha-click.php:18`

```php
foreach ($result['extra']['targets'] as $t) {  // Warning: Undefined array key "targets"
```

ClickCaptcha::generate() 返回的 extra 键名是 `texts`，不是 `targets`（参见 `ClickCaptcha.php:70`）。运行示例直接产生 PHP Warning。

**修复**: 将 `$result['extra']['targets']` 改为 `$result['extra']['texts']`。

### BUG-2 🆕 ClickCaptcha 硬编码字体回退路径 (P2)

**文件**: `src/Captcha/ClickCaptcha.php:47-49`

```php
$fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';
if (!is_file($fontFile)) {
    $fontFile = '/usr/share/fonts/fonts-gb/GB_ST_GB18030.ttf';  // 多数系统不存在此路径
}
```

应使用 `PosterConfig::get('image.font')` 统一读取字体配置，而非硬编码特定系统的路径。

---

## 二、历史修复确认（2026-08-02）

以下问题已在上一轮全部修复，重新验证通过：

| 问题 | 验证结果 |
|------|----------|
| imagettfbbox 未检查 false → 已加检查 | `GdDriver.php:165` `if ($bbox === false) continue;` |
| wrapTextTtf imagettfbbox 未检查 false → 已加检查 | `GdDriver.php:388` `if ($bbox === false) continue 2;` |
| mt_rand 用于安全敏感位置 → 已替换 random_int | ClickCaptcha/SliderCaptcha/RotateCaptcha 全部 `random_int()` |
| 模板类型名不一致 → 同时支持两种 | `PosterTemplate.php:58` 支持 `artistictext` 和 `artistic-text` |

---

## 三、安全和健壮性

### SEC-1 🆕 配置文件路径依赖包目录结构 (P2)

**文件**: `config/poster.php:21,28`

```php
'font' => dirname(__DIR__) . '/src/fonts/Alibaba-PuHuiTi-Regular.ttf',
'background_dir' => dirname(__DIR__) . '/assets/backgrounds',
```

配置通过 `Installer::copyConfig()` 发布到项目 `config/` 目录后，`dirname(__DIR__)` 指向项目根而非包目录，路径将失效。需在 Installer 发布时替换路径，或让 PosterConfig 在运行时基于包安装位置解析。

### SEC-2 Installer 使用 @mkdir 静默失败 (P3)

**文件**: `src/Installer.php:31`

```php
@mkdir($dir, 0755, true);  // 权限不足时无任何提示
```

### SEC-3 FileStorage 默认路径可预测 (P4)

**文件**: `src/Storage/FileStorage.php:18`

默认 `sys_get_temp_dir() . '/poster-captcha'` 在共享主机上可能被其他用户读取验证码答案。生产环境应使用项目内受保护目录。

---

## 四、性能

### PERF-1 GdDriver::circle() 逐像素 O(n²) (P2)

**文件**: `src/Drivers/GdDriver.php:113-122`

200px 圆形需要 40,000 次 `imagesetpixel()`。可使用 `imagefilledellipse` 预生成蒙版 + `imagecopymerge`，性能提升 10-50 倍。

### PERF-2 GdDriver::roundCornersGD() 同样 O(n²) (P2)

**文件**: `src/Drivers/GdDriver.php:455-461`

同上，每个圆角头像/图片都触发。

### PERF-3 drawShadowGD 最多 40 次高斯模糊 (P3)

**文件**: `src/Drivers/GdDriver.php:502`

```php
for ($i = 0; $i < min($blur * 2, 20); $i++) {  // $blur 最大 20 → 40 次迭代
    imagefilter($shadowImg, IMG_FILTER_GAUSSIAN_BLUR);
}
```

可减少上限或使用缩小-模糊-放大快速近似法。

### PERF-4 PosterBuilder 渐变逐行绘制 (P4)

**文件**: `src/Poster/PosterBuilder.php:93-98`

750x1334 画布需 1334 条 line 调用。用 `imagefill` + 区间矩形可减少调用次数。

### PERF-5 AbstractCaptcha 渐变 120 次矩形 (P4)

**文件**: `src/Captcha/AbstractCaptcha.php:111-119`

同 PERF-4，可合并相邻同色行为更少矩形。

---

## 五、代码质量

### CQ-1 🆕 ChartElement 抽象泄漏 — 绕过驱动接口 (P3)

**文件**: `src/Poster/Elements/ChartElement.php:150-153`

```php
$res = $canvas->getResource();
if ($res instanceof \GdImage) {
    imagefilledarc($res, ...);  // 直接操作 GD 资源
}
```

其他元素通过 `ImageDriverInterface` 操作，唯独 ChartElement 直接访问底层 GD。Imagick 回退用线条模拟扇形，效果很差。应通过驱动接口添加 arc/pie 方法解决。

### CQ-2 🆕 ChartElement 重复 hexToRgb (P4)

**文件**: `src/Poster/Elements/ChartElement.php:176-181`

与 `GdDriver::hexToRgb()` 完全重复。考虑提取为工具函数。

### CQ-3 ArtisticTextElement gradient 逐像素着色 (P4)

**文件**: `src/Poster/Elements/ArtisticTextElement.php:93-106`

600x80 文本区域需处理 48,000 像素。与 PERF-1 同类问题。

### CQ-4 元素 resolve 方法存在重复模式 (P4)

TextElement/ImageElement/QrcodeElement/WatermarkElement 等元素的 `resolve()` 结构高度相似但选项键名各不相同。AbstractElement 可提供通用实现。

---

## 六、生态配置完整性

### 🆕 缺失项

| 文件/配置 | 重要性 | 说明 |
|-----------|--------|------|
| `.gitattributes` | **高** | Composer 包关键文件。缺少会导致 tests/docs/examples 被安装到生产项目 |
| `.github/workflows/ci.yml` | **高** | 无 CI/CD，代码变更无自动化验证 |
| `CHANGELOG.md` | 中 | 无版本变更记录 |
| `phpstan.neon` / `psalm.xml` | 中 | 无静态分析，类型安全问题靠人工发现 |
| `.php-cs-fixer.php` | 中 | 无代码风格统一工具 |
| `CONTRIBUTING.md` | 低 | 无外部贡献者指南 |

### 已具备

| 项目 | 状态 |
|------|------|
| composer.json（含 autoload/scripts/extra） | 完善 |
| phpunit.xml.dist | 有 |
| LICENSE (MIT) | 有 |
| .gitignore | 有 |
| README.md + README_EN.md（中英双语，含支付支持） | 完善 |
| config/poster.php（详细注释，中英双语） | 完善 |
| docs/architecture.md | 有 |
| examples/（2 个示例） | ⚠️ captcha-click.php 有 bug |
| 框架适配器（Laravel/ThinkPHP/Webman/Hyperf） | 4 主流框架 |
| 资源文件（6 背景图 + 2 支付二维码图） | 有 |
| 字体文件（5 种阿里巴巴普惠体字重） | 有 |

---

## 七、测试覆盖

### 覆盖情况

| 模块 | 覆盖程度 | 缺口 |
|------|----------|------|
| GdDriver | 基础 | 无 rotate/circle/crop/clone/blur 等高级操作测试 |
| ImagickDriver | **零覆盖** | 未安装扩展，整个驱动未测试 |
| Captcha 生成 | 良好 | RotateCaptcha 验证流程被测试主动跳过（line 143） |
| Captcha 验证 | 良好 | 无 max_attempts 超限测试、无 TTL 过期测试 |
| PosterBuilder | 良好 | 各元素有输出测试，但无视觉正确性验证 |
| PosterTemplate | 基础 | 无 fromJson/toJson/toArray 序列化测试 |
| Storage | 基础 | 仅 FileStorage；RedisStorage/SessionStorage 零覆盖 |
| QrcodeGenerator | 基础 | 仅输出格式验证 |
| PosterConfig | 基础 | 无 findProjectConfig/merge 测试 |
| Adapters | **零覆盖** | 4 个框架适配器全无测试 |
| DriverFactory | **零覆盖** | 无 auto-detection 逻辑测试 |

---

## 八、优先修复建议

### 第一批（P1 — 立即）

1. **修复 BUG-1** — 示例脚本键名错误（1 行）
2. **添加 `.gitattributes`** — 排除 tests/docs/examples 出生产包

### 第二批（P2 — 本周）

3. **修复 BUG-2** — 统一字体路径到 PosterConfig
4. **添加 `.github/workflows/ci.yml`** — PHP 8.0/8.1/8.2/8.3 矩阵测试
5. **修复 SEC-1** — Installer 发布配置时处理路径
6. **性能 PERF-1** — circle() 改用蒙版方案

### 第三批（P3/P4 — 下次迭代）

7. 修复 CQ-1 — ChartElement 添加驱动级 arc/pie 方法
8. 补充 ImagickDriver 测试
9. 补充 RotateCaptcha 验证测试
10. 添加 phpstan baseline
11. 创建 CHANGELOG.md

---

## 九、测试执行记录

```
PHPUnit 11.5.55 — PHP 8.3.7
OK (33 tests, 70 assertions)
Time: 00:01.276, Memory: 16.00 MB
```

- 全部 PHP 源文件语法检查通过
- 示例 `poster-basic.php` → 海报生成成功
- 示例 `captcha-click.php` → PHP Warning（见 BUG-1）
- 所有元素类型在测试中均能正常渲染输出

---

## 十、总结

poster-php 核心功能完整、代码结构清晰、框架适配广泛，上次审查发现的问题已全部修复。本审查新增发现 2 个 bug、3 个安全/健壮性问题、5 个性能优化点、4 个代码质量改进点和 6 个生态缺失项。**可生产使用**，建议优先修复 `.gitattributes` 和示例 bug。
