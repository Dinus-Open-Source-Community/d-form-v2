<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Menautkan identitas OAuth ke akun lokal.
 *
 * Provider subject ID adalah identitas utama. Email hanya dipakai untuk mencari akun
 * bila provider menyatakan email tersebut verified; tanpa itu akun yang sudah ada
 * tidak boleh diambil alih lewat login OAuth.
 */
final class OAuthAccountLinker
{
    /**
     * Google: `email_verified` pada userinfo menentukan apakah email boleh dipakai.
     */
    public function resolveGoogle(SocialiteUser $googleUser, bool $emailVerified): ?User
    {
        $verifiedEmail = $emailVerified ? $this->normalizeEmail($googleUser->getEmail()) : null;

        return $this->resolveIdentity('google_id', $googleUser, $verifiedEmail);
    }

    /**
     * GitHub: Socialite GithubProvider meminta scope `user:email` dan hanya mengisi email
     * dari `/user/emails` yang `primary && verified`, sehingga email non-null sudah terverifikasi.
     */
    public function resolveGithub(SocialiteUser $githubUser): ?User
    {
        return $this->resolveIdentity('github_id', $githubUser, $this->normalizeEmail($githubUser->getEmail()));
    }

    private function resolveIdentity(string $providerColumn, SocialiteUser $providerUser, ?string $verifiedEmail): ?User
    {
        $providerId = (string) $providerUser->getId();
        $linked = User::query()->where($providerColumn, $providerId)->first();

        if ($linked !== null) {
            $this->attachProvider($linked, $providerColumn, $providerId);
            $this->syncAvatar($linked, $providerUser->getAvatar());
            $this->markEmailVerified($linked, $verifiedEmail);

            return $linked;
        }

        if ($verifiedEmail === null) {
            return null;
        }

        $existing = User::resolveByNormalizedEmails([$verifiedEmail])[$verifiedEmail] ?? null;

        if ($existing !== null) {
            $existingProviderId = $existing->getAttribute($providerColumn);

            if (is_string($existingProviderId) && $existingProviderId !== $providerId) {
                return null;
            }

            $this->attachProvider($existing, $providerColumn, $providerId);
            $this->syncAvatar($existing, $providerUser->getAvatar());
            $this->markEmailVerified($existing, $verifiedEmail);

            return $existing;
        }

        if (User::resolveByNormalizedEmails([$verifiedEmail], true) !== []) {
            return null;
        }

        $user = User::create([
            'name' => $providerUser->getName() ?? $providerUser->getNickname() ?? $verifiedEmail,
            'email' => $verifiedEmail,
            $providerColumn => $providerId,
            'avatar' => $providerUser->getAvatar(),
            'password' => null,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('member');

        return $user;
    }

    private function attachProvider(User $user, string $providerColumn, string $providerId): void
    {
        if ($user->getAttribute($providerColumn) !== null) {
            return;
        }

        $user->forceFill([$providerColumn => $providerId])->save();
    }

    private function syncAvatar(User $user, ?string $avatar): void
    {
        if ($avatar === null || $user->avatar) {
            return;
        }

        $user->forceFill(['avatar' => $avatar])->save();
    }

    private function markEmailVerified(User $user, ?string $verifiedEmail): void
    {
        if ($verifiedEmail === null || $user->email_verified_at !== null) {
            return;
        }

        if ($this->normalizeEmail($user->email) !== $verifiedEmail) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return $normalized === '' ? null : $normalized;
    }
}
