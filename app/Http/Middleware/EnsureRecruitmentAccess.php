<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akses modul OpRec: recruitment.dashboard.view atau super-admin.
 */
class EnsureRecruitmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('auth.login'));
        }

        if ($user->hasRole('super-admin') || $user->can('recruitment.dashboard.view')) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
