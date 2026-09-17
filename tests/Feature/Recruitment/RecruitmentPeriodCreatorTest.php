<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_store_mengisi_created_by_dengan_user_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec Creator',
            ])
            ->assertRedirect();

        $period = RecruitmentPeriod::query()->where('name', 'OpRec Creator')->firstOrFail();

        $this->assertSame($admin->id, $period->created_by);
    }

    public function test_payload_dashboard_memuat_creator_dengan_avatar(): void
    {
        $admin = User::factory()->create(['avatar' => 'https://example.com/avatars/admin.png']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec Creator Payload',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('periods.data', 1)
                ->where('periods.data.0.creator.name', $admin->name)
                ->where('periods.data.0.creator.avatar_url', 'https://example.com/avatars/admin.png'));
    }

    public function test_periode_lama_tanpa_creator_tetap_valid_dan_payload_null(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create();

        $this->assertNull($period->created_by);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('periods.data.0.creator', null));

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('period.creator', null));
    }
}
