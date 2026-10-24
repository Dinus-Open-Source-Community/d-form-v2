<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpOutcome;
use App\Jobs\SendRegistrationOtpJob;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Auth\RegistrationOtpService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Alur register: membuat pending + kirim OTP, bukan membuat user/login.
 */
class RegisterOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia123';

    private const NEUTRAL_MESSAGE = 'Pendaftaran dengan email ini sedang menunggu verifikasi. Selesaikan verifikasi atau gunakan email lain.';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_register_creates_pending_and_redirects_to_otp_page(): void
    {
        $this->post(route('auth.register.store'), $this->payload('baru@example.test'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHas('otp.pending_email', 'baru@example.test')
            ->assertSessionHas('otp.cancel_token');

        $this->assertSame(32, strlen((string) session('otp.cancel_token')));
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.test']);
        $this->assertDatabaseHas('pending_registrations', ['email' => 'baru@example.test', 'otp_sends' => 1]);
        Queue::assertPushed(SendRegistrationOtpJob::class);
        $this->assertGuest();
    }

    public function test_resubmit_with_same_password_issues_code_on_same_pending(): void
    {
        $this->post(route('auth.register.store'), $this->payload('ulang@example.test'));
        $this->post(route('auth.register.store'), $this->payload('ulang@example.test'))
            ->assertRedirect(route('auth.otp.show'));

        $this->assertSame(1, PendingRegistration::query()->where('email', 'ulang@example.test')->count());
        $this->assertDatabaseHas('pending_registrations', ['email' => 'ulang@example.test', 'otp_sends' => 2]);
    }

    public function test_resubmit_with_different_password_returns_neutral_message(): void
    {
        $this->post(route('auth.register.store'), $this->payload('beda@example.test'));

        $this->post(route('auth.register.store'), $this->payload('beda@example.test', 'password-lain'))
            ->assertRedirect()
            ->assertSessionHas(
                'inertia.flash_data',
                fn ($flash): bool => ($flash['toast']['message'] ?? null) === self::NEUTRAL_MESSAGE
            );

        $this->assertDatabaseHas('pending_registrations', ['email' => 'beda@example.test', 'otp_sends' => 1]);
        $this->assertGuest();
    }

    public function test_register_with_verified_email_fails_unique_validation(): void
    {
        User::factory()->create(['email' => 'terdaftar@example.test']);

        $this->post(route('auth.register.store'), $this->payload('terdaftar@example.test'))
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('pending_registrations', 0);
    }

    public function test_register_while_locked_redirects_to_otp_page_without_new_send(): void
    {
        $service = app(RegistrationOtpService::class);
        $data = ['name' => 'Uji', 'email' => 'kunci@example.test', 'password' => self::PASSWORD];

        for ($send = 0; $send < (int) config('otp.max_sends'); $send++) {
            $service->start($data);
        }

        $this->post(route('auth.register.store'), $this->payload('kunci@example.test'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHas('otp.pending_email', 'kunci@example.test');

        $this->assertDatabaseHas('pending_registrations', [
            'email' => 'kunci@example.test',
            'otp_sends' => (int) config('otp.max_sends'),
        ]);
    }

    public function test_unique_violation_from_parallel_create_is_retried(): void
    {
        $this->mock(RegistrationOtpService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')
                ->once()
                ->andThrow(new UniqueConstraintViolationException(
                    'mysql',
                    'insert into pending_registrations',
                    [],
                    new RuntimeException('duplicate entry')
                ));

            $mock->shouldReceive('start')
                ->once()
                ->andReturn(OtpOutcome::Issued);
        });

        $this->post(route('auth.register.store'), $this->payload('balapan@example.test'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHas('otp.pending_email', 'balapan@example.test');
    }

    /**
     * Payload form register yang valid.
     *
     * @return array{name: string, email: string, password: string, password_confirmation: string}
     */
    private function payload(string $email, string $password = self::PASSWORD): array
    {
        return [
            'name' => 'Uji',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
