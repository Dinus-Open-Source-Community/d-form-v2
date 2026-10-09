<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentInterviewSessionRequest;
use App\Http\Requests\Recruitment\UpdateRecruitmentInterviewSessionRequest;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\InterviewSessionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentInterviewSessionController extends Controller
{
    public function __construct(
        private readonly InterviewSessionService $sessionService,
    ) {
    }

    public function store(StoreRecruitmentInterviewSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $session = $this->sessionService->create($validated);

        Inertia::flash('toast', [
            'message' => 'Sesi interview berhasil dibuat.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.periods.show', [
            'period' => $session->recruitment_period_id,
            'tab' => 'interview',
        ]);
    }

    public function show(RecruitmentInterviewSession $session): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Dashboard/Recruitment/InterviewSessions/Show', [
            'session' => $this->sessionService->toShowArray($session),
        ]);
    }

    public function update(
        UpdateRecruitmentInterviewSessionRequest $request,
        RecruitmentInterviewSession $session,
    ): RedirectResponse {
        $this->authorize('update', $session);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $session = $this->sessionService->update($session, $validated);

        Inertia::flash('toast', [
            'message' => 'Sesi interview berhasil diperbarui.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.periods.show', [
            'period' => $session->recruitment_period_id,
            'tab' => 'interview',
        ]);
    }

    public function destroy(RecruitmentInterviewSession $session): RedirectResponse
    {
        $this->authorize('delete', $session);

        if (
            $session->interviews()->exists()
            || $session->attendances()->exists()
        ) {
            Inertia::flash('toast', [
                'message' => 'Sesi dengan jadwal atau presensi tidak bisa dihapus.',
                'type' => 'error',
            ]);

            return redirect()->back();
        }

        $periodId = $session->recruitment_period_id;

        $session->delete();

        Inertia::flash('toast', [
            'message' => 'Sesi interview berhasil dihapus.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.periods.show', [
            'period' => $periodId,
            'tab' => 'interview',
        ]);
    }
}
