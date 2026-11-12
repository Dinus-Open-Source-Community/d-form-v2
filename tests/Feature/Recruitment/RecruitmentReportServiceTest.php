<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentReportService;
use Database\Factories\Recruitment\RecruitmentInterviewFactory;
use Database\Factories\Recruitment\RecruitmentInterviewSessionFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $division;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->division = RecruitmentDivision::factory()->create();
        $this->session = $this->makeSession($this->period, $this->division);
    }

    public function test_interview_stats_grouped_statuses_with_interviews(): void
    {
        $statuses = [
            InterviewStatus::Waiting,
            InterviewStatus::InProgress,
            InterviewStatus::Completed,
            InterviewStatus::Cancelled,
        ];

        foreach ($statuses as $status) {
            $application = $this->makeApplication($this->period, $this->division);
            $this->makeInterview($application, $this->session, $status);
        }

        $this->makeApplication($this->period, $this->division);
        $this->makeApplication($this->period, $this->division);

        $otherPeriod = RecruitmentPeriod::factory()->create();
        $otherSession = $this->makeSession($otherPeriod, $this->division);
        $otherApplication = $this->makeApplication($otherPeriod, $this->division);
        $this->makeInterview($otherApplication, $otherSession, InterviewStatus::Waiting);

        $stats = app(RecruitmentReportService::class)->build($this->period->id)['interview_stats'];

        $this->assertSame(1, $stats['waiting']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(4, $stats['total']);
    }

    public function test_interview_stats_count_primary_and_secondary_rows(): void
    {
        $application = $this->makeApplication($this->period, $this->division);

        $this->makeInterview($application, $this->session, InterviewStatus::Waiting);
        $this->makeInterview($application, $this->session, InterviewStatus::Completed, RecruitmentInterview::KIND_SECONDARY);

        $stats = app(RecruitmentReportService::class)->build($this->period->id)['interview_stats'];

        $this->assertSame(1, $stats['waiting']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(2, $stats['total']);
    }

    public function test_interview_stats_empty_period_returns_zeroes(): void
    {
        $stats = app(RecruitmentReportService::class)->build($this->period->id)['interview_stats'];

        $this->assertSame([
            'waiting' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total' => 0,
        ], $stats);
    }

    public function test_interview_stats_total_includes_unknown_status(): void
    {
        $application = $this->makeApplication($this->period, $this->division);
        $interview = $this->makeInterview($application, $this->session, InterviewStatus::Waiting);

        DB::table('recruitment_interviews')
            ->where('id', $interview->id)
            ->update(['status' => 'no_show']);

        $stats = app(RecruitmentReportService::class)->build($this->period->id)['interview_stats'];

        $this->assertSame([
            'waiting' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total' => 1,
        ], $stats);
        $this->assertSame(
            ['waiting', 'in_progress', 'completed', 'cancelled', 'total'],
            array_keys($stats)
        );
    }

    public function test_interview_stats_stays_single_statement_with_one_binding(): void
    {
        $application = $this->makeApplication($this->period, $this->division);
        $this->makeInterview($application, $this->session, InterviewStatus::Waiting);

        /** @var list<array{sql: string, bindings: array<int, mixed>}> $captured */
        $captured = [];

        DB::listen(function (QueryExecuted $query) use (&$captured): void {
            $captured[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
            ];
        });

        app(RecruitmentReportService::class)->build($this->period->id);

        $small = $this->interviewStatements($captured);

        $this->assertCount(1, $small);
        $this->assertStringContainsString('group by', strtolower($small[0]['sql']));
        $this->assertCount(1, $small[0]['bindings']);

        // Guard against re-introducing pluck('id'): every SELECT from
        // recruitment_applications must be an aggregate (COUNT). The interview
        // statement's subquery is excluded because it also references
        // recruitment_interviews; its shape is asserted separately above.
        $applicationSelects = array_values(array_filter(
            $captured,
            static function (array $statement): bool {
                $sql = str_replace(['`', '"'], '', strtolower($statement['sql']));

                return str_starts_with(ltrim($sql), 'select')
                    && str_contains($sql, 'from recruitment_applications')
                    && ! str_contains($sql, 'recruitment_interviews');
            }
        ));

        $this->assertNotEmpty($applicationSelects);

        foreach ($applicationSelects as $statement) {
            $this->assertStringContainsString('count(', strtolower($statement['sql']));
        }

        for ($index = 0; $index < 200; $index++) {
            $application = $this->makeApplication($this->period, $this->division);
            $this->makeInterview($application, $this->session, InterviewStatus::Waiting);
        }

        $captured = [];

        app(RecruitmentReportService::class)->build($this->period->id);

        $large = $this->interviewStatements($captured);

        $this->assertCount(1, $large);
        $this->assertCount(1, $large[0]['bindings']);
    }

    public function test_feedback_stats_hanya_memakai_satu_query_agregat(): void
    {
        $this->makeFeedback($this->period, $this->division, [5, 4, 3, 2, 1]);
        $this->makeFeedback($this->period, $this->division, [4, 4, 4, 4, 4]);

        /** @var list<array{sql: string, bindings: array<int, mixed>}> $captured */
        $captured = [];

        DB::listen(function (QueryExecuted $query) use (&$captured): void {
            $captured[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
            ];
        });

        $feedback = app(RecruitmentReportService::class)->build($this->period->id)['feedback'];

        $statements = array_values(array_filter(
            $captured,
            static fn (array $statement): bool => str_contains(strtolower($statement['sql']), 'recruitment_feedbacks')
        ));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('count(', strtolower($statements[0]['sql']));
        $this->assertSame(5, substr_count(strtolower($statements[0]['sql']), 'avg('));
        $this->assertSame(2, $feedback['count']);
    }

    public function test_feedback_stats_sama_dengan_perhitungan_lama(): void
    {
        $ratings = [
            [5, 5, 5, 5, 5],
            [4, 3, 2, 1, 5],
            [3, 1, 4, 2, 3],
            [2, 2, 2, 2, 2],
        ];

        foreach ($ratings as $row) {
            $this->makeFeedback($this->period, $this->division, $row);
        }

        $otherPeriod = RecruitmentPeriod::factory()->create();
        $this->makeFeedback($otherPeriod, $this->division, [1, 1, 1, 1, 1]);

        $feedback = app(RecruitmentReportService::class)->build($this->period->id)['feedback'];

        $this->assertSame([
            'count' => count($ratings),
            'averages' => [
                'registration_ease' => round((5 + 4 + 3 + 2) / 4, 2),
                'info_clarity' => round((5 + 3 + 1 + 2) / 4, 2),
                'tracking_ease' => round((5 + 2 + 4 + 2) / 4, 2),
                'interview_experience' => round((5 + 1 + 2 + 2) / 4, 2),
                'staff_service' => round((5 + 5 + 3 + 2) / 4, 2),
            ],
        ], $feedback);
    }

    public function test_feedback_stats_periode_kosong_mengembalikan_average_null(): void
    {
        $feedback = app(RecruitmentReportService::class)->build($this->period->id)['feedback'];

        $this->assertSame([
            'count' => 0,
            'averages' => [
                'registration_ease' => null,
                'info_clarity' => null,
                'tracking_ease' => null,
                'interview_experience' => null,
                'staff_service' => null,
            ],
        ], $feedback);
    }

    public function test_build_returns_report_contract(): void
    {
        $report = app(RecruitmentReportService::class)->build($this->period->id);

        $this->assertSame(
            ['period', 'funnel', 'by_division', 'by_semester', 'interview_stats', 'feedback'],
            array_keys($report)
        );

        $this->assertSame(
            ['waiting', 'in_progress', 'completed', 'cancelled', 'total'],
            array_keys($report['interview_stats'])
        );
    }

    private function makeApplication(RecruitmentPeriod $period, RecruitmentDivision $division): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
        ]);
    }

    /**
     * @param  list<int>  $ratings  [registration_ease, info_clarity, tracking_ease, interview_experience, staff_service]
     */
    private function makeFeedback(RecruitmentPeriod $period, RecruitmentDivision $division, array $ratings): RecruitmentFeedback
    {
        $application = $this->makeApplication($period, $division);

        return RecruitmentFeedback::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_period_id' => $period->id,
            'rating_registration_ease' => $ratings[0],
            'rating_info_clarity' => $ratings[1],
            'rating_tracking_ease' => $ratings[2],
            'rating_interview_experience' => $ratings[3],
            'rating_staff_service' => $ratings[4],
            'submitted_at' => now(),
        ]);
    }

    private function makeSession(RecruitmentPeriod $period, RecruitmentDivision $division): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSessionFactory::new()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $division->id,
        ]);
    }

    private function makeInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
        InterviewStatus $status,
        string $kind = RecruitmentInterview::KIND_PRIMARY,
    ): RecruitmentInterview {
        return RecruitmentInterviewFactory::new()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => $kind,
            'status' => $status,
        ]);
    }

    /**
     * @param  list<array{sql: string, bindings: array<int, mixed>}>  $captured
     * @return list<array{sql: string, bindings: array<int, mixed>}>
     */
    private function interviewStatements(array $captured): array
    {
        return array_values(array_filter(
            $captured,
            static function (array $statement): bool {
                $sql = strtolower($statement['sql']);

                return str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'recruitment_interviews');
            }
        ));
    }
}
