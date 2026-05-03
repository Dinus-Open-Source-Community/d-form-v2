<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStoreRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RegisterController extends Controller
{
    // method for GET: /auth/register
    public function index()
    {
        return inertia('Auth/Register');
    }

    // method for POST: /auth/register
    public function store(RegisterStoreRequest $request)
    {
        $newUserData = $request->validated();

        try {
            DB::transaction(function () use ($newUserData) {
                $user = User::create(collect($newUserData)->only(['name', 'email', 'password'])->all());

                $user->assignRole('member');

                Auth::login($user);
            });

            $request->session()->regenerate();

            Inertia::flash('toast', [
                'message' => 'Register success',
                'type' => 'success'
            ]);

            return redirect()->route('dashboard.user.events');
        } catch (\Exception $e) {
            return Inertia::flash('toast', [
                'message' => 'Register failed',
                'type' => 'error'
             ])->back();
        }
    }
}
