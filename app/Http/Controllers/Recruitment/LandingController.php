<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\RecruitmentDivisionService;
use App\Services\Recruitment\RecruitmentPeriodRegistrationGate;
use App\Services\Recruitment\RecruitmentPeriodService;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(
        private readonly RecruitmentPeriodRegistrationGate $registrationGate,
        private readonly RecruitmentPeriodService $periodService,
        private readonly RecruitmentDivisionService $divisionService,
    ) {
    }

    public function __invoke(): Response
    {
        $openPeriod = $this->registrationGate->findOpenPeriod();
        $latestPeriod = \App\Models\Recruitment\RecruitmentPeriod::query()
            ->orderByDesc('created_at')
            ->first();

        $displayPeriod = $openPeriod ?? $latestPeriod;

        return Inertia::render('OpenRecruitment/Landing', [
            'period' => $displayPeriod ? $this->periodService->toInertiaArray($displayPeriod) : null,
            'registration' => [
                'is_open' => $openPeriod !== null,
                'message' => $this->registrationGate->closedMessage($displayPeriod),
            ],
            'divisions' => $this->divisionService->listActiveOrdered()
                ->map(fn ($division) => $this->divisionService->toInertiaArray($division))
                ->values()
                ->all(),
        ]);
    }
}
