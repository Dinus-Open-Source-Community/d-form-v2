<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.list');
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.view') && ($event->created_by === null || $user->id === $event->created_by);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        if ($event->trashed()) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.edit') && ($event->created_by === null || $user->id === $event->created_by);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($event->trashed()) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.delete') && ($event->created_by === null || $user->id === $event->created_by);
    }

    public function restore(User $user, Event $event): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.delete') && ($event->created_by === null || $user->id === $event->created_by);
    }

    public function forceDelete(User $user, Event $event): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('events.delete') && ($event->created_by === null || $user->id === $event->created_by);
    }
}
