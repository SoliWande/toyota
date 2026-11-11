<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Policies\CallAuditBatchPolicy;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallAuditBatchPolicyTest extends TestCase
{
    #[DataProvider('accounts')]
    public function test_only_active_admins_receive_batch_capabilities_without_extra_powers(UserRole $role, UserStatus $status): void
    {
        $user = new User;
        $user->forceFill(['id' => 9, 'role' => $role, 'status' => $status]);
        $batch = new CallAuditBatch;
        $batch->forceFill(['id' => 1, 'created_by' => 3]);
        $policy = new CallAuditBatchPolicy;
        $allowed = $role === UserRole::Admin && $status === UserStatus::Active;

        $this->assertSame($allowed, $policy->viewAny($user));
        $this->assertSame($allowed, $policy->create($user));
        foreach (['view', 'freeze', 'claim', 'recordAttempt', 'verifyReproduction'] as $ability) {
            $this->assertSame($allowed, $policy->$ability($user, $batch), $ability);
        }
        foreach (['viewSensitiveData', 'reproduce', 'update', 'delete', 'correctAttempt', 'void', 'regenerate', 'adjustScore', 'publish'] as $ability) {
            $this->assertFalse($policy->$ability($user, $batch), $ability);
        }
    }

    public function test_policy_is_discoverable_without_changing_existing_policy_mappings(): void
    {
        $gate = new Gate(new Container, fn () => null);
        $this->assertInstanceOf(CallAuditBatchPolicy::class, $gate->getPolicyFor(CallAuditBatch::class));
    }

    public static function accounts(): array
    {
        $cases = [];
        foreach (UserRole::cases() as $role) {
            foreach (UserStatus::cases() as $status) {
                $cases[$role->value.':'.$status->value] = [$role, $status];
            }
        }

        return $cases;
    }
}
