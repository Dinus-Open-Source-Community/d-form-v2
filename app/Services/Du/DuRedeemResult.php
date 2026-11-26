<?php

namespace App\Services\Du;

use App\Enums\DuRedeemStatus;

final readonly class DuRedeemResult
{
    private function __construct(
        public DuRedeemStatus $status,
        public ?string $code = null,
        public ?DuRedeemData $data = null,
    ) {
    }

    public static function issued(DuRedeemData $data): self
    {
        return new self(DuRedeemStatus::Issued, $data->code, $data);
    }

    public static function rejected(): self
    {
        return new self(DuRedeemStatus::Failed);
    }

    public static function unknown(): self
    {
        return new self(DuRedeemStatus::Unknown);
    }
}
