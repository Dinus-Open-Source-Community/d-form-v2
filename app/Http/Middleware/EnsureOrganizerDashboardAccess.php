<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya penyelenggara (permission events.list): admin / super-admin.
 * Peserta biasa diarahkan ke portal pengguna, tanpa 403.
 */
class EnsureOrganizerDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('auth.login'));
        }

        if (! $user->can('events.list')) {
            return redirect()->route('dashboard.user.events');
        }

        return $next($request);
    }
}
