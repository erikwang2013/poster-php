<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Poster\PosterTemplate;
use PHPUnit\Framework\TestCase;

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
