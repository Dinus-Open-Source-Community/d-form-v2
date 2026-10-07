<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

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

        $interview = $application->primaryInterview()->first();

        if ($interview === null) {
            throw ValidationException::withMessages([
                'interview' => ['Applicant has no scheduled interview.'],
            ]);
        }

        $this->evaluationService->submit(
            $request->user(),
            $interview,
            $request->validated(),
            staffOverride: true,
            request: $request,
        );

        return redirect()
            ->back()
            ->with('message', 'Penilaian interview diperbarui (staff override).');
    }
}
