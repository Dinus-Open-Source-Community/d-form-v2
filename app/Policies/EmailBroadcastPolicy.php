<?php

namespace App\Policies;

use App\Models\EmailBroadcast;
use App\Models\User;

class EmailBroadcastPolicy
{
    private function allows(User $user, string $permission): bool
    {
        return config('features.broadcast', false) && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'email-broadcast.view');
    }

    public function view(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'email-broadcast.create');
    }

    public function update(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.create');
    }

    public function schedule(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.schedule');
    }

    public function cancel(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.cancel');
    }

    public function retry(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.retry');
    }

    public function delete(User $user, EmailBroadcast $broadcast): bool
    {
        return $this->allows($user, 'email-broadcast.delete');
    }
}
