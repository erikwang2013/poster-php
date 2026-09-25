<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Qrcode\QrcodeGenerator;

class QrcodeElement extends AbstractElement
{
    protected array $resolveKeys = ['content', 'label'];

    public function render(ImageDriverInterface $canvas): void
    {
        $content = $this->options['content'] ?? '';
        if (empty($content)) return;

        $size  = $this->positive(intval($this->options['size'] ?? 200), 'size');
        $level = $this->options['level'] ?? 'H';
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);

        $generator = new QrcodeGenerator();
        $generator->setText($content)->setSize($size)->setErrorLevel($level);
        $qrGd = $generator->render();

        $qrDriver = new GdDriver();
        $qrDriver->setGdResource($qrGd);
        // 实际渲染尺寸被模块数量化，通常小于请求的 size，label 定位要用它
        $qrSize = $qrDriver->getSize();

        if (!empty($this->options['logo']) && is_file($this->options['logo'])) {
            $logo = DriverFactory::create()->load($this->options['logo']);
            $logoSize = intval($size * 0.22);
            $logo->resize($logoSize, $logoSize);
            $logoX = intval(($size - $logoSize) / 2);
            $logoY = intval(($size - $logoSize) / 2);
            $qrDriver->image($logo, $logoX, $logoY);
            $logo->destroy();
        }

        $canvas->image($qrDriver, $x, $y, $this->options);
        $qrDriver->destroy();

        if (!empty($this->options['label'])) {
            // 居中：x 取码左边缘 + 半个码宽（align=center 让文本以此为中心）
            $canvas->text($this->options['label'], $x + intdiv($size, 2), $y + $qrSize['height'] + 20, [
                'size'  => intval($this->options['label_size'] ?? 14),
                'color' => $this->options['label_color'] ?? '#999999',
                'font'  => $this->font(),
                'align' => 'center',
            ]);
        }
    }

}
