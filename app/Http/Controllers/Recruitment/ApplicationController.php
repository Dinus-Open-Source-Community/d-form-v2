<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreApplicationRequest;
use App\Services\Recruitment\ApplicationSubmitter;
use App\Services\Recruitment\RecruitmentDivisionService;
use App\Services\Recruitment\RecruitmentPeriodRegistrationGate;
use App\Services\Recruitment\RecruitmentPeriodService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly RecruitmentPeriodRegistrationGate $registrationGate,
        private readonly RecruitmentPeriodService $periodService,
        private readonly RecruitmentDivisionService $divisionService,
        private readonly ApplicationSubmitter $applicationSubmitter,
    ) {
    }

    public function create(): Response
    {
        $openPeriod = $this->registrationGate->findOpenPeriod();
        $latestPeriod = \App\Models\Recruitment\RecruitmentPeriod::query()
            ->orderByDesc('created_at')
            ->first();

        $displayPeriod = $openPeriod ?? $latestPeriod;

        return Inertia::render('OpenRecruitment/Apply', [
            'period' => $displayPeriod ? $this->periodService->toInertiaArray($displayPeriod) : null,
            'registration' => [
                'is_open' => $openPeriod !== null,
                'message' => $this->registrationGate->closedMessage($displayPeriod),
            ],
            'divisions' => $this->divisionService->listActiveOrdered()
                ->map(fn ($division) => $this->divisionService->toInertiaArray($division))
                ->values()
                ->all(),
            'submitUrl' => route('open-recruitment.apply.store'),
        ]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        /** @var \App\Models\Recruitment\RecruitmentPeriod $period */
        $period = $request->attributes->get('recruitment_period');

        $result = $this->applicationSubmitter->submit(
            $period,
            $request->validatedPayload(),
            $request->file('cv'),
            $request->file('portfolio_file'),
        );

        $application = $result['application'];

        return redirect()
            ->route('open-recruitment.success')
            ->with('recruitment_success', [
                'registration_number' => $application->registration_number,
                'period_name' => $period->name,
                'applicant_name' => $application->full_name,
            ]);
    }

    public function success(): Response|RedirectResponse
    {
        $payload = session('recruitment_success');

        if (! is_array($payload) || empty($payload['registration_number'])) {
            return redirect()->route('open-recruitment.landing');
        }

        return Inertia::render('OpenRecruitment/Success', [
            'registrationNumber' => $payload['registration_number'],
            'periodName' => $payload['period_name'] ?? null,
            'applicantName' => $payload['applicant_name'] ?? null,
        ]);
    }
}
