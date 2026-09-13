<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ReassignInterviewRequest;
use App\Http\Requests\Recruitment\RescheduleInterviewRequest;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Illuminate\Http\RedirectResponse;

class RecruitmentInterviewController extends Controller
{
    public function __construct(
        private readonly InterviewSchedulingService $schedulingService,
    ) {
    }

    public function reschedule(
        RescheduleInterviewRequest $request,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        $session = RecruitmentInterviewSession::query()->findOrFail(
            $request->validated('recruitment_interview_session_id')
        );

        $this->schedulingService->reschedule(
            $request->user(),
            $interview,
            $session,
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.interview-sessions.show', $session)
            ->with('message', 'Jadwal interview diperbarui.');
    }

    public function reassign(
        ReassignInterviewRequest $request,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        $interviewer = User::query()->findOrFail($request->validated('interviewer_id'));

        $interview = $this->schedulingService->reassign(
            $request->user(),
            $interview,
            $interviewer,
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.interview-sessions.show', $interview->recruitment_interview_session_id)
            ->with('message', 'Interviewer berhasil diubah.');
    }
}
