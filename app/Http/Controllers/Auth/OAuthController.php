<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OAuthAccountLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

class OAuthController extends Controller
{
    /**
     * Redirect to Google OAuth provider
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(OAuthAccountLinker $linker): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $emailVerified = $googleUser instanceof SocialiteTwoUser
                && filter_var(
                    $googleUser->getRaw()['email_verified'] ?? false,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) === true;

            $user = $linker->resolveGoogle($googleUser, $emailVerified);

            if ($user === null) {
                return $this->flashErrorAndRedirect(
                    'Email Google belum terverifikasi. Masuk dengan kata sandi atau daftar ulang.',
                );
            }

            Auth::login($user);

            return $this->flashSuccessAndRedirectIntended();
        } catch (\Exception $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to login with Google. Please try again.',
            ]);

            return redirect()->route('auth.login');
        }
    }

    /**
     * Redirect to GitHub OAuth provider
     */
    public function redirectToGithub(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    /**
     * Handle GitHub OAuth callback
     */
    public function handleGithubCallback(OAuthAccountLinker $linker): RedirectResponse
    {
        try {
            $githubUser = Socialite::driver('github')->user();
            $user = $linker->resolveGithub($githubUser);

            if ($user === null) {
                return $this->flashErrorAndRedirect(
                    'GitHub tidak mengirim email terverifikasi. Masuk dengan kata sandi atau daftar ulang.',
                );
            }

            Auth::login($user);

            return $this->flashSuccessAndRedirectIntended();
        } catch (\Exception $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to login with GitHub. Please try again.',
            ]);

            return redirect()->route('auth.login');
        }
    }

    private function flashErrorAndRedirect(string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect()->route('auth.login');
    }

    private function flashSuccessAndRedirectIntended(): RedirectResponse
    {
        $parts = explode(', ', __('auth.login_success'));

        Inertia::flash('toast', [
            'message' => ($parts[0] ?? 'Welcome').(isset($parts[1]) ? ' — '.$parts[1] : ''),
            'type' => 'success',
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
