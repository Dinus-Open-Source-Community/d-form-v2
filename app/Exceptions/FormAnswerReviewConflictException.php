<?php

namespace App\Exceptions;

use App\Enums\FormAnswerReviewStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Carries the current individual-review status required by the 409 response contract. */
final class FormAnswerReviewConflictException extends ConflictHttpException
{
    public readonly string $currentStatus;

    public function __construct(FormAnswerReviewStatus $currentStatus)
    {
        $this->currentStatus = $currentStatus->value;
        parent::__construct('Submission has already been reviewed.');
    }
}
