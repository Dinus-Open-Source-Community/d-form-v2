<?php

namespace App\Services\Registration;

use App\Support\RegistrationQrPayload;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;

final class RegistrationQrPngGenerator
{
    /**
     * PNG generation uses GD via endroid/qr-code (no Imagick required).
     *
     * @throws \JsonException
     */
    public function pngForSubmission(string $formAnswerId): string
    {
        $payload = RegistrationQrPayload::encode($formAnswerId);

        $result = (new Builder)->build(
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 8,
        );

        return $result->getString();
    }
}
