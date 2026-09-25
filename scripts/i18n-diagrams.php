<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 *
 * 多语言图表生成器：读取 scripts/i18n/{locale}.json 的文案，输出
 * docs/i18n/{locale}/{architecture,feature-design,lifecycle}.svg
 *
 * 用法:
 *   php scripts/i18n-diagrams.php            # 生成 scripts/i18n/*.json 里的全部语言
 *   php scripts/i18n-diagrams.php en ja ko   # 只生成指定语言
 *
 * 缺失的键自动回退到 en.json；文本超宽会提示并自动缩字号（下限 8.5px）。
 */

const ROOT = __DIR__ . '/..';
const I18N_SRC = __DIR__ . '/i18n';
const I18N_OUT = ROOT . '/docs/i18n';

const INK = '#2D3436', MUTED = '#7F8C8D', LINE = '#E1E8ED', PANEL = '#F8FAFC';
const CORAL = '#FF6B6B', TEAL = '#4ECDC4', BLUE = '#45B7D1', GREEN = '#96CEB4', ORANGE = '#FF8E53';
const TINT = [CORAL => '#FFF6F6', TEAL => '#F2FBF9', BLUE => '#F4FAFD', GREEN => '#F5FBF7', ORANGE => '#FFF8F2', MUTED => '#F8FAFC'];

const MONO = "'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace";
const FONT_DEFAULT = "-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei','Noto Sans CJK SC',sans-serif";

/** 各语言更合适的字体栈；JSON 里的 meta.fontStack 可覆盖 */
const FONT_BY_LANG = [
    'ja' => "'Hiragino Sans','Yu Gothic','Noto Sans JP','Meiryo',sans-serif",
    'ko' => "'Apple SD Gothic Neo','Malgun Gothic','Noto Sans KR',sans-serif",
    'ar' => "'Noto Naskh Arabic','Noto Sans Arabic','Segoe UI',sans-serif",
    'hi' => "'Noto Sans Devanagari','Nirmala UI',sans-serif",
    'bn' => "'Noto Sans Bengali','Nirmala UI',sans-serif",
    'ru' => "'PT Sans','Noto Sans',Arial,sans-serif",
];

$BUFFER = [];
$WARNINGS = [];

// ───────────────────────────── 基础绘制 ─────────────────────────────

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** 粗略字宽：CJK / 全角标点 / 箭头约 1em，其余约 0.55em；粗体按 +6% 估算 */
function textWidth(string $s, float $size, string $weight = '400'): float
{
    $w = 0.0;
    $len = mb_strlen($s, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $cp = mb_ord(mb_substr($s, $i, 1, 'UTF-8'), 'UTF-8');
        $w += $size * match (true) {
            $cp > 0x2000 => 1.0,                        // CJK / 全角标点 / 箭头
            $cp >= 0x0400 && $cp <= 0x04FF => 0.62,     // 西里尔（实测约 0.62em）
            default => 0.55,
        };
    }
    return $w * ($weight === '400' ? 1.0 : 1.06);
}

/** 超宽时自动缩字号（下限 8.5），并记录提示 */
function fitSize(string $s, float $maxWidth, float $size, string $where): float
{
    global $WARNINGS;
    if (textWidth($s, $size) <= $maxWidth) {
        return $size;
    }
    $scaled = max(8.5, $size * $maxWidth / textWidth($s, $size));
    $WARNINGS[] = sprintf('缩小字号 %s: %.1f→%.1fpx :: %s', $where, $size, $scaled, $s);
    return $scaled;
}

function el(string $markup): void
{
    global $BUFFER;
    $BUFFER[] = $markup;
}

function text(float $x, float $y, string $s, float $size = 13, string $fill = INK, string $anchor = 'start', string $weight = '400', ?string $family = null, ?string $opacity = null): void
{
    $a = $anchor !== 'start' ? ' text-anchor="' . $anchor . '"' : '';
    $w = $weight !== '400' ? ' font-weight="' . $weight . '"' : '';
    $f = $family !== null ? ' font-family="' . $family . '"' : '';
    $o = $opacity !== null ? ' opacity="' . $opacity . '"' : '';
    el(sprintf('<text x="%s" y="%s" font-size="%s" fill="%s"%s%s%s%s>%s</text>', n($x), n($y), n($size), $fill, $w, $a, $f, $o, esc($s)));
}

