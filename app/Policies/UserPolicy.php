<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $admin): bool
    {
        return $admin->hasActiveRole(UserRole::Admin);
    }

    public function create(User $admin): bool
    {
        return $this->viewAny($admin);
    }

    public function view(User $admin, User $sales): bool
    {
        return $this->viewAny($admin) && ($sales->getAttributes()['role'] ?? null) === UserRole::Dealer->value;
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
