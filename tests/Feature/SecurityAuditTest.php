<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Exceptions\Handler;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use LogicException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
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

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_api_user_exposes_only_allowlisted_account_fields_and_never_review_notes(): void
    {
        $user = User::factory()->active()->create(['admin_note' => 'private-review-note', 'phone' => 'private-phone']);
        Sanctum::actingAs($user);
        $this->getJson('/api/user')->assertOk()->assertExactJson([
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => 'sales', 'status' => 'active', 'dealer_id' => $user->dealer_id,
        ])->assertDontSee('private-review-note')->assertDontSee('private-phone')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('admin_note', $user->toArray());
        $this->assertArrayNotHasKey('rejection_reason', $user->toArray());
    }

    /** @dataProvider inactiveStatuses */
    public function test_inactive_accounts_cannot_bypass_status_protection_through_api(UserStatus $status): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => $status, 'admin_note' => 'private-review-note']));
        $this->getJson('/api/user')->assertForbidden()->assertDontSee('private-review-note');
    }

    public static function inactiveStatuses(): array
    {
        return [[UserStatus::Pending], [UserStatus::Rejected], [UserStatus::Blocked]];
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_customer_pages_cannot_be_cached_and_have_security_headers(): void
    {
        $sales = User::factory()->active()->create();
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($sales)->get(route('sales.submissions.show', $submission))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.submissions.show', $submission))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_auth_forms_are_not_cacheable(): void
    {
        foreach (['login', 'register'] as $route) {
            $this->get(route($route))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    /** @dataProvider unsafeEmails */
    public function test_email_control_characters_are_rejected_at_registration_and_login(string $email): void
    {
        $dealer = Dealer::factory()->create();
        $this->postJson('/register', [
            'name' => 'Audit Sales', 'email' => $email, 'dealer_id' => $dealer->id,
            'password' => 'password-123', 'password_confirmation' => 'password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', ['email' => $email, 'password' => 'password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public static function unsafeEmails(): array
    {
        return [
            ["alice@example.test\r\nBcc: victim@example.test"],
            ["alice(\r\n folded)@example.test"],
            ["\"alice\r\n folded\"@example.test"],
            ["alice\0@example.test"],
            ["alice\t@example.test"],
        ];
    }

    public function test_admin_write_rate_limit_is_shared_between_moderation_and_management_endpoints(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson(route('admin.dealers.store'), [])->assertUnprocessable();
        }
        $this->postJson(route('admin.awards.store'), [])->assertStatus(429)->assertHeader('Retry-After');
        $this->get(route('admin.sales.index'))->assertOk();
        $this->assertDatabaseCount('dealers', 0);
        $this->assertDatabaseCount('awards', 0);
    }

    public function test_all_existing_admin_and_sales_mutation_types_require_real_csrf_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $pendingSales = User::factory()->create();
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $dealer = $sales->dealer;
        // Exercise the real CSRF implementation rather than Laravel's testing bypass.
        app()->detectEnvironment(fn () => 'local');
        $this->actingAs($admin)->withSession(['_token' => 'known-csrf-token']);
        foreach ([
            ['POST', route('admin.dealers.store')],
            ['PATCH', route('admin.dealers.update', $dealer)],
            ['PATCH', route('admin.dealers.status', $dealer)],
            ['POST', route('admin.sales.review', [$pendingSales, 'approve'])],
            ['POST', route('admin.submissions.approve', $submission)],
            ['POST', route('admin.submissions.reject', $submission)],
            ['POST', route('admin.awards.store')],
        ] as [$method, $url]) {
            $this->call($method, $url)->assertStatus(419);
        }
        $this->actingAs($sales);
        foreach ([
            ['POST', route('sales.submissions.store')],
            ['PATCH', route('sales.submissions.update', $submission)],
            ['DELETE', route('sales.submissions.destroy', $submission)],
        ] as [$method, $url]) {
            $this->call($method, $url)->assertStatus(419);
        }
        $this->assertSame('pending', $submission->fresh()->status->value);
        $this->assertSame('pending', $pendingSales->fresh()->status->value);
        $this->assertDatabaseCount('moderation_reviews', 0);
    }

    public function test_sql_payloads_and_forged_owner_filters_do_not_escape_sales_scope(): void
    {
        $sales = User::factory()->active()->create();
        $other = CustomerSubmission::factory()->create(['customer_name' => 'Other private customer']);
        $this->actingAs($sales);
        foreach (["' OR 1=1 --", '%', '_', "'; DROP TABLE users; --"] as $search) {
            $this->get(route('sales.submissions.index', ['q' => $search, 'sales_id' => $other->sales_id]))
                ->assertOk()->assertDontSee($other->customer_name)
                ->assertViewHas('submissions', fn ($items) => $items->isEmpty());
        }
        $this->getJson(route('sales.submissions.show', $other))->assertForbidden();
        $this->patchJson(route('sales.submissions.update', $other), [])->assertForbidden();
        $this->deleteJson(route('sales.submissions.destroy', $other))->assertForbidden();
        $this->assertNotNull($other->fresh());
    }

    public function test_sales_requests_cannot_modify_account_privileges_or_approved_customer_data(): void
    {
        $sales = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $submission = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $original = $submission->refresh()->getRawOriginal();
        $this->actingAs($sales)->patchJson(route('sales.submissions.update', $submission), [
            'customer_name' => 'Changed customer', 'facebook_url' => 'https://facebook.com/changed.customer',
            'role' => 'admin', 'status' => 'pending', 'dealer_id' => $other->dealer_id, 'sales_id' => $other->id,
        ])->assertForbidden();
        $this->deleteJson(route('sales.submissions.destroy', $submission))->assertForbidden();
        $this->assertSame($original, $submission->fresh()->getRawOriginal());
        $this->assertSame('sales', $sales->fresh()->role->value);
        $this->assertSame('active', $sales->fresh()->status->value);
        $this->assertSame($sales->dealer_id, $sales->fresh()->dealer_id);
    }

    public function test_public_routes_do_not_render_private_customer_or_account_fields(): void
    {
        $sales = User::factory()->active()->create(['email' => 'private-account@example.test', 'phone' => 'private-account-phone']);
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'private-customer-name',
            'facebook_url' => 'https://facebook.com/private.customer', 'phone' => 'private-customer-phone',
            'notes' => 'private-sales-note', 'admin_note' => 'private-admin-note',
        ]);
        $draft = Award::factory()->create(['title' => 'private-draft']);
        foreach (['home', 'leaderboard', 'awards.index', 'login', 'register'] as $route) {
            $response = $this->get(route($route))->assertOk();
            foreach (['private-account@example.test', 'private-account-phone', 'private-customer-name',
                'private.customer', 'private-customer-phone', 'private-sales-note', 'private-admin-note', 'private-draft'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
        $this->get(route('awards.show', $draft))->assertNotFound();
    }

    public function test_production_disables_debug_and_requires_secure_session_cookie_even_with_unsafe_configuration(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true, 'session.secure' => false]);
        app()->getProvider(AppServiceProvider::class)->boot();
        $this->assertFalse(config('app.debug'));
        $this->assertTrue(config('session.secure'));
        $response = $this->get('/login')->assertOk();
        $cookies = collect($response->headers->getCookies());
        $cookie = $cookies->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $request = Request::create('/api/user', 'GET');
        $request->headers->set('Accept', 'application/json');
        $errorResponse = app(Handler::class)->render($request, new RuntimeException('private-error-detail'));
        $this->assertSame(500, $errorResponse->getStatusCode());
        $this->assertStringNotContainsString('private-error-detail', $errorResponse->getContent());
    }

    public function test_untrusted_host_is_rejected_outside_local_and_test_bypass(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://toyota.example.test', 'app.debug' => false]);
        $this->get('https://attacker.example.test/login')->assertNotFound()->assertDontSee('name="password"', false);
    }

    public function test_debug_solution_endpoints_are_unavailable_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);
        app()->getProvider(AppServiceProvider::class)->boot();
        $this->getJson('/_ignition/health-check')->assertNotFound();
        $this->postJson('/_ignition/execute-solution', [])->assertNotFound();
        $this->postJson('/_ignition/update-config', [])->assertNotFound();
    }

    public function test_database_preserves_dealers_referenced_only_by_historical_sales_snapshots(): void
    {
        $historicDealer = Dealer::factory()->create();
        $winner = AwardWinner::factory()->create(['sales_dealer_id_snapshot' => $historicDealer->id]);
        $this->assertNotSame($winner->sales->dealer_id, $historicDealer->id);
        try {
            DB::table('dealers')->where('id', $historicDealer->id)->delete();
            $this->fail('Foreign key must protect historical dealer references even when model events are bypassed.');
        } catch (QueryException $exception) {
            $this->assertSame(1451, $exception->errorInfo[1]);
        }
        $this->assertModelExists($historicDealer);
        $this->assertSame($historicDealer->id, $winner->fresh()->sales_dealer_id_snapshot);
    }

    public function test_database_rejects_nonexistent_dealer_snapshot_references(): void
    {
        $winner = AwardWinner::factory()->create();
        try {
            DB::table('award_winners')->where('id', $winner->id)->update(['sales_dealer_id_snapshot' => 999999999]);
            $this->fail('Foreign key must reject orphaned historical dealer references.');
        } catch (QueryException $exception) {
            $this->assertSame(1452, $exception->errorInfo[1]);
        }
        $this->assertSame($winner->sales_dealer_id_snapshot, $winner->fresh()->sales_dealer_id_snapshot);
    }

    /** @dataProvider exceptionWrapping */
    public function test_database_reporting_omits_bindings_messages_and_nested_exception_traces(bool $wrapped): void
    {
        $previous = new PDOException('Duplicate entry private-customer@example.test');
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry private-customer@example.test'];
        $queryError = new QueryException('mysql', 'insert into users (email, password) values (?, ?)',
            ['private-customer@example.test', 'private-password'], $previous);
        $error = $wrapped ? new RuntimeException('private-wrapper: '.$queryError->getMessage(), 0, $queryError) : $queryError;
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            $this->assertSame('Database operation failed.', $message);
            $this->assertSame('23000', $context['sql_state']);
            $this->assertSame(1062, $context['driver_code']);
            $serialized = json_encode([$message, $context]);
            foreach (['private-customer', 'private-password', 'private-wrapper', 'insert into', 'exception"', 'trace'] as $secret) {
                $this->assertStringNotContainsString($secret, $serialized);
            }

            return true;
        });
        app(Handler::class)->report($error);
    }

    public static function exceptionWrapping(): array
    {
        return [[false], [true]];
    }
}
