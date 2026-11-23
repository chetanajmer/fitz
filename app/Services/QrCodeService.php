<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function svg(string $data, int $size = 240): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        $svg = (new Writer($renderer))->writeString($data);

        // Strip the XML prolog so the SVG can be inlined in HTML.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
    }
}
