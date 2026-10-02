<?php

namespace App\Policies;

use App\Models\EmailBroadcast;
use App\Models\User;

class EmailBroadcastPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('email-broadcast.view');
    }

    public function view(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.view');
    }

    public function create(User $user): bool
    {
        return $user->can('email-broadcast.create');
    }

    public function update(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.create') || $user->can('email-broadcast.schedule');
    }

    public function schedule(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.schedule');
    }

    public function cancel(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.cancel');
    }

    public function retry(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.retry');
    }

    public function delete(User $user, EmailBroadcast $broadcast): bool
    {
        return $user->can('email-broadcast.delete');
    }
}
