<?php

namespace App\Services\User;

use App\Enums\SocialProvider;
use App\Models\User;

/**
 * Menyusun status koneksi provider untuk payload halaman profil.
 *
 * `$user` wajib hasil query ulang lewat primary key, karena session user tidak
 * memuat `google_id`/`github_id` dan status akan selalu `connected: false`.
 */
final class ProfileAccountService
{
    /**
     *
     * @return list<array{provider: string, connected: bool, can_disconnect: bool, configured: bool}>
     *
     */
    public function connections(User $user): array
    {
        $hasPassword = filled($user->getRawOriginal('password'));

        $connected = [];
        foreach (SocialProvider::cases() as $provider) {
            $connected[$provider->value] = filled($user->getAttribute($provider->userColumn()));
        }

        return array_map(
            fn (SocialProvider $provider): array => [
                'provider' => $provider->value,
                'connected' => $connected[$provider->value],
                'can_disconnect' => $this->canDisconnect($provider, $connected, $hasPassword),
                'configured' => $provider->isConfigured(),
            ],
            SocialProvider::cases(),
        );
    }

    /**
     * @param  array<string, bool>  $connected
     */
    private function canDisconnect(SocialProvider $provider, array $connected, bool $hasPassword): bool
    {
        if (! $connected[$provider->value]) {
            return false;
        }

        if ($hasPassword) {
            return true;
        }

        $otherProvider = $provider === SocialProvider::Google ? SocialProvider::Github : SocialProvider::Google;

        return $connected[$otherProvider->value];
    }
}
