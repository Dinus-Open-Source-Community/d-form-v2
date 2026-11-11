<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecruitmentPeriodPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_sec_p3_013_null_creator_does_not_grant_view_to_staff_or_interviewer(): void
    {
        $period = RecruitmentPeriod::factory()->create(['created_by' => null]);

        foreach (['recruitment-staff', 'recruitment-interviewer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            self::assertTrue($user->can('recruitment.dashboard.view'));
            self::assertFalse($user->can('recruitment.periods.view'));
            self::assertFalse($user->can('view', $period));

            $this->actingAs($user)
                ->get(route('dashboard.recruitment.periods.show', $period))
                ->assertForbidden();
        }
    }

    public function test_sec_p3_013_null_creator_qr_status_denies_staff_and_interviewer(): void
    {
        $period = RecruitmentPeriod::factory()->create(['created_by' => null]);

        foreach (['recruitment-staff', 'recruitment-interviewer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)
                ->getJson(route('dashboard.recruitment.periods.qr-status', $period))
                ->assertForbidden();
        }
    }

    public function test_sec_p3_013_owner_and_super_admin_can_view_period(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('recruitment-staff');
        self::assertFalse($owner->can('recruitment.periods.view'));
        $ownedPeriod = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);

        self::assertTrue($owner->can('view', $ownedPeriod));

        $this->actingAs($owner)
            ->get(route('dashboard.recruitment.periods.show', $ownedPeriod))
            ->assertOk();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $periodWithoutCreator = RecruitmentPeriod::factory()->create(['created_by' => null]);
        self::assertTrue($superAdmin->can('view', $periodWithoutCreator));

        $this->actingAs($superAdmin)
            ->get(route('dashboard.recruitment.periods.show', $periodWithoutCreator))
            ->assertOk();
    }

    public function test_sec_p3_013_owner_and_super_admin_keep_qr_status_access(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('recruitment-staff');
        $ownedPeriod = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($owner)
            ->getJson(route('dashboard.recruitment.periods.qr-status', $ownedPeriod))
            ->assertOk();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->getJson(route('dashboard.recruitment.periods.qr-status', RecruitmentPeriod::factory()->create([
                'created_by' => null,
            ])))
            ->assertOk();
    }

    public function test_sec_p3_013_qr_status_recipients_never_include_another_period(): void
    {
        $this->seed(RecruitmentDivisionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create(['created_by' => null]);
        $foreignPeriod = RecruitmentPeriod::factory()->create(['created_by' => null]);

        $ownRecipient = $this->applicationWithSentQr($period->id, $division->id, 'OWN-0001');
        $foreignRecipient = $this->applicationWithSentQr($foreignPeriod->id, $division->id, 'FOREIGN-0001');

        $response = $this->actingAs($admin)
            ->getJson(route('dashboard.recruitment.periods.qr-status', $period))
            ->assertOk()
            ->assertJsonPath('sent', 1)
            ->assertJsonCount(1, 'recipients')
            ->assertJsonPath('recipients.0.registration_number', 'OWN-0001');

        $payload = (string) $response->getContent();
        self::assertStringContainsString($ownRecipient->registration_number, $payload);
        self::assertStringNotContainsString($foreignRecipient->registration_number, $payload);
        self::assertStringNotContainsString($foreignRecipient->full_name, $payload);
    }

    public function test_sec_p3_013_unknown_and_soft_deleted_periods_return_not_found(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $unknown = (string) Str::uuid();
        $this->get(route('dashboard.recruitment.periods.show', $unknown))->assertNotFound();
        $this->getJson(route('dashboard.recruitment.periods.qr-status', $unknown))->assertNotFound();

        $deleted = RecruitmentPeriod::factory()->create(['created_by' => null]);
        $deleted->delete();

        $this->get(route('dashboard.recruitment.periods.show', $deleted->id))->assertNotFound();
        $this->getJson(route('dashboard.recruitment.periods.qr-status', $deleted->id))->assertNotFound();
    }

    private function applicationWithSentQr(string $periodId, string $divisionId, string $registrationNumber): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $periodId,
            'primary_division_id' => $divisionId,
            'stage' => ApplicationStage::Interview,
            'registration_number' => $registrationNumber,
        ]);

        EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'sent_at' => now(),
        ]);

        return $application;
    }
}
