<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableSsrForPrivatePages
{
    /**
     * Matikan Inertia SSR untuk halaman privat: area ini noindex (tanpa manfaat SEO),
     * dan form-nya bisa ter-submit native sebelum Vue mount bila HTML berasal dari SSR.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isPrivatePath($request->path())) {
            config(['inertia.ssr.enabled' => false]);
        }

        return $next($request);
    }

    /** Cocokkan path privat yang tidak butuh SSR. */
    private function isPrivatePath(string $path): bool
    {
        $path = '/'.ltrim($path, '/');

        foreach (['/auth', '/admin', '/dashboard', '/joined', '/recruitment'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
