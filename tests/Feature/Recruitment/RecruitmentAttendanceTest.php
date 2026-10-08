<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\AttendanceService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class RecruitmentAttendanceTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $this->session = RecruitmentInterviewSession::query()->create([
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

    private function eligibleApplication(string $suffix): RecruitmentApplication
    {
        return $this->applicationReadyForInterview($this->session, [
            'registration_number' => 'OPREC-2026-AT'.$suffix,
        ]);
    }

    public function test_staff_check_in_via_registration_number_records_attendance(): void
    {
        $application = $this->eligibleApplication('001');

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
                'recruitment_session_id' => $this->session->id,
            ])
            ->assertOk()
            ->assertJsonPath('attendee.registration_number', $application->registration_number);

        $this->assertDatabaseHas('recruitment_attendances', [
            'recruitment_application_id' => $application->id,
        ]);

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'status' => InterviewStatus::Waiting->value,
            'interviewer_id' => null,
        ]);
    }

    public function test_check_in_preserves_existing_interviewer_assignment(): void
    {
        $application = $this->eligibleApplication('010');
        $owner = User::factory()->create();

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => $owner->id,
            'booked_at' => now()->subHour(),
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interviewer_id' => $owner->id,
            'status' => InterviewStatus::Waiting->value,
        ]);
    }

    public function test_duplicate_check_in_is_idempotent(): void
    {
        $application = $this->eligibleApplication('002');

        $this->checkInApplicant($this->session, $application, $this->staff);

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
                'recruitment_session_id' => $this->session->id,
            ])
            ->assertStatus(409);
    }

    public function test_check_in_losing_a_unique_race_returns_duplicate_not_error(): void
    {
        $application = $this->eligibleApplication('003');

        DB::transaction(function () use ($application): void {
            DB::table('recruitment_attendances')->insert([
                'id' => (string) str()->uuid(),
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $this->session->id,
                'method' => 'registration_number',
                'checked_in_at' => now(),
            ]);

            $result = app(AttendanceService::class)->checkInFromInput(
                $this->session,
                $application->registration_number,
                null,
                null,
                $this->staff,
            );

            $this->assertTrue($result['duplicate']);
        });
    }
}
