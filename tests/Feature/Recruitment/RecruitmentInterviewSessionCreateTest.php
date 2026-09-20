<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentInterviewSessionCreateTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->period = RecruitmentPeriod::factory()->create();
        $this->division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
    }

    private function scheduler(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['recruitment.periods.view', 'recruitment.interviews.schedule']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'location' => 'Gedung A',
            'room' => 'A101',
        ], $overrides);
    }

    public function test_store_menolak_jam_selesai_sebelum_mulai(): void
    {
        $this->actingAs($this->scheduler())
            ->post(route('dashboard.recruitment.interview-sessions.store'), $this->payload([
                'starts_at' => '12:00',
                'ends_at' => '09:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('recruitment_interview_sessions', 0);
    }

    public function test_store_menolak_tanggal_lampau(): void
    {
        $this->actingAs($this->scheduler())
            ->post(route('dashboard.recruitment.interview-sessions.store'), $this->payload([
                'session_date' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('session_date');

        $this->assertDatabaseCount('recruitment_interview_sessions', 0);
    }

    public function test_store_menolak_divisi_nonaktif(): void
    {
        $inactive = RecruitmentDivision::factory()->create(['is_active' => false]);

        $this->actingAs($this->scheduler())
            ->post(route('dashboard.recruitment.interview-sessions.store'), $this->payload([
                'recruitment_division_id' => $inactive->id,
            ]))
            ->assertSessionHasErrors('recruitment_division_id');

        $this->assertDatabaseCount('recruitment_interview_sessions', 0);
    }

    public function test_store_menolak_periode_kosong(): void
    {
        $this->actingAs($this->scheduler())
            ->post(route('dashboard.recruitment.interview-sessions.store'), $this->payload([
                'recruitment_period_id' => '',
            ]))
            ->assertSessionHasErrors('recruitment_period_id');

        $this->assertDatabaseCount('recruitment_interview_sessions', 0);
    }

    public function test_store_mengalihkan_ke_tab_interview_periode(): void
    {
        $this->actingAs($this->scheduler())
            ->post(route('dashboard.recruitment.interview-sessions.store'), $this->payload())
            ->assertRedirect(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]));

        $this->assertDatabaseHas('recruitment_interview_sessions', [
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'location' => 'Gedung A',
            'room' => 'A101',
        ]);
    }

    public function test_tab_interview_memuat_opsi_divisi_aktif(): void
    {
        $inactive = RecruitmentDivision::factory()->create(['is_active' => false]);

        $this->actingAs($this->scheduler())
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('interview_division_options')
                ->where('interview_division_options', function ($options) use ($inactive): bool {
                    $ids = collect($options)->pluck('id');

                    return $ids->contains($this->division->id)
                        && ! $ids->contains($inactive->id);
                }));
    }

    public function test_tab_interview_ditolak_tanpa_permission_jadwal(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->actingAs($interviewer)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'interview',
            ]))
            ->assertForbidden();
    }
}
