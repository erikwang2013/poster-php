<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Qrcode;

use Erikwang2013\Poster\Qrcode\QrcodeGenerator;
use PHPUnit\Framework\TestCase;

/**
 * 端到端解码验证：生成 PNG 后交给 zxing-cpp（第三方解码器）读回内容。
 *
 * 环境缺少 python3 或 zxingcpp 时跳过——纯 PHP 侧的不变量断言见 QrcodeTest。
 */
class QrDecodeTest extends TestCase
{
    /** 覆盖 L/M/Q/H 四个等级、小尺寸到 v40 最大符号以及 UTF-8 中文内容。 */
    private function samples(): array
    {
        $lorem = 'The quick brown fox jumps over the lazy dog. 0123456789 !@#$%^&*()_+-=[]{};:,.<>/?| ';

        return [
            'level-L small' => ['L', 'https://erik.xyz/poster?level=L&source=qr-decode-test'],
            'level-M medium' => ['M', substr(str_repeat($lorem, 4), 0, 200)],
            'level-Q medium' => ['Q', substr(str_repeat($lorem, 8), 0, 400)],
            'level-H small' => ['H', 'POSTER-PHP-QR-DECODE-TEST'],
            'level-H large' => ['H', substr(str_repeat($lorem, 20), 0, 1000)],
            'level-L largest' => ['L', substr(str_repeat($lorem, 60), 0, 2900)],
            'utf8 chinese' => ['H', str_repeat('中文二维码解码测试，Poster-PHP 纯 PHP 实现。', 3)],
            'utf8 chinese large' => ['M', str_repeat('中文二维码端到端解码验证：Reed-Solomon 纠错、掩码与分块交织。', 8)],
        ];
    }

    public function testEverySampleDecodesBackToItsContent(): void
    {
        $python = $this->pythonWithZxingCpp();
        if ($python === null) {
            $this->markTestSkipped('本机缺少 python3 或 zxingcpp，无法端到端解码验证');
        }

        $dir = sys_get_temp_dir() . '/poster-qr-decode-' . getmypid() . '-' . mt_rand(1000, 9999);
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            $this->markTestSkipped("无法创建临时目录 $dir");
        }

        $expected = [];
        try {
            foreach ($this->samples() as $name => [$level, $text]) {
                $generator = new QrcodeGenerator();
                $image = $generator->setText($text)->setErrorLevel($level)->setSize(600)->setMargin(4)->render();
                $file = "$dir/$name.png";
                imagepng($image, $file);
                imagedestroy($image);
                $expected[$file] = ['text' => $text, 'level' => $level, 'name' => $name];
            }

            $decoded = $this->decodeWithZxingCpp($python, $dir, array_keys($expected));
            $this->assertCount(count($expected), $decoded, 'zxing-cpp 未返回全部样本的解码结果');

            foreach ($expected as $file => $meta) {
                $result = $decoded[$file];
                $this->assertNotNull($result, "{$meta['name']}: zxing-cpp 无法解码该二维码");
                $this->assertSame($meta['text'], $result['text'], "{$meta['name']}: 解码内容与原文不一致");
                $this->assertSame(
                    base64_encode($meta['text']),
                    $result['bytes'],
                    "{$meta['name']}: 解码字节与原文不一致"
                );
                $this->assertSame($meta['level'], $result['level'], "{$meta['name']}: 读出的纠错级别与配置不符");
            }
        } finally {
            foreach (glob("$dir/*") ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /** zxing-cpp 可用时返回 python3 可执行文件路径，否则返回 null。 */
    private function pythonWithZxingCpp(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $status = 0;
        exec('command -v python3 2>/dev/null', $output, $status);
        if ($status !== 0 || empty($output[0])) {
            return null;
        }

        $python = trim($output[0]);
        $output = [];
        $status = 0;
        exec(escapeshellarg($python) . ' -c ' . escapeshellarg('import zxingcpp, PIL') . ' 2>/dev/null', $output, $status);
        return $status === 0 ? $python : null;
    }

    /** @param string[] $files @return array<string, array{text: string, bytes: string, level: string}|null> */
    private function decodeWithZxingCpp(string $python, string $dir, array $files): array
    {
        $script = <<<'PY'
import base64, json, sys
from PIL import Image
from zxingcpp import read_barcode

result = {}
for path in sys.argv[1:]:
    barcode = read_barcode(Image.open(path))
    result[path] = None if barcode is None else {
        'text': barcode.text,
        'bytes': base64.b64encode(bytes(barcode.bytes)).decode(),
        'level': (barcode.extra or {}).get('ECLevel'),
    }
with open(sys.argv[0] + '.json', 'w') as handle:
    json.dump(result, handle)
PY;

        $scriptFile = "$dir/decode.py";
        $outFile = "$scriptFile.json";
        file_put_contents($scriptFile, $script);

        $command = escapeshellarg($python) . ' ' . escapeshellarg($scriptFile);
        foreach ($files as $file) {
            $command .= ' ' . escapeshellarg($file);
        }
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0 || !is_file($outFile)) {
            $this->markTestSkipped('zxingcpp 调用失败：' . implode("\n", $output));
        }

        return json_decode((string)file_get_contents($outFile), true) ?? [];
    }
}
