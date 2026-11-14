<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRobotsTagForPrivateAreas
{
    /** Set header X-Robots-Tag untuk area autentikasi / dashboard / auth agar tidak terindeks walau ter-crawl. */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldNoIndexPath($request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive', true);
        }

        return $response;
    }

    private function shouldNoIndexPath(string $path): bool
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/info') {
            return true;
        }

        $prefixes = [
            '/admin',
            '/user',
            '/dashboard',
            '/auth',
        ];

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
