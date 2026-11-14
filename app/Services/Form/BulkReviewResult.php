<?php

namespace App\Services\Form;

use App\Models\FormAnswer;
use Illuminate\Support\Collection;

final class BulkReviewResult
{
    /**
     *
     * @param  Collection<int, FormAnswer>  $answers
     * @param  Collection<int, string>  $outboxIds
     *
     */
    public function __construct(
        public Collection $answers,
        public Collection $outboxIds,
    ) {
    }

    public function processedCount(): int
    {
        return $this->answers->count();
    }

    public function queuedIntentCount(): int
    {
        return $this->outboxIds->count();
    }
}
