<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodEditRemovedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_b24_get_edit_lama_mengembalikan_404(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/recruitment/periods/{$period->id}/edit")
            ->assertNotFound();
    }
}
