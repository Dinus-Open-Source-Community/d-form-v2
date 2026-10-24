<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket limiter memakai cache array; kosongkan agar tiap test mulai dari nol.
        Cache::flush();
    }

    public function test_unregistered_email_is_available(): void
    {
        $this->postJson(route('auth.email.availability'), ['email' => 'free.account@example.test'])
            ->assertOk()
            ->assertExactJson(['available' => true]);
    }

    public function test_registered_email_is_unavailable(): void
    {
        User::factory()->create(['email' => 'taken.account@example.test']);

        $this->postJson(route('auth.email.availability'), ['email' => 'taken.account@example.test'])
            ->assertOk()
            ->assertExactJson(['available' => false]);
    }

    public function test_soft_deleted_email_stays_unavailable(): void
    {
        $user = User::factory()->create(['email' => 'deleted.account@example.test']);
        $user->delete();

        $this->postJson(route('auth.email.availability'), ['email' => 'deleted.account@example.test'])
            ->assertOk()
            ->assertExactJson(['available' => false]);
    }

    public function test_empty_email_is_rejected(): void
    {
        $this->postJson(route('auth.email.availability'), ['email' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->postJson(route('auth.email.availability'), ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_check_is_throttled_after_twenty_requests(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson(route('auth.email.availability'), [
                'email' => "probe.{$attempt}@example.test",
            ])->assertOk();
        }

        $this->postJson(route('auth.email.availability'), ['email' => 'probe.21@example.test'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_authenticated_user_is_redirected_by_guest_middleware(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('auth.email.availability'), ['email' => 'free.account@example.test'])
            ->assertRedirect();
    }
}
