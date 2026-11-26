<?php

namespace App\Services\Du;

use App\Enums\DuRedeemStatus;

final readonly class DuRedeemIssuanceResult
{
    public function __construct(
        public DuRedeemStatus $status,
        public ?string $code = null,
        public ?string $message = null,
        public bool $createdNewCode = false,
    ) {
    }
}
