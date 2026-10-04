<?php

namespace Tests\Unit;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Policies\CustomerSubmissionPolicy;
use PHPUnit\Framework\TestCase;

class CustomerSubmissionPolicyTest extends TestCase
{
    /** @dataProvider permissions */
    public function test_policy_checks_role_account_status_ownership_and_submission_status(UserRole $role, UserStatus $accountStatus, int $owner, SubmissionStatus $status, bool $canView, bool $canChange): void
    {
        $user = new User;
        $user->id = 1;
        $user->role = $role;
        $user->status = $accountStatus;
        $submission = new CustomerSubmission;
        $submission->sales_id = $owner;
        $submission->status = $status;
        $policy = new CustomerSubmissionPolicy;

        $this->assertSame($canView, $policy->view($user, $submission));
        $this->assertSame($canChange, $policy->update($user, $submission));
        $this->assertSame($canChange, $policy->delete($user, $submission));
        $this->assertSame($role === UserRole::Sales && $accountStatus === UserStatus::Active, $policy->create($user));
        $this->assertSame($role === UserRole::Admin && $accountStatus === UserStatus::Active, $policy->review($user, $submission));
    }

    public static function permissions(): array
    {
        $cases = [];
        foreach (UserRole::cases() as $role) {
            foreach (UserStatus::cases() as $account) {
                foreach ([1, 2] as $owner) {
                    foreach (SubmissionStatus::cases() as $status) {
                        $view = $role === UserRole::Sales && $account === UserStatus::Active && $owner === 1;
                        $cases[$role->value.':'.$account->value.':'.$owner.':'.$status->value] = [$role, $account, $owner, $status, $view, $view && $status === SubmissionStatus::Pending];
                    }
                }
            }
        }

        return $cases;
    }
}
