<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormSession;
use Carbon\Carbon;

class FormSessionSchedule
{
    public function todayFor(Form $form): ?FormSession
    {
        return $form->formSessions()
            ->whereDate('session_date', now()->toDateString())
            ->first();
    }

    /** @return array<int, string> */
    public function datesFor(Form $form): array
    {
        return $form->formSessions()
            ->orderBy('session_date')
            ->pluck('session_date')
            ->map(static fn (string $date): string => Carbon::parse($date)->toDateString())
            ->all();
    }

    public function totalFor(Form $form): int
    {
        return $form->formSessions()->count();
    }
}
