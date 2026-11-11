<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CallAuditBatch;
use App\Models\User;

class CallAuditBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin && $user->status === UserStatus::Active;
    }

    public function view(User $user, CallAuditBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function freeze(User $user, CallAuditBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function claim(User $user, CallAuditBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function recordAttempt(User $user, CallAuditBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    // Disclosure and changes to completed history require separate V1 contracts.
    public function viewSensitiveData(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function reproduce(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    // Server-side integrity verification returns a summary, never decrypted reproduction material.
    public function verifyReproduction(User $user, CallAuditBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function delete(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function correctAttempt(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function void(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function regenerate(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function adjustScore(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }

    public function publish(User $user, CallAuditBatch $batch): bool
    {
        return false;
    }
}
