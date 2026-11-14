<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OAuthAccountLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

class OAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request, OAuthAccountLinker $linker): RedirectResponse
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
            $this->logOAuthCallbackFailure('google', $request, $e);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to login with Google. Please try again.',
            ]);

            return redirect()->route('auth.login');
        }
    }

    public function redirectToGithub(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function handleGithubCallback(Request $request, OAuthAccountLinker $linker): RedirectResponse
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
            $this->logOAuthCallbackFailure('github', $request, $e);
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

    /** Record safe OAuth failure metadata without logging provider payloads or exception messages. */
    private function logOAuthCallbackFailure(string $provider, Request $request, \Exception $exception): void
    {
        $requestId = $request->header('X-Request-ID');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        Log::error('oauth_callback_failed', [
            'provider' => $provider,
            'route' => $request->route()?->getName(),
            'request_id' => $requestId,
            'exception_class' => $exception::class,
        ]);
    }
}
