<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\CustomerSubmission;
use App\Models\User;

class CustomerSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveRole(UserRole::Dealer);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, CustomerSubmission $submission): bool
    {
        return $this->viewAny($user) && $submission->dealer_id !== null && (int) $submission->dealer_id === (int) $user->dealer_id;
    }

    public function update(User $user, CustomerSubmission $submission): bool
    {
        return $this->view($user, $submission) && $submission->status === SubmissionStatus::Pending;
    }

    public function delete(User $user, CustomerSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    public function review(User $user, CustomerSubmission $submission): bool
    {
        return $user->hasActiveRole(UserRole::Admin);
    }
}
