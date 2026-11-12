<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melewati rate limiter tanpa mengecek batas; dipakai hanya saat uji beban non-production.
 */
final class StressBypassThrottle
{
    /**
     * Teruskan request ke handler berikutnya tanpa menerapkan throttle.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
