<?php

namespace App\Support;

use App\Models\User;

/** Satu pintu tujuan setelah autentikasi: dasbor organizer atau portal peserta. */
final class PostAuthDestination
{
    /**
     * URL relatif tujuan pasca-autentikasi sesuai kemampuan user.
     *
     * Penyelenggara (`events.list`) kembali ke dasbor organizer; user lain ke portal peserta.
     */
    public static function urlFor(User $user): string
    {
        return $user->can('events.list')
            ? route('dashboard', absolute: false)
            : route('dashboard.user.events', absolute: false);
    }
}
