<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $admin): bool
    {
        return $admin->role === UserRole::Admin && $admin->status === UserStatus::Active;
    }

    public function view(User $admin, User $sales): bool
    {
        return $sales->role === UserRole::Sales && ($this->viewAny($admin)
            || ($admin->role === UserRole::Dealer && $admin->status === UserStatus::Active
                && $admin->dealer_id !== null && $admin->dealer_id === $sales->dealer_id));
    }

    public function moderate(User $admin, User $sales): bool
    {
        return $this->viewAny($admin) && $sales->role === UserRole::Sales;
    }

    public function update(User $admin, User $sales): bool
    {
        return $this->moderate($admin, $sales);
    }
}