function rect(float $x, float $y, float $w, float $h, float $rx = 0, string $fill = 'none', ?string $stroke = null, float $sw = 1): void
{
    el(sprintf('<rect x="%s" y="%s" width="%s" height="%s"%s fill="%s"%s/>',
        n($x), n($y), n($w), n($h), $rx ? ' rx="' . n($rx) . '"' : '', $fill,
        $stroke ? ' stroke="' . $stroke . '" stroke-width="' . n($sw) . '"' : ''));
}

function circle(float $cx, float $cy, float $r, string $fill = 'none', ?string $stroke = null, float $sw = 1): void
{
    el(sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"%s/>', n($cx), n($cy), n($r), $fill,
        $stroke ? ' stroke="' . $stroke . '" stroke-width="' . n($sw) . '"' : ''));
}

function line(float $x1, float $y1, float $x2, float $y2, string $stroke = LINE, float $sw = 2, bool $arrow = false): void
{
    el(sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s"%s/>',
        n($x1), n($y1), n($x2), n($y2), $stroke, n($sw), $arrow ? ' marker-end="url(#arrow)"' : ''));
}

function n(float $v): string
{
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

/** 药丸标签；返回宽度 */
function chip(float $x, float $y, string $label, float $size = 12, string $fill = '#FFFFFF', string $fg = INK, string $stroke = LINE, float $pad = 11, float $h = 26, string $weight = '400'): float
{
    $w = textWidth($label, $size) + $pad * 2;
    rect($x, $y, $w, $h, $h / 2, $fill, $stroke, 1);
    text($x + $w / 2, $y + $h / 2 + $size * 0.36, $label, $size, $fg, 'middle', $weight);
    return $w;
}

/**
 * 一排药丸标签。$rowW > 0 时表示整行可用宽度：药丸按文字自适应宽度，
 * 仅当整行放不下才统一缩小字号（下限 9.5px），避免单个标签被压成蚂蚁字。
 */
function chipRow(float $x, float $y, array $items, float $size = 12, string $fill = '#FFFFFF', string $fg = INK, string $stroke = LINE, float $gap = 8, float $h = 26, float $pad = 11, string $weight = '400', float $rowW = 0): float
{
    global $WARNINGS;
    if ($rowW > 0 && $items) {
        $textTotal = array_sum(array_map(fn($it) => textWidth((string)$it, $size), $items));
        $fixed = count($items) * 2 * $pad + (count($items) - 1) * $gap;
        if ($textTotal + $fixed > $rowW) {
            $scaled = max(9.5, $size * ($rowW - $fixed) / $textTotal);
            $WARNINGS[] = sprintf('整行缩字号 %.1f→%.1fpx（%d 个标签）', $size, $scaled, count($items));
            $size = $scaled;
        }
    }
    foreach ($items as $it) {
        $x += chip($x, $y, (string)$it, $size, $fill, $fg, $stroke, $pad, $h, $weight) + $gap;
    }
    return $x;
}

function titleBlock(string $title, string $subtitle, float $w = 1040): void
{
    text($w / 2, 42, $title, 23, INK, 'middle', '700');
    text($w / 2, 66, $subtitle, 13, MUTED, 'middle');
}

function panel(float $x, float $y, float $w, float $h, string $color, string $label): void
{
    rect($x, $y, $w, $h, 16, TINT[$color] ?? PANEL, $color, 1.5);
    $lw = textWidth($label, 13) + 30;
    rect($x + 20, $y - 15, $lw, 30, 15, $color);
    text($x + 20 + $lw / 2, $y + 5, $label, 13, '#FFFFFF', 'middle', '700');
}

function svg(string $file, string $name, float $w, float $h, string $font = FONT_DEFAULT): void
{
    global $BUFFER;
    $head = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s" font-family="%s" role="img" aria-label="%s">', n($w), n($h), n($w), n($h), $font, esc($name)) . "\n"
        . '  <title>' . esc($name) . '</title>' . "\n"
        . '  <defs><marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">'
        . '<path d="M0 0L10 5L0 10Z" fill="#B2BEC3"/></marker></defs>' . "\n"
        . sprintf('  <rect width="%s" height="%s" fill="#FFFFFF"/>', n($w), n($h));
    $body = implode("\n  ", $BUFFER);
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, $head . "\n  " . $body . "\n</svg>\n");
    $BUFFER = [];
}

