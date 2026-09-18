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
        $user = auth()->user();
        abort_unless($user?->can('recruitment.evaluations.view'), 403);

        $page = (int) request()->integer('page', 1);
        $queue = request()->query('queue');
        $q = request()->query('q');
        $divisionId = request()->query('division_id');
        $sessionId = request()->query('session_id');
        $dateFrom = request()->query('date_from');
        $dateTo = request()->query('date_to');
        $eval = request()->query('eval');
        $sort = request()->query('sort');

        $filters = [];
        if (is_string($queue) && $queue !== '') {
            $filters['queue'] = $queue;
        }
        if (is_string($q) && trim($q) !== '') {
            $filters['q'] = trim($q);
        }
        if (is_string($divisionId) && $divisionId !== '') {
            $filters['division_id'] = $divisionId;
        }
        if (is_string($sessionId) && $sessionId !== '') {
            $filters['session_id'] = $sessionId;
        }
        if (is_string($dateFrom) && trim($dateFrom) !== '') {
            $filters['date_from'] = trim($dateFrom);
        }
        if (is_string($dateTo) && trim($dateTo) !== '') {
            $filters['date_to'] = trim($dateTo);
        }
        if (is_string($eval) && $eval !== '') {
            $filters['eval'] = $eval;
        }
        if (is_string($sort) && $sort !== '') {
            $filters['sort'] = $sort;
        }

        $interviews = $this->myInterviewService->paginateForInterviewer(
            $user,
            $filters,
            $page,
        );

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Index', [
            'interviews' => $interviews,
            'query' => [
                'q' => is_string($q) ? $q : '',
                'division_id' => is_string($divisionId) ? $divisionId : '',
                'session_id' => is_string($sessionId) ? $sessionId : '',
                'date_from' => is_string($dateFrom) ? $dateFrom : '',
                'date_to' => is_string($dateTo) ? $dateTo : '',
                'eval' => is_string($eval) ? $eval : '',
                'sort' => is_string($sort) ? $sort : '',
                'queue' => is_string($queue) ? $queue : '',
                'page' => $page,
            ],
            'queue_counts' => $this->myInterviewService->queueCounts($user),
            'today_sessions' => $this->myInterviewService->todaySessionsForInterviewer($user),
            'next_action' => $this->myInterviewService->nextActionForInterviewer($user),
            'division_options' => $this->myInterviewService->divisionsForInterviewer($user),
            'session_options' => $this->myInterviewService->sessionsForInterviewer($user),
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
