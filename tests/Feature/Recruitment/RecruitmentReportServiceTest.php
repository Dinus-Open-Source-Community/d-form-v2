<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentReportService;
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

    private function makeSession(RecruitmentPeriod $period, RecruitmentDivision $division): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    private function makeInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
        InterviewStatus $status,
        string $kind = RecruitmentInterview::KIND_PRIMARY,
    ): RecruitmentInterview {
        return RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => $kind,
            'interviewer_id' => null,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
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
