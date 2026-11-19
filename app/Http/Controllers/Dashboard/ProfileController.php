<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ProviderDisconnectionResult;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDashboardProfileAvatarRequest;
use App\Http\Requests\UpdateDashboardProfilePasswordRequest;
use App\Http\Requests\UpdateDashboardProfileRequest;
use App\Models\User;
use App\Services\Auth\OAuthConnectionService;
use App\Services\Auth\TwoFactorPinService;
use App\Services\User\ProfileAccountService;
use App\Services\User\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /** Halaman profil: koneksi akun dan status PIN faktor kedua untuk sakelar di kartu PIN. */
    public function show(Request $request, ProfileAccountService $accounts, TwoFactorPinService $pins): Response
    {
        $sessionUser = $request->user();
        abort_if($sessionUser === null, 403);

        $user = User::query()->findOrFail($sessionUser->getKey());

        return Inertia::render('Dashboard/Profile', [
            'connections' => $accounts->connections($user),
            'two_factor' => $pins->status($user),
        ]);
    }

    public function update(UpdateDashboardProfileRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();

        if (($validated['email'] ?? null) !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profile updated successfully.',
        ]);

        return redirect()->back();
    }

    public function updateAvatar(UpdateDashboardProfileAvatarRequest $request, UserAvatarService $avatars): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $file = $request->file('avatar');
        if (! $file instanceof UploadedFile) {
            return redirect()->back();
        }

        $avatars->update($user, $file);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Foto profil berhasil diperbarui.',
        ]);

        return redirect()->back();
    }

    public function destroyAvatar(Request $request, UserAvatarService $avatars): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $avatars->clear($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Foto profil dihapus.',
        ]);

        return redirect()->back();
    }

    public function updatePassword(UpdateDashboardProfilePasswordRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Password updated successfully.',
        ]);

        return redirect()->back();
    }

    public function destroyConnection(Request $request, string $provider, OAuthConnectionService $connections): RedirectResponse
    {
        $socialProvider = SocialProvider::fromRoute($provider);
        abort_if($socialProvider === null, 404);

        $sessionUser = $request->user();
        abort_if($sessionUser === null, 403);

        $user = User::query()->findOrFail($sessionUser->getKey());
        $result = $connections->disconnect($user, $socialProvider);

        $this->flashDisconnectionResult($socialProvider, $result);

        return redirect()->back();
    }

    private function flashDisconnectionResult(SocialProvider $provider, ProviderDisconnectionResult $result): void
    {
        Inertia::flash('toast', match ($result) {
            ProviderDisconnectionResult::Disconnected => [
                'type' => 'success',
                'message' => 'Akun '.$provider->label().' berhasil dilepas.',
            ],
            ProviderDisconnectionResult::NotConnected => [
                'type' => 'error',
                'message' => 'Akun '.$provider->label().' belum terhubung.',
            ],
            ProviderDisconnectionResult::LastCredential => [
                'type' => 'error',
                'message' => 'Hubungkan kata sandi atau akun lain dulu supaya Anda tidak kehilangan akses.',
            ],
        });
    }
}