/** 从译文取键，缺失回退到英文 */
function t(array $lang, array $en, string $path, string $fallback = ''): string
{
    $get = function (array $a, string $p) {
        foreach (explode('.', $p) as $seg) {
            if (!is_array($a) || !array_key_exists($seg, $a)) {
                return null;
            }
            $a = $a[$seg];
        }
        return $a;
    };
    $v = $get($lang, $path);
    if ($v === null || $v === '') {
        $v = $get($en, $path);
    }
    return is_string($v) ? $v : ($v === null ? $fallback : (string)$v);
}

function tArr(array $lang, array $en, string $path): array
{
    $get = function (array $a, string $p) {
        foreach (explode('.', $p) as $seg) {
            if (!is_array($a) || !array_key_exists($seg, $a)) {
                return null;
            }
            $a = $a[$seg];
        }
        return $a;
    };
    $v = $get($lang, $path);
    if (!is_array($v) || $v === []) {
        $v = $get($en, $path);
    }
    return is_array($v) ? $v : [];
}

// ───────────────────────────── 架构图 ─────────────────────────────

function drawArchitecture(array $L, array $EN, string $font, string $out): void
{
    $W = 1040; $H = 700; $CX = 520;
    $A = 88; $B = 236; $C = 404; $D = 572;

    titleBlock(t($L, $EN, 'architecture.title'), t($L, $EN, 'architecture.subtitle'));

    // 接口层
    panel(40, $A, 960, 92, CORAL, t($L, $EN, 'architecture.layerApi'));
    $api = tArr($L, $EN, 'architecture.api');
    foreach ($api as $i => $box) {
        $x = 64 + $i * 186;
        rect($x, $A + 30, 168, 50, 10, '#FFFFFF', LINE, 1);
        rect($x, $A + 40, 4, 30, 2, CORAL);
        $t = fitSize($box[0] ?? '', 140, 13.5, 'api-title');
        $s = fitSize($box[1] ?? '', 140, 11, 'api-sub');
        text($x + 84, $A + 54, $box[0] ?? '', $t, INK, 'middle', '600');
        text($x + 84, $A + 72, $box[1] ?? '', $s, MUTED, 'middle');
    }

    // 业务层
    panel(40, $B, 960, 140, TEAL, t($L, $EN, 'architecture.layerBusiness'));
    foreach (tArr($L, $EN, 'architecture.business') as $i => $mod) {
        $x = $i === 0 ? 64 : 529;
        rect($x, $B + 28, 447, 96, 10, '#FFFFFF', LINE, 1);
        rect($x, $B + 38, 4, 76, 2, TEAL);
        text($x + 18, $B + 50, $mod[0] ?? '', fitSize($mod[0] ?? '', 410, 14, 'biz-title'), INK, 'start', '600');
        text($x + 18, $B + 68, $mod[1] ?? '', fitSize($mod[1] ?? '', 410, 12, 'biz-flow'), MUTED, 'start', '400', MONO);
        chipRow($x + 18, $B + 80, array_slice($mod[2] ?? [], 0, 5), 11.5, '#FFFFFF', INK, TEAL, 8, 30, 10, '400', 411);
    }

    // 核心层
    panel(40, $C, 960, 140, BLUE, t($L, $EN, 'architecture.layerCore'));
    foreach (tArr($L, $EN, 'architecture.core') as $i => $box) {
        $x = 64 + $i * 231;
        rect($x, $C + 28, 219, 96, 10, '#FFFFFF', LINE, 1);
        rect($x, $C + 38, 4, 76, 2, BLUE);
        text($x + 18, $C + 50, $box[0] ?? '', fitSize($box[0] ?? '', 185, 13, 'core-title'), INK, 'start', '600');
        text($x + 18, $C + 67, $box[1] ?? '', fitSize($box[1] ?? '', 188, 10, 'core-mono'), MUTED, 'start', '400', MONO);
        foreach (array_slice($box[2] ?? [], 0, 3) as $k => $sub) {
            text($x + 18, $C + 86 + $k * 15, '· ' . $sub, fitSize('· ' . $sub, 188, 11, 'core-sub'), MUTED);
        }
    }

    // 基础层
    panel(40, $D, 960, 80, GREEN, t($L, $EN, 'architecture.layerFoundation'));
    $x = chipRow(64, $D + 26, tArr($L, $EN, 'architecture.foundation.required'), 12.5, '#FFFFFF', INK, GREEN, 12, 34, 14, '600');
    text($x + 8, $D + 48, t($L, $EN, 'architecture.foundation.requiredLabel'), 11, MUTED);
    chipRow($x + 84, $D + 26, tArr($L, $EN, 'architecture.foundation.optional'), 12.5, '#FFFFFF', MUTED, LINE, 12, 34, 14);

    foreach ([[$A + 92, $B, t($L, $EN, 'architecture.arrowCall')], [$B + 140, $C, t($L, $EN, 'architecture.arrowDraw')], [$C + 140, $D, t($L, $EN, 'architecture.arrowRequire')]] as $ar) {
        line($CX, $ar[0] + 4, $CX, $ar[1] - 4, LINE, 2, true);
        text($CX + 12, ($ar[0] + $ar[1]) / 2 + 5, $ar[2], 11.5, MUTED);
    }

    text($CX, $H - 16, t($L, $EN, 'architecture.footer'), 11.5, MUTED, 'middle');
    svg($out . '/architecture.svg', t($L, $EN, 'architecture.title'), $W, $H, $font);
}

