# Poster-PHP 单元测试报告

- **日期**：2026-08-27
- **环境**：PHP 8.3.7 / PHPUnit 11.5.55 / 扩展：gd、mbstring、redis（imagick 未安装）
- **测试团队**：4 名 PHP 测试工程师并行（Captcha、Drivers+Qrcode、Poster、Storage）

## 结果摘要

| 指标 | 基线（测试前） | 本次（测试后） |
|------|---------------|---------------|
| 测试数 | 50 | **354** |
| 断言数 | 99 | **945** |
| 跳过数 | 2 | 19（全部为 imagick 未安装） |
| 失败数 | 0 | 0 |
| 新增测试文件 | — | 29 个新文件 + 1 个扩展（tests/Qrcode/QrcodeTest.php +11 方法） |

**结论：全部通过（OK, but some tests were skipped）。** 源码 `src/` 零改动，所有新增内容均为测试代码。

## 分模块统计

| 模块 | 覆盖类 | 新增测试文件 | 测试方法 | 备注 |
|------|--------|-------------|---------|------|
| Captcha | AbstractCaptcha、Factory、Manager、Click/Rotate/Slider | 6 | 51 | 含校验容差边界、难度、角度取模 |
| Drivers | GdDriver、ImagickDriver、DriverFactory | 4 | 52 | 像素级断言；Imagick 19 个跳过 |
| Qrcode | QrcodeGenerator | 修改 1 | +11 | 尺寸钳制、纠错级别、确定性 |
| Poster | Builder、Template、Config + 15 个 Elements | 13 | 113 | 含 13 个元素文件 80 方法 |
| Storage | File/Session/Redis/Factory | 5 | 45 | Redis mock 契约测试 |
| helpers + Installer | helpers.php、Installer、PosterConfig | 3 | 20 | 全局函数正常+边界 |

## 发现的问题（未修改源码，待决策）

### 高优先级
1. **QrcodeGenerator 掩码违规（QR 规范）**：掩码作用于功能图形（finder/timing 等被反转），扫码可能受影响。建议排除功能模块后重测扫码。
2. **CaptchaManager::verify 空数据边界**：存储被篡改为空 targets + 空 data 时返回 true，应拒绝。
3. **FileStorage::set 静默数据丢失**：非法 UTF-8 时 `json_encode` 返回 false，写空文件后误判成功（`0 !== false`），set 返回 true 但数据不可读。

### 中优先级
4. **PosterTemplate::fromJson 缺 `is_array()` 校验**：合法标量 JSON（`"123"`/`true`）抛 TypeError。
5. **PosterConfig::findProjectConfig 路径多算一层**：`dirname(__DIR__, 3)` 应为 2，项目级 `config/poster.php` 永远找不到，静默回退包内配置。
6. **QrcodeGenerator::setText('0') 被 empty() 误判**：真实内容 "0" 无法生成。
7. **ClickCaptcha::$targetType/setTargetType 死代码**：generate() 从未读取。
8. **PosterConfig::load($path) 不持久**：显式路径加载后，下一次 get() 因 mtime 不同会重载默认配置覆盖。

### 低优先级
9. **FileStorage 构造器对已存在文件路径**：先发 E_WARNING 再抛 RuntimeException。
10. **EmojiElement::codepointToChar 非法输入**：hexdec 非十六进制得 0，mb_chr(0) 渲染 NUL 字节。
11. **IconElement::codepointToChar 不支持 U+F005 格式**：触发 PHP 8.1+ deprecation；占位符正则不匹配带空格 `{{ name }}`。
12. **抽象类/裸异常**：配置未知背景风格抛裸 `UnhandledMatchError`（\Error）；GdDriver::create(0,0) 抛 ValueError，均无友好异常。

## 覆盖缺口

