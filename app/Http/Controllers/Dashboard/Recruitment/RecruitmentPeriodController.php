<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\MembershipType;
use App\Enums\Recruitment\ScreeningReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ShowRecruitmentPeriodApplicationsRequest;
use App\Http\Requests\Recruitment\StoreRecruitmentPeriodRequest;
use App\Http\Requests\Recruitment\UpdateRecruitmentPeriodRequest;
use App\Models\Broadcast;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentApplicationService;
use App\Services\Recruitment\RecruitmentDivisionService;
use App\Services\Recruitment\RecruitmentPeriodService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\RecruitmentReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentPeriodController extends Controller
{
    public function __construct(
        private readonly RecruitmentPeriodService $periodService,
        private readonly RecruitmentApplicationService $applicationService,
        private readonly InterviewSessionService $sessionService,
        private readonly RecruitmentReportService $reportService,
        private readonly RecruitmentDivisionService $divisionService,
    ) {
    }

    public function create(): Response
    {
        $this->authorize('create', RecruitmentPeriod::class);

        return Inertia::render('Dashboard/Recruitment/Periods/Create');
    }

    public function store(StoreRecruitmentPeriodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $period = $this->periodService->create($data, $request->file('banner'));

        Inertia::flash('toast', [
            'message' => 'Periode recruitment berhasil dibuat.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.periods.show', $period);
    }

    /** Daftar broadcast satu periode, paginasi 15/hal selaras kontrak index 4a (tanpa N+1). */
    private function broadcastsForPeriodTab(RecruitmentPeriod $period, int $page): LengthAwarePaginator
    {
        $paginator = Broadcast::query()
            ->where('period_id', $period->id)
            ->orderByDesc('created_at')
            ->paginate(15, ['id', 'name', 'subject', 'status', 'scheduled_at', 'recipient_count', 'created_at'], 'page', $page);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Broadcast $broadcast): array => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'subject' => $broadcast->subject,
                'status' => $broadcast->status,
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'recipient_count' => $broadcast->recipient_count,
                'created_at' => $broadcast->created_at?->toIso8601String(),
            ])
        );

        return $paginator->withQueryString();
    }

    public function show(ShowRecruitmentPeriodApplicationsRequest $request, RecruitmentPeriod $period): Response
    {
        $this->authorize('view', $period);

        $period->loadCount('applications');

        $validated = $request->validated();
        $tab = $validated['tab'] ?? 'peserta';
        if (! in_array($tab, ['peserta', 'interview', 'laporan', 'interviewer', 'broadcast', 'settings'], true)) {
            $tab = 'peserta';
        }

        $applications = null;
        $queueCounts = [];
        $sessions = null;
        $report = null;
        $applicantDetail = null;
        $divisions = null;
        $assignments = null;
        $interviewerCandidates = null;
        $screeningReasonOptions = [];
        $broadcasts = null;
        $divisionOptions = [];
        $semesterOptions = [];

        if ($tab === 'peserta') {
            $canListApplications = $request->user()?->can('recruitment.applications.list') ?? false;

            if ($canListApplications) {
                $applicationPaginator = RecruitmentApplication::query()
                    ->with(['primaryDivision:id,name,code', 'secondaryDivision:id,name,code', 'period:id,name'])
                    ->where('recruitment_period_id', $period->id)
                    ->orderByDesc('submitted_at')
                    ->paginate(15, ['*'], 'page', $request->integer('page', 1));
                $applicationPaginator->setCollection(
                    $applicationPaginator->getCollection()->map(
                        fn (RecruitmentApplication $application) => $this->applicationService->toListArray($application)
                    )
                );

                $applications = $applicationPaginator->withQueryString();

                $queueCounts = $this->applicationService->queueCounts($period->id);
                $screeningReasonOptions = ScreeningReason::options();
                $divisionOptions = $this->applicationService->divisionOptions();
                $semesterOptions = $this->applicationService->semesterOptions($period->id);

                $applicationId = $validated['application'] ?? null;

                if (is_string($applicationId) && $applicationId !== '' && Str::isUuid($applicationId)) {
                    $selected = RecruitmentApplication::query()->find($applicationId);

                    abort_if($selected === null, 404);
                    abort_unless($selected->recruitment_period_id === $period->id, 404);

                    $this->authorize('view', $selected);

                    $applicantDetail = $this->applicationService->toShowArray($selected);
                }
            }
        }

        if ($tab === 'interview') {
            abort_unless($request->user()?->can('recruitment.interviews.schedule'), 403);

            $interviewDivisionOptions = $this->divisionService->listAllOrdered()
                ->where('is_active', true)
                ->map(fn (RecruitmentDivision $division): array => [
                    'id' => $division->id,
                    'name' => $division->name,
                    'code' => $division->code,
                ])
                ->values()
                ->all();

            $sessionPaginator = $this->sessionService->paginate(
                ['period_id' => $period->id],
                $request->integer('page', 1),
            );
            $sessionPaginator->setCollection(
                $sessionPaginator->getCollection()->map(
                    fn (RecruitmentInterviewSession $session) => $this->sessionService->toListArray($session)
                )
            );

            $sessions = $sessionPaginator;
            $queueCounts = $this->applicationService->queueCounts($period->id);
        }

        if ($tab === 'laporan') {
            abort_unless($request->user()?->can('recruitment.reports.view'), 403);

            $report = $this->reportService->build($period->id);
        }

        if ($tab === 'broadcast') {
            $broadcasts = $this->broadcastsForPeriodTab($period, $request->integer('page', 1));
        }

        if ($tab === 'interviewer') {
            $this->authorize('assignInterviewer', RecruitmentDivision::class);

            $divisions = $this->divisionService->listAllOrdered()
                ->map(fn (RecruitmentDivision $d) => $this->divisionService->toInertiaArray($d));

            $assignments = RecruitmentInterviewerDivision::query()
                ->with(['user:id,name,email', 'division:id,name,code'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (RecruitmentInterviewerDivision $a) => [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'user_name' => $a->user?->name,
                    'user_email' => $a->user?->email,
                    'division_id' => $a->recruitment_division_id,
                    'division_name' => $a->division?->name,
                    'division_code' => $a->division?->code,
                ])
                ->values()
                ->all();

            $interviewerCandidates = User::query()
                ->role(['recruitment-interviewer', 'recruitment-staff', 'admin'])
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values()
                ->all();
        }

        $props = [
            'period' => $this->periodService->toInertiaArray($period, $request->user()),
            'tab' => $tab,
            'queue_counts' => (object) $queueCounts,
            'divisionOptions' => $divisionOptions,
            'semesterOptions' => $semesterOptions,
            'stageOptions' => collect(\App\Enums\Recruitment\ApplicationStage::cases())
                ->map(fn ($stage) => ['value' => $stage->value, 'label' => $stage->label()])
                ->values()
                ->all(),
            'query' => $validated,
        ];

        if ($tab === 'peserta') {
            $props['applications'] = $applications;
            $props['screening_reason_options'] = $screeningReasonOptions;
            $props['division_options'] = $divisionOptions;
            $props['membership_type_options'] = MembershipType::options();
        }

        if ($tab === 'interview') {
            $props['sessions'] = $sessions;
            $props['interview_division_options'] = $interviewDivisionOptions;
        }

        if ($tab === 'laporan') {
            $props['report'] = $report;
        }

        if ($tab === 'broadcast') {
            $props['broadcasts'] = $broadcasts;
        }

        if ($tab === 'interviewer') {
            $props['divisions'] = $divisions;
            $props['assignments'] = $assignments;
            $props['interviewerCandidates'] = $interviewerCandidates;
        }

        if ($applicantDetail !== null) {
            $props['applicant_detail'] = $applicantDetail;
        }

        return Inertia::render('Dashboard/Recruitment/Periods/Show', $props);
    }

    public function application(RecruitmentPeriod $period, RecruitmentApplication $application): JsonResponse
    {
        $this->authorize('view', $period);

        abort_unless($application->recruitment_period_id === $period->id, 404);

        $this->authorize('view', $application);

        $detail = $this->applicationService->toShowArray($application);

        return response()->json([
            'application' => $detail,
            'screening_reason_options' => ScreeningReason::options(),
            'division_options' => $this->applicationService->divisionOptions(),
            'membership_type_options' => MembershipType::options(),
            'can_screen' => $detail['can_screen'] ?? false,
        ]);
    }

    public function update(UpdateRecruitmentPeriodRequest $request, RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('update', $period);

        $this->periodService->update($period, $request->validated(), $request->file('banner'));

        return redirect()
            ->route('dashboard.recruitment.periods.show', $period)
            ->with('message', 'Periode recruitment berhasil diperbarui.');
    }

    public function destroy(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('delete', $period);

        $period->delete();

        Inertia::flash('toast', [
            'message' => 'Periode recruitment berhasil dihapus.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.recruitment.index');
    }

    public function open(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('open', $period);

        $this->periodService->open($period);

        return redirect()
            ->back()
            ->with('message', 'Periode recruitment dibuka untuk pendaftaran.');
    }

    public function close(RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('close', $period);

        $this->periodService->close($period);

        return redirect()
            ->back()
            ->with('message', 'Periode recruitment ditutup.');
    }
}
