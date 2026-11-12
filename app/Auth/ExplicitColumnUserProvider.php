<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Provider user dengan daftar kolom eksplisit. Dipakai guard `web` agar query
 * pemulihan sesi (setiap request) tidak memakai `select *`.
 */
class ExplicitColumnUserProvider extends EloquentUserProvider
{
    /** Ambil user dari sesi berdasarkan id dengan kolom eksplisit. */
    public function retrieveById($identifier)
    {
        $model = $this->createModel();

        return $this->newModelQuery($model)
            ->select(User::AUTH_SESSION_COLUMNS)
            ->selectRaw('(password is not null and password <> \'\') as has_local_password')
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    /** Ambil user dari cookie remember-me dengan kolom eksplisit. */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        $model = $this->createModel();

        $retrievedModel = $this->newModelQuery($model)
            ->select(User::AUTH_REMEMBER_COLUMNS)
            ->selectRaw('(password is not null and password <> \'\') as has_local_password')
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();

        if (! $retrievedModel) {
            return null;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $retrievedModel : null;
    }

    /** Ambil user berdasarkan kredensial login dengan kolom eksplisit. */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, '.')) {
                $query->where($key, $value);
            } elseif (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->select(User::AUTH_CREDENTIAL_COLUMNS)->first();
    }
}