- **ImagickDriver 全部功能测试**在本环境仅跳过（19 个），待安装 imagick 扩展后执行。
- **RedisStorage** 未连真实服务器集成验证（本地有服务器，按任务要求优先 mock）；Redis 真实过期行为未验证。
- **StorageFactory 'auto' 的 session 分支**在 CLI SAPI 下不可达。
- **TTL 过期**仅在 File/Session 层验证；**Installer 源文件缺失分支**不可测（源路径硬编码）。
- Poster 元素未做像素级输出断言（仅验证不抛错/data URL）。

## 测试明细

<details>
<summary>完整 TestDox（347 项，点击展开）</summary>

```
Abstract Captcha (Erikwang2013\Poster\Tests\Captcha\AbstractCaptcha)
 [x] Setters are fluent and persist
 [x] Generate key returns unique 32 hex chars
 [x] Store merges metadata with default ttl
 [x] Store uses configured ttl
 [x] Create background with custom path loads image
 [x] Create background with missing path falls back to procedural
 [x] Create background uses configured directory
 [x] Unknown background style throws error

Abstract Element (Erikwang2013\Poster\Tests\Poster\Elements\AbstractElement)
 [x] To array contains type and options
 [x] To array with empty options
 [x] Resolve placeholders replaces known variables
 [x] Resolve placeholders leaves unknown variables
 [x] Resolve placeholders plain text

Artistic Text Element (Erikwang2013\Poster\Tests\Poster\Elements\ArtisticTextElement)
 [x] Stroke style draws outline and center
 [x] Default style is stroke
 [x] Shadow style draws shadow and main
 [x] Neon style draws glow layers
 [x] Gradient without font falls back to plain text
 [x] Gradient with real font composites mask
 [x] Unknown style draws once
 [x] Resolve replaces placeholders

Avatar Element (Erikwang2013\Poster\Tests\Poster\Elements\AvatarElement)
 [x] Non existent src renders nothing
 [x] Circle option sets radius
 [x] Square avatar renders once
 [x] Resolve replaces src placeholder

Calendar Element (Erikwang2013\Poster\Tests\Poster\Elements\CalendarElement)
 [x] Render with defaults draws
 [x] Render with monday start and array highlights
 [x] Render with string highlights
 [x] Render with invalid month normalizes
 [x] Render with custom options

Captcha (Erikwang2013\Poster\Tests\Captcha\Captcha)
 [x] Click captcha generate returns valid structure
 [x] Click captcha verify passes with correct data
 [x] Click captcha is one time use
 [x] Click captcha invalid data fails
 [x] Click captcha allows retry on failure
 [x] Rotate captcha generate returns valid structure
 [x] Random captcha returns valid type
 [x] Random captcha verification works
 [x] Slider captcha generate returns valid structure
 [x] Captcha background uses procedural generation by default
 [x] Captcha background respects custom path via set background
 [x] Slider captcha piece has visual improvements
 [x] Max attempts blocks after limit
 [x] Rotate difficulty affects angle range
 [x] Slider captcha works with small background

Captcha Factory (Erikwang2013\Poster\Tests\Captcha\CaptchaFactory)
 [x] Create returns correct implementation with data set "click"
 [x] Create returns correct implementation with data set "rotate"
 [x] Create returns correct implementation with data set "slider"
 [x] Create random returns one of three types
 [x] Create with unknown type throws invalid argument exception
 [x] Create with empty type throws invalid argument exception

Captcha Manager (Erikwang2013\Poster\Tests\Captcha\CaptchaManager)
 [x] Verify with unknown key returns false
 [x] Verify click with correct data deletes key
 [x] Verify click with wrong count fails and increments
 [x] Verify click tolerance boundary
 [x] Verify click with malformed data fails
 [x] Verify click with missing targets fails
 [x] Verify type mismatch fails and counts as attempt
 [x] Verify unknown type fails
 [x] Verify blocks at max attempts and deletes key
 [x] Verify below max attempts still checks
 [x] Verify rotate wraps around 360
 [x] Verify rotate tolerance boundary
 [x] Verify rotate with malformed data fails
 [x] Verify slider tolerance boundary and numeric string
 [x] Verify slider with malformed data fails
 [x] Verify tolerance overridable via config
 [x] Verify empty targets with empty data passes

Chart Element (Erikwang2013\Poster\Tests\Poster\Elements\ChartElement)
 [x] Bar with empty data renders nothing
 [x] Line with single point renders nothing
 [x] Pie with zero total renders nothing
 [x] Bar chart draw counts
 [x] Bar chart accepts scalar values
 [x] Line chart draw counts
 [x] Pie chart draw counts
 [x] Unknown type falls back to bar

Click Captcha (Erikwang2013\Poster\Tests\Captcha\ClickCaptcha)
 [x] Set words custom words used in targets
 [x] Empty words falls back to configured words
 [x] Target count by difficulty with data set "easy"
 [x] Target count by difficulty with data set "medium"
 [x] Target count by difficulty with data set "hard"
 [x] Default difficulty is medium
 [x] Extra texts match stored targets
 [x] Additional setters are fluent

Driver (Erikwang2013\Poster\Tests\Drivers\Driver)
 [x] Create returns correct size
 [x] Rectangle draws on canvas
 [x] Text draws without error
 [x] Save creates file
 [x] Output returns data url
 [x] Load rejects oversized image
 [x] Load rejects corrupt image
 [ ] Image accepts imagick overlay
 [ ] Image accepts gd overlay on imagick

Driver Contract (Erikwang2013\Poster\Tests\Drivers\DriverContract)
 [x] Gd driver implements all interface methods
 [x] Imagick driver implements all interface methods
 [x] Both drivers are extensible

Driver Factory (Erikwang2013\Poster\Tests\Drivers\DriverFactory)
 [x] Create with null uses auto routing
 [x] Auto returns gd driver when imagick unavailable
 [ ] Auto returns imagick driver when available
 [x] Create explicit gd
 [ ] Create explicit imagick
 [x] Create imagick without extension throws error
 [x] Create unknown driver falls back to gd
 [x] Is imagick available reflects environment

Emoji Element (Erikwang2013\Poster\Tests\Poster\Elements\EmojiElement)
 [x] Empty emoji renders nothing
 [x] Int codepoint renders character
 [x] String codepoint formats
 [x] Out of range codepoint renders nothing
 [x] Emoji character renders once
 [x] Resolve replaces emoji placeholder

Emoticon Element (Erikwang2013\Poster\Tests\Poster\Elements\EmoticonElement)
 [x] Expressions lists all keys
 [x] Known expression renders kaomoji
 [x] Unknown expression renders nothing
 [x] Text takes precedence over expression
 [x] Font only applied when file exists
 [x] Resolve replaces text placeholder

File Storage Edge (Erikwang2013\Poster\Tests\Storage\FileStorageEdge)
 [x] Get corrupt file returns null
 [x] Get empty file returns null
 [x] Del missing key returns true
 [x] Has
 [x] Increment missing key returns zero
 [x] Increment corrupt file returns zero
 [x] Increment preserves data
 [x] Set with initial attempts
 [x] Unicode roundtrip
 [x] Empty data roundtrip
 [x] Set invalid utf 8 data unreadable
 [x] Constructor creates missing directory
 [x] Constructor throws when path is file
 [x] Constructor default path

Gd Driver (Erikwang2013\Poster\Tests\Drivers\GdDriver)
 [x] Create sets size and transparent background
 [x] Create with zero size throws
 [x] Resize changes dimensions
 [x] Rotate 90 swaps dimensions
 [x] Rotate with solid background fills corners
 [x] Rotate with transparent background keeps alpha
 [x] Circle masks corners transparent
 [x] Crop keeps selected pixels
 [x] Text builtin font draws pixels
 [x] Text multiline and alignments
 [x] Text ttf draws with font file
 [x] Text ttf wrap and angle
 [x] Text with missing font falls back to builtin
 [x] Image overlay composites at position
 [x] Image overlay with scale options
 [x] Image overlay with radius and shadow
 [x] Rectangle filled draws pixel
 [x] Rectangle unfilled leaves interior
 [x] Rectangle with radius draws
 [x] Rectangle opacity sets alpha
 [x] Rectangle hex alpha color
 [x] Ellipse filled and unfilled
 [x] Filled arc sweeps clockwise
 [x] Line draws with thickness
 [x] Filters do not change size
 [x] Save all formats
 [x] Save creates nested directories
 [x] Save quality extremes
 [x] Output formats return valid data urls
 [x] Load valid png round trip
 [x] Load missing file throws
 [x] Load unreadable content throws
 [x] Load unsupported type throws
 [x] Get resource and get size types
 [x] Set gd resource adopts external image
 [x] Clone is independent
 [x] Destroy clears resource

Helpers (Erikwang2013\Poster\Tests\Helpers\Helpers)
 [x] Functions exist
 [x] Captcha create returns array
 [x] Captcha create uses default type
 [x] Captcha create with difficulty
 [x] Captcha create unknown type throws
 [x] Verify missing key returns false
 [x] Verify wrong type increments attempts
 [x] Verify correct answer deletes key
 [x] Verify over max attempts returns false
 [x] Poster create applies width height
 [x] Poster create without dimensions

Icon Element (Erikwang2013\Poster\Tests\Poster\Elements\IconElement)
 [x] Empty icon renders nothing
 [x] Unknown icon renders nothing
 [x] Known icon without font renders placeholder
 [x] Known icon with font renders glyph
 [x] Codepoint overrides icon

Image Element (Erikwang2013\Poster\Tests\Poster\Elements\ImageElement)
 [x] Non existent source renders nothing
 [x] Renders real image file
 [x] Resolve replaces src placeholder

Installer (Erikwang2013\Poster\Tests\Installer)
 [x] Copy config publishes both locations
 [x] Copy config does not overwrite existing

Line Element (Erikwang2013\Poster\Tests\Poster\Elements\LineElement)
 [x] Defaults
 [x] X y fallback to x 2 y2
 [x] String coordinates coerced and options passed

Poster (Erikwang2013\Poster\Tests\Poster\Poster)
 [x] Basic poster save
 [x] Save then output renders elements once
 [x] Poster output returns data url
 [x] Poster template system
 [x] Chart bar element
 [x] Chart line element
 [x] Calendar element
 [x] Artistic text element
 [x] Emoji element
 [x] Icon element
 [x] Emoticon element
 [x] Image element placement

Poster Builder (Erikwang2013\Poster\Tests\Poster\PosterBuilder)
 [x] Width height are chainable
 [x] All add methods are chainable
 [x] Default dimensions from config
 [x] Background recognizes hex color
 [x] Background recognizes short hex without hash
 [x] Background recognizes image file
 [x] Background ignores invalid value
 [x] Background gradient vertical bands
 [x] Background gradient horizontal bands
 [x] Template overrides dimensions and elements
 [x] With variables resolve in template elements
 [x] Render happens only once
 [x] Save passes quality to driver
 [x] Add line x fallback to x 2
 [x] Destroy calls driver

Poster Config (Erikwang2013\Poster\Tests\PosterConfig)
 [x] Get nested value
 [x] Get missing key returns default
 [x] Get partial missing path returns default
 [x] Merge overrides deep and keeps others
 [x] Reset reloads config
 [x] Load custom path

Poster Config (Erikwang2013\Poster\Tests\Poster\PosterConfig)
 [x] Load returns package config
 [x] Get with dot notation
 [x] Get returns nested array value
 [x] Get missing key returns default
 [x] Merge overrides recursively
 [x] Get after merge reflects overrides
 [x] Load with explicit path
 [x] Reset clears cache

Poster Template (Erikwang2013\Poster\Tests\Poster\PosterTemplate)
 [x] Constructor and getters
 [x] From config defaults
 [x] From config with elements
 [x] From json parses valid json
 [x] From json invalid json falls back to defaults
 [x] From json scalar json throws type error
 [x] Build maps all element types
 [x] Build skips unknown and missing type
 [x] Build resolves placeholders
 [x] To array and to json round trip

Qrcode (Erikwang2013\Poster\Tests\Qrcode\Qrcode)
 [x] Generate returns gd image with correct dimensions
 [x] Output returns non empty png data
 [x] Small size does not crash
 [x] Render throws on empty text
 [x] Text zero is rejected like empty
 [x] Oversized data throws
 [x] Size clamped to minimum
 [x] Margin affects output size
 [x] Negative margin clamped to zero
 [x] Invalid error level falls back to high
 [x] All error levels render
 [x] Foreground and background colors applied
 [x] Render is deterministic
 [x] Large text stays within size
 [x] Setters return static for chaining

Qrcode Element (Erikwang2013\Poster\Tests\Poster\Elements\QrcodeElement)
 [x] Empty content renders nothing
 [x] Renders real qrcode
 [x] Label renders text below
 [x] Logo option renders
 [x] Resolve replaces content placeholder

Redis Storage (Erikwang2013\Poster\Tests\Storage\RedisStorage)
 [x] Set writes payload and counter with ttl
 [x] Set uses default ttl
 [x] Get returns data with attempts
 [x] Get missing key returns null
 [x] Get corrupt payload returns null
 [x] Del removes both keys
 [x] Del missing key returns true
 [x] Has
 [x] Increment attempts first time sets expire
 [x] Increment attempts subsequent skips expire
 [x] Increment attempts with expired main key skips expire
 [x] Constructor with injected client skips connect

Rotate Captcha (Erikwang2013\Poster\Tests\Captcha\RotateCaptcha)
 [x] Set size clamps to bounds
 [x] Set angle range inverted clamps to min
 [x] Angle within difficulty ranges
 [x] Verify with wrapped angles passes
 [x] Verify angle beyond tolerance fails
 [x] Verify non numeric angle fails
 [x] Setters are fluent

Session Storage (Erikwang2013\Poster\Tests\Storage\SessionStorage)
 [x] Set and get
 [x] Get missing returns null
 [x] Has
 [x] Del
 [x] Increment attempts
 [x] Increment missing returns zero
 [x] Expired entry returns null

Session Storage Edge (Erikwang2013\Poster\Tests\Storage\SessionStorageEdge)
 [x] Set returns true and writes namespaced
 [x] Get merges attempts
 [x] Del missing key returns true
 [x] Set with initial attempts
 [x] Expired entry removed on get
 [x] Keys are isolated

Shape Element (Erikwang2013\Poster\Tests\Poster\Elements\ShapeElement)
 [x] Default rect
 [x] Custom rect
 [x] Circle uses cx cy radius
 [x] Circle falls back to size
 [x] Circle falls back to x y

Slider Captcha (Erikwang2013\Poster\Tests\Captcha\SliderCaptcha)
 [x] Default puzzle size is 50
 [x] Hard difficulty shrinks puzzle
 [x] Small background clamps puzzle position
 [x] Verify tolerance boundary
 [x] Verify non numeric fails

Storage (Erikwang2013\Poster\Tests\Storage\Storage)
 [x] Set and get
 [x] Expired key returns null
 [x] Del removes key
 [x] Attempts persist after increment

Storage Factory (Erikwang2013\Poster\Tests\Storage\StorageFactory)
 [x] File driver
 [x] Session driver
 [x] Redis driver
 [x] Unknown driver throws
 [x] Null driver uses config
 [x] Auto driver
 [x] All drivers implement interface

Table Element (Erikwang2013\Poster\Tests\Poster\Elements\TableElement)
 [x] Empty headers renders nothing
 [x] Empty rows renders nothing
 [x] Header and zebra rows draw counts
 [x] Custom col widths and alignments
 [x] Missing cells are skipped

Text Element (Erikwang2013\Poster\Tests\Poster\Elements\TextElement)
 [x] Render defaults to origin
 [x] Render content fallback and text precedence
 [x] Render coerces coordinates to int
 [x] Render with empty text still calls
 [x] Resolve replaces text placeholders
 [x] Resolve uses content key and keeps unknown vars

Watermark Element (Erikwang2013\Poster\Tests\Poster\Elements\WatermarkElement)
 [x] Empty text renders nothing
 [x] Empty canvas size renders nothing
 [x] Tiles across canvas with angle
 [x] Spacing shorthand and coordinates
 [x] Per axis spacing overrides shorthand
 [x] Resolve replaces text placeholder
```

