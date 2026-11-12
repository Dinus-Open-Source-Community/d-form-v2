<?php

namespace App\Services\Form;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class FormShareBarcodeGenerator
{
    /**
     * Generate a bounded high-correction PNG for a same-origin participant URL.
     */
    public function pngForUrl(string $url): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            writerOptions: [
                PngWriter::WRITER_OPTION_COMPRESSION_LEVEL => 9,
            ],
        ))->build(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Shrink,
        );

        return $result->getString();
    }
}
