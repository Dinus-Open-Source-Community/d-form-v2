<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\EvaluationService;
use Illuminate\Http\RedirectResponse;

class RecruitmentEvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluationService,
    ) {
    }

    public function override(
        StoreRecruitmentEvaluationRequest $request,
        RecruitmentApplication $application,
    ): RedirectResponse {
        $this->authorize('overrideEvaluation', $application);

        $this->evaluationService->submit(
            $request->user(),
            $application,
            $request->validated(),
            staffOverride: true,
            request: $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Penilaian interview diperbarui (staff override).');
    }
}
