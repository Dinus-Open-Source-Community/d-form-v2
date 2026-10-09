<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewLifecycleService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class RecruitmentWaitingRoomTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private User $otherInterviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $data;

    private RecruitmentInterviewSession $session;

    private RecruitmentInterviewSession $dataSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->otherInterviewer = User::factory()->create();
        $this->otherInterviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->otherInterviewer->id,
            'recruitment_division_id' => $this->data->id,
        ]);

        $this->session = $this->makeSession($this->programming->id);
        $this->dataSession = $this->makeSession($this->data->id);
    }

    public function test_waiting_tab_lists_only_checked_in_unclaimed_pool(): void
    {
        $pool = $this->poolApplication('W1');
        $unscanned = $this->unscannedWaitingApplication('W2');
        $claimed = $this->poolApplication('W3');
        $this->claimAs($claimed->primaryInterview, $this->interviewer);
        $this->poolApplication('W4', $this->dataSession, $this->data->id);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->has('interviews.data', 1)
                ->where('interviews.data.0.application.id', $pool->id)
                ->where('tab_counts.waiting', 1)
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $pool->primaryInterview->id));

        // Tanpa tab (default antrean): pool yang sama.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('query.tab', 'waiting'));
    }

    public function test_all_tab_lists_everyone_including_unscanned_locked(): void
    {
        $pool = $this->poolApplication('A1');
        $unscanned = $this->unscannedWaitingApplication('A2');
        $this->poolApplication('A3', $this->dataSession, $this->data->id);

        // Applicant tahap Interview tanpa interview sama sekali.
        $bare = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-A4',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all', 'show_all' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 3)
                ->where('tab_counts.all', 3)
                ->where('interviews.data', function (mixed $rows) use ($pool, $unscanned, $bare): bool {
                    $list = $rows instanceof \Illuminate\Support\Collection ? $rows->all() : (array) $rows;
                    $ids = array_column(array_column($list, 'application'), 'id');
                    sort($ids);

                    $expected = [$pool->id, $unscanned->id, $bare->id];
                    sort($expected);

                    if ($ids !== $expected) {
                        return false;
                    }

                    foreach ($list as $row) {
                        $row = (array) $row;
                        if ($row['application']['id'] === $unscanned->id && ($row['has_attendance'] ?? null) !== false) {
                            return false;
                        }
                        if ($row['application']['id'] === $bare->id && ($row['interview_id'] ?? null) !== '') {
                            return false;
                        }
                    }

                    return true;
                }));
    }

    public function test_in_progress_excludes_waiting_owned(): void
    {
        $ownedWaiting = $this->poolApplication('I1');

        // Dimiliki tapi masih Waiting: tetap di Antrean, bukan in_progress.
        RecruitmentInterview::query()->whereKey($ownedWaiting->primaryInterview->id)->update([
            'interviewer_id' => $this->interviewer->id,
        ]);

        $ownedProgress = $this->poolApplication('I2');
        RecruitmentInterview::query()->whereKey($ownedProgress->primaryInterview->id)->update([
            'interviewer_id' => $this->interviewer->id,
            'status' => InterviewStatus::InProgress,
            'booked_at' => now(),
        ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $ownedProgress->id)
                ->where('tab_counts.in_progress', 1));
    }

    public function test_secondary_claim_without_check_in_rejected(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => $this->data->id,
            'registration_number' => 'OPREC-2026-S1',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        app(InterviewLifecycleService::class)
            ->createWaitingInterview($application, $this->session);

        $primary = $application->fresh()->primaryInterview;

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_id' => $primary->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'save_count' => 1,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);

        // Tanpa regis ulang: klaim secondary ditolak walau primary sudah dinilai.
        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
            ])
            ->assertSessionHasErrors('application_id');

        $this->assertDatabaseMissing('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
        ]);
    }

    /**
     * Applicant checked-in dengan waiting interview tanpa interviewer (pool).
     */
    private function poolApplication(
        string $suffix,
        ?RecruitmentInterviewSession $session = null,
        ?string $divisionId = null,
    ): RecruitmentApplication {
        $session ??= $this->session;

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $divisionId ?? $this->programming->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($session, $application, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => null,
                'status' => InterviewStatus::Waiting,
                'scheduled_at' => now()->subMinute(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    /**
     * Waiting interview tanpa regis ulang (di luar pool antrean).
     */
    private function unscannedWaitingApplication(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        app(InterviewLifecycleService::class)
            ->createWaitingInterview($application, $this->session);

        return $application->fresh(['primaryInterview']);
    }

    public function test_cards_carry_session_period_name(): void
    {
        $pool = $this->poolApplication('PD1');
        $secondaryApp = $this->secondaryEligibleApplication('PD2');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.data.0.session.period', $this->period->name)
                ->where('primary_opportunities.0.session.period', $this->period->name));

        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('secondary_opportunities.0.sessions.0.period', $this->period->name));
    }

    private function claimAs(RecruitmentInterview $interview, User $interviewer): void
    {
        RecruitmentInterview::query()->whereKey($interview->id)->update([
            'interviewer_id' => $interviewer->id,
            'status' => InterviewStatus::InProgress,
            'booked_at' => now(),
        ]);
    }

    public function test_waiting_tab_pool_suppresses_empty_state(): void
    {
        $pool = $this->poolApplication('E1');

        // Masukan kondisi EmptyState terpenuhi (pool tak kosong) → EmptyState
        // tidak tampil; daftar antrean + badge ikut terisi.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $pool->primaryInterview->id)
                ->where('tab_counts.waiting', 1));
    }

    public function test_waiting_tab_fully_empty_shows_empty_state_once(): void
    {
        // Tanpa fixture: daftar kosong + ketiga sumber konten antrean kosong
        // → tepat satu EmptyState ("Belum ada antrean").
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->has('primary_opportunities', 0)
                ->has('secondary_opportunities', 0)
                ->has('claimed_secondary', 0)
                ->where('tab_counts.waiting', 0));
    }

    public function test_all_tab_rows_never_carry_secondary_interviews(): void
    {
        $app = $this->poolApplication('E2');

        $secondary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $app->id,
            'recruitment_interview_session_id' => $this->dataSession->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'interviewer_id' => $this->otherInterviewer->id,
            'scheduled_at' => now()->subMinute(),
            'location' => 'Lab Data',
            'room' => 'B202',
            'status' => InterviewStatus::InProgress,
            'booked_at' => now(),
        ]);

        $primaryId = $app->fresh()->primaryInterview->id;
        $divisionName = $this->programming->name;

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data', function (mixed $rows) use ($primaryId, $secondary, $divisionName): bool {
                    $list = $rows instanceof \Illuminate\Support\Collection ? $rows->all() : (array) $rows;
                    if (count($list) !== 1) {
                        return false;
                    }
                    $row = (array) $list[0];

                    return ($row['interview_id'] ?? null) === $primaryId
                        && ($row['interview_id'] ?? null) !== $secondary->id
                        && ($row['session']['division'] ?? null) === $divisionName;
                }));
    }

    private function makeSession(string $divisionId): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $divisionId,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_pool_respects_session_filter(): void
    {
        $firstSession = $this->makeSession($this->programming->id);
        $secondSession = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '13:00:00',
            'ends_at' => '15:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A102',
            'is_active' => true,
        ]);

        $first = $this->poolApplication('F1', $firstSession);
        $second = $this->poolApplication('F2', $secondSession);

        // Tanpa filter: keduanya tampil.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 2)
                ->has('primary_opportunities', 2)
                ->where('tab_counts.waiting', 2));

        // Pilih sesi kedua: kartu sesi pertama sembunyi.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $secondSession->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $second->primaryInterview->id)
                ->where('tab_counts.waiting', 1));
    }

    public function test_pool_respects_division_and_search_filters(): void
    {
        $pool = $this->poolApplication('F3');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'division_id' => $this->data->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 0));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'q' => $pool->registration_number]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.application.id', $pool->id));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'q' => 'tidak-ada-nama-cocok']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 0));
    }

    public function test_secondary_opportunities_respect_session_filter(): void
    {
        $secondDataSession = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->data->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '13:00:00',
            'ends_at' => '15:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'B202',
            'is_active' => true,
        ]);

        $application = $this->secondaryEligibleApplication('F4');

        // Tanpa filter: kedua sesi divisi secondary terdaftar.
        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('secondary_opportunities', 1)
                ->has('secondary_opportunities.0.sessions', 2));

        // Pilih sesi kedua: daftar sesi tersaring, peluang tetap ada.
        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $secondDataSession->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('secondary_opportunities', 1)
                ->has('secondary_opportunities.0.sessions', 1)
                ->where('secondary_opportunities.0.sessions.0.value', $secondDataSession->id));

        // Pilih sesi divisi lain: peluang gugur.
        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $this->session->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('secondary_opportunities', 0));
    }

    public function test_claimed_secondary_respects_session_filter(): void
    {
        $application = $this->secondaryEligibleApplication('F5');

        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
            ])
            ->assertRedirect();

        $claimed = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', RecruitmentInterview::KIND_SECONDARY)
            ->firstOrFail();

        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $claimed->recruitment_interview_session_id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('claimed_secondary', 1)
                ->where('claimed_secondary.0.interview_id', $claimed->id));

        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $this->session->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('claimed_secondary', 0));
    }

    /**
     * Applicant eligible secondary: checked-in, primary dinilai, divisi
     * secondary = data.
     */
    private function secondaryEligibleApplication(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => $this->data->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        $primary = $application->fresh()->primaryInterview;

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_id' => $primary->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::NotRecommended->value,
            'save_count' => 1,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);

        return $application->fresh();
    }

    public function test_list_rows_carry_kind_and_division_values(): void
    {
        $pool = $this->poolApplication('KD1');

        $secondaryApp = $this->secondaryEligibleApplication('KD2');

        $claimedSecondary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $secondaryApp->id,
            'recruitment_interview_session_id' => $this->dataSession->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'interviewer_id' => $this->interviewer->id,
            'scheduled_at' => now()->subMinute(),
            'location' => 'Lab Data',
            'room' => 'B202',
            'status' => InterviewStatus::InProgress,
            'booked_at' => now(),
        ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.data.0.interview_kind', RecruitmentInterview::KIND_PRIMARY)
                ->where('interviews.data.0.application.primary_division', $this->programming->name)
                ->where('claimed_secondary.0.interview_kind', RecruitmentInterview::KIND_SECONDARY)
                ->where('claimed_secondary.0.application.primary_division', $this->programming->name)
                ->where('claimed_secondary.0.application.secondary_division', $this->data->name));
    }

    public function test_waiting_excludes_tomorrow_session_by_default(): void
    {
        $today = $this->poolApplication('D1');

        $otherSession = $this->makeSession($this->programming->id);
        $moved = $this->poolApplication('D2', $otherSession);
        $otherSession->update(['session_date' => today()->addDay()->toDateString()]);

        // Default: sesi yang dipindah ke besok tidak tampil di daftar, pool, maupun badge.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $today->id)
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $today->primaryInterview->id)
                ->where('tab_counts.waiting', 1));
    }

    public function test_waiting_explicit_session_filter_shows_tomorrow(): void
    {
        $this->poolApplication('D3');

        $otherSession = $this->makeSession($this->programming->id);
        $moved = $this->poolApplication('D4', $otherSession);
        $otherSession->update(['session_date' => today()->addDay()->toDateString()]);

        // Filter sesi eksplisit menang atas lingkup tanggal.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'waiting', 'session_id' => $otherSession->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $moved->id)
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $moved->primaryInterview->id)
                ->where('tab_counts.waiting', 1));
    }
}
