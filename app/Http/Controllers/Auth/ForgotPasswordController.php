<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordStoreRequest;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function index()
    {
        return inertia('Auth/ForgotPassword');
    }

    public function store(ForgotPasswordStoreRequest $request)
    {
        Password::sendResetLink($request->only('email'));

        Inertia::flash('toast', [
            'message' => 'If an account exists for that email, we sent a password reset link.',
            'type' => 'success',
        ]);

        return Inertia::back();
    }
}
