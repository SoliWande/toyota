<?php

namespace Tests\Feature;

use App\Actions\CreateDealerAccount;
use App\Actions\ReviewDealerAccount;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class DealerAccountManagementTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    private function data(int $dealerId, array $overrides = []): array
    {
        return array_replace(['name' => 'Dealer contact', 'email' => 'new-contact@example.test', 'phone' => '0901234567',
            'dealer_id' => $dealerId, 'password' => 'initial-password-123', 'password_confirmation' => 'initial-password-123'], $overrides);
    }

    private function asAccount(User $user): static
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    public function test_admin_creates_pending_dealer_without_changing_own_session_and_activates_it(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $this->asAccount($admin)->get(route('admin.dealer-accounts.index'))->assertOk()->assertSee('Tạo tài khoản đại lý');
        $this->get(route('admin.dealer-accounts.create'))->assertOk()->assertSee($dealer->name)
            ->assertDontSee('name="role"', false)->assertDontSee('name="status"', false);
        $this->post(route('admin.dealer-accounts.store'), $this->data($dealer->id, ['email' => ' NEW-CONTACT@EXAMPLE.TEST ', 'name' => ' Dealer contact ']))
            ->assertSessionHasNoErrors();
        $account = User::where('email', 'new-contact@example.test')->sole();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame('Dealer contact', $account->name);
        $this->assertSame('dealer', $account->role->value);
        $this->assertSame('pending', $account->status->value);
        $this->assertSame($dealer->id, $account->dealer_id);
        $this->assertSame('0901234567', $account->phone);
        $this->assertTrue(Hash::check('initial-password-123', $account->password));
        $this->assertNull($account->reviewed_by);
        $this->get(route('admin.dealer-accounts.show', $account))->assertOk()->assertSee($account->email)->assertDontSee('initial-password-123');
        $this->post(route('admin.dealer-accounts.review', [$account, 'activate']), ['admin_note' => 'Account verified'])->assertSessionHasNoErrors();
        $this->assertSame('active', $account->fresh()->status->value);
        $review = $account->moderationReviews()->sole();
        $this->assertSame('pending', $review->from_status);
        $this->assertSame('active', $review->to_status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->get(route('admin.dealer-accounts.show', $account))->assertSee('Account verified');
    }

    public function test_one_account_per_dealer_is_enforced_in_backend_and_database_for_every_status(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();
        $this->asAccount($admin);
        foreach (['pending', 'active', 'blocked', 'rejected'] as $status) {
            DB::table('users')->where('id', $existing->id)->update(['status' => $status]);
            $before = $existing->fresh()->getRawOriginal();
            $this->post(route('admin.dealer-accounts.store'), $this->data($existing->dealer_id))->assertSessionHasErrors('dealer_id');
            $this->get(route('admin.dealer-accounts.create'))->assertViewHas('dealers', fn ($rows) => ! $rows->contains('id', $existing->dealer_id));
            $this->assertSame($before, $existing->fresh()->getRawOriginal());
            try {
                DB::table('users')->insert(['name' => 'Bypass fixture', 'email' => 'bypass-'.$status.'@example.test',
                    'password' => 'test-only-hash', 'role' => 'dealer', 'status' => $status, 'dealer_id' => $existing->dealer_id]);
                $this->fail('Raw SQL must not create a second account.');
            } catch (QueryException $error) {
                $this->assertSame(1062, $error->errorInfo[1]);
                $this->assertStringContainsString('users_one_dealer_account_unique', $error->errorInfo[2]);
            }
        }
        $this->assertSame(1, User::where('role', 'dealer')->where('dealer_id', $existing->dealer_id)->count());
    }

    public function test_invalid_or_privileged_create_fields_are_rejected_without_flashing_passwords(): void
    {
        $this->asAccount(User::factory()->admin()->create());
        $dealer = Dealer::factory()->create();
        $before = User::count();
        foreach (['role' => 'admin', 'status' => 'active', 'reviewed_by' => 1, 'email_verified_at' => '2026-01-01', 'id' => 999] as $field => $value) {
            $this->post(route('admin.dealer-accounts.store'), $this->data($dealer->id, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->post(route('admin.dealer-accounts.store'), $this->data($dealer->id, [
            'name' => ' ', 'email' => "invalid\r\n@example.test", 'phone' => str_repeat('1', 31), 'password_confirmation' => 'mismatch',
        ]))->assertSessionHasErrors(['name', 'email', 'phone', 'password']);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
        $this->assertSame($before, User::count());
        $inactive = Dealer::factory()->inactive()->create();
        $this->post(route('admin.dealer-accounts.store'), $this->data($inactive->id))->assertSessionHasErrors('dealer_id');
        $this->post(route('admin.dealer-accounts.store'), $this->data(999999999))->assertSessionHasErrors('dealer_id');
        $existing = User::factory()->create();
        $this->post(route('admin.dealer-accounts.store'), $this->data($dealer->id, ['email' => strtoupper($existing->email)]))->assertSessionHasErrors('email');
    }

    public function test_admin_edits_only_name_and_phone_and_cannot_target_admin_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->active()->create();
        $before = $account->refresh()->getRawOriginal();
        $this->asAccount($admin)->get(route('admin.dealer-accounts.edit', $account))->assertOk()->assertSee($account->email)
            ->assertDontSee('name="email"', false)->assertDontSee('name="dealer_id"', false)->assertDontSee('name="role"', false);
        foreach (['email' => 'changed@example.test', 'role' => 'admin', 'status' => 'blocked', 'dealer_id' => $admin->dealer_id, 'password' => 'changed-password'] as $field => $value) {
            $this->patch(route('admin.dealer-accounts.update', $account), ['name' => 'Forbidden change', $field => $value])->assertSessionHasErrors($field);
        }
        $this->assertSame($before, $account->fresh()->getRawOriginal());
        $this->patch(route('admin.dealer-accounts.update', $account), ['name' => 'Updated contact', 'phone' => '0912345678'])->assertSessionHasNoErrors();
        $fresh = $account->fresh();
        $this->assertSame('Updated contact', $fresh->name);
        $this->assertSame('0912345678', $fresh->phone);
        foreach (['email', 'dealer_id', 'role', 'status', 'password'] as $field) {
            $this->assertSame($before[$field], $fresh->getRawOriginal($field));
        }
        $this->patch(route('admin.dealer-accounts.update', $account), ['name' => 'Updated contact', 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($account->fresh()->phone);
        $this->get(route('admin.dealer-accounts.edit', $admin))->assertForbidden();
        $this->patch(route('admin.dealer-accounts.update', $admin), ['name' => 'Attack'])->assertForbidden();
        $this->post(route('admin.dealer-accounts.review', [$admin, 'block']))->assertForbidden();
    }

    public function test_activation_block_and_reactivation_keep_identity_and_append_history_only_once(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        $identity = $account->only(['email', 'role', 'dealer_id', 'password']);
        $this->asAccount($admin);
        foreach (['activate' => 'active', 'block' => 'blocked'] as $action => $status) {
            $this->post(route('admin.dealer-accounts.review', [$account, $action]))->assertSessionHasNoErrors();
            $this->assertSame($status, $account->fresh()->status->value);
            $this->post(route('admin.dealer-accounts.review', [$account, $action]))->assertSessionHasErrors('action');
        }
        $this->assertSame(2, $account->moderationReviews()->count());
        $this->post(route('admin.dealer-accounts.review', [$account, 'activate']))->assertSessionHasNoErrors();
        $this->assertSame(3, $account->moderationReviews()->count());
        $this->assertSame($identity, $account->fresh()->only(['email', 'role', 'dealer_id', 'password']));
        $this->asAccount($account)->get('/profile')->assertOk();
        DB::table('users')->where('id', $account->id)->update(['status' => 'blocked']);
        $this->get('/profile')->assertRedirect(route('account.status'));
    }

    public function test_failed_review_rolls_back_account_status_and_all_history(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        new ModerationReview;
        $dispatcher = ModerationReview::getEventDispatcher();
        ModerationReview::setEventDispatcher(clone $dispatcher);
        ModerationReview::creating(fn () => throw new RuntimeException('Fixture history failure'));
        try {
            app(ReviewDealerAccount::class)->execute($admin, $account, 'activate', []);
            $this->fail('History failure must roll back activation.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture history failure', $error->getMessage());
        } finally {
            ModerationReview::setEventDispatcher($dispatcher);
        }
        $this->assertSame('pending', $account->fresh()->status->value);
        $this->assertNull($account->fresh()->reviewed_by);
        $this->assertSame(0, $account->moderationReviews()->count());
    }

    public function test_guests_dealers_and_inactive_admins_cannot_manage_accounts(): void
    {
        $target = User::factory()->create();
        $endpoints = [['get', 'index'], ['get', 'create'], ['post', 'store'], ['get', 'show'], ['get', 'edit'], ['patch', 'update']];
        foreach ($endpoints as [$method, $action]) {
            $this->$method(route('admin.dealer-accounts.'.$action, in_array($action, ['index', 'create', 'store']) ? [] : [$target]))->assertRedirect(route('login'));
        }
        foreach (['pending', 'active', 'rejected', 'blocked'] as $status) {
            $dealer = User::factory()->create(['status' => $status]);
            $this->asAccount($dealer);
            foreach ($endpoints as [$method, $action]) {
                $url = route('admin.dealer-accounts.'.$action, in_array($action, ['index', 'create', 'store']) ? [] : [$target]);
                $this->json(strtoupper($method), $url, $this->data($target->dealer_id))->assertForbidden();
            }
            foreach (['activate', 'block'] as $action) {
                $this->postJson(route('admin.dealer-accounts.review', [$target, $action]))->assertForbidden();
            }
        }
        foreach (['pending', 'rejected', 'blocked'] as $status) {
            $this->asAccount(User::factory()->admin()->create(['status' => $status]))
                ->postJson(route('admin.dealer-accounts.store'), $this->data($target->dealer_id))->assertForbidden();
        }
        $dealer = User::factory()->active()->create();
        $this->expectException(AuthorizationException::class);
        app(CreateDealerAccount::class)->execute($dealer, $this->data($target->dealer_id));
    }

    public function test_account_list_supports_search_status_dealer_filter_and_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(21)->create(['name' => 'Matching contact']);
        $target = User::factory()->create(['name' => '<script>contact</script> %_', 'phone' => '0901234567']);
        $this->asAccount($admin)->get(route('admin.dealer-accounts.index', ['q' => 'Matching']))->assertOk()
            ->assertViewHas('accounts', fn ($page) => $page->total() === 21 && $page->count() === 20 && str_contains($page->nextPageUrl(), 'q=Matching'));
        foreach (['%_', $target->email, '012345'] as $q) {
            $this->get(route('admin.dealer-accounts.index', ['q' => $q, 'dealer_id' => $target->dealer_id, 'status' => 'pending']))
                ->assertOk()->assertViewHas('accounts', fn ($page) => $page->total() === 1 && $page->first()->id === $target->id);
        }
        $this->get(route('admin.dealer-accounts.show', $target))->assertSee($target->name)->assertDontSee($target->name, false);
    }

    public function test_dealer_can_edit_own_profile_change_password_and_use_existing_reset_flow(): void
    {
        $account = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $identity = $account->only(['email', 'dealer_id', 'role', 'status']);
        $this->asAccount($account)->get('/profile')->assertOk()->assertSee($account->email);
        $this->patch('/profile', ['name' => 'Self updated', 'phone' => '0912345678'])->assertSessionHasNoErrors();
        foreach (['email' => 'changed@example.test', 'dealer_id' => $other->dealer_id, 'role' => 'admin', 'status' => 'active'] as $field => $value) {
            $this->patch('/profile', ['name' => 'Attack', $field => $value])->assertSessionHasErrors($field);
        }
        $this->assertSame($identity, $account->fresh()->only(['email', 'dealer_id', 'role', 'status']));
        $this->assertSame('Self updated', $account->fresh()->name);
        $this->assertSame('0912345678', $account->fresh()->phone);
        $sessionId = session()->getId();
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasNoErrors();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertTrue(Hash::check('new-password-123', $account->fresh()->password));
        $this->post('/logout');
        Notification::fake();
        $this->post('/forgot-password', ['email' => $account->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($account, ResetPassword::class);
        $token = Password::createToken($account);
        $this->post('/reset-password', ['email' => $account->email, 'token' => $token, 'password' => 'reset-password-123', 'password_confirmation' => 'reset-password-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('reset-password-123', $account->fresh()->password));
        $this->assertSame($identity, $account->fresh()->only(['email', 'dealer_id', 'role', 'status']));
    }

    public function test_management_mutations_require_csrf_and_no_delete_endpoint_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        $this->asAccount($admin);
        app()->detectEnvironment(fn () => 'local');
        $this->post(route('admin.dealer-accounts.store'), $this->data($account->dealer_id))->assertStatus(419);
        $this->patch(route('admin.dealer-accounts.update', $account), ['name' => 'Attack'])->assertStatus(419);
        $this->post(route('admin.dealer-accounts.review', [$account, 'activate']))->assertStatus(419);
        app()->detectEnvironment(fn () => 'testing');
        $this->delete('/admin/dealer-accounts/'.$account->id)->assertStatus(405);
    }
}
