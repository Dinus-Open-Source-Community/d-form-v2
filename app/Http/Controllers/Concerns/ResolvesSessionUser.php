<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/** Kontrak controller ber-middleware `auth`: user sesi selalu ada dan bertipe `User`. */
trait ResolvesSessionUser
{
    protected function sessionUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
