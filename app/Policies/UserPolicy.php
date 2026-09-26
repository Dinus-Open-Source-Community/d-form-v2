<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('users.list');
    }

    public function view(User $user, User $model): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin')) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('users.edit');
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole('super-admin')) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('users.delete');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}
