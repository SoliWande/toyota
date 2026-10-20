<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\User;

class CustomerSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Sales && $user->status === UserStatus::Active;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, CustomerSubmission $submission): bool
    {
        return ($this->viewAny($user) && $submission->sales_id === $user->id)
            || ($user->role === UserRole::Dealer && $user->status === UserStatus::Active
                && $user->dealer_id !== null && $submission->dealer_id === $user->dealer_id);
    }

    public function update(User $user, CustomerSubmission $submission): bool
    {
        return $this->viewAny($user) && $this->view($user, $submission) && $submission->status === SubmissionStatus::Pending;
    }

    public function delete(User $user, CustomerSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    public function review(User $user, CustomerSubmission $submission): bool
    {
        return $user->role === UserRole::Admin && $user->status === UserStatus::Active;
    }
}
