<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentScreening;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RecruitmentApplicationService
{
    public function __construct(private readonly RecruitmentEmailResendService $resendService)
    {
    }

    /** Kolom pencarian yang boleh dipakai sebagai identifier SQL. */
    private const SEARCH_COLUMNS = ['full_name', 'nim', 'registration_number'];

    /** Kolom yang dibutuhkan `toListArray()` + foreign key relasi; `SELECT *` menghidrasi kolom berat seperti phone/personal_email tanpa dipakai. */
    private const LIST_COLUMNS = [
        'id',
        'recruitment_period_id',
        'registration_number',
        'full_name',
        'nim',
        'semester',
        'stage',
        'result',
        'revision_required',
        'submitted_at',
        'primary_division_id',
        'secondary_division_id',
    ];

    /**
     *
     * Satu-satunya definisi aturan antrean: dipakai bersama oleh badge `queueCounts()` dan filter daftar `applyQueueFilter()` supaya keduanya tidak bisa berbeda arti.
     * @var array<string, array{stages?: list<ApplicationStage>, stage?: ApplicationStage, result?: ApplicationResult, revision_required?: bool}>
     *
     */
    private const QUEUE_RULES = [
        'screening' => [
            'stages' => [ApplicationStage::Submitted, ApplicationStage::Screening],
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
        ],
        'revision' => ['revision_required' => true],
        'interview' => ['stage' => ApplicationStage::Interview],
        'final' => ['stage' => ApplicationStage::FinalReview],
        'done' => ['stage' => ApplicationStage::Completed],
    ];

    /**
     *
     * Daftar applicant dengan paginasi yang totalnya tetap akurat pada 20.000 baris per periode.
     * @param  array<string, mixed>  $filters
     *
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginatorContract
    {
        $page = max(1, $page);

        if (! empty($filters['search'])) {
            return $this->paginateSearch((string) $filters['search'], $filters, $page, $perPage);
        }

        $query = $this->listQuery($filters);

        $total = ! empty($filters['division_id'])
            ? $this->countDivisionMatches($filters)
            : (clone $query)->toBase()->getCountForPagination();

        return $query->paginate($perPage, self::LIST_COLUMNS, 'page', $page, $total);
    }

    /**
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<RecruitmentApplication>
     *
     */
    private function listQuery(array $filters): Builder
    {
        $query = RecruitmentApplication::query()
            ->with(['primaryDivision:id,name,code', 'secondaryDivision:id,name,code', 'period:id,name'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $this->applyListFilters($query, $filters);

        return $query;
    }

    /**
     *
     * Terapkan filter daftar.
     * @param  Builder<RecruitmentApplication>  $query
     * @param  array<string, mixed>  $filters
     *
     */
    private function applyListFilters(Builder $query, array $filters, ?string $searchColumn = null, ?string $divisionColumn = null): void
    {
        if (! empty($filters['period_id'])) {
            $query->where('recruitment_period_id', $filters['period_id']);
        }

        if ($divisionColumn !== null) {
            $query->where($divisionColumn, $filters['division_id']);
        } elseif (! empty($filters['division_id'])) {
            $divisionId = $filters['division_id'];
            $query->where(function ($q) use ($divisionId): void {
                $q->where('primary_division_id', $divisionId)
                    ->orWhere('secondary_division_id', $divisionId);
            });
        }

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (! empty($filters['queue'])) {
            $this->applyQueueFilter($query, (string) $filters['queue']);
        }

        if (! empty($filters['semester'])) {
            $query->where('semester', (int) $filters['semester']);
        }

        if (! empty($filters['search'])) {
            $this->applySearch($query, (string) $filters['search'], $searchColumn);
        }
    }

    /**
     *
     * Terapkan pola substring yang meng-escape wildcard LIKE dari input.
     * Kolom interpolasi hanya berasal dari daftar tetap `SEARCH_COLUMNS`.
     *
     * @param  Builder<RecruitmentApplication>  $query
     *
     */
    private function applySearch(Builder $query, string $search, ?string $column = null): void
    {
        $pattern = $this->searchPattern($search);

        if ($column !== null) {
            $query->whereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern]);

            return;
        }

        $query->where(function (Builder $searchQuery) use ($pattern): void {
            $searchQuery->whereRaw("full_name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("nim LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("registration_number LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /** Escape karakter LIKE dan karakter escape agar dicari apa adanya. */
    private function searchPattern(string $search): string
    {
        $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        return '%'.$escapedSearch.'%';
    }

    /**
     *
     * Paginasi pencarian substring.
     * @param  array<string, mixed>  $filters
     *
     */
    private function paginateSearch(string $search, array $filters, int $page, int $perPage): LengthAwarePaginatorContract
    {
        $periodId = $filters['period_id'] ?? null;

        if (empty($periodId)) {
            return $this->paginateSearchWithoutPeriod($search, $filters, $page, $perPage);
        }

        $total = (int) $this->searchMatchQuery($search, $filters, (string) $periodId)->count();

        $items = RecruitmentApplication::query()->getModel()->newCollection();

        if ($total > 0) {
            $pageIds = $this->searchMatchQuery($search, $filters, (string) $periodId)
                ->orderByDesc('search_matches.submitted_at')
                ->orderByDesc('search_matches.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->pluck('search_matches.id')
                ->all();

            if ($pageIds !== []) {
                $items = RecruitmentApplication::query()
                    ->with(['primaryDivision:id,name,code', 'secondaryDivision:id,name,code', 'period:id,name'])
                    ->whereIn('id', $pageIds)
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->get(self::LIST_COLUMNS);
            }
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    /**
     *
     * Query irisan pencarian + filter (dipakai untuk COUNT dan id halaman).
     * @param  array<string, mixed>  $filters
     *
     */
    private function searchMatchQuery(string $search, array $filters, string $periodId): QueryBuilder
    {
        $filterIds = $this->nonSearchFilterIds($filters);

        if ($filterIds === null) {
            return DB::query()->fromSub($this->searchIdUnion($search, $periodId), 'search_matches');
        }

        return $this->intersectQuery($filterIds, $this->searchIdUnion($search, $periodId));
    }

    /** Irisan id filter non-pencarian dengan id pencarian. */
    private function intersectQuery(QueryBuilder $filterIds, QueryBuilder $searchIds): QueryBuilder
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return DB::query()
                ->fromSub($filterIds, 'filter_matches')
                ->joinSub($searchIds, 'search_matches', 'search_matches.id', '=', 'filter_matches.id');
        }

        // MySQL memakai STRAIGHT_JOIN (satu kata kunci, bukan "straight join") supaya
        // filter non-pencarian dibaca lebih dulu; tanpa itu optimizer memilih derived
        // pencarian tanpa auto-key dan irisan 20.000 x 20.000 menjadi ~4,6 detik.
        return DB::query()->fromRaw(
            '('.$filterIds->toSql().') as filter_matches straight_join ('.$searchIds->toSql().') as search_matches'
            .' on search_matches.id = filter_matches.id',
            [...$filterIds->getBindings(), ...$searchIds->getBindings()],
        );
    }

    /** Id + submitted_at pencarian murni (periode saja, tanpa filter lain) supaya setiap cabang memakai covering index `(period, kolom, submitted_at)`. */
    private function searchIdUnion(string $search, string $periodId): QueryBuilder
    {
        $pattern = $this->searchPattern($search);
        $branches = [];

        foreach (self::SEARCH_COLUMNS as $column) {
            $branches[] = DB::table('recruitment_applications')
                ->select(['id', 'submitted_at'])
                ->where('recruitment_period_id', $periodId)
                ->whereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern]);
        }

        $union = $branches[0];

        foreach (array_slice($branches, 1) as $branch) {
            $union->union($branch);
        }

        return $union;
    }

    /**
     *
     * Id yang lolos semua filter non-pencarian, atau null bila tidak ada filter selain periode/pencarian.
     * @param  array<string, mixed>  $filters
     *
     */
    private function nonSearchFilterIds(array $filters): ?QueryBuilder
    {
        $withoutSearch = $filters;
        unset($withoutSearch['search']);

        $hasNonSearchFilter = ! empty($filters['division_id'])
            || ! empty($filters['stage'])
            || ! empty($filters['queue'])
            || ! empty($filters['semester']);

        if (! $hasNonSearchFilter) {
            return null;
        }

        if (! empty($filters['division_id'])) {
            $branches = [];

            foreach (['primary_division_id', 'secondary_division_id'] as $column) {
                $branch = RecruitmentApplication::query()->select('id');
                $this->applyListFilters($branch, $withoutSearch, divisionColumn: $column);
                $branches[] = $branch->toBase();
            }

            return $branches[0]->union($branches[1]);
        }

        $query = RecruitmentApplication::query()->select('id');
        $this->applyListFilters($query, $withoutSearch);

        return $query->toBase();
    }

    /**
     *
     * Fallback pencarian tanpa periode: cabang UNION memuat seluruh filter agar hasil tetap benar.
     * @param  array<string, mixed>  $filters
     *
     */
    private function paginateSearchWithoutPeriod(string $search, array $filters, int $page, int $perPage): LengthAwarePaginatorContract
    {
        $total = (int) DB::query()
            ->fromSub($this->searchUnion($search, $filters), 'search_matches')
            ->count();

        $items = RecruitmentApplication::query()->getModel()->newCollection();

        if ($total > 0) {
            $pageIds = $this->searchUnion($search, $filters)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->pluck('id')
                ->all();

            if ($pageIds !== []) {
                $items = RecruitmentApplication::query()
                    ->with(['primaryDivision:id,name,code', 'secondaryDivision:id,name,code', 'period:id,name'])
                    ->whereIn('id', $pageIds)
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->get(self::LIST_COLUMNS);
            }
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    /**
     *
     * UNION tiga cabang (satu per kolom pencarian). UNION (bukan UNION ALL)
     * mendedupe baris yang cocok di lebih dari satu kolom.
     *
     * @param  array<string, mixed>  $filters
     *
     */
    private function searchUnion(string $search, array $filters): QueryBuilder
    {
        $branches = [];

        foreach (self::SEARCH_COLUMNS as $column) {
            $branch = RecruitmentApplication::query()->select(['id', 'submitted_at']);
            $this->applyListFilters($branch, $filters, searchColumn: $column);
            $branches[] = $branch->toBase();
        }

        $union = $branches[0];

        foreach (array_slice($branches, 1) as $branch) {
            $union->union($branch);
        }

        return $union;
    }

    /**
     *
     * Total filter divisi: UNION dua covering count supaya applicant yang memilih divisi di primary dan secondary tetap dihitung sekali.
     * @param  array<string, mixed>  $filters
     *
     */
    private function countDivisionMatches(array $filters): int
    {
        $branches = [];

        foreach (['primary_division_id', 'secondary_division_id'] as $column) {
            $branch = RecruitmentApplication::query()->select('id');
            $this->applyListFilters($branch, $filters, divisionColumn: $column);
            $branches[] = $branch->toBase();
        }

        return (int) DB::query()
            ->fromSub($branches[0]->union($branches[1]), 'division_matches')
            ->count();
    }

    /**
     *
     * Enam angka antrean dihitung dari satu query agregat kondisional (covering index `rec_apps_queue_counts_idx`), koreksi pending dari tabel lain dengan satu query terpisah.
     * @return array<string, int>
     *
     */
    public function queueCounts(?string $periodId = null): array
    {
        $hasPeriod = $periodId !== null && $periodId !== '';

        $selects = ['COUNT(*) as aggregate_all'];
        $bindings = [];

        foreach (self::QUEUE_RULES as $queue => $rule) {
            [$predicate, $ruleBindings] = self::queueCasePredicate($rule);
            $selects[] = "SUM(CASE WHEN {$predicate} THEN 1 ELSE 0 END) as aggregate_{$queue}";
            $bindings = [...$bindings, ...$ruleBindings];
        }

        /** @var array<string, int|string|null> $row SUM/COUNT bisa kembali string di driver tertentu. */
        $row = (array) RecruitmentApplication::query()
            ->when($hasPeriod, fn ($query) => $query->where('recruitment_period_id', $periodId))
            ->toBase()
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        $counts = [];

        foreach (['all', ...array_keys(self::QUEUE_RULES)] as $key) {
            $counts[$key] = (int) ($row['aggregate_'.$key] ?? 0);
        }

        // JOIN eksplisit + cancelled_at IS NULL: mengikuti aturan antrean (applicant
        // batal bukan pekerjaan) dan memakai index (recruitment_application_id, status).
        // Bentuk whereHas(period) memakai EXISTS yang terukur 346-477 ms; JOIN ~53 ms.
        $counts['corrections_pending'] = RecruitmentCorrectionRequest::query()
            ->join(
                'recruitment_applications',
                'recruitment_applications.id',
                '=',
                'recruitment_correction_requests.recruitment_application_id',
            )
            ->where('recruitment_correction_requests.status', CorrectionRequestStatus::Pending)
            ->whereNull('recruitment_applications.cancelled_at')
            ->when(
                $hasPeriod,
                fn ($query) => $query->where('recruitment_applications.recruitment_period_id', $periodId),
            )
            ->count();

        return $counts;
    }

    /**
     *
     * Predikat SQL untuk satu aturan `QUEUE_RULES`, memakai binding parameter.
     *
     * @param  array{stages?: list<ApplicationStage>, stage?: ApplicationStage, result?: ApplicationResult, revision_required?: bool}  $rule
     * @return array{0: string, 1: list<string|int|bool>}
     *
     */
    private static function queueCasePredicate(array $rule): array
    {
        // Applicant yang dibatalkan tidak pernah menjadi pekerjaan antrean.
        $conditions = ['cancelled_at IS NULL'];
        $bindings = [];

        if (isset($rule['stages'])) {
            $placeholders = implode(', ', array_fill(0, count($rule['stages']), '?'));
            $conditions[] = "stage IN ({$placeholders})";

            foreach ($rule['stages'] as $stage) {
                $bindings[] = $stage->value;
            }
        }

        if (isset($rule['stage'])) {
            $conditions[] = 'stage = ?';
            $bindings[] = $rule['stage']->value;
        }

        if (isset($rule['result'])) {
            $conditions[] = 'result = ?';
            $bindings[] = $rule['result']->value;
        }

        if (array_key_exists('revision_required', $rule)) {
            $conditions[] = 'revision_required = ?';
            $bindings[] = $rule['revision_required'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    /**
     *
     * @param  \Illuminate\Database\Eloquent\Builder<RecruitmentApplication>  $query
     *
     */
    private function applyQueueFilter($query, string $queue): void
    {
        $rule = self::QUEUE_RULES[$queue] ?? null;

        if ($rule === null) {
            return;
        }

        // Cermin badge: applicant batal tidak masuk antrean mana pun.
        $query->whereNull('cancelled_at');

        if (isset($rule['stages'])) {
            $query->whereIn('stage', $rule['stages']);
        }

        if (isset($rule['stage'])) {
            $query->where('stage', $rule['stage']);
        }

        if (isset($rule['result'])) {
            $query->where('result', $rule['result']);
        }

        if (array_key_exists('revision_required', $rule)) {
            $query->where('revision_required', $rule['revision_required']);
        }
    }

    /**
     *
     * @return array<string, mixed>
     *
     */
    public function toListArray(RecruitmentApplication $application): array
    {
        return [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'revision_required' => $application->revision_required,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'primary_division' => $application->primaryDivision ? [
                'id' => $application->primaryDivision->id,
                'name' => $application->primaryDivision->name,
            ] : null,
            'secondary_division' => $application->secondaryDivision ? [
                'id' => $application->secondaryDivision->id,
                'name' => $application->secondaryDivision->name,
            ] : null,
            'period' => $application->period ? [
                'id' => $application->period->id,
                'name' => $application->period->name,
            ] : null,
        ];
    }

    /**
     *
     * @return array<string, mixed>
     *
     */
    public function toShowArray(RecruitmentApplication $application): array
    {
        $application->loadMissing([
            'period',
            'primaryDivision',
            'secondaryDivision',
            'document',
            'screenings.actor',
            'activityLogs.actor',
            'correctionRequests.reviewer',
            'evaluation.evaluator',
            'primaryInterview.session.division',
            'primaryInterview.evaluation.evaluator',
            'secondaryInterview.session.division',
            'secondaryInterview.evaluation.evaluator',
            'finalDecision.finalDivision',
            'finalDecision.decider',
        ]);

        return [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'phone' => $application->phone,
            'personal_email' => $application->personal_email,
            'student_email' => $application->student_email,
            'instagram_username' => $application->instagram_username,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'is_verified' => $application->is_verified,
            'revision_required' => $application->revision_required,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'period' => $application->period ? [
                'id' => $application->period->id,
                'name' => $application->period->name,
            ] : null,
            'primary_division' => $this->divisionArray($application->primaryDivision),
            'secondary_division' => $this->divisionArray($application->secondaryDivision),
            'document' => $application->document ? [
                'cv_original_name' => $application->document->cv_original_name,
                'cv_mime' => $application->document->cv_mime,
                'cv_size_bytes' => $application->document->cv_size_bytes,
                'portfolio_type' => $application->document->portfolio_type,
                'portfolio_url' => $application->document->portfolio_url,
                'portfolio_original_name' => $application->document->portfolio_original_name,
                'portfolio_mime' => $application->document->portfolio_mime,
                'portfolio_size_bytes' => $application->document->portfolio_size_bytes,
                'instagram_follow_original_name' => $application->document->instagram_follow_original_name,
                'instagram_follow_mime' => $application->document->instagram_follow_mime,
                'instagram_follow_size_bytes' => $application->document->instagram_follow_size_bytes,
                'twibbon_url' => $application->document->twibbon_url,
                'has_cv_file' => filled($application->document->cv_path),
                'has_portfolio_file' => filled($application->document->portfolio_path),
                'has_instagram_follow_file' => filled($application->document->instagram_follow_path),
            ] : null,
            'screenings' => $application->screenings
                ->sortByDesc('acted_at')
                ->values()
                ->map(fn (RecruitmentScreening $screening): array => [
                    'id' => $screening->id,
                    'decision' => $screening->decision->value,
                    'decision_label' => $screening->decision->label(),
                    'reason' => $screening->reason?->value,
                    'reason_label' => $screening->reason?->label(),
                    'notes' => $screening->notes,
                    'acted_at' => $screening->acted_at?->toIso8601String(),
                    'actor' => $screening->actor ? [
                        'id' => $screening->actor->id,
                        'name' => $screening->actor->name,
                    ] : null,
                ])
                ->all(),
            'activity_logs' => $application->activityLogs
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (RecruitmentActivityLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'old_values' => RecruitmentActivityLogger::projectValues($log->old_values),
                    'new_values' => RecruitmentActivityLogger::projectValues($log->new_values),
                    'created_at' => $log->created_at?->toIso8601String(),
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                    ] : null,
                ])
                ->all(),
            'correction_requests' => $application->correctionRequests
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (RecruitmentCorrectionRequest $correction): array => [
                    'id' => $correction->id,
                    'status' => $correction->status->value,
                    'status_label' => $correction->status->label(),
                    'request_message' => $correction->request_message,
                    'review_notes' => $correction->review_notes,
                    'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
                    'completed_at' => $correction->completed_at?->toIso8601String(),
                    'reviewer' => $correction->reviewer ? [
                        'id' => $correction->reviewer->id,
                        'name' => $correction->reviewer->name,
                    ] : null,
                ])
                ->all(),
            'evaluation' => $this->evaluationArray($application->evaluation),
            'evaluations' => [
                'primary' => $this->evaluationArray(
                    $application->primaryInterview?->evaluation,
                    $application->primaryInterview?->session?->division?->name,
                    $application->primaryInterview?->session?->division?->id,
                ),
                'secondary' => $this->evaluationArray(
                    $application->secondaryInterview?->evaluation,
                    $application->secondaryInterview?->session?->division?->name,
                    $application->secondaryInterview?->session?->division?->id,
                ),
            ],
            'final_decision' => $this->finalDecisionArray($application->finalDecision),
            'can_screen' => $this->canScreen($application),
            'can_verify' => $this->canVerify($application),
            'can_decide_final' => $this->canDecideFinal($application),
            'can_resend_tracking' => $this->canResendTracking($application),
            'email_resend_status' => $this->emailResendStatus($application),
            'email_resend_prereq' => $this->emailResendPrereq($application),
        ];
    }

    /**
     *
     * @return list<array{id: string, name: string}>
     *
     */
    public function periodOptions(): array
    {
        return RecruitmentPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name'])
            ->map(fn (RecruitmentPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
            ])
            ->all();
    }

    /**
     *
     * @return list<array{id: string, name: string, code: string}>
     *
     */
    public function divisionOptions(): array
    {
        return RecruitmentDivision::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->map(fn (RecruitmentDivision $division): array => [
                'id' => $division->id,
                'name' => $division->name,
                'code' => $division->code,
            ])
            ->all();
    }

    /**
     *
     * Semester yang benar-benar ada pada aplikasi periode tersebut, tanpa terpengaruh filter.
     *
     * @return list<array{value: string, label: string}>
     *
     */
    public function semesterOptions(string $periodId): array
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->whereNotNull('semester')
            ->orderBy('semester')
            ->distinct()
            ->pluck('semester')
            ->map(fn (int $semester): array => [
                'value' => (string) $semester,
                'label' => "Semester {$semester}",
            ])
            ->values()
            ->all();
    }

    private function canScreen(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->result !== \App\Enums\Recruitment\ApplicationResult::Pending) {
            return false;
        }

        return in_array($application->stage, [
            \App\Enums\Recruitment\ApplicationStage::Submitted,
            \App\Enums\Recruitment\ApplicationStage::Screening,
        ], true);
    }

    private function canVerify(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->is_verified) {
            return false;
        }

        if ($application->revision_required) {
            return false;
        }

        return $application->result === \App\Enums\Recruitment\ApplicationResult::Pending;
    }

    private function canResendTracking(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        return filled($application->personal_email);
    }

    /** Status percobaan resend per jenis dari activity logs ("Terakhir dicoba"). */
    private function emailResendStatus(RecruitmentApplication $application): array
    {
        $status = [];

        foreach (RecruitmentEmailResendService::TYPES as $type) {
            $attempts = $application->activityLogs
                ->filter(fn (RecruitmentActivityLog $log): bool => $this->isResendAttempt($log, $type));

            $recent = $attempts->filter(
                fn (RecruitmentActivityLog $log): bool => $log->created_at !== null && $log->created_at->gte(now()->subDay())
            );

            $status[$type] = [
                'last_attempt_at' => $attempts->sortByDesc('created_at')->first()?->created_at?->toIso8601String(),
                'count_24h' => $recent->count(),
            ];
        }

        return $status;
    }

    /** Satu percobaan resend jenis ini (tracking gabung log lama + baru). */
    private function isResendAttempt(RecruitmentActivityLog $log, string $type): bool
    {
        if ($type === 'tracking') {
            return $log->action === 'tracking.resend'
                || ($log->action === 'email.resend' && ($log->new_values['type'] ?? null) === 'tracking');
        }

        return $log->action === 'email.resend' && ($log->new_values['type'] ?? null) === $type;
    }

    /** Prasyarat tombol resend per jenis untuk applicant ini. */
    private function emailResendPrereq(RecruitmentApplication $application): array
    {
        $hasInterview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->whereNotNull('interviewer_id')
            ->exists();

        return [
            'has_correction' => $application->correctionRequests->isNotEmpty(),
            'has_interview' => $hasInterview,
            'has_replayable_notification' => $this->hasReplayableNotification($application, $hasInterview),
            'has_final_decision' => in_array($application->result, [ApplicationResult::Accepted, ApplicationResult::Rejected], true),
        ];
    }

    /** Ada notifikasi terkirim yang bisa diputar ulang (cermin aturan resend). */
    private function hasReplayableNotification(RecruitmentApplication $application, bool $hasInterview): bool
    {
        return $this->resendService->latestReplayableNotification($application, $hasInterview) !== null;
    }

    private function canDecideFinal(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->stage !== ApplicationStage::FinalReview) {
            return false;
        }

        return $application->result === ApplicationResult::Pending;
    }

    /**
     *
     * @return array<string, mixed>|null
     *
     */
    private function evaluationArray(?RecruitmentEvaluation $evaluation, ?string $division = null, ?string $divisionId = null): ?array
    {
        if ($evaluation === null) {
            return null;
        }

        return [
            'speaking_score' => $evaluation->speaking_score,
            'technical_score' => $evaluation->technical_score,
            'attitude_score' => $evaluation->attitude_score,
            'recommendation' => $evaluation->recommendation->value,
            'recommendation_label' => $evaluation->recommendation->label(),
            'notes' => $evaluation->notes,
            'is_locked' => $evaluation->isLocked(),
            'evaluated_at' => $evaluation->evaluated_at?->toIso8601String(),
            'save_count' => $evaluation->save_count,
            'saves_remaining' => max(0, RecruitmentEvaluation::MAX_SAVES - $evaluation->save_count),
            'division' => $division,
            'division_id' => $divisionId,
            'evaluator' => $evaluation->evaluator ? [
                'id' => $evaluation->evaluator->id,
                'name' => $evaluation->evaluator->name,
            ] : null,
        ];
    }

    /**
     *
     * @return array<string, mixed>|null
     *
     */
    private function finalDecisionArray(?RecruitmentFinalDecision $decision): ?array
    {
        if ($decision === null) {
            return null;
        }

        return [
            'membership_type' => $decision->membership_type,
            'membership_type_label' => filled($decision->membership_type)
                ? \App\Enums\Recruitment\MembershipType::tryFrom($decision->membership_type)?->label()
                : null,
            'final_division' => $this->divisionArray($decision->finalDivision),
            'internal_reason' => $decision->internal_reason,
            'public_message' => $decision->public_message,
            'decided_at' => $decision->decided_at?->toIso8601String(),
            'decider' => $decision->decider ? [
                'id' => $decision->decider->id,
                'name' => $decision->decider->name,
            ] : null,
        ];
    }

    /**
     *
     * @return array{id: string, name: string, code: string}|null
     *
     */
    private function divisionArray(?RecruitmentDivision $division): ?array
    {
        if ($division === null) {
            return null;
        }

        return [
            'id' => $division->id,
            'name' => $division->name,
            'code' => $division->code,
        ];
    }
}