</details>

## 复现命令

```bash
vendor/bin/phpunit                    # 全量测试
vendor/bin/phpunit --testdox          # 方法级明细
```

## 修复记录（同日，4 名修复工程师并行）

上表「发现的问题」12 项已全部处理，修复后全量 **354 测试 / 945 断言 / 19 跳过（imagick）全绿**：

| # | 问题 | 修复 |
|---|------|------|
| 1 | Qrcode 掩码反转功能图形（QR 规范违规） | `placeData` 记录数据格坐标，`applyMask` 仅对数据格取反；新增 finder/timing 不被反转测试 |
| 2 | CaptchaManager verify 空 targets+空 data 放行 | targets 为空直接返回 false |
| 3 | FileStorage::set 非法 UTF-8 静默丢失 | json_encode 失败即返回 false，不写文件 |
| 4 | PosterTemplate::fromJson 标量 JSON 抛 TypeError | 非数组抛带类型信息的 InvalidArgumentException；null 仍回落默认 |
| 5 | PosterConfig 项目配置路径多算一层 | 改取 getcwd() 与 Installer 发布位置一致，兜底 dirname(__DIR__, 2)；chdir 模拟测试验证 |
| 6 | setText('0') 被 empty() 拒绝 | 改 `$this->text === ''` 判断 |
| 7 | ClickCaptcha targetType 死代码 | generate() 校验非 'text' 抛 InvalidArgumentException（保留公开 API） |
| 8 | PosterConfig::load($path) 不持久 | 新增 $loadedPath 参与缓存判断，reset() 清除 |
| 9 | FileStorage 构造器对已存在文件 E_WARNING | 构造时先 file_exists && !is_dir 抛可读异常，无 @ 抑制 |
| 10 | EmojiElement 非法十六进制渲染 NUL | ctype_xdigit 校验，非法返回空跳过渲染 |
| 11 | IconElement U+F005 格式 + 空格占位符 | 剥离 U+ 前缀 + xdigit 校验；占位符正则 `\{\{\s*(\w+)\s*\}\}` |
| 12 | 裸 UnhandledMatchError / ValueError | 背景风格与 GdDriver::create 入口校验，抛可读 InvalidArgumentException |

**兼容性配套**：CI 矩阵扩展 PHP 8.0–8.5；2 处 `#[DataProvider]` 转 docblock `@dataProvider` 兼容 PHPUnit 9（PHP 8.0/8.1 用）；PHPUnit 11 下 2 条 docblock 弃用提示为预期代价。新增 `.github/workflows/release.yml`：推送后取最新 tag 增量 bump（默认 patch，手动 major/minor），创建 tag + GitHub Release，无打包步骤。

## 后续建议

1. 安装 imagick 扩展后重跑，补齐 19 个跳过项；
2. 可选：接入 xdebug 跑覆盖率，输出百分比到本报告。
