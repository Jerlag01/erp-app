<?php

namespace App\Support;

use App\Models\StockItem;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes for stock items and IT assets. The code holds the item's short scan link.
 */
class StockQr
{
    public static function svg(StockItem $item): string
    {
        return static::svgFor($item->qrUrl());
    }

    /**
     * An SVG QR code for any link (also used for IT asset labels).
     */
    public static function svgFor(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'connectPaths' => true,
            'drawLightModules' => false,
            'eccLevel' => QRCode::ECC_M,
        ]);

        return (new QRCode($options))->render($url);
    }
}
