<?php

use App\Exceptions\QuotaExceededException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        $middleware->web(append: [
            HandleInertiaRequests::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
    })->create();
