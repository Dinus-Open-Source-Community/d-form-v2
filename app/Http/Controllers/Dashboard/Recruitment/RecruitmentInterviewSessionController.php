<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\IndexRecruitmentInterviewSessionRequest;
use App\Http\Requests\Recruitment\ScheduleInterviewApplicantsRequest;
use App\Http\Requests\Recruitment\StoreRecruitmentInterviewSessionRequest;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\InterviewSchedulingService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\RecruitmentApplicationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentInterviewSessionController extends Controller
{
    public function __construct(
        private readonly InterviewSessionService $sessionService,
        private readonly InterviewSchedulingService $schedulingService,
        private readonly RecruitmentApplicationService $applicationService,
    ) {
    }

    public function index(IndexRecruitmentInterviewSessionRequest $request): Response
    {
        $validated = $request->validated();
        $page = $request->integer('page', 1);

        $paginator = $this->sessionService->paginate($validated, $page);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (RecruitmentInterviewSession $session) => $this->sessionService->toListArray($session)
            )
        );

        $periodId = isset($validated['period_id']) ? (string) $validated['period_id'] : null;

        return Inertia::render('Dashboard/Recruitment/InterviewSessions/Index', [
            'sessions' => $paginator,
            'query' => $validated,
            'today_sessions' => $this->sessionService->todaySessions($periodId),
            'periodOptions' => $this->sessionService->periodOptions(),
            'divisionOptions' => $this->applicationService->divisionOptions(),
        ]);
    }

    public function store(StoreRecruitmentInterviewSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $session = $this->sessionService->create($validated);

        return redirect()
            ->route('dashboard.recruitment.interview-sessions.show', $session)
            ->with('message', 'Sesi interview berhasil dibuat.');
    }

    public function show(RecruitmentInterviewSession $session): Response
    {
        $this->authorize('view', $session);

        $eligible = $this->schedulingService->eligibleApplicants($session)
            ->map(fn ($application): array => [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard/Recruitment/InterviewSessions/Show', [
            'session' => $this->sessionService->toShowArray($session),
            'eligibleApplicants' => $eligible,
            'interviewerOptions' => $this->schedulingService->interviewerOptionsForDivision(
                $session->recruitment_division_id,
            ),
            'otherSessions' => RecruitmentInterviewSession::query()
                ->where('recruitment_period_id', $session->recruitment_period_id)
                ->where('id', '!=', $session->id)
                ->where('is_active', true)
                ->orderBy('session_date')
                ->get()
                ->map(fn (RecruitmentInterviewSession $item): array => $this->sessionService->toListArray($item))
                ->values()
                ->all(),
        ]);
    }

    public function schedule(
        ScheduleInterviewApplicantsRequest $request,
        RecruitmentInterviewSession $session,
    ): RedirectResponse {
        $this->authorize('view', $session);

        $this->schedulingService->scheduleApplicants(
            $request->user(),
            $session,
            $request->validated('application_ids'),
            $request,
        );

        return redirect()
            ->route('dashboard.recruitment.interview-sessions.show', $session)
            ->with('message', 'Applicant berhasil dijadwalkan.');
    }
}
