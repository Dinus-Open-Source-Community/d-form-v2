<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\MembershipType;
use App\Enums\Recruitment\ScreeningDecision;
use App\Enums\Recruitment\ScreeningReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\SendPeriodQrRequest;
use App\Http\Requests\Recruitment\ShowRecruitmentPeriodApplicationsRequest;
use App\Http\Requests\Recruitment\StoreRecruitmentPeriodRequest;
use App\Http\Requests\Recruitment\UpdateRecruitmentPeriodRequest;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentApplicationService;
use App\Services\Recruitment\RecruitmentDivisionService;
use App\Services\Recruitment\RecruitmentGroupLinkService;
use App\Services\Recruitment\RecruitmentQrBulkService;
use App\Services\Recruitment\RecruitmentQrDispatchRecovery;
use App\Services\Recruitment\RecruitmentQrEligibilityQuery;
use App\Services\Recruitment\RecruitmentPeriodService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\RecruitmentReportService;
use App\Support\LogRedaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        private readonly RecruitmentGroupLinkService $groupLinkService,
        private readonly RecruitmentQrBulkService $qrBulkService,
        private readonly RecruitmentQrEligibilityQuery $qrEligibilityQuery,
        private readonly RecruitmentQrDispatchRecovery $qrDispatchRecovery,
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

    /** Status link-grup terakhir per applicant (1 query, tanpa N+1). */
    private function groupLinkStatusMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $latest = EmailLog::query()
            ->whereIn('recruitment_application_id', $ids)
            ->where('notification_type', EmailNotificationType::RecruitmentGroupLink->value)
            ->orderByDesc('id')
            ->get(['recruitment_application_id', 'status']);

        $map = [];
        foreach ($latest as $log) {
            $map[$log->recruitment_application_id] ??= $log->status->value;
        }

        return $map;
    }

    /** Jumlah eligible kirim link grup (cermin filter bulk service). */
    private function groupLinkEligibleCount(RecruitmentPeriod $period): int
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->whereNull('cancelled_at')
            ->where('result', '!=', ApplicationResult::Rejected->value)
            ->whereHas('screenings', function ($query): void {
                if (self::hasScreeningDecisionIndex()) {
                    // Tanpa hint, optimizer memilih `rec_screenings_app_acted_idx` (tidak
                    // covering untuk decision) sehingga EXISTS membaca baris tabel.
                    // Index (decision, application_id) menjadikan probe point-lookup.
                    $query->useIndex('rec_screenings_decision_app_idx');
                }

                $query->where('decision', ScreeningDecision::Pass->value);
            })
            ->count();
    }

    /** Cache the decision index check so missing hints do not trigger information_schema queries per render. */
    private static function hasScreeningDecisionIndex(): bool
    {
        static $hasIndex = null;

        if ($hasIndex === null) {
            $hasIndex = DB::connection()->getDriverName() === 'mysql'
                && Schema::hasIndex('recruitment_screenings', 'rec_screenings_decision_app_idx');
        }

        return $hasIndex;
    }

    /**
     * Expensive period aggregates are cached to avoid repeated queue and QR scans.
     * @return array{
     *     queue_counts: array<string, int>,
     *     qr_counts: array{eligible: int, sent: int},
     *     group_link_eligible: int,
     *     semester_options: list<array{value: string, label: string}>
     * }
     */
    private function periodAggregates(RecruitmentPeriod $period): array
    {
        return Cache::remember(
            "recruitment.period.aggregates.{$period->id}",
            now()->addSeconds(60),
            fn (): array => [
                'queue_counts' => $this->applicationService->queueCounts($period->id),
                'qr_counts' => $this->qrEligibilityQuery->counts($period),
                'group_link_eligible' => Cache::remember(
                    "recruitment.period.group-link-eligible.{$period->id}",
                    now()->addSeconds(300),
                    fn (): int => $this->groupLinkEligibleCount($period),
                ),
                'semester_options' => $this->applicationService->semesterOptions($period->id),
            ],
        );
    }

    /** Status QR terakhir per applicant (1 query, tanpa N+1). */
    private function qrStatusMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $latest = EmailLog::query()
            ->whereIn('recruitment_application_id', $ids)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['recruitment_application_id', 'status']);

        $map = [];
        foreach ($latest as $log) {
            $map[$log->recruitment_application_id] ??= [
                'status' => $log->status->value,
                'error' => $log->status === EmailLogStatus::Failed ? LogRedaction::MAIL_DELIVERY_FAILURE_MESSAGE : null,
                'error_category' => $log->status === EmailLogStatus::Failed ? LogRedaction::MAIL_DELIVERY_FAILURE_CATEGORY : null,
            ];
        }

        return $map;
    }

    /**
     *
     * Agregat status kirim QR satu periode (log terbaru per applicant).
     *
     * @return array{sent:int,failed:int,queued:int,recipients:list<array{application_id:string,full_name:string,registration_number:string,status:string,error:?string,error_category:?string,sent_at:?string}>}
     *
     */
    private function qrStatusForPeriod(RecruitmentPeriod $period): array
    {
        $applications = RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->orderBy('submitted_at')
            ->get(['id', 'full_name', 'registration_number']);

        if ($applications->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'recipients' => []];
        }

        $latest = EmailLog::query()
            ->whereIn('recruitment_application_id', $applications->pluck('id')->all())
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'recruitment_application_id', 'status', 'sent_at']);

        $byApplication = [];
        foreach ($latest as $log) {
            $byApplication[$log->recruitment_application_id] ??= $log;
        }

        $summary = ['sent' => 0, 'failed' => 0, 'queued' => 0];
        $recipients = [];

        foreach ($applications as $application) {
            $log = $byApplication[$application->id] ?? null;

            if ($log === null) {
                continue;
            }

            $status = $log->status->value;

            if (isset($summary[$status])) {
                $summary[$status]++;
            }

            $isFailed = $status === EmailLogStatus::Failed->value;
            $recipients[] = [
                'application_id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'status' => $status,
                'error' => $isFailed ? LogRedaction::MAIL_DELIVERY_FAILURE_MESSAGE : null,
                'error_category' => $isFailed ? LogRedaction::MAIL_DELIVERY_FAILURE_CATEGORY : null,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ];
        }

        return [...$summary, 'recipients' => $recipients];
    }

    /**
     *
     * Ringkasan status QR (hitungan saja) untuk props halaman.
     * @return array{sent:int,failed:int,queued:int}
     *
     */
    private function qrStatusSummary(RecruitmentPeriod $period): array
    {
        $rows = DB::select(
            'SELECT status, COUNT(*) as total FROM ('
            .' SELECT e.status, ROW_NUMBER() OVER (PARTITION BY e.recruitment_application_id ORDER BY e.created_at DESC, e.id DESC) as row_position'
            .' FROM email_logs e JOIN recruitment_applications a ON a.id = e.recruitment_application_id'
            .' WHERE a.recruitment_period_id = ? AND e.notification_type = ?'
            .') as latest WHERE row_position = 1 GROUP BY status',
            [$period->id, EmailNotificationType::RecruitmentInterviewScheduled->value],
        );

        $summary = ['sent' => 0, 'failed' => 0, 'queued' => 0];

        foreach ($rows as $row) {
            if (array_key_exists($row->status, $summary)) {
                $summary[$row->status] = (int) $row->total;
            }
        }

        return $summary;
    }

    /** Ringkasan QR hanya dibutuhkan tab peserta dan partial reload eksplisit (refresh/polling header meminta `qr_status_summary` lewat X-Inertia-Partial-Data). */
    private function shouldComputeQrStatusSummary(Request $request, string $tab): bool
    {
        if ($tab === 'peserta') {
            return true;
        }

        $partialProps = $request->header('X-Inertia-Partial-Data');

        if (! is_string($partialProps) || $partialProps === '') {
            return false;
        }

        foreach (array_map('trim', explode(',', $partialProps)) as $prop) {
            if ($prop === 'qr_status_summary' || str_starts_with($prop, 'qr_status_summary.')) {
                return true;
            }
        }

        return false;
    }

    public function show(ShowRecruitmentPeriodApplicationsRequest $request, RecruitmentPeriod $period): Response
    {
        $this->authorize('view', $period);

        $period->loadCount('applications');
        $aggregates = $this->periodAggregates($period);

        $validated = $request->validated();
        $tab = $validated['tab'] ?? 'peserta';
        if (! in_array($tab, ['peserta', 'interview', 'laporan', 'interviewer', 'settings'], true)) {
            $tab = 'peserta';
        }

        // per_page sudah dibatasi validasi (nullable|integer|min:5|max:100);
        // aturan global: selalu 20 per halaman, pager hanya bila total > 20.
        $perPage = 20;

        $applications = null;
        $queueCounts = [];
        $sessions = null;
        $report = null;
        $applicantDetail = null;
        $divisions = null;
        $assignments = null;
        $interviewerCandidates = null;
        $screeningReasonOptions = [];
        $divisionOptions = [];
        $semesterOptions = [];

        if ($tab === 'peserta') {
            $canListApplications = $request->user()?->can('recruitment.applications.list') ?? false;

            if ($canListApplications) {
                $applicationPaginator = $this->applicationService->paginate(
                    [
                        'period_id' => $period->id,
                        'division_id' => $validated['division_id'] ?? null,
                        'stage' => $validated['stage'] ?? null,
                        'queue' => $validated['queue'] ?? null,
                        'semester' => $validated['semester'] ?? null,
                        'search' => $validated['search'] ?? null,
                    ],
                    $request->integer('page', 1),
                    $perPage,
                );
                $groupLinkStatuses = $this->groupLinkStatusMap(
                    $applicationPaginator->getCollection()->map(fn ($item) => $item->id)->all()
                );
                $qrStatuses = $this->qrStatusMap(
                    $applicationPaginator->getCollection()->map(fn ($item) => $item->id)->all()
                );
                $applicationPaginator->setCollection(
                    $applicationPaginator->getCollection()->map(
                        fn (RecruitmentApplication $application) => [
                            ...$this->applicationService->toListArray($application),
                            'group_link_status' => $groupLinkStatuses[$application->id] ?? null,
                            'qr_status' => $qrStatuses[$application->id]['status'] ?? null,
                            'qr_error' => $qrStatuses[$application->id]['error'] ?? null,
                            'qr_error_category' => $qrStatuses[$application->id]['error_category'] ?? null,
                        ]
                    )
                );

                $applications = $applicationPaginator->withQueryString();

                $queueCounts = $aggregates['queue_counts'];
                $screeningReasonOptions = ScreeningReason::options();
                $divisionOptions = $this->applicationService->divisionOptions();
                $semesterOptions = $aggregates['semester_options'];

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
            abort_unless($request->user()?->can('recruitment.interviews.schedule') ?? false, 403);

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
            $queueCounts = $aggregates['queue_counts'];
        }

        if ($tab === 'laporan') {
            abort_unless($request->user()?->can('recruitment.reports.view') ?? false, 403);

            $report = $this->reportService->build($period->id);
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

        $qrStatusSummary = null;

        if ($this->shouldComputeQrStatusSummary($request, $tab)) {
            // No scheduler runs in this repo (CONTEXT.md §3), so the QR view drives the bounded
            // recovery net: pending intents and lost `queued` jobs from earlier requests.
            $this->qrDispatchRecovery->recoverOpportunistically();
            $qrStatusSummary = $this->qrStatusSummary($period);
        }

        $qrCounts = $aggregates['qr_counts'];

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
            'group_link_eligible_count' => $aggregates['group_link_eligible'],
            'qr_eligible_count' => $qrCounts['eligible'],
            'qr_sent_count' => $qrCounts['sent'],
        ];

        if ($qrStatusSummary !== null) {
            $props['qr_status_summary'] = $qrStatusSummary;
        }

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

    public function sendGroupLink(Request $request, RecruitmentPeriod $period): RedirectResponse
    {
        $this->authorize('sendGroupLink', $period);

        $result = $this->groupLinkService->send($request->user() ?? abort(403), $period);

        return redirect()
            ->back()
            ->with('message', "Link grup dikirim ke {$result['dispatched']} applicant yang lolos.");
    }

    public function qrStatus(RecruitmentPeriod $period): JsonResponse
    {
        $this->authorize('qrStatus', $period);

        return response()->json($this->qrStatusForPeriod($period));
    }

    public function sendQr(SendPeriodQrRequest $request, RecruitmentPeriod $period): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->qrBulkService->send(
            $request->user() ?? abort(403),
            $period,
            (bool) ($validated['include_sent'] ?? false)
        );

        Cache::forget("recruitment.period.aggregates.{$period->id}");

        $scheduled = (int) ($result['scheduled'] ?? 0);

        Inertia::flash('toast', [
            'message' => $scheduled > 0
                ? "QR dijadwalkan untuk {$scheduled} applicant tahap interview."
                : 'Tidak ada applicant yang perlu dijadwalkan untuk QR.',
            'type' => $scheduled > 0 ? 'success' : 'warning',
        ]);

        return redirect()
            ->back()
            ->with('message', "QR dijadwalkan untuk {$scheduled} applicant tahap interview.");
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
