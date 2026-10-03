<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Minh Anh',
            'email' => 'minhanh@example.test',
            'dealer_id' => Dealer::factory()->create()->id,
            'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123',
        ], $overrides);
    }

    public function test_guests_can_view_auth_forms_and_only_active_dealers_are_offered(): void
    {
        $active = Dealer::factory()->create(['name' => 'Active dealer']);
        $inactive = Dealer::factory()->inactive()->create(['name' => 'Inactive dealer']);

        $this->get('/login')->assertOk()->assertSee('Đăng nhập');
        $this->get('/register')->assertOk()->assertSee($active->name)->assertDontSee($inactive->name);
    }

    public function test_no_active_dealers_disables_registration_form(): void
    {
        Dealer::factory()->inactive()->create();
        $this->get('/register')->assertOk()->assertSee('Hiện chưa có đại lý nhận đăng ký.')->assertSee('disabled');
    }

    public function test_registration_creates_pending_sales_with_hashed_password_and_selected_dealer(): void
    {
        $data = $this->registrationData(['email' => ' MINHANH@EXAMPLE.TEST ', 'name' => ' Nguyễn Minh Anh ']);
        $this->post('/register', $data)->assertRedirect(route('account.status'));
        $user = User::where('email', 'minhanh@example.test')->sole();

        $this->assertSame('Nguyễn Minh Anh', $user->name);
        $this->assertSame(UserRole::Sales, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertEquals($data['dealer_id'], $user->dealer_id);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->reviewed_by);
        $this->assertAuthenticatedAs($user);
        $this->get('/sales/dashboard')->assertRedirect(route('account.status'));
        $this->get('/account/status')->assertOk()->assertSee('Tài khoản đang chờ duyệt');
    }

    /** @dataProvider invalidRegistrationFields */
    public function test_registration_validation(string $field, mixed $value): void
    {
        $this->post('/register', $this->registrationData([$field => $value]))->assertSessionHasErrors($field);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public static function invalidRegistrationFields(): array
    {
        return [
            ['name', '   '],
            ['name', str_repeat('a', 256)],
            ['email', 'invalid-email'],
            ['email', ['invalid']],
            ['password', 'short'],
            ['password', ['invalid']],
            ['dealer_id', null],
            ['dealer_id', 'not-an-id'],
            ['dealer_id', 999999999],
            ['role', 'admin'],
            ['status', 'active'],
        ];
    }

    public function test_registration_rejects_inactive_dealer(): void
    {
        $inactive = Dealer::factory()->inactive()->create();
        $this->post('/register', $this->registrationData(['dealer_id' => $inactive->id]))->assertSessionHasErrors('dealer_id');
        $this->assertSame(0, User::count());
    }

    public function test_registration_rejects_password_confirmation_mismatch_without_flashing_passwords(): void
    {
        $this->from('/register')->post('/register', $this->registrationData(['password_confirmation' => 'different-password']))
            ->assertRedirect('/register')->assertSessionHasErrors('password');
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'minhanh@example.test']);
        $this->post('/register', $this->registrationData(['email' => 'MINHANH@EXAMPLE.TEST']))->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_registration_ignores_forged_review_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post('/register', $this->registrationData([
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-10-01 00:00:00',
            'email_verified_at' => '2026-10-01 00:00:00',
            'admin_note' => 'Forged approval',
        ]))->assertRedirect(route('account.status'));

        $user = User::where('email', 'minhanh@example.test')->sole();
        $this->assertNull($user->reviewed_by);
        $this->assertNull($user->reviewed_at);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->admin_note);
    }

    /** @dataProvider loginDestinations */
    public function test_login_redirects_by_role_and_status(string $role, string $status, string $destination): void
    {
        $factory = $role === 'admin' ? User::factory()->admin() : User::factory();
        $user = $factory->create(['status' => $status]);
        $this->withSession(['url.intended' => 'https://example.test/unsafe'])->post('/login', [
            'email' => strtoupper($user->email), 'password' => 'password',
        ])->assertRedirect(route($destination))->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($user);
    }

    public static function loginDestinations(): array
    {
        return [
            ['sales', 'active', 'sales.dashboard'],
            ['sales', 'pending', 'account.status'],
            ['sales', 'rejected', 'account.status'],
            ['sales', 'blocked', 'account.status'],
            ['admin', 'active', 'admin.dashboard'],
            ['admin', 'blocked', 'account.status'],
        ];
    }

    public function test_invalid_login_is_rejected_without_exposing_account_existence(): void
    {
        $user = User::factory()->active()->create();
        foreach ([$user->email, 'unknown@example.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
            $this->assertGuest();
        }
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_login_validates_missing_and_malformed_credentials(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->post('/login', ['email' => ['not-string'], 'password' => ['not-string']])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_rotates_session_id(): void
    {
        $user = User::factory()->active()->create();
        $this->get('/login');
        $sessionId = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('sales.dashboard'));
        $this->assertNotSame($sessionId, session()->getId());
    }

    public function test_logout_invalidates_session_and_rotates_csrf_token(): void
    {
        $user = User::factory()->active()->create();
        $this->actingAs($user)->withSession(['private_session_data' => 'secret', '_token' => 'old-token']);
        $this->post('/logout')->assertRedirect(route('login'))->assertSessionMissing('private_session_data');
        $this->assertGuest();
        $this->assertNotSame('old-token', session()->token());
        $this->get('/sales/dashboard')->assertRedirect(route('login'));
        $this->get('/logout')->assertStatus(405);
    }

    public function test_auth_mutations_require_csrf_outside_testing_bypass(): void
    {
        // Laravel skips CSRF automatically in testing; enable the real middleware path.
        app()->detectEnvironment(fn () => 'local');
        $this->post('/register', $this->registrationData())->assertStatus(419);
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(419);
        $this->assertSame(0, User::count());

        $user = User::factory()->active()->create();
        $this->actingAs($user)->withSession(['_token' => 'csrf-fixture-token']);
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout', ['_token' => 'csrf-fixture-token'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_cannot_access_protected_areas(): void
    {
        foreach (['sales.dashboard', 'admin.dashboard', 'account.status'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_active_sales_can_access_only_sales_dashboard(): void
    {
        $sales = User::factory()->active()->create();
        $this->actingAs($sales)->get('/sales/dashboard')->assertOk()->assertSee($sales->name)->assertSee($sales->dealer->name);
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/account/status')->assertRedirect(route('sales.dashboard'));
    }

    public function test_active_admin_can_access_only_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee($admin->name);
        $this->get('/sales/dashboard')->assertForbidden();
        $this->get('/account/status')->assertRedirect(route('admin.dashboard'));
    }

    /** @dataProvider inactiveSalesStatuses */
    public function test_inactive_sales_can_only_view_status(string $status, string $message): void
    {
        $sales = User::factory()->create(['status' => $status]);
        $this->actingAs($sales)->get('/sales/dashboard')->assertRedirect(route('account.status'));
        $this->getJson('/sales/dashboard')->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/account/status')->assertOk()->assertSee($message);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public static function inactiveSalesStatuses(): array
    {
        return [
            ['pending', 'Tài khoản đang chờ duyệt'],
            ['rejected', 'Đăng ký chưa được chấp nhận'],
            ['blocked', 'Tài khoản đã bị khóa'],
        ];
    }

    public function test_blocked_admin_cannot_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['status' => UserStatus::Blocked]);
        $this->actingAs($admin)->get('/admin/dashboard')->assertRedirect(route('account.status'));
    }

    public function test_blocking_an_existing_session_revokes_dashboard_access(): void
    {
        $sales = User::factory()->active()->create();
        $this->post('/login', ['email' => $sales->email, 'password' => 'password']);
        $sales->status = UserStatus::Blocked;
        $sales->save();
        // Simulate the next request loading its user from the session provider.
        auth()->forgetGuards();
        $this->get('/sales/dashboard')->assertRedirect(route('account.status'));
    }

    public function test_authenticated_users_are_redirected_away_from_auth_forms(): void
    {
        $sales = User::factory()->create();
        $this->actingAs($sales)->get('/login')->assertRedirect(route('account.status'));
        $this->get('/register')->assertRedirect(route('account.status'));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/register')->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_rate_limit_normalizes_email_and_returns_retry_header(): void
    {
        $user = User::factory()->active()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => ' '.strtoupper($user->email).' ', 'password' => 'password'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_login_ip_limit_cannot_be_bypassed_by_changing_email(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post('/login', ['email' => 'unknown'.$attempt.'@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'different@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }
        $this->post('/register', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, User::count());
    }
}
