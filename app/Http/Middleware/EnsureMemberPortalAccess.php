<?php

namespace App\Http\Middleware;

use App\Support\PostAuthDestination;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Portal /joined/* — peserta; penyelenggara murni diarahkan ke dasbor utama. */
class EnsureMemberPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('auth.login'));
        }

        if ($user->can('events.list') && ! $user->hasAnyRole(['member', 'super-admin'])) {
            return redirect()->to(PostAuthDestination::urlFor($user));
        }

        return $next($request);
    }
}