// ───────────────────────────── 功能图 ─────────────────────────────

function drawFeatures(array $L, array $EN, string $font, string $out): void
{
    $W = 1040; $H = 748;
    $LX = 40; $RX = 530; $CW = 470;

    titleBlock(t($L, $EN, 'features.title'), t($L, $EN, 'features.subtitle'));

    foreach (tArr($L, $EN, 'features.modules') as $i => $mod) {
        $x = $i === 0 ? $LX : $RX;
        $color = $i === 0 ? CORAL : TEAL;
        rect($x, 88, $CW, 54, 14, TINT[$color], $color, 1.5);
        circle($x + 38, 115, 14, $color);
        text($x + 38, 120, mb_substr($mod[0] ?? '?', 0, 1, 'UTF-8'), 13, '#FFFFFF', 'middle', '700');
        text($x + 62, 112, $mod[1] ?? '', fitSize($mod[1] ?? '', 390, 15.5, 'mod-title'), INK, 'start', '700');
        text($x + 62, 130, $mod[2] ?? '', fitSize($mod[2] ?? '', 390, 11.5, 'mod-sub'), MUTED);
    }

    foreach (tArr($L, $EN, 'features.captchaTypes') as $i => $row) {
        $y = 154 + $i * 76;
        rect($LX, $y, $CW, 64, 12, '#FFFFFF', LINE, 1);
        rect($LX, $y + 10, 4, 44, 2, CORAL);
        circle($LX + 34, $y + 32, 14, '#FFF0F0', CORAL, 1.5);
        text($LX + 34, $y + 37, (string)($i + 1), 13, CORAL, 'middle', '700');
        $t = fitSize($row[1] ?? '', 150, 14, 'cap-title');
        text($LX + 60, $y + 28, $row[1] ?? '', $t, INK, 'start', '600');
        text($LX + 66 + textWidth($row[1] ?? '', $t, '600'), $y + 28, $row[0] ?? '', 11, CORAL, 'start', '400', MONO);
        text($LX + 60, $y + 48, $row[2] ?? '', fitSize($row[2] ?? '', 260, 11.5, 'cap-desc'), MUTED);
        text($LX + $CW - 20, $y + 32, $row[3] ?? '', fitSize($row[3] ?? '', 150, 11, 'cap-meta'), MUTED, 'end');
    }

    $yBox = 154 + 4 * 76;
    rect($LX, $yBox, $CW, 122, 12, TINT[CORAL], CORAL, 1.5);
    text($LX + 20, $yBox + 28, t($L, $EN, 'features.security.title'), 14, INK, 'start', '700');
    chipRow($LX + 20, $yBox + 42, tArr($L, $EN, 'features.security.chips1'), 11.5, '#FFFFFF', INK, CORAL, 8, 28, 10, '400', 430);
    chipRow($LX + 20, $yBox + 80, tArr($L, $EN, 'features.security.chips2'), 11.5, '#FFFFFF', INK, CORAL, 8, 28, 10, '400', 430);

    $groupColors = [TEAL, BLUE, ORANGE, GREEN];
    foreach (tArr($L, $EN, 'features.elementGroups') as $i => $grp) {
        $y = 154 + $i * 76;
        $c = $groupColors[$i % 4];
        rect($RX, $y, $CW, 64, 12, '#FFFFFF', LINE, 1);
        rect($RX, $y + 10, 4, 44, 2, $c);
        text($RX + 20, $y + 28, $grp[0] ?? '', fitSize($grp[0] ?? '', 120, 14, 'grp-title'), INK, 'start', '600');
        $count = count($grp) - 1;
        text($RX + 26 + textWidth($grp[0] ?? '', 14, '600') + 6, $y + 28, sprintf(t($L, $EN, 'features.elementCount'), $count), 11, $c, 'start', '700');
        $x = $RX + 20;
        foreach (array_slice($grp, 1) as $tag) {
            $x += chip($x, $y + 34, $tag, 11, '#FFFFFF', INK, $c, 9, 24) + 6;
        }
    }

    rect($RX, $yBox, $CW, 122, 12, TINT[TEAL], TEAL, 1.5);
    text($RX + 20, $yBox + 28, t($L, $EN, 'features.template.title'), fitSize(t($L, $EN, 'features.template.title'), 420, 14, 'tpl-title'), INK, 'start', '700');
    chipRow($RX + 20, $yBox + 42, tArr($L, $EN, 'features.template.chips1'), 11.5, '#FFFFFF', INK, TEAL, 8, 28, 10, '400', 430);
    chipRow($RX + 20, $yBox + 80, tArr($L, $EN, 'features.template.chips2'), 11.5, '#FFFFFF', INK, TEAL, 8, 28, 10, '400', 430);

    $yEntry = 154 + 4 * 76 + 136;
    rect($LX, $yEntry, 960, 68, 12, PANEL, '#D8E0E6', 1.5);
    text($LX + 20, $yEntry + 28, t($L, $EN, 'features.entry.title'), 13.5, INK, 'start', '700');
    text($LX + 104, $yEntry + 28, t($L, $EN, 'features.entry.subtitle'), 11.5, MUTED);
    chipRow($LX + 20, $yEntry + 35, tArr($L, $EN, 'features.entry.chips'), 11.5, '#FFFFFF', INK, LINE, 8, 26, 10);
    text($LX + 430, $yEntry + 50, t($L, $EN, 'features.entry.note'), fitSize(t($L, $EN, 'features.entry.note'), 520, 11.5, 'entry-note'), MUTED);

    svg($out . '/feature-design.svg', t($L, $EN, 'features.title'), $W, $H, $font);
}

