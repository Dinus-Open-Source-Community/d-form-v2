<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal /events/joined/* — peserta; penyelenggara murni diarahkan ke /admin/dashboard.
 * Akun yang hanya penyelenggara (punya events.list, tanpa peran member/super-admin)
 * diarahkan ke dasbor utama agar tidak campur alur admin.
 */
class EnsureMemberPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('auth.login'));
        }

        if ($user->can('events.list') && ! $user->hasAnyRole(['member', 'super-admin'])) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
