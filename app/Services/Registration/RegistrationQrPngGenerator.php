<?php

namespace App\Services\Registration;

use App\Support\RegistrationQrPayload;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class RegistrationQrPngGenerator
{
    /**
     *
     * PNG generation uses GD via endroid/qr-code (no Imagick required).
     *
     * @throws \JsonException
     *
     */
    public function pngForSubmission(string $formAnswerId): string
    {
        $payload = RegistrationQrPayload::encode($formAnswerId);

        $result = (new Builder(
            writer: new PngWriter(),
            writerOptions: [
                PngWriter::WRITER_OPTION_COMPRESSION_LEVEL => 9,
            ],
        ))->build(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Shrink,
        );

        return $result->getString();
    }
}
