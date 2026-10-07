<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class RecruitmentInterviewSessionUpdateTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $division;

    private RecruitmentInterviewSession $session;

    private User $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->scheduler = User::factory()->create();
        $this->scheduler->assignRole('admin');
        $this->scheduler->givePermissionTo(['recruitment.periods.view', 'recruitment.interviews.schedule']);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Gedung A',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    /**
     * Regis ulang applicant sehingga baris interview Waiting terbentuk
     * (scheduled_at/lokasi/ruang disalin dari sesi via InterviewLifecycleService).
     */
    private function checkedInInterview(string $suffix = '1'): RecruitmentInterview
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->division->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->scheduler);

        return RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->addDays(2)->toDateString(),
            'starts_at' => '14:00',
            'ends_at' => '17:00',
            'location' => 'Gedung B',
            'room' => 'B202',
        ], $overrides);
    }

    public function test_update_menyinkron_scheduled_at_lokasi_dan_ruang_interview(): void
    {
        $interview = $this->checkedInInterview();
        $oldScheduledAt = $interview->scheduled_at?->copy();

        $payload = $this->updatePayload();

        $this->actingAs($this->scheduler)
            ->put(route('dashboard.recruitment.interview-sessions.update', $this->session), $payload)
            ->assertRedirect(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]));

        $expected = Carbon::parse(
            $payload['session_date'].' '.$payload['starts_at'],
            config('app.timezone'),
        );

        $fresh = $interview->fresh();

        $this->assertNotEquals(
            $oldScheduledAt?->toIso8601String(),
            $fresh->scheduled_at?->toIso8601String(),
        );
        $this->assertTrue($fresh->scheduled_at->equalTo($expected));
        $this->assertSame('Gedung B', $fresh->location);
        $this->assertSame('B202', $fresh->room);
        $this->assertSame(InterviewStatus::Waiting, $fresh->status);
    }

    public function test_update_tidak_mereset_status_dan_reminder_interview(): void
    {
        $interview = $this->checkedInInterview();
        $interview->update([
            'status' => InterviewStatus::Completed,
            'reminder_h1_sent_at' => now()->subHour(),
            'reminder_h2_sent_at' => now()->subHour(),
        ]);

        $this->actingAs($this->scheduler)
            ->put(route('dashboard.recruitment.interview-sessions.update', $this->session), $this->updatePayload())
            ->assertRedirect();

        $fresh = $interview->fresh();

        $this->assertSame(InterviewStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->reminder_h1_sent_at);
        $this->assertNotNull($fresh->reminder_h2_sent_at);
        $this->assertSame('Gedung B', $fresh->location);
    }

    public function test_update_melewati_interview_cancelled(): void
    {
        $interview = $this->checkedInInterview();
        $interview->update(['status' => InterviewStatus::Cancelled]);

        $oldScheduledAt = $interview->scheduled_at?->copy();

        $this->actingAs($this->scheduler)
            ->put(route('dashboard.recruitment.interview-sessions.update', $this->session), $this->updatePayload())
            ->assertRedirect();

        $fresh = $interview->fresh();

        $this->assertSame(InterviewStatus::Cancelled, $fresh->status);
        $this->assertTrue($fresh->scheduled_at->equalTo($oldScheduledAt));
        $this->assertSame('Gedung A', $fresh->location);
        $this->assertSame('A101', $fresh->room);
    }

    public function test_update_tanpa_perubahan_jadwal_tidak_menyentuh_interview(): void
    {
        $interview = $this->checkedInInterview();

        RecruitmentInterview::query()
            ->where('id', $interview->id)
            ->update(['updated_at' => now()->subHour()]);

        $staleUpdatedAt = $interview->fresh()->updated_at;

        $this->actingAs($this->scheduler)
            ->put(route('dashboard.recruitment.interview-sessions.update', $this->session), [
                'recruitment_division_id' => $this->division->id,
                'session_date' => $this->session->session_date->toDateString(),
                'starts_at' => '09:00',
                'ends_at' => '12:00',
                'location' => 'Gedung A',
                'room' => 'A101',
                'notes' => 'Catatan baru tanpa ubah jadwal.',
            ])
            ->assertRedirect();

        $this->assertTrue($interview->fresh()->updated_at->equalTo($staleUpdatedAt));
    }
}
