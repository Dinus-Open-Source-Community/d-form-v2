<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Support\Collection;

/** Typed inputs required to validate and map one form submission. */
final readonly class FormSubmissionContext
{
    /**
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, FormField>  $fields
     *
     */
    public function __construct(
        public array $input,
        public Form $form,
        public User $user,
        public Collection $fields,
        public FormSubmissionMode $mode,
        public int $teamSize,
    ) {
    }
}
