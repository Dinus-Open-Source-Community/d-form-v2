<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\MyInterviewService;
use App\Services\Recruitment\QueueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentMyInterviewController extends Controller
{
    public function __construct(
        private readonly MyInterviewService $myInterviewService,
        private readonly EvaluationService $evaluationService,
        private readonly InterviewSessionService $sessionService,
        private readonly QueueService $queueService,
    ) {
    }

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('recruitment.evaluations.view'), 403);

        $interviews = $this->myInterviewService->paginateForInterviewer(
            auth()->user(),
            (int) request()->integer('page', 1),
        );

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Index', [
            'interviews' => $interviews,
        ]);
    }

    public function show(RecruitmentApplication $application): Response
    {
        $this->authorize('viewAssignedInterview', $application);

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Show', [
            'detail' => $this->myInterviewService->toShowArray($application),
            'evaluateUrl' => route('dashboard.recruitment.my-interviews.evaluate', $application),
            'recommendationOptions' => \App\Enums\Recruitment\EvaluationRecommendation::options(),
        ]);
    }

    public function evaluate(
        StoreRecruitmentEvaluationRequest $request,
        RecruitmentApplication $application,
    ): RedirectResponse {
        $this->authorize('evaluate', $application);

        $this->evaluationService->submit(
            $request->user(),
            $application,
            $request->validated(),
            staffOverride: false,
            request: $request,
        );

        return redirect()
            ->route('dashboard.recruitment.my-interviews.show', $application)
            ->with('message', 'Penilaian interview berhasil disimpan.');
    }

    public function queue(RecruitmentInterviewSession $session): Response
    {
        abort_unless(auth()->user()?->can('recruitment.queue.view'), 403);

        $assignedSessionIds = $this->myInterviewService->sessionIdsForInterviewer(auth()->user());

        abort_unless(in_array($session->id, $assignedSessionIds, true), 403);

        $this->authorize('viewQueue', $session);

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Queue', [
            'session' => $this->sessionService->toShowArray($session),
            'queue' => $this->queueService->snapshot($session),
            'pollUrl' => route('dashboard.recruitment.queue.poll', $session),
        ]);
    }
}
