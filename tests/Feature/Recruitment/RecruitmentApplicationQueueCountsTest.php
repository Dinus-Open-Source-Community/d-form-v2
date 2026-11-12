<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentApplicationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi BE-P2-008: badge antrean dihitung dari satu query agregat
 * `recruitment_applications` ditambah satu query koreksi, dan aturannya tidak
 * boleh drift dari filter daftar (`applyQueueFilter`).
 */
class RecruitmentApplicationQueueCountsTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentPeriod $otherPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->otherPeriod = RecruitmentPeriod::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function application(RecruitmentPeriod $period, array $attributes = []): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
            'submitted_at' => now(),
            ...$attributes,
        ]);
    }

    private function correction(RecruitmentApplication $application, CorrectionRequestStatus $status): RecruitmentCorrectionRequest
    {
        return RecruitmentCorrectionRequest::query()->create([
            'recruitment_application_id' => $application->id,
            'status' => $status,
            'request_message' => 'Lengkapi dokumen pendaftaran.',
        ]);
    }

    private function service(): RecruitmentApplicationService
    {
        return app(RecruitmentApplicationService::class);
    }

    /**
     * Fixture: 10 aplikasi pada periode uji, 1 pada periode lain, dan 3 koreksi
     * (pending di periode uji, pending di periode lain, approved di periode uji).
     */
    private function seedQueues(): void
    {
        $screeningSubmitted = $this->application($this->period);
        $screeningStage = $this->application($this->period, ['stage' => ApplicationStage::Screening]);

        // revision_required=true menang atas screening: masuk antrean revisi saja.
        $this->application($this->period, [
            'stage' => ApplicationStage::Screening,
            'revision_required' => true,
        ]);

        // Hasil rejected bukan pekerjaan screening.
        $this->application($this->period, [
            'stage' => ApplicationStage::Screening,
            'result' => ApplicationResult::Rejected,
        ]);

        // Hasil cancelled juga bukan pekerjaan screening.
        $this->application($this->period, [
            'stage' => ApplicationStage::Screening,
            'result' => ApplicationResult::Cancelled,
        ]);

        // Screening hanya untuk result pending: accepted bukan pekerjaan screening.
        $this->application($this->period, [
            'stage' => ApplicationStage::Screening,
            'result' => ApplicationResult::Accepted,
        ]);

        $this->application($this->period, ['stage' => ApplicationStage::Interview]);

        // Revision bisa menumpang di stage lain: dihitung di interview DAN revisi.
        $this->application($this->period, [
            'stage' => ApplicationStage::Interview,
            'revision_required' => true,
        ]);

        $this->application($this->period, ['stage' => ApplicationStage::FinalReview]);
        $this->application($this->period, ['stage' => ApplicationStage::Completed]);

        $otherApplication = $this->application($this->otherPeriod);

        $this->correction($screeningSubmitted, CorrectionRequestStatus::Pending);
        $this->correction($otherApplication, CorrectionRequestStatus::Pending);
        $this->correction($screeningStage, CorrectionRequestStatus::Approved);
    }

    public function test_period_counts_follow_queue_rules(): void
    {
        $this->seedQueues();

        $this->assertSame([
            'all' => 10,
            'screening' => 2,
            'revision' => 2,
            'interview' => 2,
            'final' => 1,
            'done' => 1,
            'corrections_pending' => 1,
        ], $this->service()->queueCounts($this->period->id));
    }

    public function test_null_and_empty_period_return_global_counts_without_period_filter(): void
    {
        $this->seedQueues();

        $expected = [
            'all' => 11,
            'screening' => 3,
            'revision' => 2,
            'interview' => 2,
            'final' => 1,
            'done' => 1,
            'corrections_pending' => 2,
        ];

        $this->assertSame($expected, $this->service()->queueCounts(null));
        $this->assertSame($expected, $this->service()->queueCounts(''));
    }

    public function test_period_without_applications_returns_zero_integers(): void
    {
        $this->seedQueues();
        $emptyPeriod = RecruitmentPeriod::factory()->create();

        $this->assertSame([
            'all' => 0,
            'screening' => 0,
            'revision' => 0,
            'interview' => 0,
            'final' => 0,
            'done' => 0,
            'corrections_pending' => 0,
        ], $this->service()->queueCounts($emptyPeriod->id));
    }

    public function test_counts_run_exactly_two_queries(): void
    {
        $this->seedQueues();

        $executed = [];
        DB::listen(static function (QueryExecuted $query) use (&$executed): void {
            $executed[] = $query->sql;
        });

        $this->service()->queueCounts($this->period->id);

        // Acceptance adalah <= 2 query; tepat 2 diassert agar query tambahan tidak lolos diam-diam.
        $this->assertCount(2, $executed);
        $this->assertStringContainsString('case when', strtolower($executed[0]));
        $this->assertStringContainsString('recruitment_applications', $executed[0]);
        $this->assertStringContainsString('recruitment_correction_requests', $executed[1]);
    }

    public function test_badge_counts_match_queue_filter_totals(): void
    {
        $this->seedQueues();
        $counts = $this->service()->queueCounts($this->period->id);

        // Kunci antrean diturunkan dari hasil, jadi aturan baru di QUEUE_RULES
        // otomatis ikut dijaga tanpa mengedit daftar di test ini.
        $queueKeys = array_keys(array_diff_key($counts, ['all' => 0, 'corrections_pending' => 0]));

        $this->assertNotEmpty($queueKeys);

        foreach ($queueKeys as $queue) {
            $total = $this->service()
                ->paginate(['period_id' => $this->period->id, 'queue' => $queue])
                ->total();

            $this->assertSame($counts[$queue], $total, "Badge {$queue} tidak sama dengan filter daftar.");
        }
    }

    /**
     * BE-P2-027/BE-P3-020: applicant batal bukan pekerjaan antrean. Badge dan
     * filter harus sama-sama mengabaikannya, sementara `all` (daftar tanpa
     * filter) tetap menghitungnya.
     */
    public function test_cancelled_applications_are_excluded_from_queues_but_not_from_all(): void
    {
        $active = $this->application($this->period);
        $cancelledScreening = $this->application($this->period, ['cancelled_at' => now()]);
        $cancelledInterview = $this->application($this->period, [
            'stage' => ApplicationStage::Interview,
            'cancelled_at' => now(),
        ]);

        $counts = $this->service()->queueCounts($this->period->id);

        $this->assertSame(3, $counts['all']);
        $this->assertSame(1, $counts['screening']);
        $this->assertSame(0, $counts['interview']);

        $screeningIds = $this->service()
            ->paginate(['period_id' => $this->period->id, 'queue' => 'screening'])
            ->getCollection()
            ->pluck('id')
            ->all();

        $interviewIds = $this->service()
            ->paginate(['period_id' => $this->period->id, 'queue' => 'interview'])
            ->getCollection()
            ->pluck('id')
            ->all();

        $this->assertSame([$active->id], $screeningIds);
        $this->assertNotContains($cancelledScreening->id, $screeningIds);
        $this->assertNotContains($cancelledInterview->id, $interviewIds);
        $this->assertContains($cancelledInterview->id, $this->service()
            ->paginate(['period_id' => $this->period->id])
            ->getCollection()
            ->pluck('id')
            ->all());
    }
}
