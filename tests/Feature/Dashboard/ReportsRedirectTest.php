<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Redirect lawas `/admin/reports` tetap berlaku setelah halaman laporan event dihapus. */
class ReportsRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    public function test_member_cannot_view_reports_dashboard(): void
    {
        $this->actingAs($this->member())
            ->get(route('dashboard.reports.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_reports_route_redirects_to_events_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.reports.index'))
            ->assertRedirect(route('dashboard.events.index'));
    }
}
