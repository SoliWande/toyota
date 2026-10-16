<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;

class DealerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Dealer $dealer): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Dealer $dealer): bool
    {
        return false; // No dealer deletion workflow in V1.
    }
}
