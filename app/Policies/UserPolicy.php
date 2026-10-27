<?php

namespace App\Policies;

use App\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    public function update(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    public function delete(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    public function updateAdmin(User $user, User $account): bool
    {
        return false;
    }
}
