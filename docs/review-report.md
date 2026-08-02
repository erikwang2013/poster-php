# poster-php 代码审查报告

**审查日期**: 2026-08-02
**测试结果**: 33 个测试，70 个断言，全部通过，0 个 warning（已全部修复）

### 已修复问题（2026-08-02）

| 问题 | 文件 | 修复方式 |
|------|------|---------|
| imagettfbbox 未检查 false | `src/Drivers/GdDriver.php:164` | 增加 false 检查，跳过无法渲染的字符 |
| wrapTextTtf imagettfbbox 未检查 | `src/Drivers/GdDriver.php:385` | 增加 false 检查 |
| mt_rand 用于安全敏感位置 | `ClickCaptcha.php`, `SliderCaptcha.php`, `RotateCaptcha.php` | 替换为 `random_int()` |
| 模板类型名 artistictext/artistic-text | `src/Poster/PosterTemplate.php:58` | 同时支持两种写法 |

---

## 一、Bug（已全部修复 ✅）

### 1.1 ✅ GdDriver::text() — imagettfbbox/imagettftext 未检查 false 返回值

**文件**: `src/Drivers/GdDriver.php:164-171`
**严重程度**: 中

当字体文件无法渲染某个字符（如 emoji 字符 😀）时，`imagettfbbox()` 和 `imagettftext()` 返回 `false`，但代码直接访问数组索引：

```php
// 第164行 — $bbox 可能是 false
$bbox = imagettfbbox($size, $angle, $fontFile, $line);
$lineW = $bbox[2] - $bbox[0];  // PHP Warning: Trying to access array offset on false
```

**已确认触发**: 测试 `testEmojiElement` 产生了 3 个 PHP Warning。

**修复建议**:
```php
$bbox = @imagettfbbox($size, $angle, $fontFile, $line);
if ($bbox === false) {
    continue; // 跳过无法渲染的字符
}
$lineW = $bbox[2] - $bbox[0];
```

### 1.2 ✅ GdDriver::wrapTextTtf() — imagettfbbox 未检查 false

**文件**: `src/Drivers/GdDriver.php:385`
**严重程度**: 低

同样的问题在自动换行函数中：
```php
$bbox = imagettfbbox($size, 0, $fontFile, $test);
$w = $bbox[2] - $bbox[0];  // $bbox 可能是 false
```

---

## 二、安全问题

### 2.1 ✅ 验证码位置生成使用 mt_rand — 已修复

**文件**: `src/Captcha/ClickCaptcha.php:91-92`, `src/Captcha/SliderCaptcha.php:29-30`, `src/Captcha/RotateCaptcha.php:48`
**严重程度**: 低

`mt_rand()` 的输出是确定性可预测的。攻击者如果知道种子，可以预测验证码答案位置。
`AbstractCaptcha::generateKey()` 已经用了 `random_bytes(16)` 做 key，但与位置/角度生成不一致。

**建议**: 安全敏感的位置/角度生成改用 `random_int()`.

### 2.2 文件存储默认路径为系统临时目录

**文件**: `src/Storage/FileStorage.php:18`
**严重程度**: 低

默认路径 `sys_get_temp_dir() . '/poster-captcha'` 存储验证码答案数据。在共享主机上，`/tmp` 可能被其他用户读取。

**建议**: 生产环境文档中提醒设置专用路径，或在配置中默认使用项目内目录。

### 2.3 SessionStorage 直接操作 $_SESSION 不检查状态

**文件**: `src/Storage/SessionStorage.php`
**严重程度**: 低

虽然 `StorageFactory` 在 auto 模式下检查了 `session_status()`，但如果显式使用 `SessionStorage`，没有 session 状态检查。如果在 CLI 模式下使用会静默失败。

---

## 三、代码质量 / 潜在优化

### 3.1 QrcodeGenerator RS_BLOCKS 数据不完整

**文件**: `src/Qrcode/QrcodeGenerator.php:53-68`
**严重程度**: 低

`RS_BLOCKS` 常量只定义了 version 1-20、25、30、35、40 的数据（简化近似值），缺少 21-24、26-29、31-34、36-39。当这些 version 被命中时，`nearestVersion()` 会回退到最近的已知版本，导致 RS block 计算有偏差，可能生成不完全标准的二维码。

### 3.2 GdDriver::circle() 逐像素处理性能差

**文件**: `src/Drivers/GdDriver.php:113-123`
**严重程度**: 低（功能正确，大尺寸时慢）

200px 直径的圆需要 40,000 次 `imagesetpixel()` 调用。对于简单圆形 mask，可用 GD 内置 `imagefilledellipse` + `imagecopymerge` 替代，速度有数量级提升。

### 3.3 GdDriver::roundCornersGD() 同样逐像素

**文件**: `src/Drivers/GdDriver.php:449-455`
**严重程度**: 低

与 circle 相同问题，大图片上圆角裁剪缓慢。

### 3.4 ✅ PosterTemplate 类型名不一致 — 已修复

**文件**: `src/Poster/PosterTemplate.php:58`
**严重程度**: 低

模板中艺术字体的类型名是 `'artistictext'`，但 README 文档中写的是 `'artistic-text'`。这是一个文档与实际实现的差异，会导致用户按文档使用时模板元素被跳过（`match` default 返回 null）。

### 3.5 渐变背景生成 120 次矩形绘制

**文件**: `src/Captcha/AbstractCaptcha.php:111-119`
**严重程度**: 建议

生成 120 个 1px 高的矩形来模拟渐变。可以改用 `imagefill` + 逐行颜色分配减少函数调用，或使用 GD 内置 `IMG_FILTER_SMOOTH` 做模糊模拟渐变。

### 3.6 海报渐变背景逐像素绘制

**文件**: `src/Poster/PosterBuilder.php:88-99`
**严重程度**: 建议

对于 1334px 高的海报，绘制 1334 条线段。功能上没问题，但可以预计算颜色段。

---

## 四、测试覆盖缺口

### 4.1 缺失的测试

| 缺失测试项 | 优先级 |
|-----------|--------|
| RotateCaptcha verify 流程 | 中 |
| max_attempts 超过限制后被删除并拒绝 | 中 |
| 验证码 TTL 过期后 get 返回 null | 中 |
| CaptchaFactory 传入未知类型抛异常 | 低 |
| DriverFactory 返回正确驱动类型 | 低 |
| RedisStorage / SessionStorage 基本读写 | 低 |
| PosterConfig findProjectConfig 路径解析 | 低 |
| PosterTemplate 未知元素类型被跳过 | 低 |

### 4.2 测试基础设施建议

- 测试文件中对 `PosterConfig::reset()` 的调用分布在各个测试方法中，但没有在所有 tearDown 中统一调用，可能导致测试间状态污染
- CaptchaTest 引用了 `CaptchaManager` 但直接构造 `FileStorage`，绕过了 `StorageFactory`，缺少集成测试覆盖

---

## 五、总结

| 分类 | 数量 | 状态 |
|------|------|------|
| Bug | 2 | ✅ 已全部修复 |
| 安全问题 | 1 (已修复) + 2 (待关注) | ✅ 核心安全问题已修复 |
| 代码质量 | 5 (1 已修复 + 4 建议) | 正确但可优化 |
| 测试覆盖 | 8+ | 核心路径已覆盖，边界和错误路径有缺口 |

**总体评价**: 代码质量良好，架构清晰，核心功能测试覆盖充分。已修复的关键问题包括：GD 字体渲染容错、验证码随机数安全性提升、模板类型名一致性。
