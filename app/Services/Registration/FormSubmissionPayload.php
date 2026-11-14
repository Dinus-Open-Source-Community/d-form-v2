<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;

/** Validated and mapped values consumed by the HTTP submission controller. */
final readonly class FormSubmissionPayload
{
    /**
     *
     * @param  array{leader: array<string, mixed>, members: list<array<string, mixed>>}  $answers
     * @param  array<array-key, string>  $participantEmails
     * @param  array<string, string>|null  $resolvedGuestIdsByEmail
     *
     */
    public function __construct(
        public FormSubmissionMode $mode,
        public array $answers,
        public array $participantEmails,
        public ?array $resolvedGuestIdsByEmail,
    ) {
    }
}
