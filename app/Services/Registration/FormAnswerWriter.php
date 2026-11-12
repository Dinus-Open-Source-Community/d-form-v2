<?php

namespace App\Services\Registration;

use App\Models\FormAnswer;

/**
 * Single application write seam for new form answers.
 *
 * The model observer also protects factory and other Eloquent boundaries; this
 * writer makes the production registration paths explicit and reusable.
 */
final class FormAnswerWriter
{
    /**
     * Persist one form answer with the marker derived from its active predicate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FormAnswer
    {
        $answer = new FormAnswer($attributes);
        $answer->is_active = $answer->isActiveSubmission() ? 1 : null;
        $answer->save();

        return $answer;
    }
}
