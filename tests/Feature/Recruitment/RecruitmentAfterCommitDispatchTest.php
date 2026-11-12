<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\MembershipType;
use App\Enums\Recruitment\ScreeningReason;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\CorrectionRequestService;
use App\Services\Recruitment\FinalSelectionService;
use App\Services\Recruitment\ScreeningService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Closure;
use RuntimeException;
use Tests\TestCase;

final class RecruitmentAfterCommitDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentApplication $application;

    private string $queueName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->queueName = 'be-p1-003-'.Str::lower((string) Str::uuid());
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.queue' => $this->queueName,
        ]);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Queue::connection('redis')->clear($this->queueName);

        parent::tearDown();
    }

    public function test_screening_notification_is_discarded_when_outer_transaction_rolls_back(): void
    {
        try {
            DB::transaction(function (): void {
                app(ScreeningService::class)->pass($this->staff, $this->application);

                throw new RuntimeException('Force transaction rollback for regression test.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force transaction rollback for regression test.', $exception->getMessage());
        }

        $this->assertSame(0, Queue::connection('redis')->size($this->queueName));
        $this->assertDatabaseCount('recruitment_screenings', 0);
        $this->assertDatabaseHas('recruitment_applications', [
            'id' => $this->application->id,
            'stage' => ApplicationStage::Submitted->value,
        ]);
    }

    public function test_screening_notification_is_enqueued_after_outer_transaction_commits(): void
    {
        DB::transaction(function (): void {
            app(ScreeningService::class)->pass($this->staff, $this->application);

            $this->assertSame(0, Queue::connection('redis')->size($this->queueName));
        });

        $this->assertSame(1, Queue::connection('redis')->size($this->queueName));
    }

    public function test_all_recruitment_dispatches_wait_for_outer_transaction_commit(): void
    {
        $operations = [
            function (): void {
                app(ScreeningService::class)->requireRevision(
                    $this->staff,
                    $this->createRecruitmentApplication(ApplicationStage::Submitted),
                    ScreeningReason::IncompleteData,
                    'Lengkapi data.',
                    sections: ['data_diri'],
                );
            },
            function (): void {
                app(ScreeningService::class)->reject(
                    $this->staff,
                    $this->createRecruitmentApplication(ApplicationStage::Submitted),
                    ScreeningReason::RequirementsNotMet,
                    'Persyaratan belum terpenuhi.',
                );
            },
            function (): void {
                app(FinalSelectionService::class)->accept(
                    $this->staff,
                    $this->createRecruitmentApplication(ApplicationStage::FinalReview),
                    MembershipType::Member,
                    $this->programmingDivision()->id,
                );
            },
            function (): void {
                app(FinalSelectionService::class)->reject(
                    $this->staff,
                    $this->createRecruitmentApplication(ApplicationStage::FinalReview),
                    'Alasan internal.',
                    'Pesan publik.',
                );
            },
            function (): void {
                app(CorrectionRequestService::class)->store(
                    $this->createRecruitmentApplication(ApplicationStage::Screening, isVerified: true),
                    'Mohon koreksi data saya.',
                );
            },
        ];

        foreach ($operations as $operation) {
            $this->assertJobIsDeferredUntilCommit($operation);
        }

        $this->assertSame(5, Queue::connection('redis')->size($this->queueName));
    }

    public function test_interview_scheduled_notification_is_deferred_until_commit(): void
    {
        RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->application->recruitment_period_id,
            'recruitment_division_id' => $this->programmingDivision()->id,
            'session_date' => today(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        DB::transaction(function (): void {
            app(ScreeningService::class)->pass($this->staff, $this->application);

            $this->assertSame(0, Queue::connection('redis')->size($this->queueName));
        });

        $this->assertSame(2, Queue::connection('redis')->size($this->queueName));
    }

    private function assertJobIsDeferredUntilCommit(Closure $operation): void
    {
        $queueSizeBeforeTransaction = Queue::connection('redis')->size($this->queueName);

        DB::transaction(function () use ($operation, $queueSizeBeforeTransaction): void {
            $operation();

            $this->assertSame(
                $queueSizeBeforeTransaction,
                Queue::connection('redis')->size($this->queueName),
            );
        });

        $this->assertSame(
            $queueSizeBeforeTransaction + 1,
            Queue::connection('redis')->size($this->queueName),
        );
    }

    private function createRecruitmentApplication(ApplicationStage $stage, bool $isVerified = false): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->application->recruitment_period_id,
            'primary_division_id' => $this->programmingDivision()->id,
            'stage' => $stage,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
            'is_verified' => $isVerified,
        ]);
    }

    private function programmingDivision(): RecruitmentDivision
    {
        return RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
    }
}
