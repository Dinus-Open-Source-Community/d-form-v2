<?php

namespace App\Providers;

use App\Http\Middleware\StressBypassThrottle;
use App\Support\PendingRegistrationSession;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(App::getLocale());

        // Uji beban non-production boleh melewati rate limiter lewat STRESS_BYPASS_THROTTLE.
        if (! App::isProduction() && (bool) config('stress.bypass_throttle')) {
            app('router')->aliasMiddleware('throttle', StressBypassThrottle::class);
        }

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });

        Blade::anonymousComponentPath(resource_path() . '/views/layouts', 'layout');
        Blade::anonymousComponentNamespace('components.core', 'core');
        Blade::anonymousComponentNamespace('components.module', 'module');

        RateLimiter::for('oprec-apply', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('oprec-track', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('oprec-queue', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('scan-page', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('scan-feed', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('scan-export', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('scan-store', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Daftar + pencarian acara publik: satu limiter untuk seluruh route index /events.
        // Guard `web` memakai provider kolom eksplisit (tanpa `select *` pada setiap request).
        Auth::provider('explicit-columns', function ($app, array $config): \App\Auth\ExplicitColumnUserProvider {
            return new \App\Auth\ExplicitColumnUserProvider($app['hash'], $config['model']);
        });

        RateLimiter::for('event-search', function (Request $request) {
            return Limit::perMinute((int) config('events.search.rate_limit_per_minute', 60))
                ->by($request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower($input) : '';

            return $this->authThrottleLimit('login', $email.'|'.$request->ip());
        });

        RateLimiter::for('auth-register', function (Request $request): Limit {
            return $this->authThrottleLimit('register', $request->ip());
        });

        RateLimiter::for('auth-password-email', function (Request $request): Limit {
            return $this->authThrottleLimit('password_email', $request->ip());
        });

        RateLimiter::for('auth-password-link', function (Request $request): Limit {
            return $this->authThrottleLimit('password_link', $request->ip());
        });

        RateLimiter::for('auth-password-reset', function (Request $request): Limit {
            return $this->authThrottleLimit('password_reset', $request->ip());
        });

        RateLimiter::for('auth-email-check', function (Request $request): Limit {
            return $this->authThrottleLimit('email_check', $request->ip());
        });

        // Email OTP diambil dari sesi; limiter berjalan setelah StartSession.
        RateLimiter::for('auth-otp-verify', function (Request $request): Limit {
            $email = PendingRegistrationSession::email($request) ?? '';

            return $this->authThrottleLimit('otp_verify', $email.'|'.$request->ip());
        });

        RateLimiter::for('auth-otp-resend', function (Request $request): Limit {
            return $this->authThrottleLimit('otp_resend', $request->ip());
        });

        // Cap lintas window per alamat email agar satu email tidak bisa dispam dari banyak IP.
        RateLimiter::for('auth-otp-address', function (Request $request): Limit {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower(trim($input)) : '';

            return $this->authThrottleLimit('otp_address', $email !== '' ? $email : $request->ip());
        });

        // Temporary scan-test helper: redirect ALL outgoing mail to one inbox.
        // Active only on local + MAIL_TEST_REDIRECT set. Production untouched.
        $testRedirect = config('mail.test_redirect');
        if ($this->app->environment('local') && filled($testRedirect)) {
            Mail::alwaysTo($testRedirect);
        }
    }

    /**
     * Bangun batas percobaan dari config/auth_throttle.php agar angka tidak ditulis di route.
     */
    private function authThrottleLimit(string $endpoint, string $key): Limit
    {
        return Limit::perMinutes(
            (int) config('auth_throttle.'.$endpoint.'.decay_minutes'),
            (int) config('auth_throttle.'.$endpoint.'.max_attempts'),
        )->by($key);
    }
}
