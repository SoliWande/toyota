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
        return $this->viewAny($admin) && $sales->role === UserRole::Sales;
    }

    public function moderate(User $admin, User $sales): bool
    {
        return $this->view($admin, $sales);
    }

    public function update(User $admin, User $sales): bool
    {
        return $this->view($admin, $sales);
    }
}
