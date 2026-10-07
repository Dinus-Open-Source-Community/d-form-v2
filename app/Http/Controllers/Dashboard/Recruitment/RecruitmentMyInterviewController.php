<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ClaimSecondaryInterviewRequest;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\InterviewLifecycleService;
use App\Services\Recruitment\MyInterviewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentMyInterviewController extends Controller
{
    public function __construct(
        private readonly MyInterviewService $myInterviewService,
        private readonly EvaluationService $evaluationService,
        private readonly InterviewLifecycleService $interviewLifecycleService,
    ) {
    }

    public function index(): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('recruitment.evaluations.view'), 403);

        $page = (int) request()->integer('page', 1);
        $tab = request()->query('tab');
        $q = request()->query('q');
        $divisionId = request()->query('division_id');
        $sessionId = request()->query('session_id');
        $dateFrom = request()->query('date_from');
        $dateTo = request()->query('date_to');
        $eval = request()->query('eval');
        $sort = request()->query('sort');

        $filters = [];
        if (is_string($tab) && $tab !== '') {
            $filters['tab'] = $tab;
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
                'tab' => is_string($tab) ? $tab : 'in_progress',
                'q' => is_string($q) ? $q : '',
                'division_id' => is_string($divisionId) ? $divisionId : '',
                'session_id' => is_string($sessionId) ? $sessionId : '',
                'date_from' => is_string($dateFrom) ? $dateFrom : '',
                'date_to' => is_string($dateTo) ? $dateTo : '',
                'eval' => is_string($eval) ? $eval : '',
                'sort' => is_string($sort) ? $sort : '',
                'page' => $page,
            ],
            'tab_counts' => $this->myInterviewService->tabCounts($user),
            'pending_start_count' => $this->myInterviewService->countPendingStart($user),
            'today_sessions' => $this->myInterviewService->todaySessionsForInterviewer($user),
            'next_action' => $this->myInterviewService->nextActionForInterviewer($user),
            'division_options' => $this->myInterviewService->divisionsForInterviewer($user),
            'session_options' => $this->myInterviewService->sessionsForInterviewer($user),
            'secondary_opportunities' => $this->myInterviewService->secondaryOpportunitiesForInterviewer($user),
            'claimed_secondary' => $this->myInterviewService->claimedSecondaryForInterviewer($user),
        ]);
    }

    public function show(string $id): Response|RedirectResponse
    {
        $interview = RecruitmentInterview::query()->find($id);

        // BC shim (rilis transisi): URL lama per-application diarahkan ke
        // interview primary. Hapus bila tidak ada lagi link/bookmark lama.
        if ($interview === null) {
            $application = RecruitmentApplication::query()->find($id);
            $primary = $application?->primaryInterview()->first();

            if ($primary !== null) {
                return redirect()->route('dashboard.recruitment.my-interviews.show', $primary);
            }

            abort(404);
        }

        $this->authorize('view', $interview);

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Show', [
            'detail' => $this->myInterviewService->toShowArray($interview),
            'evaluateUrl' => route('dashboard.recruitment.my-interviews.evaluate', $interview),
            'recommendationOptions' => \App\Enums\Recruitment\EvaluationRecommendation::options(),
        ]);
    }

    public function evaluate(
        StoreRecruitmentEvaluationRequest $request,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        $this->authorize('evaluate', $interview);

        $this->evaluationService->submit(
            $request->user(),
            $interview,
            $request->validated(),
            staffOverride: false,
            request: $request,
        );

        return redirect()
            ->route('dashboard.recruitment.my-interviews.index', ['tab' => 'done'])
            ->with('toast', ['message' => 'Penilaian interview berhasil disimpan.', 'type' => 'success']);
    }

    public function claimSecondary(ClaimSecondaryInterviewRequest $request): RedirectResponse
    {
        $application = RecruitmentApplication::query()->findOrFail($request->validated('application_id'));

        $this->authorize('claimSecondaryInterview', $application);

        $session = RecruitmentInterviewSession::query()->findOrFail($request->validated('session_id'));

        $secondaryInterview = $this->interviewLifecycleService->createSecondaryInterview(
            $request->user(),
            $application,
            $session,
        );

        return redirect()
            ->route('dashboard.recruitment.my-interviews.show', $secondaryInterview)
            ->with('toast', ['message' => 'Interview secondary berhasil diambil.', 'type' => 'success']);
    }
}
