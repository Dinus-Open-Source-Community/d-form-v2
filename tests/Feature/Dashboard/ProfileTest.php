<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Auth\TwoFactorPinService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_when_updating_profile(): void
    {
        $this->patch(route('dashboard.profile.update'), [
            'name' => 'N',
            'email' => 'n@example.com',
        ])
            ->assertRedirectToRoute('auth.login');
    }

    public function test_authenticated_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old',
            'email' => 'old@example.com',
        ]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->patch(route('dashboard.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create(['email' => 'mine@example.com']);
        $user->assignRole('member');

        $this->actingAs($user)
            ->patch(route('dashboard.profile.update'), [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_keep_same_email_without_uniqueness_error(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);
        $user->assignRole('member');

        $this->actingAs($user)
            ->patch(route('dashboard.profile.update'), [
                'name' => 'Updated',
                'email' => 'same@example.com',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated', $user->name);
    }

    public function test_password_update_requires_correct_current_password_when_user_has_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->put(route('dashboard.profile.password.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-99',
                'password_confirmation' => 'new-password-99',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
    }

    public function test_password_update_succeeds_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->put(route('dashboard.profile.password.update'), [
                'current_password' => 'correct-password',
                'password' => 'new-password-99',
                'password_confirmation' => 'new-password-99',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-99', $user->fresh()->password));
    }

    public function test_user_without_local_password_can_set_password_without_current(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->put(route('dashboard.profile.password.update'), [
                'password' => 'first-password-99',
                'password_confirmation' => 'first-password-99',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('first-password-99', $user->fresh()->password));
    }

    public function test_after_setting_password_current_password_is_required_for_further_changes(): void
    {
        $user = User::factory()->create(['password' => null]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->put(route('dashboard.profile.password.update'), [
                'password' => 'first-password-99',
                'password_confirmation' => 'first-password-99',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user->fresh())
            ->put(route('dashboard.profile.password.update'), [
                'password' => 'second-password-99',
                'password_confirmation' => 'second-password-99',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user->fresh())
            ->put(route('dashboard.profile.password.update'), [
                'current_password' => 'first-password-99',
                'password' => 'second-password-99',
                'password_confirmation' => 'second-password-99',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('second-password-99', $user->fresh()->password));
    }

    public function test_profile_page_sends_the_inactive_pin_status(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $user->assignRole('member');

        $this->actingAs($user)
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Profile')
                ->where('two_factor.enabled', false)
                ->where('two_factor.attemptsRemaining', TwoFactorPinService::MAX_FAILED_ATTEMPTS)
                ->where('two_factor.lockedUntil', null));
    }

    public function test_profile_page_sends_the_active_pin_status(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $user->assignRole('member');
        app(TwoFactorPinService::class)->enable($user, '123456');

        // User ber-PIN hanya boleh membuka profil setelah lolos tantangan pada sesi ini.
        $this->actingAs($user)
            ->withSession([
                'two_factor_user_id' => $user->getKey(),
                'two_factor_verified_at' => now()->toIso8601String(),
            ])
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Profile')
                ->where('two_factor.enabled', true)
                ->where('two_factor.attemptsRemaining', TwoFactorPinService::MAX_FAILED_ATTEMPTS)
                ->where('two_factor.lockedUntil', null));
    }

    public function test_profile_routes_are_throttled(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->patch(route('dashboard.profile.update'), [
                    'name' => 'T'.$i,
                    'email' => 't'.$i.'@example.com',
                ]);
        }

        $this->actingAs($user)
            ->patch(route('dashboard.profile.update'), [
                'name' => 'Too many',
                'email' => 'too@example.com',
            ])
            ->assertStatus(429);
    }

    public function test_authenticated_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        $user->assignRole('member');

        $file = UploadedFile::fake()->image('face.jpg', 100, 100);

        $this->actingAs($user)
            ->post(route('dashboard.profile.avatar.update'), [
                'avatar' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/'.$user->id.'/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_avatar_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('member');

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('dashboard.profile.avatar.update'), [
                'avatar' => $file,
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_user_can_delete_stored_avatar_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        $user->assignRole('member');

        $path = 'avatars/'.$user->id.'/stored.jpg';
        Storage::disk('public')->put($path, 'binary');
        $user->forceFill(['avatar' => $path])->save();

        $this->actingAs($user)
            ->delete(route('dashboard.profile.avatar.destroy'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing($path);
    }
}
