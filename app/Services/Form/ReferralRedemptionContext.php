<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\ReferralCode;

/** Data yang dibekukan saat satu unit submission menukar kode referral. */
final readonly class ReferralRedemptionContext
{
    /** @param list<array{name: string|null, email: string|null}> $members */
    public function __construct(
        public Form $form,
        public ReferralCode $code,
        public FormAnswer $answer,
        public ?string $groupToken,
        public ?string $participantName,
        public ?string $participantEmail,
        public array $members = [],
    ) {
    }
}
