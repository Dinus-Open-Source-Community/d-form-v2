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
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class RecruitmentMyInterviewScopeTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

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

    private function application(string $suffix): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-MI'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    /**
     * Regis ulang + booking langsung di DB agar deterministik dan tidak
     * tergantung jam eksekusi test (startedScope memakai scheduled_at).
     */
    private function bookedApplication(string $suffix): RecruitmentApplication
    {
        $application = $this->application($suffix);

        $this->checkInApplicant($this->session, $application, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subMinute(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    /**
     * Booking langsung di DB tanpa regis ulang (policy book butuh attendance).
     */
    private function bookedWithoutCheckIn(string $suffix): RecruitmentApplication
    {
        $application = $this->application($suffix);

        app(InterviewLifecycleService::class)->createWaitingInterview($application, $this->session);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subMinute(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    public function test_index_lists_booked_without_attendance_as_locked(): void
    {
        $application = $this->bookedWithoutCheckIn('008');

        // Tanpa attendance tetap tampil (flag false), counts + opsi sesi/divisi ikut mencakup.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $application->id)
                ->where('interviews.data.0.has_attendance', false)
                ->where('tab_counts.in_progress', 1)
                ->where('tab_counts.done', 0)
                ->where('pending_start_count', 0)
                ->has('session_options', 1)
                ->has('division_options', 1));

        // Terkunci: detail + nilai ditolak sampai regis ulang (attendance guard di policy).
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $application->primaryInterview))
            ->assertForbidden();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), [
                'speaking_score' => 8,
                'technical_score' => 7,
                'attitude_score' => 9,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Solid candidate.',
            ])
            ->assertForbidden();
    }

    /**
     * Assignment Waiting yang sudah ditempati interviewer tapi belum dipanggil.
     */
    private function waitingAssigned(string $suffix): RecruitmentApplication
    {
        $application = $this->application($suffix);

        app(InterviewLifecycleService::class)->createWaitingInterview($application, $this->session);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'scheduled_at' => now()->subMinute(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    public function test_in_progress_tab_lists_only_in_progress_without_evaluation(): void
    {
        $waiting = $this->waitingAssigned('009');
        $progress = $this->bookedWithoutCheckIn('011');

        // Tab in_progress hanya InProgress milik sendiri yang belum dinilai;
        // Waiting milik sendiri pindah ke tab Antrean.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->has('interviews.data', 1)
                ->where('interviews.data.0.application.id', $progress->id)
                ->where('tab_counts.in_progress', 1)
                ->where('tab_counts.done', 0));

        // Done tetap hanya yang sudah dievaluasi.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('interviews.total', 0));
    }

    public function test_index_only_lists_booked_in_progress_applicants(): void
    {
        $booked = $this->application('001');
        $waitingOnly = $this->application('002');

        $this->checkInApplicant($this->session, $booked, $this->staff);
        $this->checkInApplicant($this->session, $waitingOnly, $this->staff);

        // Pre-assign langsung di DB (endpoint book dibuang).
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $booked->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
            ]);

        // Kunci scheduled_at ke masa lalu agar startedScope deterministik.
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $booked->id)
            ->update(['scheduled_at' => now()->subMinute()]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/MyInterviews/Index')
                ->where('interviews.total', 1)
                ->has('interviews.data', 1)
                ->where('interviews.data.0.application.id', $booked->id));
    }

    public function test_done_tab_lists_evaluated_applicants(): void
    {
        $applicant = $this->application('010');
        $this->checkInApplicant($this->session, $applicant, $this->staff);

        // Pre-assign langsung di DB (endpoint book dibuang).
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $applicant->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
            ]);

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $applicant->id)
            ->firstOrFail();

        // Kunci scheduled_at ke masa lalu agar startedScope deterministik.
        $interview->update(['scheduled_at' => now()->subMinute()]);

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $applicant->id,
            'recruitment_interview_id' => $interview->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);

        $interview->update(['status' => InterviewStatus::Completed]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('interviews.total', 1));
    }

    public function test_index_hides_assignments_while_session_closed(): void
    {
        $application = $this->bookedApplication('005');

        $this->session->update(['is_active' => false]);

        // Sesi belum dibuka: kartu tidak tampil, counts ikut nol.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->where('tab_counts.in_progress', 0)
                ->where('tab_counts.done', 0)
                ->where('pending_start_count', 0)
                ->has('today_sessions', 0)
                ->has('division_options', 0)
                ->has('session_options', 0));

        $this->session->update(['is_active' => true]);

        // Sesi dibuka: kartu muncul kembali dengan flag attendance.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $application->id)
                ->where('interviews.data.0.has_attendance', true)
                ->where('tab_counts.in_progress', 1)
                ->where('tab_counts.done', 0)
                ->where('pending_start_count', 0)
                ->has('today_sessions', 1)
                ->has('division_options', 1)
                ->where('division_options.0.value', $this->programming->id)
                ->has('session_options', 1)
                ->where('session_options.0.value', $this->session->id));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $application->primaryInterview))
            ->assertOk();
    }

    public function test_index_hides_interviews_not_started_yet(): void
    {
        $started = $this->bookedApplication('006');
        $future = $this->bookedApplication('007');

        $future->primaryInterview->update(['scheduled_at' => now()->addHours(3)]);

        // Belum mulai (future) tersembunyi; started tetap tampil.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $started->id)
                ->where('pending_start_count', 1));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['q' => $future->registration_number]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->where('pending_start_count', 1));
    }

    public function test_default_view_only_lists_today_interviews(): void
    {
        $today = $this->bookedApplication('T01');

        $tomorrowSession = $this->makeSession($this->programming->id, today()->addDay()->toDateString());
        $tomorrow = $this->application('T02');
        $this->checkInApplicant($tomorrowSession, $tomorrow, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $tomorrow->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                // Sengaja masa lalu: tanpanya startedScope yang menyaring,
                // sehingga yang diuji murni lingkup tanggal sesi.
                'scheduled_at' => now()->subMinute(),
            ]);

        // Default (tanpa filter sesi): besok tersembunyi dari daftar + counts.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $today->id)
                ->where('tab_counts.in_progress', 1)
                ->where('tab_counts.done', 0)
                ->where('pending_start_count', 0));
    }

    public function test_session_filter_includes_future_session_interviews(): void
    {
        $tomorrowSession = $this->makeSession($this->programming->id, today()->addDay()->toDateString());

        $started = $this->application('T03');
        $this->checkInApplicant($tomorrowSession, $started, $this->staff);
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $started->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subMinute(),
            ]);

        $future = $this->application('T04');
        $this->checkInApplicant($tomorrowSession, $future, $this->staff);
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $future->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::Waiting,
                'booked_at' => now(),
                'scheduled_at' => now()->addHours(3),
            ]);

        // Default tetap kosong (besok di luar lingkup).
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->where('tab_counts.in_progress', 0)
                ->where('pending_start_count', 0));

        // Dengan filter sesi: yang sudah mulai tampil, yang future terhitung pending.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress', 'session_id' => $tomorrowSession->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $started->id)
                ->where('tab_counts.in_progress', 1)
                ->where('pending_start_count', 1));
    }

    public function test_done_tab_lists_evaluated_today_interviews(): void
    {
        $applicant = $this->application('T05');
        $this->checkInApplicant($this->session, $applicant, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $applicant->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subMinute(),
            ]);

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $applicant->id)
            ->firstOrFail();

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $applicant->id,
            'recruitment_interview_id' => $interview->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);

        $interview->update(['status' => InterviewStatus::Completed]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('tab_counts.done', 1));
    }

    private function makeSession(string $divisionId, ?string $sessionDate = null): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $divisionId,
            'session_date' => $sessionDate ?? now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_all_defaults_to_today_unless_filtered(): void
    {
        $todayApp = $this->application('A01');
        $this->checkInApplicant($this->session, $todayApp, $this->staff);
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $todayApp->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subMinute(),
            ]);

        $tomorrowSession = $this->makeSession($this->programming->id, today()->addDay()->toDateString());
        $tomorrowApp = $this->application('A02');
        $this->checkInApplicant($tomorrowSession, $tomorrowApp, $this->staff);
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $tomorrowApp->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::Waiting,
                'booked_at' => now(),
                'scheduled_at' => now()->addHours(3),
            ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $todayApp->id)
                ->where('tab_counts.all', 1));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all', 'session_id' => $tomorrowSession->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $tomorrowApp->id)
                ->where('pending_start_count', 1));
    }

    public function test_search_wildcard_treated_literal(): void
    {
        $this->bookedApplication('W01');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all', 'show_all' => '1', 'q' => '%']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('interviews.total', 0));
    }

    public function test_search_does_not_leak_other_divisions(): void
    {
        $dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $dataDivision->id,
            'registration_number' => 'OPREC-2026-LK1',
            'nim' => 'LEAK-999',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'all', 'show_all' => '1', 'q' => 'LEAK-999']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('interviews.total', 0));
    }

    public function test_session_date_outside_period_is_rejected(): void
    {
        $payload = [
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
        ];

        // Window factory +2..+3 bulan: today di luar → 422.
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.store'), $payload)
            ->assertSessionHasErrors('session_date');

        // Kontrol: window mencakup today → lolos.
        $this->period->update([
            'interview_starts_at' => today()->subDay()->toDateString(),
            'interview_ends_at' => today()->addMonth()->toDateString(),
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.store'), $payload)
            ->assertRedirect();
    }
}
