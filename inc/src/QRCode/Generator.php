<?php

namespace MyBB\QRCode;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Generator
{
    /**
     * Render a QR code as an embeddable data URI.
     *
     * @param string $data Input payload to encode.
     * @param int $errorCorrectionLevel Use EccLevel::L/M/Q/H.
     * @param string $outputType Output mode, defaults to SVG.
     */
    public function render(string $data, int $errorCorrectionLevel = EccLevel::M, string $outputType = 'svg'): string
    {
        $options = new QROptions([
            'eccLevel' => $errorCorrectionLevel,
            'outputType' => $outputType,
            'outputBase64' => true,
            'svgAddXmlHeader' => false,
        ]);

        return (new QRCode($options))->render($data);
    }
}
