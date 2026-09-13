<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\ScreeningReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ScreeningPassRequest;
use App\Http\Requests\Recruitment\ScreeningRejectRequest;
use App\Http\Requests\Recruitment\ScreeningRevisionRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\ScreeningService;
use Illuminate\Http\RedirectResponse;

class RecruitmentScreeningController extends Controller
{
    public function __construct(
        private readonly ScreeningService $screeningService,
    ) {
    }

    public function pass(ScreeningPassRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->screeningService->pass(
            $request->user(),
            $application,
            $request->validated('notes'),
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Applicant lolos screening.');
    }

    public function revision(ScreeningRevisionRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $validated = $request->validated();

        $this->screeningService->requireRevision(
            $request->user(),
            $application,
            ScreeningReason::from($validated['reason']),
            $validated['notes'] ?? null,
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Permintaan revisi telah dikirim.');
    }

    public function reject(ScreeningRejectRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $validated = $request->validated();

        $this->screeningService->reject(
            $request->user(),
            $application,
            ScreeningReason::from($validated['reason']),
            $validated['notes'] ?? null,
            $validated['public_message'] ?? null,
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Applicant ditolak pada tahap screening.');
    }
}
