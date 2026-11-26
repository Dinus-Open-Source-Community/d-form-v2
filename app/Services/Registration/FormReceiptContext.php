<?php

namespace App\Services\Registration;

final readonly class FormReceiptContext
{
    /**
     * @param array<string, string> $answersSummary
     * @param array<int, string> $sessionDates
     */
    public function __construct(
        public array $answersSummary,
        public ?string $qrPngBinary,
        public array $sessionDates,
    ) {
    }
}
