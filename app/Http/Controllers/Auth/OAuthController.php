<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ProviderConnectionResult;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OAuthAccountLinker;
use App\Services\Auth\OAuthConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class OAuthController extends Controller
{
    /** Sesi intent bind: sekali pakai, berlaku 10 menit. */
    private const BIND_INTENT_KEY = 'oauth_bind';

    private const BIND_INTENT_TTL_MINUTES = 10;

    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(
        Request $request,
        OAuthAccountLinker $linker,
        OAuthConnectionService $connections,
    ): RedirectResponse {
        $bindIntent = $request->session()->pull(self::BIND_INTENT_KEY, null);

        if (Auth::check()) {
            return $this->handleConnectionCallback($request, SocialProvider::Google, $bindIntent, $connections);
        }

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

    public function handleGithubCallback(
        Request $request,
        OAuthAccountLinker $linker,
        OAuthConnectionService $connections,
    ): RedirectResponse {
        $bindIntent = $request->session()->pull(self::BIND_INTENT_KEY, null);

        if (Auth::check()) {
            return $this->handleConnectionCallback($request, SocialProvider::Github, $bindIntent, $connections);
        }

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

    /** Mulai bind: tulis intent lalu pakai callback URI yang sudah terdaftar. */
    public function redirectToProvider(Request $request, string $provider): SymfonyRedirectResponse
    {
        $socialProvider = SocialProvider::fromRoute($provider);
        abort_if($socialProvider === null, 404);

        if (! $socialProvider->isConfigured()) {
            return $this->flashConnectionErrorAndRedirect('Belum tersedia di server ini.');
        }

        $sessionUser = $request->user();
        abort_if($sessionUser === null, 403);

        $request->session()->put(self::BIND_INTENT_KEY, [
            'provider' => $socialProvider->value,
            'user_id' => $sessionUser->getKey(),
            'expires_at' => now()->addMinutes(self::BIND_INTENT_TTL_MINUTES)->getTimestamp(),
        ]);

        return Socialite::driver($socialProvider->value)->redirect();
    }

    /** Cabang callback untuk user yang sudah login: bind saja, tanpa Auth::login(). */
    private function handleConnectionCallback(
        Request $request,
        SocialProvider $provider,
        mixed $intent,
        OAuthConnectionService $connections,
    ): RedirectResponse {
        $sessionUser = $request->user();
        abort_if($sessionUser === null, 403);

        if (! $this->isValidBindIntent($intent, $provider, $sessionUser->getKey())) {
            return $this->flashConnectionErrorAndRedirect(
                'Sesi menghubungkan akun tidak valid atau sudah kedaluwarsa. Silakan coba lagi.',
            );
        }

        try {
            $identity = Socialite::driver($provider->value)->user();
        } catch (\Exception $e) {
            $this->logOAuthCallbackFailure($provider->value, $request, $e);

            return $this->flashConnectionErrorAndRedirect(
                'Gagal menghubungkan akun '.$provider->label().'. Silakan coba lagi.',
            );
        }

        $account = User::query()->findOrFail($sessionUser->getKey());
        $result = $connections->connect($account, $provider, $identity);

        return $this->flashConnectionResultAndRedirect($provider, $result);
    }

    /** Intent harus milik user ini, provider yang sama, dan belum kedaluwarsa. */
    private function isValidBindIntent(mixed $intent, SocialProvider $provider, string $userId): bool
    {
        if (! is_array($intent)) {
            return false;
        }

        $intentProvider = $intent['provider'] ?? null;
        $intentUserId = $intent['user_id'] ?? null;
        $expiresAt = $intent['expires_at'] ?? null;

        return is_string($intentProvider)
            && $intentProvider === $provider->value
            && is_string($intentUserId)
            && $intentUserId === $userId
            && is_int($expiresAt)
            && $expiresAt >= now()->getTimestamp();
    }

    private function flashConnectionResultAndRedirect(
        SocialProvider $provider,
        ProviderConnectionResult $result,
    ): RedirectResponse {
        if ($result === ProviderConnectionResult::Connected) {
            return $this->flashConnectionSuccessAndRedirect('Akun '.$provider->label().' berhasil dihubungkan.');
        }

        if ($result === ProviderConnectionResult::AlreadyConnected) {
            return $this->flashConnectionSuccessAndRedirect('Akun '.$provider->label().' sudah terhubung.');
        }

        if ($result === ProviderConnectionResult::OwnedByAnotherUser) {
            return $this->flashConnectionErrorAndRedirect(
                'Akun '.$provider->label().' ini sudah terhubung ke pengguna lain.',
            );
        }

        return $this->flashConnectionErrorAndRedirect(
            'Identitas '.$provider->label().' tidak valid. Silakan coba lagi.',
        );
    }

    private function flashConnectionSuccessAndRedirect(string $message): RedirectResponse
    {
        return $this->flashConnectionToastAndRedirect('success', $message);
    }

    private function flashConnectionErrorAndRedirect(string $message): RedirectResponse
    {
        return $this->flashConnectionToastAndRedirect('error', $message);
    }

    private function flashConnectionToastAndRedirect(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);

        return redirect(route('dashboard.profile').'#koneksi');
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
