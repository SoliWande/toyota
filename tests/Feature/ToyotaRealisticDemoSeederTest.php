<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Carbon\CarbonImmutable;
use Database\Seeders\ToyotaDealerSeeder;
use Database\Seeders\ToyotaRealisticDemoSeeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** No RefreshDatabase: never migrate, wipe or truncate an existing database. */
class ToyotaRealisticDemoSeederTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        TestDatabase::assertIsolated();
        if (! DB::getSchemaBuilder()->hasTable('submission_edits')) {
            $this->markTestSkipped('Prepare an isolated MySQL schema separately; this test never runs migrations.');
        }
        if (User::exists() || Dealer::exists() || CustomerSubmission::exists()) {
            $this->markTestSkipped('Requires empty isolated schema; refuses to alter pre-existing data.');
        }
        DB::beginTransaction();
        $this->transactionStarted = true;
        config(['realistic_demo.enabled' => true, 'realistic_demo.password' => 'test-only-random-secret-12345']);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 14:30:00'));
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_dataset_business_rules_privacy_idempotence_and_cleanup(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        $directory = Dealer::orderBy('id')->get()->map->getRawOriginal()->all();
        $demo = app(ToyotaRealisticDemo::class);
        $demo->confirmed = true;
        $this->seed(ToyotaRealisticDemoSeeder::class);
        $this->assertDatabaseCount('customer_submissions', 1500);
        $this->assertSame(120, User::where('role', 'sales')->count());
        $this->assertSame(25, CustomerSubmission::distinct()->count('dealer_id'));
        $this->assertSame(1500, CustomerSubmission::distinct()->count('phone'));
        $this->assertSame(1500, CustomerSubmission::distinct()->count('license_plate'));
        $this->assertSame(1125, CustomerSubmission::where('status', 'approved')->count());
        $this->assertSame(270, CustomerSubmission::where('status', 'pending')->count());
        $this->assertSame(105, CustomerSubmission::where('status', 'rejected')->count());
        $this->assertSame(0, CustomerSubmission::where('submitted_at', '>', now())->count());
        $this->assertSame(0, CustomerSubmission::whereColumn('reviewed_at', '<', 'submitted_at')->count());
        $this->assertSame(0, CustomerSubmission::where('source', 'dealer_qr')->whereNotNull('sales_id')->count());
        $this->assertSame(1230, DB::table('moderation_reviews')->count());
        $this->assertSame(0, CustomerSubmission::where('status', 'pending')->whereNotNull('reviewed_at')->count());
        $this->assertSame(0, CustomerSubmission::where('status', 'approved')->whereNull('reviewed_by')->count());
        $this->assertSame(0, CustomerSubmission::where('status', 'rejected')->whereNull('rejection_reason')->count());
        foreach ($demo->status()['manifest']['transfers'] as $transfer) {
            $history = CustomerSubmission::where('sales_id', $transfer['sales_id']);
            $this->assertGreaterThan(0, (clone $history)->where('submitted_at', '<', $transfer['at'])->count());
            $this->assertSame(0, (clone $history)->where('submitted_at', '<', $transfer['at'])->where('dealer_id', '!=', $transfer['from'])->count());
            $this->assertGreaterThan(0, (clone $history)->where('submitted_at', '>=', $transfer['at'])->count());
            $this->assertSame(0, (clone $history)->where('submitted_at', '>=', $transfer['at'])->where('dealer_id', '!=', $transfer['to'])->count());
        }
        $leaders = [];
        $service = app(LeaderboardService::class);
        for ($offset = 0; $offset < 6; $offset++) {
            $at = CarbonImmutable::now()->startOfMonth()->subMonths($offset);
            [$start, $end] = $service->bounds(LeaderboardPeriod::Month, $at);
            $base = CustomerSubmission::where('status', 'approved')->where('submitted_at', '>=', $start)->where('submitted_at', '<', $end);
            $expected = (clone $base)->select('dealer_id')->selectRaw('COUNT(*) AS score')->groupBy('dealer_id')->pluck('score', 'dealer_id')->map(fn ($v) => (int) $v)->sortKeys()->all();
            $actual = $service->dealers(LeaderboardPeriod::Month, $at)->get();
            $this->assertSame($expected, $actual->pluck('score', 'id')->map(fn ($v) => (int) $v)->sortKeys()->all());
            $leaders[] = $actual->first()->id;
            $expectedSales = (clone $base)->whereNotNull('sales_id')->select('sales_id')->selectRaw('COUNT(*) AS score')->groupBy('sales_id')->pluck('score', 'sales_id')->map(fn ($v) => (int) $v)->sortKeys()->all();
            $this->assertSame($expectedSales, $service->sales(LeaderboardPeriod::Month, $at)->get()->pluck('score', 'id')->map(fn ($v) => (int) $v)->sortKeys()->all());
        }
        $this->assertGreaterThanOrEqual(4, count(array_unique($leaders)));
        $this->assertSame(6, DB::table('customer_submissions')->selectRaw("COUNT(DISTINCT DATE_FORMAT(submitted_at, '%Y-%m')) AS n")->value('n'));
        $this->assertGreaterThan(0, CustomerSubmission::join('users', 'users.id', '=', 'customer_submissions.sales_id')->whereColumn('customer_submissions.dealer_id', '!=', 'users.dealer_id')->count());
        $customers = CustomerSubmission::orderBy('id')->get()->map->getRawOriginal()->all();
        $accounts = User::orderBy('id')->get()->map->getRawOriginal()->all();
        $this->seed(ToyotaRealisticDemoSeeder::class);
        $this->assertSame($customers, CustomerSubmission::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($accounts, User::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($directory, Dealer::orderBy('id')->get()->map->getRawOriginal()->all());
        $customer = CustomerSubmission::first();
        $sales = User::where('role', 'sales')->first();
        foreach ([route('leaderboard'), route('sales.public.show', $sales->public_slug)] as $url) {
            $this->get($url)->assertOk()->assertDontSee($customer->phone)->assertDontSee($customer->facebook_url)->assertDontSee($customer->address_line)->assertDontSee($sales->email);
        }
        $real = User::factory()->admin()->create();
        $demo->cleanup();
        $this->assertDatabaseCount('users', 1);
        $this->assertNotNull($real->fresh());
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertSame($directory, Dealer::orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_direct_seeder_cannot_bypass_confirmation(): void
    {
        $this->expectException(LogicException::class);
        $this->seed(ToyotaRealisticDemoSeeder::class);
    }

    public function test_production_requires_separate_opt_in(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->expectException(LogicException::class);
        app(ToyotaRealisticDemo::class)->assertEnabled();
    }

    public function test_failure_rolls_back_all_new_records(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        app(ToyotaRealisticDemo::class)->confirmed = true;
        CustomerSubmission::creating(fn () => throw new LogicException('Injected failure'));
        try {
            $this->seed(ToyotaRealisticDemoSeeder::class);
            $this->fail('Must roll back.');
        } catch (LogicException $e) {
            $this->assertSame('Injected failure', $e->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customer_submissions', 0);
        $this->assertDatabaseCount('dealers', 87);
    }

    public function test_reserved_real_account_collision_is_never_overwritten(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        $real = User::factory()->admin()->create(['email' => ToyotaRealisticDemo::EMAIL]);
        $original = $real->getRawOriginal();
        app(ToyotaRealisticDemo::class)->confirmed = true;
        try {
            $this->seed(ToyotaRealisticDemoSeeder::class);
            $this->fail('Must refuse reserved identity collision.');
        } catch (LogicException) {
            $this->assertSame($original, $real->fresh()->getRawOriginal());
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('customer_submissions', 0);
        }
    }

    public function test_cleanup_refuses_modified_demo_records(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        $demo = app(ToyotaRealisticDemo::class);
        $demo->confirmed = true;
        $this->seed(ToyotaRealisticDemoSeeder::class);
        $customer = CustomerSubmission::first();
        $customer->forceFill(['customer_name' => 'Edited after demo'])->save();
        try {
            $demo->cleanup();
            $this->fail('Must refuse modified records.');
        } catch (LogicException) {
            $this->assertDatabaseCount('customer_submissions', 1500);
            $this->assertSame('Edited after demo', $customer->fresh()->customer_name);
        }
    }
}
