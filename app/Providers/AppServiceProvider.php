<?php

namespace App\Providers;

use App\Http\Middleware\StressBypassThrottle;
use App\Services\Recruitment\RecruitmentTrackingSession;
use App\Support\PendingRegistrationSession;
use App\Support\RedactedDatabaseUuidFailedJobProvider;
use App\Support\RedactedFailedJobProvider;
use App\Support\RedactedPrunableFailedJobProvider;
use App\Support\Security\ProductionSecurityPolicy;
use App\Support\Security\SecurityEnvironment;
use App\Support\Security\SessionCookieSettings;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Register any application services. */
    public function register(): void
    {
        // The binding resolves lazily, so this runs on the queue failure path rather than at boot.
        // An unrecognised provider is kept instead of throwing: a broken failure handler would
        // also hide the job error that is being recorded.
        $this->app->extend('queue.failer', function (mixed $provider, ApplicationContract $app): mixed {
            if (! $provider instanceof FailedJobProviderInterface) {
                Log::warning('queue.failed_provider_not_redacted', [
                    'provider' => is_object($provider) ? $provider::class : get_debug_type($provider),
                ]);

                return $provider;
            }

            // The resolved instance is replaced by an equivalent provider built from the same
            // config keys, because the UUID adapter's insert cannot be redacted from outside.
            if ($provider instanceof DatabaseUuidFailedJobProvider) {
                return new RedactedDatabaseUuidFailedJobProvider(
                    $app->make(ConnectionResolverInterface::class),
                    (string) config('queue.failed.database'),
                    (string) config('queue.failed.table'),
                );
            }

            // The legacy driver never writes the NOT NULL failed_jobs.uuid column, so the insert
            // would throw exactly when a job fails. Warning instead of throwing keeps that path
            // recording, and surfaces the misconfiguration before the first incident.
            if ($provider instanceof DatabaseFailedJobProvider) {
                Log::warning('queue.failed_provider_legacy_database', [
                    'provider' => $provider::class,
                    'reason' => 'legacy database driver does not populate failed_jobs.uuid',
                ]);
            }

            if ($provider instanceof PrunableFailedJobProvider) {
                return new RedactedPrunableFailedJobProvider($provider);
            }

            return new RedactedFailedJobProvider($provider);
        });
    }

    /** Bootstrap any application services. */
    public function boot(): void
    {
        // Resolve the failer at deploy time when it is not the schema-compatible default, so a
        // misconfigured failed-job driver warns during boot instead of on the first job failure.
        if (config('queue.failed.driver') !== 'database-uuids') {
            $this->app->make('queue.failer');
        }

        $policy = new ProductionSecurityPolicy();

        $policy->assertProductionSafe(new SessionCookieSettings(
            isProduction: SecurityEnvironment::isProduction(),
            secure: SecurityEnvironment::flag(config('session.secure'), false),
            httpOnly: SecurityEnvironment::flag(config('session.http_only'), true),
            sameSite: SecurityEnvironment::sameSite(config('session.same_site')),
        ));

        $policy->assertCspModeSafe(
            SecurityEnvironment::cspMode(config('security.csp.mode')),
            SecurityEnvironment::flag(config('security.csp.allow_off_in_production'), false),
        );

        $policy->assertCspReportUriFirstParty(config('security.csp.report_uri'));

        Carbon::setLocale(App::getLocale());

        // Uji beban non-production boleh melewati rate limiter lewat STRESS_BYPASS_THROTTLE.
        if (! App::isProduction() && (bool) config('stress.bypass_throttle')) {
            app('router')->aliasMiddleware('throttle', StressBypassThrottle::class);
        }

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $path = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false);

            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
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

        // Mutation portal tracking OpRec. Kunci memakai id aplikasi dari sesi
        // tracking (satu pendaftar = satu bucket) supaya pendaftar di belakang
        // NAT tidak saling memakan kuota; request tanpa sesi jatuh ke IP agar
        // flood tetap dibatasi sebelum guard sesi berjalan.
        foreach (['update', 'correction', 'feedback'] as $trackingMutation) {
            RateLimiter::for('oprec-track-'.$trackingMutation, function (Request $request) use ($trackingMutation): Limit {
                return $this->recruitmentTrackingLimit($trackingMutation, $request);
            });
        }

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

        // Berkas jawaban form sensitif: batasi per user/IP agar signed URL yang
        // bocor tidak bisa dipakai untuk scraping massal.
        RateLimiter::for('form-answer-file', function (Request $request): Limit {
            return Limit::perMinute((int) config('events.forms.file_download_rate_limit_per_minute', 120))
                ->by($request->user()?->id ?? $request->ip());
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

        RateLimiter::for('recruitment-application-search', function (Request $request): Limit {
            return Limit::perMinute((int) config('recruitment.application_search_rate_limit_per_minute', 60))
                ->by($this->authenticatedUserOrIpKey($request));
        });

        RateLimiter::for('recruitment-export', function (Request $request): Limit {
            return Limit::perMinute((int) config('recruitment.export_rate_limit_per_minute', 10))
                ->by($this->authenticatedUserOrIpKey($request));
        });

        RateLimiter::for('broadcast-test', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.broadcast_test_rate_limit_per_minute', 5))
                ->by($this->authenticatedUserOrIpKey($request));
        });

        RateLimiter::for('recruitment-send-qr', function (Request $request): Limit {
            return Limit::perMinute((int) config('recruitment.qr_send_rate_limit_per_minute', 1))
                ->by(sprintf(
                    'recruitment-send-qr|%s|%s',
                    $this->authenticatedUserOrIpKey($request),
                    $this->routeParameterKey($request, 'period'),
                ));
        });

        // Submit form dikunci per user + form: kuota satu peserta tidak memakan kuota
        // peserta lain, sementara retry validasi tetap punya ruang di bawah batas.
        RateLimiter::for('form-submit', function (Request $request): Limit {
            return Limit::perMinute((int) config('events.forms.submit_rate_limit_per_minute', 10))
                ->by(sprintf(
                    '%s|%s|%s',
                    $request->user()?->id ?? $request->ip(),
                    $this->routeParameterKey($request, 'event'),
                    $this->routeParameterKey($request, 'form'),
                ));
        });

        RateLimiter::for('form-review-bulk', function (Request $request): Limit {
            return Limit::perMinute((int) config('events.forms.bulk_review_rate_limit_per_minute', 30))
                ->by(sprintf(
                    '%s|%s|%s',
                    $request->user()?->id ?? $request->ip(),
                    $this->routeParameterKey($request, 'event'),
                    $this->routeParameterKey($request, 'form'),
                ));
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

        // Endpoint guest memakai IP; endpoint ber-auth dashboards memakai user id,
        // supaya kuota satu IP bersama (NAT kantor) tidak memblokir peserta lain.
        RateLimiter::for('auth-email-check', function (Request $request): Limit {
            return $this->authThrottleLimit('email_check', $request->user()?->id ?? $request->ip());
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

    /** Batas satu mutation tracking OpRec. */
    private function recruitmentTrackingLimit(string $mutation, Request $request): Limit
    {
        $applicationId = $request->hasSession()
            ? $request->session()->get(RecruitmentTrackingSession::APPLICATION_ID_KEY)
            : null;

        $identity = is_string($applicationId) && $applicationId !== ''
            ? 'application:'.$applicationId
            : 'ip:'.$request->ip();

        $maxAttempts = (int) config('recruitment.tracking_mutation_throttle.'.$mutation);

        return Limit::perMinute($maxAttempts)->by('oprec-track-'.$mutation.'|'.$identity);
    }

    /** Nilai route parameter sebagai string yang sudah dikanonikalisasi. */
    private function routeParameterKey(Request $request, string $parameter): string
    {
        $value = $request->route($parameter);

        if ($value instanceof Model) {
            return (string) $value->getKey();
        }

        return strtolower(trim((string) $value));
    }

    /** Give each signed-in user a bucket and use IP only when no user is present. */
    private function authenticatedUserOrIpKey(Request $request): string
    {
        $user = $request->user();

        return $user !== null
            ? 'user:'.$user->getAuthIdentifier()
            : 'ip:'.($request->ip() ?? '');
    }

    /** Bangun batas percobaan dari config/auth_throttle.php agar angka tidak ditulis di route. */
    private function authThrottleLimit(string $endpoint, string|int|null $key): Limit
    {
        return Limit::perMinutes(
            (int) config('auth_throttle.'.$endpoint.'.decay_minutes'),
            (int) config('auth_throttle.'.$endpoint.'.max_attempts'),
        )->by((string) ($key ?? ''));
    }
}
