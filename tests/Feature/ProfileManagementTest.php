<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Profile tests may only refresh toyota_testing.');
        }
    }

    public function test_sales_updates_name_and_phone_without_changing_identity(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->get(route('profile.edit'))->assertOk()->assertSee($sales->email)
            ->assertDontSee('name="email"', false)->assertDontSee('name="dealer_id"', false);
        $this->patch(route('profile.update'), ['name' => 'Sales Updated', 'phone' => '0901234567'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $sales->id, 'name' => 'Sales Updated', 'phone' => '0901234567', 'email' => $sales->email, 'dealer_id' => $sales->dealer_id]);
    }

    /** @dataProvider forbiddenFields */
    public function test_sales_cannot_change_protected_fields(string $field, mixed $value): void
    {
        $sales = User::factory()->active()->create();
        $original = $sales->refresh()->getAttributes();
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => 'Malicious', $field => $value])->assertSessionHasErrors($field);
        $this->assertSame($original, $sales->fresh()->getAttributes());
    }

    public static function forbiddenFields(): array
    {
        return [['email', 'new@example.test'], ['email', null], ['dealer_id', 123], ['role', 'admin'], ['status', 'active'], ['user_id', 123], ['id', 123], ['password', 'unchecked-password']];
    }

    public function test_sales_cannot_edit_another_sales_profile_or_admin_form(): void
    {
        $sales = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $original = $other->refresh()->getAttributes();
        $this->actingAs($sales)->patch('/profile/'.$other->id, ['name' => 'Attack'])->assertNotFound();
        $this->get(route('admin.sales.edit', $other))->assertForbidden();
        $this->patch(route('admin.sales.update', $other), ['name' => 'Attack', 'dealer_id' => $sales->dealer_id])->assertForbidden();
        $this->assertSame($original, $other->fresh()->getAttributes());
    }

    /** @dataProvider inactiveStatuses */
    public function test_inactive_sales_cannot_access_or_update_profile(string $status): void
    {
        $sales = User::factory()->create(['status' => $status]);
        $this->actingAs($sales)->get(route('profile.edit'))->assertRedirect(route('account.status'));
        $this->patchJson(route('profile.update'), ['name' => 'No'])->assertForbidden();
        $this->putJson(route('profile.password'), ['current_password' => 'password', 'password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertForbidden();
    }

    public static function inactiveStatuses(): array
    {
        return [['pending'], ['rejected'], ['blocked']];
    }

    public function test_change_password_requires_correct_current_password_and_keeps_current_session(): void
    {
        $sales = User::factory()->active()->create();
        $token = $sales->remember_token;
        $this->actingAs($sales)->get(route('profile.edit'))->assertOk();
        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $sales->fresh()->password));
        $this->assertFalse(Hash::check('password', $sales->fresh()->password));
        $this->assertNotSame($token, $sales->fresh()->remember_token);
        $this->get(route('profile.edit'))->assertOk();
        $this->assertSame($sales->email, $sales->fresh()->email);
    }

    /** @dataProvider invalidPasswords */
    public function test_change_password_rejects_invalid_attempts(array $override, string $error): void
    {
        $sales = User::factory()->active()->create();
        $original = $sales->password;
        $this->actingAs($sales)->put(route('profile.password'), array_merge(['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'], $override))->assertSessionHasErrors($error);
        $this->assertSame($original, $sales->fresh()->password);
        $this->assertArrayNotHasKey('current_password', session()->getOldInput());
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public static function invalidPasswords(): array
    {
        return [[['current_password' => 'wrong'], 'current_password'], [['current_password' => null], 'current_password'], [['password_confirmation' => 'mismatch'], 'password'], [['password' => 'short', 'password_confirmation' => 'short'], 'password'], [['email' => 'attack@example.test'], 'email']];
    }

    public function test_admin_can_update_own_name_and_password_but_not_phone_email(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertDontSee('name="phone"', false)->assertDontSee('name="email"', false);
        $this->patch(route('profile.update'), ['name' => 'New Admin'])->assertSessionHasNoErrors();
        $this->assertSame('New Admin', $admin->fresh()->name);
        $this->patch(route('profile.update'), ['name' => 'Attack', 'phone' => '123', 'email' => 'new@example.test'])->assertSessionHasErrors(['phone', 'email']);
        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'new-admin-password', 'password_confirmation' => 'new-admin-password'])->assertSessionHasNoErrors();
        $this->assertSame($admin->email, $admin->fresh()->email);
        $this->assertTrue(Hash::check('new-admin-password', $admin->fresh()->password));
    }

    public function test_admin_can_edit_sales_name_phone_dealer_but_not_email_role_status_password(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $dealer = Dealer::factory()->create();
        $data = ['name' => 'Updated', 'phone' => '0901234567', 'dealer_id' => $dealer->id];
        $this->actingAs($admin)->get(route('admin.sales.edit', $sales))->assertOk()->assertDontSee('name="email"', false)->assertDontSee('name="role"', false);
        foreach (['email' => 'new@example.test', 'role' => 'admin', 'status' => 'blocked', 'password' => 'changed-password'] as $field => $value) {
            $this->patch(route('admin.sales.update', $sales), $data + [$field => $value])->assertSessionHasErrors($field);
        }
        $this->patch(route('admin.sales.update', $sales), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', $data + ['id' => $sales->id, 'email' => $sales->email, 'role' => 'sales', 'status' => 'active']);
        $this->get(route('admin.sales.edit', $admin))->assertForbidden();
        $this->patch(route('admin.sales.update', $admin), $data)->assertForbidden();
        $this->patch(route('admin.sales.update', $sales), array_merge($data, ['dealer_id' => 99999999]))->assertSessionHasErrors('dealer_id');
    }

    public function test_email_is_immutable_at_model_level(): void
    {
        $user = User::factory()->create();
        $email = $user->email;
        try {
            $user->forceFill(['email' => 'new@example.test'])->save();
            $this->fail('Email changed.');
        } catch (LogicException) {
            $this->assertSame($email, $user->fresh()->email);
        }
    }

    public function test_profile_writes_and_password_changes_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->active()->create());
        for ($i = 0; $i < 20; $i++) {
            $this->patchJson(route('profile.update'), ['name' => 'Sales'])->assertRedirect();
        }
        $this->patchJson(route('profile.update'), ['name' => 'Sales'])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) {
            $this->putJson(route('profile.password'), [])->assertUnprocessable();
        }
        $this->putJson(route('profile.password'), [])->assertStatus(429);
    }
}
