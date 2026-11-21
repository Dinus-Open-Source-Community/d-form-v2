<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Collection;

/** Typed inputs required to validate and map one guest form submission. */
final readonly class GuestFormSubmissionContext
{
    /**
     * @param array<string, mixed> $input
     * @param Collection<int, FormField> $fields
     */
    public function __construct(
        public array $input,
        public Form $form,
        public Collection $fields,
        public string $guestTokenHash,
    ) {
    }
}
