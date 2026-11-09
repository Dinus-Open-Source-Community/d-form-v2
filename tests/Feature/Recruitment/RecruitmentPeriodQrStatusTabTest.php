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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BE-P2-005: ringkasan QR hanya dihitung pada tab yang membutuhkannya.
 *
 * Cold-load tab non-peserta tidak boleh memicu query berat `email_logs ... in (...)`,
 * sementara partial reload Inertia yang secara eksplisit meminta `qr_status_summary`
 * (refresh/polling header) tetap harus menghitungnya.
 */
class RecruitmentPeriodQrStatusTabTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->period = RecruitmentPeriod::factory()->open()->create([
            'interview_starts_at' => today()->subDay()->toDateString(),
            'interview_ends_at' => today()->addMonth()->toDateString(),
        ]);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        foreach ([EmailLogStatus::Sent, EmailLogStatus::Failed, EmailLogStatus::Queued] as $status) {
            $application = RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $this->period->id,
                'primary_division_id' => $division->id,
                'stage' => ApplicationStage::Interview,
            ]);

            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => (string) $application->personal_email,
                'status' => $status,
                'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                'error_message' => $status === EmailLogStatus::Failed ? 'SMTP timeout' : null,
                'sent_at' => $status === EmailLogStatus::Sent ? now() : null,
            ]);
        }

        // Applicant keempat tanpa log: tidak masuk hitungan summary.
        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach ($permissions as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    /**
     * Query berat `qrStatusForPeriod()` selalu memakai
     * `whereIn('recruitment_application_id', ...)` pada tabel `email_logs`.
     * Predikat `email_logs` + ` in (` sengaja dipakai karena subquery eligibility
     * memakai EXISTS/NOT EXISTS, bukan `whereIn`, sehingga tidak ikut terhitung.
     *
     * @return list<string>
     */
    private function heavyEmailLogQueries(): array
    {
        return collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'email_logs') && str_contains($query, ' in ('))
            ->values()
            ->all();
    }

    public function test_tab_peserta_memuat_qr_status_summary_dengan_nilai_sama(): void
    {
        $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.applications.list',
        ]))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('qr_status_summary.sent', 1)
                ->where('qr_status_summary.failed', 1)
                ->where('qr_status_summary.queued', 1));
    }

    public function test_tab_interview_tidak_menghitung_qr_status_summary(): void
    {
        DB::enableQueryLog();

        $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.interviews.schedule',
        ]))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'interview')
                ->missing('qr_status_summary')
                ->has('qr_eligible_count')
                ->has('qr_sent_count'));

        $heavy = $this->heavyEmailLogQueries();
        DB::disableQueryLog();

        self::assertSame([], $heavy, 'tab interview tidak boleh menjalankan query berat email_logs ... in (...).');
    }

    public function test_tab_laporan_settings_dan_interviewer_tidak_menghitung_qr_status_summary(): void
    {
        $admin = $this->admin([
            'recruitment.periods.view',
            'recruitment.reports.view',
            'recruitment.interviewers.assign',
        ]);

        foreach (['laporan', 'settings', 'interviewer'] as $tab) {
            DB::enableQueryLog();

            $this->actingAs($admin)
                ->get(route('dashboard.recruitment.periods.show', [
                    'period' => $this->period->id,
                    'tab' => $tab,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('tab', $tab)
                    ->missing('qr_status_summary')
                    ->has('qr_eligible_count')
                    ->has('qr_sent_count'));

            $heavy = $this->heavyEmailLogQueries();
            DB::disableQueryLog();

            self::assertSame([], $heavy, "tab {$tab} tidak boleh menjalankan query berat email_logs ... in (...).");
        }
    }

    public function test_partial_reload_tab_interview_yang_meminta_qr_status_summary_tetap_menghitung(): void
    {
        config(['app.asset_url' => 'be-p2-005-test']);
        $version = hash('xxh128', 'be-p2-005-test');

        $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.interviews.schedule',
        ]))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'Dashboard/Recruitment/Periods/Show',
                'X-Inertia-Partial-Data' => 'qr_status_summary',
            ])
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertOk()
            // Respons partial reload adalah payload Inertia JSON, bukan view;
            // assertInertia() hanya bisa membaca view response.
            ->assertJsonPath('component', 'Dashboard/Recruitment/Periods/Show')
            ->assertJsonPath('props.qr_status_summary.sent', 1)
            ->assertJsonPath('props.qr_status_summary.failed', 1)
            ->assertJsonPath('props.qr_status_summary.queued', 1);
    }

    public function test_partial_reload_child_qr_status_summary_tetap_menghitung(): void
    {
        config(['app.asset_url' => 'be-p2-005-test']);
        $version = hash('xxh128', 'be-p2-005-test');

        $response = $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.interviews.schedule',
        ]))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'Dashboard/Recruitment/Periods/Show',
                'X-Inertia-Partial-Data' => 'qr_status_summary.sent',
            ])
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertOk();

        $response->assertJsonPath('props.qr_status_summary.sent', 1);
        self::assertNull($response->json('props.qr_status_summary.failed'));
        self::assertNull($response->json('props.qr_status_summary.queued'));
    }

    public function test_navigasi_tab_interview_meminta_tab_only_tanpa_qr_status_summary(): void
    {
        // Bentuk request yang dikirim `visitTab()` setelah halaman pertama:
        // partial Inertia dengan hanya prop milik tab interview (TAB_ONLY.interview).
        config(['app.asset_url' => 'be-p2-005-test']);
        $version = hash('xxh128', 'be-p2-005-test');

        DB::enableQueryLog();

        $response = $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.interviews.schedule',
        ]))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'Dashboard/Recruitment/Periods/Show',
                'X-Inertia-Partial-Data' => 'period,tab,sessions',
            ])
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertOk();

        $heavy = $this->heavyEmailLogQueries();
        DB::disableQueryLog();

        self::assertNull($response->json('props.qr_status_summary'));
        $response->assertJsonPath('props.tab', 'interview');
        self::assertSame([], $heavy, 'navigasi tab interview tidak boleh menjalankan query berat email_logs ... in (...).');
    }

    public function test_tab_invalid_fallback_ke_peserta_tetap_menghitung_qr_status_summary(): void
    {
        $this->actingAs($this->admin([
            'recruitment.periods.view',
            'recruitment.applications.list',
        ]))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'hacker-value',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'peserta')
                ->where('qr_status_summary.sent', 1)
                ->where('qr_status_summary.failed', 1)
                ->where('qr_status_summary.queued', 1));
    }

    public function test_endpoint_json_qr_status_tetap_mengembalikan_summary_dan_recipients(): void
    {
        $response = $this->actingAs($this->admin([
            'recruitment.periods.view',
        ]))
            ->getJson(route('dashboard.recruitment.periods.qr-status', $this->period->id))
            ->assertOk()
            ->assertJsonPath('sent', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('queued', 1)
            ->assertJsonCount(3, 'recipients');

        $recipients = $response->json('recipients');
        self::assertIsArray($recipients);

        foreach ($recipients as $recipient) {
            self::assertIsArray($recipient);
            self::assertSame(
                ['application_id', 'full_name', 'registration_number', 'status', 'error', 'sent_at'],
                array_keys($recipient),
            );
        }
    }
}
