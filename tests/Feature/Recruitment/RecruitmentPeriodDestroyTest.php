<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodDestroyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_user_berwenang_dapat_menghapus_periode(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create(['name' => 'OpRec Terhapus']);

        $this->actingAs($admin)
            ->delete(route('dashboard.recruitment.periods.destroy', $period))
            ->assertRedirect(route('dashboard.recruitment.index'))
            ->assertSessionHas('inertia.flash_data', fn ($flash) => ($flash['toast']['message'] ?? null) === 'Periode recruitment berhasil dihapus.'
                && ($flash['toast']['type'] ?? null) === 'success');

        $this->assertSoftDeleted('recruitment_periods', ['id' => $period->id]);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('periods.data', 0));
    }

    public function test_user_tanpa_permission_delete_ditolak(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($staff)
            ->delete(route('dashboard.recruitment.periods.destroy', $period))
            ->assertForbidden();

        $this->assertNotSoftDeleted('recruitment_periods', ['id' => $period->id]);
    }

    public function test_payload_index_memuat_can_delete_sesuai_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        RecruitmentPeriod::factory()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('periods.data.0.can_delete', true));

        $this->actingAs($staff)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('periods.data.0.can_delete', false));
    }
}
