<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CustomerSubmission;
use App\Models\LegacySalesAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    private function asAccount(User $user): static
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function submission(User $account, string $status = 'pending'): CustomerSubmission
    {
        $id = DB::table('customer_submissions')->insertGetId([
            'created_by_user_id' => $account->id, 'dealer_id' => $account->dealer_id,
            'customer_name' => 'Private customer '.$account->id,
            'facebook_url' => 'https://facebook.com/auth.fixture.'.$account->id.'.'.$status,
            'facebook_url_normalized' => 'https://www.facebook.com/auth.fixture.'.$account->id.'.'.$status,
            'submitted_at' => now(), 'evidence_image_path' => 'auth-'.$account->id.'.png',
        ]);
        if ($status !== 'pending') {
            DB::table('customer_submissions')->where('id', $id)->update([
                'status' => $status, 'reviewed_by' => User::where('role', 'admin')->value('id'), 'reviewed_at' => now(),
            ]);
        }
        $submission = CustomerSubmission::findOrFail($id);
        Storage::disk('submission_evidence')->put($submission->evidence_image_path, 'private-evidence');

        return $submission;
    }

    public function test_only_two_login_roles_and_dealer_login_rotates_session_and_redirects(): void
    {
        $this->assertSame(['admin', 'dealer'], array_map(fn ($role) => $role->value, UserRole::cases()));
        $this->assertNull(UserRole::tryFrom('sales'));
        $dealer = User::factory()->active()->create();
        $this->get('/login')->assertOk()->assertSee('Đăng ký tài khoản đại lý');
        $session = session()->getId();
        $this->withSession(['url.intended' => '/admin/dashboard'])->post('/login', ['email' => strtoupper($dealer->email), 'password' => 'password'])
            ->assertRedirect(route('dealer.dashboard'))->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($dealer);
        $this->assertNotSame($session, session()->getId());
        $this->get(route('dealer.dashboard'))->assertOk();
        $this->get('/sales/dashboard')->assertNotFound();
    }

    public function test_registration_creates_one_pending_dealer_and_protected_fields_are_not_assignable(): void
    {
        $dealerId = DB::table('dealers')->value('id');
        $data = ['name' => 'Dealer account', 'email' => ' NEW-DEALER@EXAMPLE.TEST ', 'dealer_id' => $dealerId,
            'password' => 'testing-password', 'password_confirmation' => 'testing-password'];
        $this->post('/register', $data + ['role' => 'admin', 'status' => 'active'])->assertSessionHasErrors(['role', 'status']);
        $this->post('/register', $data)->assertRedirect(route('account.status'))->assertSessionHasNoErrors();
        $account = User::where('email', 'new-dealer@example.test')->sole();
        $this->assertSame(UserRole::Dealer, $account->role);
        $this->assertSame('pending', $account->status->value);
        $this->assertTrue(Hash::check('testing-password', $account->password));
        $this->assertAuthenticatedAs($account);
        $this->get(route('dealer.submissions.index'))->assertRedirect(route('account.status'));
        $this->post('/logout');
        $this->post('/register', array_replace($data, ['email' => 'second@example.test']))->assertSessionHasErrors('dealer_id');
        $this->assertSame(1, User::where('dealer_id', $dealerId)->where('role', 'dealer')->count());
        $this->get('/register')->assertViewHas('dealers', fn ($rows) => ! $rows->contains('id', $dealerId));
    }

    public function test_registration_rejects_inactive_dealer_and_duplicate_account_in_every_status(): void
    {
        $account = User::factory()->create();
        $data = ['name' => 'Duplicate', 'email' => 'duplicate@example.test', 'dealer_id' => $account->dealer_id,
            'password' => 'testing-password', 'password_confirmation' => 'testing-password'];
        foreach (['pending', 'active', 'rejected', 'blocked'] as $status) {
            DB::table('users')->where('id', $account->id)->update(['status' => $status]);
            $this->post('/register', $data)->assertSessionHasErrors('dealer_id');
        }
        DB::table('dealers')->where('id', $account->dealer_id)->update(['is_active' => false]);
        $this->post('/register', $data)->assertSessionHasErrors('dealer_id');
    }

    public function test_role_status_gates_keep_dealers_out_of_every_admin_area(): void
    {
        $account = User::factory()->active()->create();
        $target = $this->submission(User::factory()->active()->create());
        foreach (['pending', 'active', 'rejected', 'blocked'] as $status) {
            DB::table('users')->where('id', $account->id)->update(['status' => $status]);
            $this->post('/login', ['email' => $account->email, 'password' => 'password'])
                ->assertRedirect(route($status === 'active' ? 'dealer.dashboard' : 'account.status'));
            foreach (['/admin/dashboard', '/admin/dealers', '/admin/dealer-accounts', '/admin/submissions', '/admin/awards'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
            $this->postJson(route('admin.submissions.approve', $target))->assertForbidden();
            $this->postJson(route('admin.submissions.reject', $target))->assertForbidden();
            if ($status !== 'active') {
                $this->getJson('/dealer/submissions')->assertForbidden();
                $this->patchJson('/profile', ['name' => 'Denied'])->assertForbidden();
            }
            $this->post('/logout');
        }
    }

    public function test_admin_login_and_management_remain_protected_and_dealer_review_is_atomic(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/dealer-accounts')->assertOk()->assertSee($target->email);
        $this->post(route('admin.dealer-accounts.review', [$target, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame('active', $target->fresh()->status->value);
        $this->assertSame(1, DB::table('moderation_reviews')->where('account_user_id', $target->id)->count());
        $this->post(route('admin.dealer-accounts.review', [$target, 'approve']))->assertSessionHasErrors('action');
        $this->assertSame(1, DB::table('moderation_reviews')->where('account_user_id', $target->id)->count());
        $this->get('/dealer/submissions')->assertForbidden();
        $this->get('/admin/sales')->assertNotFound();
    }

    public function test_dealer_cannot_change_identity_through_profile_password_or_admin_routes(): void
    {
        $dealer = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $before = $dealer->refresh()->getRawOriginal();
        $this->asAccount($dealer)->get('/profile')->assertOk()->assertDontSee('name="email"', false)->assertDontSee('name="dealer_id"', false);
        foreach (['email' => 'attack@example.test', 'dealer_id' => $other->dealer_id, 'role' => 'admin', 'status' => 'active', 'id' => $other->id, 'user_id' => $other->id] as $field => $value) {
            $this->patch('/profile', ['name' => 'Attack', $field => $value])->assertSessionHasErrors($field);
        }
        $this->patch('/profile', ['name' => 'Attack', 'email' => null, 'dealer_id' => null, 'role' => null, 'status' => null])
            ->assertSessionHasErrors(['email', 'dealer_id', 'role', 'status']);
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'changed-password', 'password_confirmation' => 'changed-password', 'dealer_id' => $other->dealer_id])->assertSessionHasErrors('dealer_id');
        $this->get(route('admin.dealer-accounts.show', $other))->assertForbidden()->assertDontSee($other->email);
        $this->patch(route('admin.dealer-accounts.update', $other), ['name' => 'Attack'])->assertForbidden();
        $this->patch('/profile/'.$other->id, ['name' => 'Attack'])->assertNotFound();
        $this->assertSame($before, $dealer->fresh()->getRawOriginal());
        $this->patch('/profile', ['name' => 'Updated name', 'phone' => '0901234567'])->assertSessionHasNoErrors();
        $this->assertSame('Updated name', $dealer->fresh()->name);
    }

    public function test_dealer_list_details_edits_and_evidence_are_scoped_to_historical_dealer(): void
    {
        $a = User::factory()->active()->create();
        $b = User::factory()->active()->create();
        $own = $this->submission($a);
        $foreign = $this->submission($b);
        $this->asAccount($a)->get(route('dealer.submissions.index', ['dealer_id' => $b->dealer_id, 'user_id' => $b->id, 'sales_id' => $b->id]))
            ->assertOk()->assertSee($own->customer_name)->assertDontSee($foreign->customer_name)
            ->assertViewHas('submissions', fn ($rows) => $rows->modelKeys() === [$own->id]);
        $this->get(route('dealer.submissions.show', $own))->assertOk();
        $this->get(route('submissions.evidence', $own))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        foreach (['show', 'edit'] as $action) {
            $this->get(route('dealer.submissions.'.$action, $foreign))->assertForbidden()->assertDontSee($foreign->customer_name);
        }
        $this->put(route('dealer.submissions.update', $foreign), [])->assertForbidden();
        $this->delete(route('dealer.submissions.destroy', $foreign))->assertForbidden();
        $this->get(route('submissions.evidence', $foreign))->assertForbidden();
        $legacy = CustomerSubmission::whereNotNull('sales_id')->whereNotNull('dealer_id')->firstOrFail();
        $legacyOwner = User::factory()->active()->create(['dealer_id' => $legacy->dealer_id]);
        $this->asAccount($legacyOwner)->get(route('dealer.submissions.show', $legacy))->assertOk();
        $unknown = CustomerSubmission::whereNull('dealer_id')->firstOrFail();
        $this->get(route('dealer.submissions.show', $unknown))->assertForbidden();
        $this->get(route('submissions.evidence', $unknown))->assertForbidden();
    }

    public function test_admin_can_read_private_evidence_but_reviewed_submission_cannot_be_changed_by_dealer(): void
    {
        $dealer = User::factory()->active()->create();
        $approved = $this->submission($dealer, 'approved');
        $this->asAccount($dealer)->get(route('dealer.submissions.show', $approved))->assertOk();
        $this->get(route('dealer.submissions.edit', $approved))->assertForbidden();
        $this->delete(route('dealer.submissions.destroy', $approved))->assertForbidden();
        $this->asAccount(User::factory()->admin()->create())->get(route('submissions.evidence', $approved))->assertOk();
    }

    public function test_submission_writes_ignore_forged_identity_and_keep_dealer_attribution(): void
    {
        $dealer = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $data = ['customer_name' => 'Own new customer', 'facebook_url' => 'https://facebook.com/auth.submission.write',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2022, 'vehicle_color' => 'White', 'license_plate' => '29D-428.12',
            'evidence_image' => \Illuminate\Http\UploadedFile::fake()->image('evidence.png'),
            'dealer_id' => $other->dealer_id, 'sales_id' => LegacySalesAccount::value('id'), 'created_by_user_id' => $other->id,
            'role' => 'admin', 'status' => 'approved'];
        $this->asAccount($dealer)->post(route('dealer.submissions.store'), $data)->assertSessionHasNoErrors();
        $submission = $dealer->submissions()->sole();
        $this->assertSame($dealer->id, $submission->created_by_user_id);
        $this->assertSame($dealer->dealer_id, $submission->dealer_id);
        $this->assertNull($submission->sales_id);
        $this->assertSame('pending', $submission->status->value);
        $this->assertSame('dealer', $dealer->fresh()->role->value);
        unset($data['evidence_image']);
        $this->put(route('dealer.submissions.update', $submission), $data)->assertSessionHasNoErrors();
        $this->assertSame($dealer->dealer_id, $submission->fresh()->dealer_id);
        $this->asAccount($other)->get(route('dealer.submissions.show', $submission))->assertForbidden();
        $this->asAccount(User::factory()->admin()->create())->get('/admin/dashboard')->assertOk();
        $this->get(route('admin.submissions.show', $submission))->assertOk();
        $this->post(route('admin.submissions.approve', $submission))->assertSessionHasNoErrors();
        $this->assertSame('approved', $submission->fresh()->status->value);
    }

    public function test_change_password_requires_current_password_and_preserves_all_identity_fields(): void
    {
        $dealer = User::factory()->active()->create();
        $identity = $dealer->only(['email', 'dealer_id', 'role', 'status']);
        $this->asAccount($dealer)->get('/profile')->assertOk();
        $password = ['current_password' => 'wrong', 'password' => 'changed-password', 'password_confirmation' => 'changed-password'];
        $this->put('/profile/password', $password)->assertSessionHasErrors('current_password');
        $this->put('/profile/password', array_replace($password, ['current_password' => 'password']))->assertSessionHasNoErrors();
        $this->assertSame($identity, $dealer->fresh()->only(['email', 'dealer_id', 'role', 'status']));
        $this->assertTrue(Hash::check('changed-password', $dealer->fresh()->password));
        $this->get('/profile')->assertOk();
    }

    public function test_blocking_a_logged_in_dealer_revokes_web_and_token_access(): void
    {
        $dealer = User::factory()->active()->create();
        $this->post('/login', ['email' => $dealer->email, 'password' => 'password'])->assertRedirect();
        DB::table('users')->where('id', $dealer->id)->update(['status' => 'blocked']);
        auth()->forgetGuards();
        $this->get('/dealer/submissions')->assertRedirect(route('account.status'));
        Sanctum::actingAs($dealer);
        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_api_returns_only_the_current_dealer_and_forged_query_ids_do_not_change_it(): void
    {
        $dealer = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $token = $dealer->createToken('authentication-test')->plainTextToken;
        $this->getJson('/api/user?dealer_id='.$other->dealer_id.'&user_id='.$other->id, ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertExactJson(['id' => $dealer->id, 'name' => $dealer->name, 'email' => $dealer->email,
                'role' => 'dealer', 'status' => 'active', 'dealer_id' => $dealer->dealer_id])
            ->assertDontSee($other->email)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_invalid_registration_and_login_inputs_do_not_create_accounts_or_expose_credentials(): void
    {
        $before = User::count();
        $this->post('/register', ['name' => ' ', 'email' => "invalid\r\n@example.test", 'dealer_id' => 999999999,
            'password' => 'short', 'password_confirmation' => 'mismatch', 'role' => 'sales', 'status' => 'active'])
            ->assertSessionHasErrors(['name', 'email', 'dealer_id', 'password', 'role', 'status']);
        $this->assertSame($before, User::count());
        $this->post('/login', ['email' => ['bad'], 'password' => ['bad']])->assertSessionHasErrors(['email', 'password']);
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        $this->assertGuest();
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_legacy_accounts_have_no_login_session_recovery_or_api_access(): void
    {
        $legacy = LegacySalesAccount::firstOrFail();
        $this->assertNotInstanceOf(\Illuminate\Contracts\Auth\Authenticatable::class, $legacy);
        $provider = auth()->getProvider();
        $this->assertNull($provider->retrieveById($legacy->id));
        $this->assertNull($provider->retrieveByToken($legacy->id, 'legacy-remember-token'));
        $this->post('/login', ['email' => $legacy->getRawOriginal('email'), 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->withSession([auth()->guard('web')->getName() => $legacy->id])->get('/dealer/submissions')->assertRedirect(route('login'));
        $forged = new User;
        $forged->setRawAttributes(['id' => $legacy->id, 'role' => 'sales', 'status' => 'active', 'dealer_id' => $legacy->dealer_id]);
        $this->asAccount($forged)->getJson('/profile')->assertForbidden();
        Sanctum::actingAs($forged);
        $this->getJson('/api/user')->assertForbidden();
        auth()->forgetGuards();
        $this->flushSession();
        Notification::fake();
        $this->post('/forgot-password', ['email' => $legacy->getRawOriginal('email')])->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_password_recovery_and_change_preserve_dealer_identity_and_status(): void
    {
        Notification::fake();
        $dealer = User::factory()->create(['status' => 'blocked']);
        $identity = $dealer->only(['email', 'role', 'status', 'dealer_id']);
        $this->post('/forgot-password', ['email' => $dealer->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($dealer, ResetPassword::class);
        $token = Password::createToken($dealer);
        $data = ['email' => $dealer->email, 'token' => $token, 'password' => 'reset-password', 'password_confirmation' => 'reset-password'];
        $this->post('/reset-password', $data + ['dealer_id' => 999, 'role' => 'admin', 'status' => 'active'])->assertSessionHasErrors(['dealer_id', 'role', 'status']);
        $this->post('/reset-password', $data)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertSame($identity, $dealer->fresh()->only(['email', 'role', 'status', 'dealer_id']));
        $this->assertTrue(Hash::check('reset-password', $dealer->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $dealer->email, 'password' => 'reset-password'])->assertRedirect(route('account.status'));
    }

    public function test_login_logout_throttle_and_csrf_still_apply(): void
    {
        $dealer = User::factory()->active()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $dealer->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => strtoupper($dealer->email), 'password' => 'password'])->assertStatus(429);
        $this->asAccount($dealer)->get('/profile')->assertOk();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/dealer/submissions')->assertRedirect(route('login'));
        app()->detectEnvironment(fn () => 'local');
        $this->post('/login', ['email' => $dealer->email, 'password' => 'password'])->assertStatus(419);
        $this->actingAs($dealer)->patch('/profile', ['name' => 'Missing csrf'])->assertStatus(419);
    }
}
