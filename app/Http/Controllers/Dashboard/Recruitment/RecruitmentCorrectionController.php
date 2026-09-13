<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ReviewCorrectionRequest;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Services\Recruitment\CorrectionRequestService;
use Illuminate\Http\RedirectResponse;

class RecruitmentCorrectionController extends Controller
{
    public function __construct(
        private readonly CorrectionRequestService $correctionRequestService,
    ) {
    }

    public function approve(ReviewCorrectionRequest $request, RecruitmentCorrectionRequest $correction): RedirectResponse
    {
        $this->correctionRequestService->approve(
            $request->user(),
            $correction,
            $request->validated('review_notes'),
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $correction->recruitment_application_id)
            ->with('message', 'Permintaan koreksi disetujui.');
    }

    public function reject(ReviewCorrectionRequest $request, RecruitmentCorrectionRequest $correction): RedirectResponse
    {
        $this->correctionRequestService->reject(
            $request->user(),
            $correction,
            $request->validated('review_notes'),
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $correction->recruitment_application_id)
            ->with('message', 'Permintaan koreksi ditolak.');
    }
}
