<?php

use App\Exceptions\QuotaExceededException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('recruitment:send-interview-reminders')->everyFifteenMinutes();
        $schedule->command('broadcast:dispatch-scheduled')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        $middleware->redirectUsersTo(fn () => route('home', absolute: false));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            \App\Http\Middleware\AddRobotsTagForPrivateAreas::class,
        ]);

        $middleware->alias([
            'organizer' => \App\Http\Middleware\EnsureOrganizerDashboardAccess::class,
            'member_portal' => \App\Http\Middleware\EnsureMemberPortalAccess::class,
            'broadcast.access' => \App\Http\Middleware\EnsureBroadcastAccess::class,
            'recruitment.access' => \App\Http\Middleware\EnsureRecruitmentAccess::class,
            'recruitment.period.open' => \App\Http\Middleware\EnsureRecruitmentPeriodOpen::class,
            'recruitment.tracking.session' => \App\Http\Middleware\EnsureTrackingSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia membuang header rate limit; salin ulang agar klien tahu kapan boleh mencoba lagi.
        $copyThrottleHeaders = static function (Response $source, Response $target): Response {
            $headers = ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

            foreach ($headers as $header) {
                $value = $source->headers->get($header);

                if ($value !== null) {
                    $target->headers->set($header, $value);
                }
            }

            return $target;
        };

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            Log::warning('http_429_throttle', [
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
            ]);

            return null;
        });

        $exceptions->render(function (QuotaExceededException $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                Inertia::flash('toast', [
                    'type'    => 'error',
                    'message' => $e->getMessage(),
                ]);

                return redirect()->back();
            }

            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) use ($copyThrottleHeaders) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                if ($request->header('X-Inertia')) {
                    return redirect()
                        ->back()
                        ->with('message', 'Halaman kedaluwarsa, silakan coba lagi.');
                }

                return $response;
            }

            if (! in_array($status, [403, 404, 429, 500, 502, 503], true)) {
                return $response;
            }

            $rendered = Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);

            if ($status === 429) {
                return $copyThrottleHeaders($response, $rendered);
            }

            return $rendered;
        });
    })->create();
