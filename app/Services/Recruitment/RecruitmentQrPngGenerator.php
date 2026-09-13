<?php

namespace App\Services\Recruitment;

use App\Support\RecruitmentQrPayload;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class RecruitmentQrPngGenerator
{
    /**
     * @throws \JsonException
     */
    public function pngForApplication(string $applicationId): string
    {
        $payload = RecruitmentQrPayload::encode($applicationId);

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
