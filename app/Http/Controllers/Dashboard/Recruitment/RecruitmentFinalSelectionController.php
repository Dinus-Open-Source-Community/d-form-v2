<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\MembershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\FinalAcceptRequest;
use App\Http\Requests\Recruitment\FinalRejectRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\FinalSelectionService;
use Illuminate\Http\RedirectResponse;

class RecruitmentFinalSelectionController extends Controller
{
    public function __construct(
        private readonly FinalSelectionService $finalSelectionService,
    ) {
    }

    public function accept(FinalAcceptRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $validated = $request->validated();

        $this->finalSelectionService->accept(
            $request->user(),
            $application,
            MembershipType::from($validated['membership_type']),
            $validated['final_division_id'],
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Applicant diterima. Email hasil telah dikirim.');
    }

    public function reject(FinalRejectRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $validated = $request->validated();

        $this->finalSelectionService->reject(
            $request->user(),
            $application,
            $validated['internal_reason'],
            $validated['public_message'],
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.applications.show', $application)
            ->with('message', 'Applicant ditolak. Email hasil telah dikirim.');
    }
}
