<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRecruitmentEvaluationRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\MyInterviewService;
use App\Services\Recruitment\WaitingRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentMyInterviewController extends Controller
{
    public function __construct(
        private readonly MyInterviewService $myInterviewService,
        private readonly EvaluationService $evaluationService,
        private readonly WaitingRoomService $waitingRoomService,
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
            'waiting_pool_poll_url' => route('dashboard.recruitment.my-interviews.waiting-pool'),
            'has_active_booking' => $this->waitingRoomService->hasActiveInProgressBooking($user),
        ]);
    }

    public function waitingPool(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('recruitment.evaluations.view'), 403);

        $sessionId = $request->query('session_id');
        $search = $request->query('q');

        return response()->json([
            'entries' => $this->waitingRoomService->waitingPoolSnapshot(
                $user,
                is_string($sessionId) ? $sessionId : null,
                is_string($search) ? $search : null,
            ),
            'has_active_booking' => $this->waitingRoomService->hasActiveInProgressBooking($user),
        ]);
    }

    public function book(Request $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->authorize('bookInterview', $application);

        $this->waitingRoomService->book($request->user(), $application, $request);

        return redirect()
            ->route('dashboard.recruitment.my-interviews.show', $application)
            ->with('message', 'Applicant berhasil dibooking.');
    }

    public function release(Request $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->authorize('releaseInterview', $application);

        $this->waitingRoomService->release($request->user(), $application, $request);

        return redirect()
            ->route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting'])
            ->with('message', 'Booking dibatalkan. Applicant kembali ke ruang tunggu.');
    }

    public function show(RecruitmentApplication $application): Response
    {
        $this->authorize('viewAssignedInterview', $application);

        return Inertia::render('Dashboard/Recruitment/MyInterviews/Show', [
            'detail' => $this->myInterviewService->toShowArray($application),
            'evaluateUrl' => route('dashboard.recruitment.my-interviews.evaluate', $application),
            'releaseUrl' => route('dashboard.recruitment.my-interviews.release', $application),
            'recommendationOptions' => \App\Enums\Recruitment\EvaluationRecommendation::options(),
            'flashMessage' => session('message'),
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
}
