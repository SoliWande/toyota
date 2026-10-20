<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DealerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_one_dealer_account_with_a_supplied_password_and_can_block_it(): void
    {
        $dealer = Dealer::factory()->create();
        $admin = User::factory()->admin()->create();
        $password = Str::random(25);
        $data = ['name' => 'Dealer Manager', 'email' => 'manager@example.test', 'status' => 'active',
            'password' => $password, 'password_confirmation' => $password];
        $this->actingAs($admin)->get(route('admin.dealers.account.edit', $dealer))->assertOk();
        $this->post(route('admin.dealers.account.store', $dealer), $data)->assertSessionHasNoErrors()->assertRedirect();
        $manager = $dealer->manager()->sole();
        $this->assertSame(UserRole::Dealer, $manager->role);
        $this->assertTrue(Hash::check($password, $manager->password));
        $this->assertArrayNotHasKey('dealer_manager_key', $manager->toArray());
        $this->post(route('admin.dealers.account.store', $dealer), [...$data, 'email' => 'another@example.test'])->assertSessionHasErrors('email');
        $this->patch(route('admin.dealers.account.update', $dealer), ['name' => $manager->name, 'status' => 'blocked'])->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Blocked, $manager->fresh()->status);
        $this->post(route('admin.dealers.account.store', $dealer), [...$data, 'email' => 'third@example.test'])->assertSessionHasErrors('email');
        $this->assertSame(1, $dealer->manager()->count());
        $this->patch(route('admin.dealers.account.update', $dealer), ['name' => 'Changed', 'status' => 'active', 'email' => 'changed@example.test'])
            ->assertSessionHasErrors('email');
        $this->assertSame('manager@example.test', $manager->fresh()->email);
    }

    public function test_database_guards_unique_manager_and_required_assignment_without_limiting_sales(): void
    {
        $manager = User::factory()->dealer()->create();
        User::factory()->active()->count(3)->create(['dealer_id' => $manager->dealer_id]);
        try {
            User::factory()->dealer()->create(['dealer_id' => $manager->dealer_id]);
            $this->fail('Duplicate manager accepted.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
        try {
            User::factory()->dealer()->create(['dealer_id' => null]);
            $this->fail('Unassigned manager accepted.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
        $this->assertSame(3, $manager->dealer->sales()->count());
    }

    public function test_dealer_login_routes_to_own_dashboard_and_inactive_accounts_remain_blocked(): void
    {
        $password = Str::random(24);
        $manager = User::factory()->dealer()->create(['password' => $password]);
        $this->post(route('login'), ['email' => $manager->email, 'password' => $password])->assertRedirect(route('dealer.dashboard'));
        $this->get(route('dealer.dashboard'))->assertOk()->assertSee($manager->dealer->name);
        $this->post(route('logout'));
        $manager->forceFill(['status' => UserStatus::Blocked])->save();
        $this->post(route('login'), ['email' => $manager->email, 'password' => $password])->assertRedirect(route('account.status'));
        $this->getJson(route('dealer.dashboard'))->assertForbidden();
    }

    public function test_read_permissions_never_grant_dealer_customer_writes_or_admin_sales_moderation(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->assertTrue($manager->can('view', $submission));
        $this->assertTrue($manager->can('view', $sales));
        foreach (['create', 'update', 'delete', 'review'] as $ability) {
            $this->assertFalse($manager->can($ability, $submission), $ability);
        }
        foreach (['update', 'moderate'] as $ability) {
            $this->assertFalse($manager->can($ability, $sales));
        }
        $this->assertFalse($manager->can('view', User::factory()->active()->create()));
        $this->assertFalse($manager->can('update', $manager->dealer));
        $this->actingAs($manager)->get(route('admin.dealers.account.edit', $manager->dealer_id))->assertForbidden();
        $this->post(route('admin.sales.review', [$sales, 'block']))->assertForbidden();
        $this->post(route('admin.submissions.approve', $submission))->assertForbidden();
    }

    public function test_creation_requires_unique_email_explicit_password_and_admin_authorization(): void
    {
        $dealer = Dealer::factory()->create();
        $this->postJson(route('admin.dealers.account.store', $dealer), [])->assertUnauthorized();
        $this->actingAs(User::factory()->active()->create())->post(route('admin.dealers.account.store', $dealer), [])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.dealers.account.store', $dealer), ['name' => 'Manager', 'email' => 'manager@example.test', 'status' => 'active'])
            ->assertSessionHasErrors('password');
        $this->assertFalse($dealer->manager()->exists());
    }

    public function test_dealer_uses_existing_password_recovery_without_changing_email_or_assignment(): void
    {
        Notification::fake();
        $manager = User::factory()->dealer()->create();
        $token = null;
        $this->post(route('password.email'), ['email' => $manager->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($manager, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $password = Str::random(25);
        $this->post(route('password.store'), ['email' => $manager->email, 'token' => $token,
            'password' => $password, 'password_confirmation' => $password])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $manager->fresh()->password));
        $this->assertSame($manager->email, $manager->fresh()->email);
        $this->assertSame($manager->dealer_id, $manager->fresh()->dealer_id);
        $this->actingAs($manager->fresh())->patch(route('profile.update'), ['name' => $manager->name, 'email' => 'tampered@example.test'])
            ->assertSessionHasErrors('email');
        $this->assertSame($manager->email, $manager->fresh()->email);
    }
}
