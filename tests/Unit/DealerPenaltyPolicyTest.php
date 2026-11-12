<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DealerPenaltyEntry;
use App\Models\User;
use App\Policies\DealerPenaltyEntryPolicy;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DealerPenaltyPolicyTest extends TestCase
{
    #[DataProvider('accounts')]
    public function test_apply_is_a_separate_active_admin_capability_without_correction_or_publication(UserRole $role, UserStatus $status): void
    {
        $user = (new User)->forceFill(['id' => 8, 'role' => $role, 'status' => $status]);
        $entry = (new DealerPenaltyEntry)->forceFill(['id' => 1, 'applied_by' => 9]);
        $policy = new DealerPenaltyEntryPolicy;
        $allowed = $role === UserRole::Admin && $status === UserStatus::Active;
        $this->assertSame($allowed, $policy->applyDealerPenalty($user));
        $this->assertSame($allowed, $policy->viewAny($user));
        $this->assertSame($allowed, $policy->view($user, $entry));
        $this->assertFalse($policy->create($user));
        foreach (['update', 'delete', 'correct', 'reverse', 'publish', 'viewSensitiveData'] as $ability) {
            $this->assertFalse($policy->$ability($user, $entry));
        }
    }

    public function test_policy_auto_discovery_and_class_ability_do_not_require_call_audit_policy(): void
    {
        $user = (new User)->forceFill(['id' => 8, 'role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $gate = new Gate(new Container, fn () => $user);
        $this->assertInstanceOf(DealerPenaltyEntryPolicy::class, $gate->getPolicyFor(DealerPenaltyEntry::class));
        $this->assertTrue($gate->allows('applyDealerPenalty', DealerPenaltyEntry::class));
        $this->assertFalse($gate->allows('create', DealerPenaltyEntry::class));
        $this->assertFalse((new Gate(new Container, fn () => null))->allows('applyDealerPenalty', DealerPenaltyEntry::class));
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
