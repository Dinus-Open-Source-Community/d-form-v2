<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentInterviewExportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $dataDivision;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_export_all_resolves_relations_without_uuids(): void
    {
        $evaluated = $this->makeInterviewRow(fullName: 'Export Evaluated', withEvaluation: true);
        $pending = $this->makeInterviewRow(fullName: 'Export Pending', withEvaluation: false);
        $foreign = $this->makeForeignInterviewRow();

        $content = $this->exportContent();

        $lines = $this->csvLines($content);
        self::assertCount(3, $lines);

        $header = str_getcsv($lines[0]);
        self::assertCount(28, $header);
        self::assertSame('Nomor Pendaftaran', $header[0]);
        self::assertSame('Tipe Keanggotaan', $header[27]);

        self::assertStringContainsString('Export Evaluated', $content);
        self::assertStringContainsString('Export Pending', $content);
        self::assertStringNotContainsString($foreign->application->full_name, $content);

        // Label enum, bukan value mentah.
        self::assertStringContainsString('Menunggu interview', $content);
        self::assertStringContainsString(',Primer,', $content);
        self::assertStringContainsString('Direkomendasikan', $content);
        self::assertStringContainsString('Nomor pendaftaran', $content);
        self::assertStringContainsString($this->programming->name, $content);

        foreach ([$evaluated, $pending, $foreign] as $interview) {
            $interview->loadMissing(['application', 'session', 'interviewer', 'evaluation']);
            foreach ([
                $interview->id,
                $interview->application->id,
                $interview->recruitment_interview_session_id,
                (string) $interview->interviewer_id,
                $interview->evaluation?->id,
            ] as $uuid) {
                if ($uuid !== null && $uuid !== '') {
                    self::assertStringNotContainsString($uuid, $content);
                }
            }
        }

        foreach ([$this->programming->id, $this->staff->id] as $uuid) {
            self::assertStringNotContainsString($uuid, $content);
        }
    }

    public function test_export_scope_filters_by_evaluation(): void
    {
        $this->makeInterviewRow(fullName: 'Export Evaluated', withEvaluation: true);
        $this->makeInterviewRow(fullName: 'Export Pending', withEvaluation: false);

        $evaluated = $this->exportContent(['scope' => 'evaluated']);
        self::assertStringContainsString('Export Evaluated', $evaluated);
        self::assertStringNotContainsString('Export Pending', $evaluated);

        $pending = $this->exportContent(['scope' => 'pending']);
        self::assertStringContainsString('Export Pending', $pending);
        self::assertStringNotContainsString('Export Evaluated', $pending);
    }

    public function test_export_include_secondary_false_hides_secondary(): void
    {
        $app = $this->makeApplicant('Export Secondary', secondaryDivisionId: $this->dataDivision->id);

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $app->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => $this->staff->id,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Completed,
        ]);

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $app->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'interviewer_id' => $this->staff->id,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::InProgress,
        ]);

        $all = $this->exportContent();
        self::assertStringContainsString(',Sekunder,', $all);

        $primaryOnly = $this->exportContent(['include_secondary' => false]);
        self::assertStringNotContainsString(',Sekunder,', $primaryOnly);
        self::assertStringContainsString(',Primer,', $primaryOnly);
    }

    public function test_export_column_subset_keeps_requested_order(): void
    {
        $this->makeInterviewRow(fullName: 'Export Columns', withEvaluation: false);

        $content = $this->exportContent(['columns' => ['full_name', 'registration_number', 'interview_status']]);
        $lines = $this->csvLines($content);

        self::assertCount(2, $lines);
        self::assertSame(['Nama Lengkap', 'Nomor Pendaftaran', 'Status Interview'], str_getcsv($lines[0]));

        $row = str_getcsv($lines[1]);
        self::assertSame('Export Columns', $row[0]);
        self::assertSame('Menunggu interview', $row[2]);
    }

    public function test_export_sanitizes_formula_cells(): void
    {
        $this->makeInterviewRow(fullName: '=CMD|calc.exe', withEvaluation: false);

        $content = $this->exportContent(['columns' => ['full_name']]);
        $lines = $this->csvLines($content);

        self::assertSame(["'".'=CMD|calc.exe'], str_getcsv($lines[1]));
    }

    public function test_export_forbidden_without_schedule_permission(): void
    {
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.periods.interviews.export', $this->period))
            ->assertForbidden();
    }

    public function test_export_uses_period_slug_filename(): void
    {
        $this->makeInterviewRow(fullName: 'Export Filename', withEvaluation: false);

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.interviews.export', $this->period))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=interview-'.$this->period->slug.'-'.now()->format('Ymd').'.csv');
    }

    public function test_export_query_count_does_not_scale_with_rows(): void
    {
        $this->makeInterviewRow(fullName: 'Export N1', withEvaluation: true);
        $this->makeInterviewRow(fullName: 'Export N2', withEvaluation: false);

        $twoRows = $this->countExportQueries();

        $this->makeInterviewRow(fullName: 'Export N3', withEvaluation: true);
        $this->makeInterviewRow(fullName: 'Export N4', withEvaluation: false);
        $this->makeInterviewRow(fullName: 'Export N5', withEvaluation: false);
        $this->makeInterviewRow(fullName: 'Export N6', withEvaluation: false);

        $sixRows = $this->countExportQueries();

        self::assertSame($twoRows, $sixRows);
    }

    /**
     * @return list<string>
     */
    private function csvLines(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        return array_values(array_filter(
            array_map(fn (string $line): string => trim($line), explode("\n", trim($content))),
            fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function exportContent(array $query = []): string
    {
        $response = $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.interviews.export', [$this->period, ...$query]));

        $response->assertOk();

        $content = $response->streamedContent();

        self::assertIsString($content);

        return $content;
    }

    private function countExportQueries(): int
    {
        DB::enableQueryLog();

        try {
            $this->actingAs($this->staff)
                ->get(route('dashboard.recruitment.periods.interviews.export', $this->period))
                ->assertOk()
                ->streamedContent();

            $count = 0;

            foreach (DB::getQueryLog() as $entry) {
                $sql = (string) ($entry['query'] ?? '');

                if (str_contains($sql, 'recruitment_') || str_contains($sql, 'users')) {
                    $count++;
                }
            }

            return $count;
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function makeApplicant(string $fullName, ?string $secondaryDivisionId = null): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => $secondaryDivisionId,
            'full_name' => $fullName,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    private function makeInterviewRow(string $fullName, bool $withEvaluation): RecruitmentInterview
    {
        $app = $this->makeApplicant($fullName);

        if ($withEvaluation) {
            RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $app->id,
                'recruitment_interview_session_id' => $this->session->id,
                'method' => AttendanceMethod::RegistrationNumber,
                'checked_in_at' => now(),
                'checked_in_by' => $this->staff->id,
            ]);
        }

        $interview = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $app->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => $this->staff->id,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => $withEvaluation ? InterviewStatus::Completed : InterviewStatus::Waiting,
        ]);

        if ($withEvaluation) {
            RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $app->id,
                'recruitment_interview_id' => $interview->id,
                'speaking_score' => 8,
                'technical_score' => 9,
                'attitude_score' => 8,
                'recommendation' => EvaluationRecommendation::Recommended,
                'notes' => 'Catatan evaluasi export.',
                'save_count' => 1,
                'evaluated_by' => $this->staff->id,
                'evaluated_at' => now(),
            ]);
        }

        return $interview;
    }

    private function makeForeignInterviewRow(): RecruitmentInterview
    {
        $otherPeriod = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $app = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $otherPeriod->id,
            'primary_division_id' => $this->programming->id,
            'full_name' => 'Export Foreign',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        return RecruitmentInterview::query()->create([
            'recruitment_application_id' => $app->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => $this->staff->id,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);
    }
}
