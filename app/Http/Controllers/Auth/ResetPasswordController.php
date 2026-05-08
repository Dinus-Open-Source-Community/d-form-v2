<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordStoreRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResetPasswordController extends Controller
{
    public function index(string $token)
    {
        return inertia('Auth/ResetPassword', [
            'token' => $token,
            'email' => request()->string('email')->value(),
        ]);
    }

    public function store(ResetPasswordStoreRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => $request->validated('password'),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            Inertia::flash('toast', [
                'message' => 'Your password has been reset. You can sign in.',
                'type' => 'success',
            ]);

            return redirect()->route('auth.login');
        }

        Inertia::flash('toast', [
            'message' => __($status),
            'type' => 'error',
        ]);

        return redirect()->back();
    }
}
