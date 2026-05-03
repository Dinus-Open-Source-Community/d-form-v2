<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginStoreRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    // method for GET: /auth/login
    public function index()
    {
        return inertia('Auth/Login');
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

            /** @var User $user */
            $user = Auth::user();

            $default = $user->hasRole('member') && ! $user->hasAnyRole(['admin', 'super-admin'])
                ? route('dashboard.user.events', absolute: false)
                : '/dashboard';

            return redirect()->intended($default);
        }

        return Inertia::flash('toast', [
            'message' => 'Login failed',
            'type' => 'error',
        ])->back();
    }
}
