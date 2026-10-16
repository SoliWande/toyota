<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Award;
use App\Models\User;

class AwardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Award $award): bool
    {
        return $this->viewAny($user);
    }

    public function publish(User $user, Award $award): bool
    {
        return $this->viewAny($user);
    }
}
