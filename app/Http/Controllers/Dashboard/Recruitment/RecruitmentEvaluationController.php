<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Services\Recruitment\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecruitmentEvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluationService,
    ) {
    }

    public function override(
        StoreRecruitmentEvaluationRequest $request,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        $this->authorize('override', $interview);

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

    /**
     * BC shim (rilis transisi) untuk route lama applications.evaluation.override.
     * Hapus bersama route-nya bila tidak ada lagi pemakai lama.
     */
    public function legacyOverride(
        Request $request,
        RecruitmentApplication $application,
    ): RedirectResponse {
        $primary = $application->primaryInterview()->firstOrFail();

        return redirect()->route('dashboard.recruitment.interviews.evaluation.override', $primary, 307);
    }
}
