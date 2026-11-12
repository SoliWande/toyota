<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DealerPenaltyEntry;
use App\Models\User;

class DealerPenaltyEntryPolicy
{
    // Dedicated V1 capability, independent of call-audit resolution permissions.
    public function applyDealerPenalty(User $user): bool
    {
        return $user->role === UserRole::Admin && $user->status === UserStatus::Active;
    }

    public function viewAny(User $user): bool
    {
        return $this->applyDealerPenalty($user);
    }

    public function view(User $user, DealerPenaltyEntry $entry): bool
    {
        return $this->applyDealerPenalty($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }

    public function correct(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }

    public function reverse(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }

    public function publish(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }

    public function viewSensitiveData(User $user, DealerPenaltyEntry $entry): bool
    {
        return false;
    }
}
