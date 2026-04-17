<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('events.list');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can('events.view');
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('events.edit');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('events.delete');
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->can('events.delete');
    }
}
