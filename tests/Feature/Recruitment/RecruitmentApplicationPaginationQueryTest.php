<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentApplicationService;
use App\Services\Recruitment\RecruitmentDashboardService;
use App\Services\Recruitment\RecruitmentPeriodService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EP-03 regresi query daftar applicant:
 * - BE-P2-012 total filter divisi (UNION, dedupe aplikasi dua divisi);
 * - BE-P2-013 UNION pencarian + BE-P2-015 substring dan escape wildcard;
 * - BE-P2-028 ringkasan dashboard satu agregat;
 * - BE-P2-029 payload periode tanpa N+1 count.
 */
class RecruitmentApplicationPaginationQueryTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $primaryDivision;

    private RecruitmentDivision $secondaryDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->primaryDivision = RecruitmentDivision::factory()->create();
        $this->secondaryDivision = RecruitmentDivision::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function application(array $attributes = []): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->primaryDivision->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
            'submitted_at' => now(),
            ...$attributes,
        ]);
    }

    private function service(): RecruitmentApplicationService
    {
        return app(RecruitmentApplicationService::class);
    }

    /** Aplikasi yang memilih divisi yang sama di primary dan secondary dihitung sekali. */
    public function test_division_total_counts_an_application_with_both_divisions_once(): void
    {
        $both = $this->application([
            'primary_division_id' => $this->primaryDivision->id,
            'secondary_division_id' => $this->primaryDivision->id,
        ]);
        $primaryOnly = $this->application();
        $this->application(['primary_division_id' => $this->secondaryDivision->id]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'division_id' => $this->primaryDivision->id,
        ]);

        $this->assertSame(2, $paginator->total());
        $this->assertEqualsCanonicalizing(
            [$both->id, $primaryOnly->id],
            $paginator->getCollection()->pluck('id')->all(),
        );
    }

    public function test_division_total_respects_other_filters(): void
    {
        $this->application(['stage' => ApplicationStage::Submitted]);
        $this->application(['stage' => ApplicationStage::Interview]);
        $this->application(['stage' => ApplicationStage::Interview]);
        $this->application([
            'stage' => ApplicationStage::Interview,
            'primary_division_id' => $this->secondaryDivision->id,
            'secondary_division_id' => $this->primaryDivision->id,
        ]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'division_id' => $this->primaryDivision->id,
            'stage' => ApplicationStage::Interview->value,
        ]);

        $this->assertSame(3, $paginator->total());
        $this->assertCount(3, $paginator->getCollection());
    }

    /**
     * BE-P2-015: normal substring search tetap menemukan kata di tengah nama.
     */
    public function test_search_matches_prefix_and_mid_string(): void
    {
        $budi = $this->application([
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2021.00001',
            'registration_number' => 'OPREC-2021-000001',
        ]);
        $budiman = $this->application(['full_name' => 'Siti Budiman']);

        $byPrefix = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Budi',
        ]);
        $midWord = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Santoso',
        ]);

        $this->assertSame(2, $byPrefix->total());
        $this->assertEqualsCanonicalizing(
            [$budi->id, $budiman->id],
            $byPrefix->getCollection()->pluck('id')->all(),
        );
        $this->assertSame(1, $midWord->total());
        $this->assertSame([$budi->id], $midWord->getCollection()->pluck('id')->all());
    }

    public function test_search_keeps_exact_total_and_page_rows(): void
    {
        for ($index = 1; $index <= 25; $index++) {
            $this->application([
                'full_name' => sprintf('Zahra Peserta %02d', $index),
                'nim' => sprintf('A11.2024.%05d', $index),
                'registration_number' => sprintf('OPREC-2024-%06d', $index),
                'submitted_at' => now()->subMinutes($index),
            ]);
        }

        $firstPage = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Zahra',
        ], 1, 20);
        $secondPage = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Zahra',
        ], 2, 20);

        $this->assertSame(25, $firstPage->total());
        $this->assertCount(20, $firstPage->items());
        $this->assertCount(5, $secondPage->items());
    }

    /**
     * BE-P2-028: delapan statistik + accepted_count dihitung satu query agregat
     * kondisional. Blok ini tidak boleh kembali menjadi satu COUNT per statistik.
     */
    public function test_dashboard_stats_use_a_single_aggregate_query(): void
    {
        $this->application(['stage' => ApplicationStage::Submitted]);
        $this->application(['stage' => ApplicationStage::Screening]);
        $this->application(['stage' => ApplicationStage::Interview]);
        $this->application(['stage' => ApplicationStage::FinalReview]);
        $this->application(['stage' => ApplicationStage::Completed, 'result' => ApplicationResult::Accepted]);
        $this->application(['stage' => ApplicationStage::Screening, 'result' => ApplicationResult::Rejected]);

        $queries = $this->captureQueries(fn (): array => app(RecruitmentDashboardService::class)
            ->summary(null, $this->period->id));

        $statsQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'total_applicants'),
        ));
        $statBlockQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'total_applicants')
                || str_contains($sql, 'aggregate_all')
                || str_contains($sql, 'recruitment_correction_requests'),
        ));

        $this->assertCount(1, $statsQueries, 'Statistik harus dihitung satu query agregat.');
        $this->assertLessThanOrEqual(3, count($statBlockQueries));

        $summary = app(RecruitmentDashboardService::class)->summary(null, $this->period->id);

        $this->assertSame([
            'total_applicants' => 6,
            'pending_screening' => 1,
            'in_screening' => 2,
            'passed_screening' => 2,
            'rejected_applicants' => 1,
            'in_interview' => 1,
            'final_review' => 1,
            'completed' => 1,
        ], $summary['stats']);
        $this->assertSame(1, $summary['accepted_count']);
    }

    /**
     * BE-P2-029: payload 20 periode memakai withCount, bukan satu count per baris.
     */
    public function test_period_payload_uses_with_count_for_application_totals(): void
    {
        $periods = RecruitmentPeriod::factory()->count(20)->create();

        foreach ($periods as $period) {
            RecruitmentApplication::factory()->count(2)->create([
                'recruitment_period_id' => $period->id,
                'primary_division_id' => $this->primaryDivision->id,
            ]);
        }

        $queries = $this->captureQueries(fn (): array => app(RecruitmentPeriodService::class)
            ->paginate([], 1, 20)
            ->items());

        $this->assertLessThanOrEqual(3, count($queries));

        $paginator = app(RecruitmentPeriodService::class)->paginate([], 1, 20);

        foreach ($paginator->items() as $period) {
            $this->assertSame(2, $period->applications_count);
        }
    }

    /**
     * C2: pencarian + filter harus berupa irisan dua id-set, bukan filter yang
     * disalin ke dalam setiap cabang UNION (dulu memindai satu periode per cabang).
     */
    public function test_search_with_filters_intersects_search_and_filter_sets(): void
    {
        $screening = $this->application([
            'full_name' => 'Budi Santoso',
            'semester' => 3,
            'stage' => ApplicationStage::Submitted,
        ]);
        $this->application([
            'full_name' => 'Siti Santoso',
            'semester' => 5,
            'stage' => ApplicationStage::Interview,
            'primary_division_id' => $this->secondaryDivision->id,
        ]);
        $interview = $this->application([
            'full_name' => 'Agus Santoso',
            'semester' => 3,
            'stage' => ApplicationStage::Interview,
        ]);
        $cancelled = $this->application([
            'full_name' => 'Candra Santoso',
            'semester' => 3,
            'stage' => ApplicationStage::Submitted,
            'cancelled_at' => now(),
        ]);

        $bySemester = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Santoso',
            'semester' => '3',
        ]);
        $this->assertSame(3, $bySemester->total());
        $this->assertEqualsCanonicalizing(
            [$screening->id, $interview->id, $cancelled->id],
            $bySemester->getCollection()->pluck('id')->all(),
        );

        $byDivision = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Santoso',
            'division_id' => $this->primaryDivision->id,
        ]);
        $this->assertSame(3, $byDivision->total());

        $byQueue = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Santoso',
            'queue' => 'screening',
        ]);
        $this->assertSame(1, $byQueue->total());
        $this->assertSame([$screening->id], $byQueue->getCollection()->pluck('id')->all());
    }

    public function test_search_with_filter_count_keeps_three_like_predicates_and_a_join(): void
    {
        $this->application(['full_name' => 'Budi Santoso', 'semester' => 3]);
        $this->application(['full_name' => 'Siti Aminah', 'semester' => 3]);

        $queries = $this->captureQueries(fn (): array => $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Santoso',
            'semester' => '3',
        ])->items());

        $countQuery = collect($queries)->first(
            static fn (string $sql): bool => str_starts_with($sql, 'select count(*)')
                && str_contains($sql, 'search_matches')
                && str_contains($sql, 'filter_matches'),
        );

        $this->assertIsString($countQuery, 'COUNT pencarian+filter harus berupa irisan search_matches dan filter_matches.');
        // Tiga LIKE hanya dari cabang pencarian; filter non-pencarian tidak boleh ikut LIKE.
        $this->assertSame(3, substr_count($countQuery, ' like '));
    }

    /**
     * @param  callable(): mixed  $callback
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];

        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $callback();

        return array_values($queries);
    }
}