// ───────────────────────────── 生命周期图 ─────────────────────────────

function drawLifecycle(array $L, array $EN, string $font, string $out): void
{
    $W = 1040; $H = 812;
    $top = 118; $colW = 465; $gap = 30; $lx = 40; $rx = 40 + $colW + $gap;
    $cardH = 58; $step = 76;

    titleBlock(t($L, $EN, 'lifecycle.title'), t($L, $EN, 'lifecycle.subtitle'));

    $lanes = tArr($L, $EN, 'lifecycle.lanes');
    foreach ($lanes as $i => $lane) {
        $x = $i === 0 ? $lx : $rx;
        $color = $i === 0 ? CORAL : TEAL;
        panel($x, $top, $colW, $H - $top - 56, $color, $lane['title'] ?? '');
        text($x + 20, $top + 26, $lane['sub'] ?? '', fitSize($lane['sub'] ?? '', $colW - 40, 11.5, 'lane-sub'), MUTED, 'start', '400', MONO);

        $steps = $lane['steps'] ?? [];
        foreach ($steps as $k => $st) {
            $y = $top + 42 + $k * $step;
            rect($x + 20, $y, $colW - 40, $cardH, 12, '#FFFFFF', LINE, 1);
            circle($x + 48, $y + $cardH / 2, 15, $color);
            text($x + 48, $y + $cardH / 2 + 5, (string)($k + 1), 13, '#FFFFFF', 'middle', '700');
            text($x + 74, $y + 25, $st[0] ?? '', fitSize($st[0] ?? '', 150, 14, 'step-title'), INK, 'start', '700');
            text($x + 74, $y + 44, $st[1] ?? '', fitSize($st[1] ?? '', $colW - 94, 11, 'step-detail'), MUTED);
            if ($k < count($steps) - 1) {
                line($x + $colW / 2, $y + $cardH + 4, $x + $colW / 2, $y + $step - 4, LINE, 2, true);
            }
        }

        $y = $top + 42 + count($steps) * $step + 4;
        text($x + 20, $y + 10, $lane['branchTitle'] ?? '', 12, INK, 'start', '700');
        rect($x + 20, $y + 22, $colW - 40, 92, 12, '#FFFFFF', LINE, 1);
        foreach (($lane['branches'] ?? []) as $bi => $br) {
            $ry = $y + 22 + 16 + $bi * 26;
            $c = [$i === 0 ? GREEN : BLUE, $i === 0 ? ORANGE : CORAL, $i === 0 ? MUTED : TEAL][$bi] ?? MUTED;
            circle($x + 40, $ry, 4.5, $c);
            $label = $br[0] ?? '';
            text($x + 54, $ry + 4, $label, 12.5, $c, 'start', '700');
            $descX = $x + 54 + textWidth($label, 12.5, '700') + 10;
            text($descX, $ry + 4, $br[1] ?? '', fitSize($br[1] ?? '', $x + $colW - 40 - $descX, 10.5, 'branch-desc'), MUTED);
        }
    }

    svg($out . '/lifecycle.svg', t($L, $EN, 'lifecycle.title'), $W, $H, $font);
}

