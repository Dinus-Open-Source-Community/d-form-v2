<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Tujuan pasca-login mengikuti kemampuan user, bukan satu dasbor tunggal. */
class PostAuthDestinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_member_login_lands_on_the_joined_portal(): void
    {
        $member = $this->userWithRole('member');

        $this->post('/auth/login', [
            'email' => $member->email,
            'password' => 'rahasia-uji',
        ])->assertRedirect(route('dashboard.user.events'));

        $this->assertAuthenticatedAs($member);
    }

    public function test_organizer_login_lands_on_the_organizer_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->post('/auth/login', [
            'email' => $admin->email,
            'password' => 'rahasia-uji',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /** User aktif dengan kredensial kata sandi yang diketahui. */
    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['password' => Hash::make('rahasia-uji')]);
        $user->assignRole($role);

        return $user;
    }
}
