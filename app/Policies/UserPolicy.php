<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\User;

class UserPolicy
{
    public function reviewDealerRegistrations(User $actor): bool
    {
        return $actor->can('viewAny', Dealer::class);
    }

    public function manageStaff(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->role === UserRole::Dealer && $actor->status === UserStatus::Active
            && $target->role === UserRole::Sales && $actor->dealer_id !== null && $actor->dealer_id === $target->dealer_id;
    }

    public function reviewRegistration(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }
        if ($target->role === UserRole::Sales) {
            return $this->manageStaff($actor, $target);
        }

        return $target->role === UserRole::Dealer && $target->dealer !== null && $actor->can('update', $target->dealer);
    }

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