// ───────────────────────────── 入口 ─────────────────────────────

$files = array_slice($argv, 1);
if (!$files) {
    $files = array_map(fn($p) => basename($p, '.json'), glob(I18N_SRC . '/*.json') ?: []);
}

$en = json_decode((string)file_get_contents(I18N_SRC . '/en.json'), true);
if (!is_array($en)) {
    fwrite(STDERR, "缺少 scripts/i18n/en.json（基准文案）\n");
    exit(1);
}

foreach ($files as $locale) {
    $path = I18N_SRC . '/' . $locale . '.json';
    if (!is_file($path)) {
        fwrite(STDERR, "跳过 $locale：找不到 $path\n");
        continue;
    }
    $lang = json_decode((string)file_get_contents($path), true);
    if (!is_array($lang)) {
        fwrite(STDERR, "跳过 $locale：$path JSON 解析失败\n");
        continue;
    }
    $font = $lang['meta']['fontStack'] ?? FONT_BY_LANG[$locale] ?? FONT_DEFAULT;
    $out = I18N_OUT . '/' . $locale;
    $WARNINGS = [];
    drawArchitecture($lang, $en, $font, $out);
    drawFeatures($lang, $en, $font, $out);
    drawLifecycle($lang, $en, $font, $out);
    printf("%-4s → docs/i18n/%s/*.svg%s\n", $locale, $locale, $WARNINGS ? '  (' . count($WARNINGS) . ' 条缩字号提示)' : '');
    foreach ($WARNINGS as $w) {
        echo "      ! $w\n";
    }
}
