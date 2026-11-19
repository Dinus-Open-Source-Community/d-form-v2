<?php

namespace App\Services\Auth;

use App\Enums\ProviderConnectionResult;
use App\Enums\ProviderDisconnectionResult;
use App\Enums\SocialProvider;
use App\Models\User;
use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Menghubungkan dan melepas identitas OAuth dari akun yang sudah login.
 *
 * Pemanggil wajib mengirim `$account` hasil query ulang lewat primary key, karena
 * session user tidak memuat `google_id`, `github_id`, atau `password`.
 */
final class OAuthConnectionService
{
    /** Key pada provider id saja; nama, email, dan avatar tidak pernah disentuh. */
    public function connect(User $account, SocialProvider $provider, SocialiteUser $identity): ProviderConnectionResult
    {
        $column = $provider->userColumn();
        $providerId = trim((string) $identity->getId());

        if ($providerId === '') {
            return ProviderConnectionResult::InvalidIdentity;
        }

        $owner = User::withTrashed()->where($column, $providerId)->first();

        if ($owner !== null) {
            return $owner->getKey() === $account->getKey()
                ? ProviderConnectionResult::AlreadyConnected
                : ProviderConnectionResult::OwnedByAnotherUser;
        }

        try {
            DB::transaction(function () use ($account, $column, $providerId): void {
                $account->forceFill([$column => $providerId])->save();
            });
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::isViolation($exception)) {
                throw $exception;
            }

            // Race dengan unique index: user lain menyimpan id yang sama lebih dulu.
            return ProviderConnectionResult::OwnedByAnotherUser;
        }

        return ProviderConnectionResult::Connected;
    }

    /** Menolak pelepasan bila user tidak akan punya kredensial tersisa. */
    public function disconnect(User $account, SocialProvider $provider): ProviderDisconnectionResult
    {
        $column = $provider->userColumn();

        if (! filled($account->getAttribute($column))) {
            return ProviderDisconnectionResult::NotConnected;
        }

        if (! $this->hasRemainingCredential($account, $provider)) {
            return ProviderDisconnectionResult::LastCredential;
        }

        $account->forceFill([$column => null])->save();

        return ProviderDisconnectionResult::Disconnected;
    }

    /** Password lokal atau provider lain yang masih terhubung. */
    private function hasRemainingCredential(User $account, SocialProvider $provider): bool
    {
        if (filled($account->getRawOriginal('password'))) {
            return true;
        }

        $otherProvider = $provider === SocialProvider::Google ? SocialProvider::Github : SocialProvider::Google;

        return filled($account->getAttribute($otherProvider->userColumn()));
    }
}
