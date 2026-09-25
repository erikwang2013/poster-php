<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Poster\PosterTemplate;
use PHPUnit\Framework\TestCase;

class TextCountingDriver extends GdDriver
{
    public int $textCalls = 0;

    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $this->textCalls++;
        return parent::text($text, $x, $y, $options);
    }
}

class PosterTest extends TestCase
{
    public function testBasicPosterSave(): void
    {
        $path = sys_get_temp_dir() . '/poster-test-basic-' . uniqid() . '.jpg';
        $builder = new PosterBuilder();
        $builder->width(300)->height(300);
        $builder->background('#FFFFFF');
        $builder->addShape('rect', ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 100, 'color' => '#FF6B6B']);
        $builder->addText('Hello Poster', ['x' => 50, 'y' => 50, 'size' => 24, 'color' => '#FFFFFF']);
        $result = $builder->save($path);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        unlink($path);
        $builder->destroy();
    }

    public function testSaveThenOutputRendersElementsOnce(): void
    {
        $driver = new TextCountingDriver();
        $builder = new PosterBuilder($driver);
        $builder->width(100)->height(100)->background('#FFFFFF');
        $builder->addText('Once', ['x' => 10, 'y' => 50, 'size' => 16, 'color' => '#000000']);
        $path = sys_get_temp_dir() . '/poster-test-save-output-' . uniqid() . '.jpg';
        $this->assertTrue($builder->save($path));
        $this->assertStringStartsWith('data:image/png;base64,', $builder->output('png'));
        $this->assertSame(1, $driver->textCalls);
        unlink($path);
        $builder->destroy();
    }

    public function testPosterOutputReturnsDataUrl(): void
    {
        $builder = new PosterBuilder();
        $builder->width(200)->height(200);
        $builder->background('#FFFFFF');
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testPosterTemplateSystem(): void
    {
        $template = new PosterTemplate(200, 200, [
            ['type' => 'text', 'text' => '{{ title }}', 'x' => 20, 'y' => 50, 'size' => 20, 'color' => '#000000'],
        ]);
        $builder = new PosterBuilder();
        $builder->width(200)->height(200)->background('#FFFFFF');
        $builder->useTemplate($template)->with(['title' => 'Template Test']);
        $path = sys_get_temp_dir() . '/poster-test-template-' . uniqid() . '.jpg';
        $result = $builder->save($path);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        unlink($path);
        $builder->destroy();
    }

    public function testChartBarElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(400)->height(300)->background('#FFFFFF');
        $builder->addChart('bar', [
            ['label' => 'A', 'value' => 30],
            ['label' => 'B', 'value' => 60],
            ['label' => 'C', 'value' => 40],
        ], ['x' => 10, 'y' => 10, 'width' => 380, 'height' => 280]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testChartLineElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(400)->height(300)->background('#FFFFFF');
        $builder->addChart('line', [
            ['label' => 'Jan', 'value' => 10],
            ['label' => 'Feb', 'value' => 25],
            ['label' => 'Mar', 'value' => 15],
        ], ['x' => 10, 'y' => 10, 'width' => 380, 'height' => 280]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testCalendarElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(500)->height(500)->background('#FFFFFF');
        $builder->addCalendar([
            'x' => 10, 'y' => 10,
            'year' => 2026, 'month' => 5,
            'highlights' => ['2026-05-16' => '今天'],
        ]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testArtisticTextElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(400)->height(150)->background('#FFFFFF');
        $builder->addArtisticText('STROKE', 'stroke', [
            'x' => 50, 'y' => 80, 'size' => 48,
            'color' => '#FF6B6B', 'strokeColor' => '#000000', 'strokeWidth' => 2,
        ]);
        $builder->addArtisticText('NEON', 'neon', [
            'x' => 250, 'y' => 80, 'size' => 36,
            'color' => '#FF6B6B',
        ]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testEmojiElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(150)->height(100)->background('#FFFFFF');
        $builder->addEmoji('😀', ['x' => 20, 'y' => 20, 'size' => 48]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testIconElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(150)->height(100)->background('#FFFFFF');
        $builder->addIcon('heart', ['x' => 20, 'y' => 40, 'size' => 32, 'color' => '#E74C3C']);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testEmoticonElement(): void
    {
        $builder = new PosterBuilder();
        $builder->width(400)->height(100)->background('#FFFFFF');
        $builder->addEmoticon('happy', ['x' => 20, 'y' => 40, 'size' => 24]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    /** README 头像示例：缺省圆形裁剪 + border 生效（角上是背景、圆环上是边框色） */
    public function testAvatarRendersCircularWithBorder(): void
    {
        $avatar = sys_get_temp_dir() . '/poster-avatar-' . uniqid() . '.png';
        $src = imagecreatetruecolor(120, 120);
        imagefilledrectangle($src, 0, 0, 119, 119, imagecolorallocate($src, 255, 0, 0));
        imagepng($src, $avatar);
        imagedestroy($src);

        try {
            $builder = new PosterBuilder();
            $builder->width(200)->height(200)->background('#FFFFFF');
            $builder->addAvatar($avatar, ['x' => 40, 'y' => 40, 'size' => 120, 'border' => '#0000FF', 'borderWidth' => 6]);
            $img = imagecreatefromstring(base64_decode(explode(',', $builder->output('png'))[1]));

            $this->assertSame(0xFFFFFF, $this->rgbAt($img, 40, 40), '圆形裁剪后左上角应是背景色');
            $this->assertSame(0xFF0000, $this->rgbAt($img, 100, 100), '圆心应是头像本体');
            $this->assertSame(0x0000FF, $this->rgbAt($img, 100, 37), '半径 60~66 的圆环应是边框色');

            imagedestroy($img);
            $builder->destroy();
        } finally {
            unlink($avatar);
        }
    }

    /** 验证 save() 按扩展名写真实格式（此前恒写 JPEG，save('x.png') 得到的是 jpeg） */
    public function testSaveHonorsExtensionFormat(): void
    {
        $png = sys_get_temp_dir() . '/poster-fmt-' . uniqid() . '.png';
        $jpg = sys_get_temp_dir() . '/poster-fmt-' . uniqid() . '.jpg';
        try {
            $builder = new PosterBuilder();
            $builder->width(60)->height(60)->background('#123456')->addText('x', ['x' => 5, 'y' => 30]);
            $this->assertTrue($builder->save($png));
            $this->assertTrue($builder->save($jpg));
            $builder->destroy();

            $this->assertSame("\x89PNG", substr((string) file_get_contents($png), 0, 4));
            $this->assertSame("\xFF\xD8\xFF", substr((string) file_get_contents($jpg), 0, 3));
        } finally {
            @unlink($png);
            @unlink($jpg);
        }
    }

    private function rgbAt(\GdImage $img, int $x, int $y): int
    {
        return imagecolorat($img, $x, $y) & 0xFFFFFF;
    }

    public function testImageElementPlacement(): void
    {
        $imgPath = sys_get_temp_dir() . '/poster-test-img-' . uniqid() . '.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $imgPath);
        imagedestroy($img);

        $builder = new PosterBuilder();
        $builder->width(200)->height(200);
        $builder->background('#FFFFFF');
        $builder->addImage($imgPath, [
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100,
        ]);
        $output = $builder->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
        unlink($imgPath);
    }
}
