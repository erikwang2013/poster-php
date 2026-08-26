<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests;

use Erikwang2013\Poster\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Installer::copyConfig 测试：在临时工作目录下发布配置文件。
 */
class InstallerTest extends TestCase
{
    private string $tempRoot;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->tempRoot = sys_get_temp_dir() . '/poster-installer-' . uniqid();
        mkdir($this->tempRoot, 0755, true);
        chdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        foreach (['/config/autoload/poster.php', '/config/poster.php', '/config/autoload', '/config'] as $p) {
            $full = $this->tempRoot . $p;
            if (is_file($full)) {
                @unlink($full);
            } elseif (is_dir($full)) {
                @rmdir($full);
            }
        }
        @rmdir($this->tempRoot);
    }

    /** 测试 copyConfig() 将配置发布到标准位置与 Hyperf 位置，并替换 dirname(__DIR__) */
    public function testCopyConfigPublishesBothLocations(): void
    {
        Installer::copyConfig();

        $standard = $this->tempRoot . '/config/poster.php';
        $hyperf = $this->tempRoot . '/config/autoload/poster.php';
        $this->assertFileExists($standard);
        $this->assertFileExists($hyperf);

        $content = file_get_contents($standard);
        $this->assertStringContainsString(var_export(dirname(__DIR__), true), $content);
        $this->assertStringNotContainsString('dirname(__DIR__)', $content);
    }

    /** 测试目标文件已存在时不会被覆盖 */
    public function testCopyConfigDoesNotOverwriteExisting(): void
    {
        Installer::copyConfig();
        $standard = $this->tempRoot . '/config/poster.php';
        file_put_contents($standard, '<?php return ["custom" => true];');

        Installer::copyConfig();

        $this->assertSame('<?php return ["custom" => true];', file_get_contents($standard));
    }
}
