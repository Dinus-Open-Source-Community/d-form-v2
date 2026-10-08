<?php

namespace Tests\Feature\Recruitment;

use App\Models\Broadcast;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodTabPaginationTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->period = RecruitmentPeriod::factory()->create();
    }

    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach ($permissions as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    public function test_tab_peserta_duapuluh_satu_page_dua_satu_row(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        for ($i = 0; $i < 21; $i++) {
            RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $this->period->id,
                'primary_division_id' => $division->id,
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'peserta')
                ->where('applications.current_page', 2)
                ->where('applications.per_page', 20)
                ->where('applications.total', 21)
                ->where('applications.last_page', 2)
                ->has('applications.data', 1)
                ->missing('broadcasts')
                ->missing('sessions')
                ->missing('report'));
    }

    public function test_tab_peserta_enambelas_jadi_satu_halaman_tanpa_pager(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        for ($i = 0; $i < 16; $i++) {
            RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $this->period->id,
                'primary_division_id' => $division->id,
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.per_page', 20)
                ->where('applications.total', 16)
                ->where('applications.last_page', 1)
                ->has('applications.data', 16));
    }

    public function test_tab_broadcast_page_satu_memuat_rows_ter_scoped(): void
    {
        // Fitur broadcast dinonaktifkan (config/features.php + migrasi .bak).
        $this->markTestSkipped('Fitur broadcast dinonaktifkan.');
        $otherPeriod = RecruitmentPeriod::factory()->create();

        $first = Broadcast::factory()->create([
            'name' => 'Paginasi Broadcast 1',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $this->period->id,
        ]);
        $second = Broadcast::factory()->create([
            'name' => 'Paginasi Broadcast 2',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $this->period->id,
        ]);
        Broadcast::factory()->create([
            'name' => 'Paginasi Broadcast Lain',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $otherPeriod->id,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'broadcast',
                'page' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'broadcast')
                ->where('broadcasts.current_page', 1)
                ->where('broadcasts.per_page', 15)
                ->where('broadcasts.total', 2)
                ->has('broadcasts.data', 2)
                ->where('broadcasts.data', function (array $rows) use ($first, $second): bool {
                    $ids = array_column($rows, 'id');
                    sort($ids);

                    $expected = [$first->id, $second->id];
                    sort($expected);

                    return $ids === $expected;
                })
                ->missing('applications')
                ->missing('sessions')
                ->missing('report'));
    }

    public function test_tab_invalid_fallback_peserta_dengan_paginasi(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'tab-ngawur',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'peserta')
                ->where('applications.current_page', 1)
                ->where('applications.per_page', 20)
                ->where('applications.total', 1)
                ->has('applications.data', 1));
    }

    public function test_per_page_param_diabaikan_selalu_duapuluh(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        for ($i = 0; $i < 7; $i++) {
            RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $this->period->id,
                'primary_division_id' => $division->id,
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'peserta')
                ->where('applications.current_page', 1)
                ->where('applications.per_page', 20)
                ->where('applications.total', 7)
                ->where('applications.last_page', 1)
                ->has('applications.data', 7));
    }

    public function test_peserta_search_server_side_memfilter_nama(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $target = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Cari Saya Budi',
        ]);
        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Orang Lain Saja',
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'search' => 'Cari Saya',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.id', $target->id));
    }

    public function test_peserta_terurut_submitted_terbaru_dulu(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $older = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'submitted_at' => now()->subDay(),
        ]);
        $newer = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.data.0.id', $newer->id)
                ->where('applications.data.1.id', $older->id));
    }

    public function test_peserta_filter_divisi_mencakup_secondary(): void
    {
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $secondaryMatch = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $data->id,
        ]);
        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => null,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'division_id' => $data->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.id', $secondaryMatch->id));
    }

    public function test_per_page_melebihi_maks_ditolak_validasi(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'per_page' => 101,
            ]))
            ->assertSessionHasErrors('per_page');
    }

    public function test_per_page_non_numerik_ditolak_validasi(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'per_page' => 'abc',
            ]))
            ->assertSessionHasErrors('per_page');
    }
}
