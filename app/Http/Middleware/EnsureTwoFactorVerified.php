<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang faktor kedua: user ber-PIN yang belum lolos tantangan pada sesi ini hanya boleh
 * menyentuh route allowlist (spec §6.3). Middleware ini ikut grup `web` namun hanya bertindak
 * pada route yang menuntut `auth`, sehingga route publik dan 404 tidak pernah dialihkan.
 */
class EnsureTwoFactorVerified
{
    /** Penanda sesi: user id yang sudah lolos tantangan pada sesi ini. */
    public const SESSION_USER_ID = 'two_factor_user_id';

    /** Penanda sesi: waktu lolos tantangan; tanpa nilai ini penanda user id diabaikan. */
    public const SESSION_VERIFIED_AT = 'two_factor_verified_at';

    /** Penanda sesi: OTP pemulihan sudah dikonfirmasi; belum berarti faktor kedua lolos. */
    public const SESSION_RECOVERY_USER_ID = 'two_factor_recovery_user_id';

    /** Allowlist berdasarkan nama route: halaman tantangan, logout, dan verifikasi email. */
    public const ALLOWED_ROUTE_NAMES = ['two-factor.*', 'auth.logout', 'verification.*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->routeRequiresAuthentication($request)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->has_pin_2fa) {
            return $next($request);
        }

        if (self::isSatisfied($request, $user) || $this->isAllowlisted($request)) {
            return $next($request);
        }

        return $this->redirectToChallenge($request);
    }

    /** Apakah sesi ini sudah membuktikan faktor kedua milik user tersebut. */
    public static function isSatisfied(Request $request, User $user): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $userId = $request->session()->get(self::SESSION_USER_ID);
        $verifiedAt = $request->session()->get(self::SESSION_VERIFIED_AT);

        // Penanda milik user lain (mis. sesi dipakai bergantian) tidak pernah berlaku.
        return is_string($userId)
            && $userId === (string) $user->getAuthIdentifier()
            && is_string($verifiedAt)
            && $verifiedAt !== '';
    }

    /** Tandai sesi lolos faktor kedua; panggil setelah `session()->regenerate()`. */
    public static function markVerified(Request $request, User $user): void
    {
        $request->session()->put([
            self::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
            self::SESSION_VERIFIED_AT => now()->toIso8601String(),
        ]);
    }

    /** Hapus penanda faktor kedua, mis. setelah PIN dinonaktifkan. */
    public static function clearVerified(Request $request): void
    {
        $request->session()->forget([self::SESSION_USER_ID, self::SESSION_VERIFIED_AT]);
    }

    /**
     *
     * Gerbang hanya berlaku pada route yang memang menuntut login: route publik (`home`, `seo.robots`,
     * `storage.local`) dan request tanpa route (404) tidak boleh dialihkan ke halaman tantangan.
     *
     */
    private function routeRequiresAuthentication(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth' || str_starts_with($middleware, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    /** Route tantangan, logout, dan verifikasi email tetap terbuka agar tidak terjadi loop. */
    private function isAllowlisted(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        foreach (self::ALLOWED_ROUTE_NAMES as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /** Simpan URL asal sebagai `url.intended`, lalu kirim user ke halaman tantangan. */
    private function redirectToChallenge(Request $request): Response
    {
        if ($request->hasSession() && $request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $target = route('two-factor.challenge');

        // Navigasi Inertia butuh 409 + X-Inertia-Location agar klien melakukan kunjungan penuh.
        if ($request->header('X-Inertia') !== null) {
            return Inertia::location($target);
        }

        return redirect($target);
    }
}
