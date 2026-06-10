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
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        $middleware->redirectUsersTo(fn () => route('dashboard', absolute: false));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            \App\Http\Middleware\AddRobotsTagForPrivateAreas::class,
        ]);

        $middleware->alias([
            'organizer' => \App\Http\Middleware\EnsureOrganizerDashboardAccess::class,
            'member_portal' => \App\Http\Middleware\EnsureMemberPortalAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
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

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
