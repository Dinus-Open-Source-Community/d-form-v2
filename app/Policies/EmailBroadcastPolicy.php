<?php

namespace App\Policies;

use App\Models\EmailBroadcast;
use App\Models\User;

class EmailBroadcastPolicy
{
    // Fitur broadcast dinonaktifkan (config/features.php, default mati).
    // Seluruh method diganjal di baris pertama; logika asli dipertahankan.

    private function disabled(): bool
    {
        return ! config('features.broadcast', false);
    }

    public function viewAny(User $user): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.view');
    }

    public function view(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.view');
    }

    public function create(User $user): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.create');
    }

    public function update(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.create') || $user->can('email-broadcast.schedule');
    }

    public function schedule(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.schedule');
    }

    public function cancel(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.cancel');
    }

    public function retry(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.retry');
    }

    public function delete(User $user, EmailBroadcast $broadcast): bool
    {
        if ($this->disabled()) {
            return false;
        }

        return $user->can('email-broadcast.delete');
    }
}
