<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Jobs\Recruitment\SendRecruitmentCorrectionRequestStaffJob;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RecruitmentEmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentRevisionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'revision-correction-token-1234567890';

    private RecruitmentApplication $application;

    private RecruitmentDivision $programming;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        $this->seed(RecruitmentEmailTemplateSeeder::class);

        Storage::fake('local');
        Queue::fake();

        $period = RecruitmentPeriod::factory()->create();
        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->application = RecruitmentApplication::factory()
            ->for($period, 'period')
            ->withTrackingToken(self::TRACKING_TOKEN)
            ->create([
                'registration_number' => 'OPREC-2026-00099',
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Screening,
                'revision_required' => true,
                'is_verified' => false,
            ]);

        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $this->application->id,
            'cv_path' => 'recruitment/'.$period->id.'/'.$this->application->id.'/cv.pdf',
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1000,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/original',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Budi Revisi',
            'nim' => $this->application->nim,
            'semester' => 2,
            'phone' => '081234567890',
            'personal_email' => 'budi@gmail.com',
            'student_email' => 'budi@students.udinus.ac.id',
            'instagram_username' => 'budirevisi',
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => null,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/revised',
        ], $overrides);
    }

    private function authenticateTracking(): void
    {
        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ])->assertRedirect(route('open-recruitment.track.show'));
    }

    public function test_applicant_edit_during_revision_window_updates_data_for_rescreening(): void
    {
        $this->authenticateTracking();

        $this->put(route('open-recruitment.track.update'), $this->editPayload())
            ->assertRedirect(route('open-recruitment.track.show'));

        $this->application->refresh();
        $this->assertSame('Budi Revisi', $this->application->full_name);
        $this->assertSame(ApplicationStage::Submitted, $this->application->stage);
        $this->assertFalse($this->application->revision_required);

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'application.updated',
            'actor_type' => 'applicant',
        ]);
    }

    public function test_applicant_edit_after_verified_is_blocked(): void
    {
        $this->application->update([
            'is_verified' => true,
            'revision_required' => false,
            'verified_at' => now(),
        ]);

        $this->authenticateTracking();

        $this->get(route('open-recruitment.track.edit'))
            ->assertRedirect(route('open-recruitment.track.show'));

        $this->put(route('open-recruitment.track.update'), $this->editPayload())
            ->assertSessionHasErrors('application');
    }

    public function test_correction_request_approve_edit_flow_completes(): void
    {
        $this->application->update([
            'is_verified' => true,
            'revision_required' => false,
            'verified_at' => now(),
        ]);

        $this->authenticateTracking();

        $this->post(route('open-recruitment.track.correction'), [
            'request_message' => 'NIM saya salah ketik, mohon izin koreksi.',
        ])->assertRedirect(route('open-recruitment.track.show'));

        Queue::assertPushed(SendRecruitmentCorrectionRequestStaffJob::class);

        $correction = RecruitmentCorrectionRequest::query()->firstOrFail();
        $this->assertSame(CorrectionRequestStatus::Pending, $correction->status);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.corrections.approve', $correction))
            ->assertRedirect(route('dashboard.recruitment.applications.show', $this->application));

        $correction->refresh();
        $this->assertSame(CorrectionRequestStatus::Approved, $correction->status);

        $this->put(route('open-recruitment.track.update'), $this->editPayload(['full_name' => 'Budi Koreksi']))
            ->assertRedirect(route('open-recruitment.track.show'));

        $correction->refresh();
        $this->application->refresh();

        $this->assertSame(CorrectionRequestStatus::Completed, $correction->status);
        $this->assertFalse($this->application->is_verified);
        $this->assertSame('Budi Koreksi', $this->application->full_name);
    }

    public function test_correction_request_reject_blocks_edit(): void
    {
        $this->application->update([
            'is_verified' => true,
            'revision_required' => false,
            'verified_at' => now(),
        ]);

        $this->authenticateTracking();

        $this->post(route('open-recruitment.track.correction'), [
            'request_message' => 'Ingin ganti divisi pilihan saya.',
        ]);

        $correction = RecruitmentCorrectionRequest::query()->firstOrFail();

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.corrections.reject', $correction), [
                'review_notes' => 'Divisi tidak dapat diubah.',
            ])
            ->assertRedirect(route('dashboard.recruitment.applications.show', $this->application));

        $correction->refresh();
        $this->assertSame(CorrectionRequestStatus::Rejected, $correction->status);

        $this->put(route('open-recruitment.track.update'), $this->editPayload())
            ->assertSessionHasErrors('application');
    }

    public function test_all_edits_are_logged_in_activity_trail(): void
    {
        $this->authenticateTracking();

        $this->put(route('open-recruitment.track.update'), $this->editPayload());

        $this->assertSame(
            1,
            RecruitmentActivityLog::query()
                ->where('recruitment_application_id', $this->application->id)
                ->where('action', 'application.updated')
                ->count(),
        );

        $log = RecruitmentActivityLog::query()
            ->where('action', 'application.updated')
            ->firstOrFail();

        $this->assertSame('applicant', $log->actor_type);
        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertSame('Budi Revisi', $log->new_values['full_name'] ?? null);
    }

    public function test_staff_can_verify_application_after_correction_resubmit(): void
    {
        $this->application->update([
            'stage' => ApplicationStage::Submitted,
            'revision_required' => false,
        ]);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.verify', $this->application))
            ->assertRedirect(route('dashboard.recruitment.applications.show', $this->application));

        $this->application->refresh();
        $this->assertTrue($this->application->is_verified);

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'application.verified',
            'actor_id' => $staff->id,
        ]);
    }
}
