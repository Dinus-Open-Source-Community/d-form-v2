<?php

namespace Tests\Feature\Scan;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

class GlobalScanExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    /**
     * @return array{0: Event, 1: User}
     */
    private function eventWithAttendance(): array
    {
        $admin = $this->admin();

        $event = Event::factory()->create(['title' => 'Global Export Event']);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration Form',
        ]);

        $participant = User::factory()->create([
            'name' => 'Attendee One',
            'email' => 'attendee1@example.test',
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-EXP-001',
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now(),
        ]);

        return [$event, $admin];
    }

    /**
     * @return array{0: User, 1: RecruitmentApplication, 2: RecruitmentInterviewSession}
     */
    private function scheduledRecruitmentApplication(string $suffix): array
    {
        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $programming->id,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $staff,
            $session,
            [$application->id],
        );

        return [$staff, $application->fresh(), $session];
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertUnauthorized();
    }

    public function test_member_is_forbidden(): void
    {
        $this->actingAs($this->member())->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_event_without_update_permission_is_forbidden(): void
    {
        [$event] = $this->eventWithAttendance();

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_oprec_without_scan_permission_is_forbidden(): void
    {
        [, , $session] = $this->scheduledRecruitmentApplication('F');

        $eventsOnly = User::factory()->create();
        $eventsOnly->givePermissionTo('events.list');

        $this->actingAs($eventsOnly)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_unknown_event_target_returns_not_found(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertNotFound();
    }

    public function test_unknown_oprec_target_returns_not_found(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertNotFound();
    }

    public function test_event_csv_export_contains_expected_columns_and_values(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$event, $admin] = $this->eventWithAttendance();

        $response = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('attendance-'.$event->slug.'-20260915-103000.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(
            'scanned_at,attendee_name,attendee_email,form_title,registration_code,scanned_by_name,scanned_by_email',
            $content,
        );
        $this->assertStringContainsString('Attendee One', $content);
        $this->assertStringContainsString('attendee1@example.test', $content);
        $this->assertStringContainsString('Registration Form', $content);
        $this->assertStringContainsString('CHK-EXP-001', $content);
        $this->assertStringContainsString($admin->email, $content);

        Carbon::setTestNow();
    }

    public function test_event_xlsx_export_is_a_valid_xlsx_workbook(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$event, $admin] = $this->eventWithAttendance();

        $response = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'xlsx',
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->assertDownload('attendance-'.$event->slug.'-20260915-103000.xlsx');

        $content = $response->streamedContent();

        $this->assertSame('PK', substr($content, 0, 2), 'XLSX payload must be a zip (PK header), not CSV.');
        $this->assertGreaterThan(100, strlen($content));
        $this->assertStringNotContainsString('attendee_name', $content);

        $header = $this->readXlsxHeaderRow($content);

        $this->assertSame([
            'scanned_at',
            'attendee_name',
            'attendee_email',
            'form_title',
            'registration_code',
            'scanned_by_name',
            'scanned_by_email',
        ], $header);

        Carbon::setTestNow();
    }

    public function test_oprec_csv_export_contains_expected_columns_and_values(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$staff, $application, $session] = $this->scheduledRecruitmentApplication('1');

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), [
            'raw' => $application->registration_number,
        ])->assertOk();

        $response = $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $divisionSlug = Str::slug($session->division->name);
        $response->assertDownload('attendance-oprec-'.$divisionSlug.'-2026-09-15-20260915-103000.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(
            'checked_in_at,applicant_name,registration_number,nim,division,session_date,method,checked_in_by',
            $content,
        );
        $this->assertStringContainsString($application->registration_number, $content);
        $this->assertStringContainsString($application->full_name, $content);
        $this->assertStringContainsString($application->nim, $content);
        $this->assertStringContainsString($session->division->name, $content);
        $this->assertStringContainsString('2026-09-15', $content);
        $this->assertStringContainsString(',qr,', $content);

        Carbon::setTestNow();
    }

    /**
     * @return list<mixed>
     */
    private function readXlsxHeaderRow(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-export-xlsx-');

        try {
            file_put_contents($path, $binary);

            $reader = new XlsxReader();
            $reader->open($path);

            $header = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $header = array_map(static fn ($cell): mixed => $cell->getValue(), $row->getCells());
                    break;
                }
                break;
            }

            $reader->close();

            return $header;
        } finally {
            @unlink($path);
        }
    }
}
