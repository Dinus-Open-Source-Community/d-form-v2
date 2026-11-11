<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\ScreeningReason;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentActivityLogger;
use App\Services\Recruitment\ScreeningService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentActivityLogProjectionTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentApplication $application;

    private RecruitmentPeriod $period;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->open()->create();
        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo([
            'recruitment.activity.view',
            'recruitment.periods.view',
            'recruitment.applications.list',
        ]);
    }

    public function test_projection_keeps_audit_fields_and_drops_every_pii_key(): void
    {
        $projected = RecruitmentActivityLogger::projectValues([
            'stage' => 'completed',
            'result' => 'accepted',
            'final_division_name' => 'Programming',
            'revision_required' => false,
            'save_count' => 3,
            'notes_present' => true,
            'personal_email' => 'personal@example.test',
            'student_email' => 'student@example.test',
            'recipient_email' => 'recipient@example.test',
            'nim' => 'EP02-NIM-482915',
            'phone' => '+62 812-3456-7890',
            'full_name' => 'Synthetic Person',
            'notes' => 'note with name',
            'review_notes' => 'review with phone',
            'request_message' => 'correction message',
            'sections' => ['identity'],
        ]);

        self::assertSame([
            'stage' => 'completed',
            'result' => 'accepted',
            'final_division_name' => 'Programming',
            'revision_required' => false,
            'save_count' => 3,
            'notes_present' => true,
        ], $projected);
    }

    public function test_dropped_activity_value_is_reported_by_key_only(): void
    {
        Log::spy();

        $log = app(RecruitmentActivityLogger::class)->log(
            action: 'activity.drop',
            actor: $this->admin,
            application: $this->application,
            newValues: [
                'stage' => 'screening',
                'personal_email' => 'leaked.value@example.test',
            ],
        );

        self::assertSame(['stage' => 'screening'], $log->new_values);

        Log::shouldHaveReceived('debug')->once()->withArgs(
            static function (string $event, array $context = []): bool {
                $serialized = json_encode($context, JSON_THROW_ON_ERROR);

                return $event === 'activity_value_dropped'
                    && ($context['action'] ?? null) === 'activity.drop'
                    && ($context['key'] ?? null) === 'personal_email'
                    && array_keys($context) === ['action', 'key']
                    && ! str_contains($serialized, 'leaked.value@example.test');
            },
        );
    }

    public function test_revision_required_records_that_notes_were_supplied_without_storing_them(): void
    {
        Queue::fake();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->application->primary_division_id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        app(ScreeningService::class)->requireRevision(
            $this->admin,
            $application,
            ScreeningReason::IncompleteData,
            'Lengkapi CV dan portofolio.',
            null,
            ['data_diri'],
        );

        $activity = RecruitmentActivityLog::query()
            ->where('action', 'screening.revision_required')
            ->firstOrFail();

        self::assertTrue($activity->new_values['notes_present']);
        self::assertArrayNotHasKey('notes', $activity->new_values);
        self::assertArrayNotHasKey('sections', $activity->new_values);
        self::assertSame('incomplete_data', $activity->new_values['reason']);
    }

    public function test_current_and_historical_activity_values_are_allowlisted_in_responses(): void
    {
        $currentLog = app(RecruitmentActivityLogger::class)->log(
            action: 'activity.current',
            actor: $this->admin,
            application: $this->application,
            oldValues: [
                'stage' => 'submitted',
                'personal_email' => 'current-old@example.test',
            ],
            newValues: [
                'stage' => 'screening',
                'result' => 'accepted',
                'type' => 'tracking',
                'recipient_email' => 'current-new@example.test',
                'notes' => 'password=secret-current',
            ],
        );

        self::assertSame(['stage' => 'submitted'], $currentLog->old_values);
        self::assertSame([
            'stage' => 'screening',
            'result' => 'accepted',
            'type' => 'tracking',
        ], $currentLog->new_values);

        $legacyLog = RecruitmentActivityLog::query()->create([
            'recruitment_application_id' => $this->application->id,
            'recruitment_period_id' => $this->period->id,
            'actor_id' => $this->admin->id,
            'actor_type' => 'staff',
            'action' => 'activity.legacy',
            'old_values' => [
                'stage' => 'screening',
                'result' => 'pending',
                'personal_email' => 'historic-old@example.test',
                'student_email' => 'historic-student@example.test',
                'nim' => 'EP02-NIM-482915',
                'phone' => '+62 812-3456-7890',
                'full_name' => 'Historic Person',
            ],
            'new_values' => [
                'stage' => 'interview',
                'result' => 'accepted',
                'recipient_email' => 'historic-new@example.test',
                'review_notes' => 'token=secret-historic',
            ],
            'created_at' => now()->addSecond(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.recruitment.activity-logs.index', ['action' => 'activity.current']))
            ->assertOk()
            ->assertDontSee('current-new@example.test')
            ->assertDontSee('current-old@example.test')
            ->assertDontSee('secret-current')
            ->assertInertia(fn ($page) => $page
                ->where('logs.data.0.old_values', ['stage' => 'submitted'])
                ->where('logs.data.0.new_values', ['stage' => 'screening', 'result' => 'accepted', 'type' => 'tracking']));

        $this->actingAs($this->admin)
            ->get(route('dashboard.recruitment.activity-logs.index', ['action' => 'activity.legacy']))
            ->assertOk()
            ->assertDontSee('historic-old@example.test')
            ->assertDontSee('historic-student@example.test')
            ->assertDontSee('historic-new@example.test')
            ->assertDontSee('EP02-NIM-482915')
            ->assertDontSee('+62 812-3456-7890')
            ->assertDontSee('Historic Person')
            ->assertDontSee('secret-historic')
            ->assertInertia(fn ($page) => $page
                ->where('logs.data.0.id', $legacyLog->id)
                ->where('logs.data.0.old_values', ['stage' => 'screening', 'result' => 'pending'])
                ->where('logs.data.0.new_values', ['stage' => 'interview', 'result' => 'accepted']));

        $this->actingAs($this->admin)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertDontSee('historic-old@example.test')
            ->assertDontSee('historic-new@example.test')
            ->assertDontSee('secret-historic')
            ->assertInertia(fn ($page) => $page
                ->where('applicant_detail.activity_logs.0.id', $legacyLog->id)
                ->where('applicant_detail.activity_logs.0.old_values', ['stage' => 'screening', 'result' => 'pending'])
                ->where('applicant_detail.activity_logs.0.new_values', ['stage' => 'interview', 'result' => 'accepted']));
    }

    /**
     * M-1: `recruitment_activity_logs.entity_id` is a UUID column (char(36) on
     * MySQL/MariaDB). A long descriptive scope must never be written there, or the
     * strict-mode insert fails and the export audit row silently disappears.
     */
    public function test_record_export_keeps_the_scope_out_of_the_uuid_entity_id(): void
    {
        $scope = 'interviews:all:secondary=included:columns=28';
        self::assertGreaterThan(36, mb_strlen($scope));

        app(RecruitmentActivityLogger::class)->recordExport($this->admin, $this->period->id, $scope, 42);

        $audit = RecruitmentActivityLog::query()->where('action', 'export.generated')->sole();

        self::assertNull($audit->entity_id);
        self::assertSame('recruitment_export', $audit->entity_type);
        self::assertSame($this->admin->id, $audit->actor_id);
        self::assertSame($this->period->id, $audit->recruitment_period_id);
        self::assertSame([
            'scope' => $scope,
            'rows_generated' => 42,
        ], $audit->new_values);
    }
}
