<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginStoreRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    // method for GET: /auth/login
    public function index(Request $request)
    {
        $intended = $request->query('intended');

        if ($this->isSafeLocalIntended($intended)) {
            $request->session()->put('url.intended', $intended);
        }

        return inertia('Auth/Login');
    }

    /**
     * Only accept same-origin, path-only redirect targets. Rejects protocol-relative
     * URLs, backslash tricks, header-injection characters, and absolute URLs.
     */
    private function isSafeLocalIntended(mixed $intended): bool
    {
        if (! is_string($intended) || $intended === '') {
            return false;
        }

        if (! str_starts_with($intended, '/')) {
            return false;
        }

        if (str_starts_with($intended, '//') || str_starts_with($intended, '/\\')) {
            return false;
        }

        if (str_contains($intended, "\n") || str_contains($intended, "\r")) {
            return false;
        }

        if (parse_url($intended, PHP_URL_SCHEME) !== null || parse_url($intended, PHP_URL_HOST) !== null) {
            return false;
        }

        return true;
    }

    // method for POST: /auth/login
    public function store(LoginStoreRequest $request)
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            Inertia::flash('toast', [
                'message' => 'Login success',
                'type' => 'success',
            ]);

            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::flash('toast', [
            'message' => 'Login failed',
            'type' => 'error',
        ])->back();
    }
}
