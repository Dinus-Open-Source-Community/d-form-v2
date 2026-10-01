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

    public function test_tab_peserta_page_dua_meta_paginator_tepat(): void
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
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'peserta')
                ->where('applications.current_page', 2)
                ->where('applications.per_page', 15)
                ->where('applications.total', 16)
                ->where('applications.last_page', 2)
                ->has('applications.data', 1)
                ->missing('broadcasts')
                ->missing('sessions')
                ->missing('report'));
    }

    public function test_tab_broadcast_page_satu_memuat_rows_ter_scoped(): void
    {
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
                ->where('applications.per_page', 15)
                ->where('applications.total', 1)
                ->has('applications.data', 1));
    }
}
