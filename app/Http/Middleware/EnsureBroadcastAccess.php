<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terisolasi untuk modul Email Broadcasting (PRD §6).
 * Mengandalkan permission Spatie, bukan hardcode role.
 */
class EnsureBroadcastAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('auth.login'));
        }

        if (! $user->can('email-broadcast.view')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
