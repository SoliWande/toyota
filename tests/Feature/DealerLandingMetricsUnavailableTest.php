<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\ResetDemoRetirement;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerLandingMetricsUnavailableTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFixtureTransaction; }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertContains(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1']);
        $identity = DB::selectOne('SELECT DATABASE() db, @@hostname host');
        $this->assertSame('toyota_testing', $identity->db);
        $this->assertSame(strtolower(gethostname()), strtolower($identity->host));
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['realistic_demo.retired' => false, 'realistic_demo.enabled' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false,
            'dealer-registration.member_count' => 1126]);
        $this->app->instance(ResetDemoRetirement::class, Mockery::mock(ResetDemoRetirement::class, fn ($mock) => $mock->shouldReceive('isPresent')->andReturn(false)));
    }

    private function demoAdmin(): User
    {
        // Transaction-only modification of the reserved test account, never reset/seed a dataset.
        return User::where('email', ToyotaRealisticDemo::EMAIL)->first()
            ?? User::factory()->admin()->create(['email' => ToyotaRealisticDemo::EMAIL]);
    }

    private function proof(User $admin, array $ids, string $database = 'toyota_testing'): array
    {
        return ['tag' => ToyotaRealisticDemo::TAG, 'users' => [$admin->id], 'reviews' => [], 'submissions' => $ids,
            'ownership' => Crypt::encryptString(json_encode(['dataset' => ToyotaRealisticDemo::TAG,
                'database' => $database, 'admin' => $admin->id, 'submissions' => $ids], JSON_THROW_ON_ERROR))];
    }

    #[DataProvider('untrustedStates')]
    public function test_unverified_demo_metrics_hide_count_but_keep_dealer_cta_and_registration(string $problem, bool $enabled, bool $include, bool $productionLike): void
    {
        $dealer = Dealer::factory()->create(['address' => 'Địa chỉ từ dữ liệu đại lý', 'opening_hours' => '08:00 – 17:00']);
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->approved()->create(['dealer_id' => $dealer->id, 'sales_id' => $sales->id]);
        $admin = $this->demoAdmin();
        $ids = range(2_000_000_000, 2_000_001_499);
        $manifest = $this->proof($admin, $ids);
        if ($problem === 'missing_metadata') {
            $manifest = null;
        } elseif ($problem === 'missing_proof') {
            unset($manifest['ownership']);
        } elseif ($problem === 'bad_ciphertext') {
            $manifest['ownership'] = 'not-a-valid-envelope';
        } elseif ($problem === 'foreign_database') {
            $manifest = $this->proof($admin, $ids, 'different_database_binding');
        } elseif ($problem === 'conflicting_ids') {
            $manifest['submissions'][1] = $manifest['submissions'][0];
        } elseif ($problem === 'changed_legacy_fingerprint') {
            unset($manifest['ownership']);
            $manifest['fingerprint'] = str_repeat('0', 64);
        }
        $admin->forceFill(['role' => 'admin', 'admin_note' => $manifest === null ? null : json_encode($manifest, JSON_THROW_ON_ERROR)])->save();
        config(['realistic_demo.enabled' => $enabled, 'realistic_demo.include_in_metrics' => $include,
            'realistic_demo.allow_production' => $enabled, 'app.debug' => false]);
        Log::spy();
        if ($productionLike) {
            $this->app['env'] = 'production';
        }
        try {
            $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk()
                ->assertSee($dealer->name)->assertSee('Địa chỉ từ dữ liệu đại lý')->assertSee('08:00 – 17:00')
                ->assertSee('Tham gia ngay')->assertSee('id="registration"', false)->assertSee('data-step-one', false)
                ->assertSee(route('dealers.registrations.step-one', $dealer->slug))
                ->assertDontSee('class="dr-hero-count"', false)->assertDontSee('0 chủ xe')->assertDontSee('1.126')
                ->assertDontSee('metrics require operator review')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertNull($response->viewData('dealerOwnerCount'));
            $this->assertNull($response->viewData('standing'));
            try {
                app(ToyotaRealisticDemo::class)->ownedSubmissionIds();
                $this->fail('The underlying ownership guard must still reject this manifest.');
            } catch (\LogicException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
            Log::shouldHaveReceived('warning')->once()->with('Dealer landing metrics unavailable; demo verification requires operator review.', [
                'dealer_id' => $dealer->id, 'reason' => 'demo_verification_failed',
            ]);
            if ($problem === 'bad_ciphertext' && $productionLike) {
                $directory = base_path('.laravel-tools/dealer-metrics-unavailable');
                if (! is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                file_put_contents($directory.'/unavailable.html', $response->getContent());
            }
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public static function untrustedStates(): array
    {
        return [
            ['missing_metadata', false, false, true],
            ['missing_metadata', true, false, false],
            ['missing_proof', false, false, false],
            ['bad_ciphertext', true, true, true],
            ['bad_ciphertext', false, false, true],
            ['foreign_database', false, false, true],
            ['conflicting_ids', true, false, false],
            ['changed_legacy_fingerprint', false, false, true],
        ];
    }

    #[DataProvider('verifiedFlags')]
    public function test_real_verifier_excludes_owned_demo_and_other_dealers_when_proof_matches(bool $enabled, bool $include, bool $productionLike): void
    {
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        $owned = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->approved()->create();
        $ids = [$owned->id, ...range(2_000_000_000, 2_000_001_498)];
        $admin = $this->demoAdmin();
        $admin->forceFill(['role' => 'admin', 'admin_note' => json_encode($this->proof($admin, $ids), JSON_THROW_ON_ERROR)])->save();
        $this->assertSame($ids, app(ToyotaRealisticDemo::class)->ownedSubmissionIds());
        config(['realistic_demo.enabled' => $enabled, 'realistic_demo.include_in_metrics' => $include,
            'realistic_demo.allow_production' => $enabled]);
        Log::spy();
        if ($productionLike) {
            $this->app['env'] = 'production';
        }
        try {
            $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertViewHas('dealerOwnerCount', 1)->assertSee('1 chủ xe');
            Log::shouldNotHaveReceived('warning');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public static function verifiedFlags(): array
    {
        return [[false, false, true], [true, false, false], [true, true, true]];
    }

    public function test_no_metrics_query_runs_after_verification_failure_and_exception_details_are_not_logged(): void
    {
        $dealer = Dealer::factory()->create();
        $service = Mockery::mock(ToyotaRealisticDemo::class);
        $service->shouldReceive('ownedSubmissionIds')->once()->andThrow(new \LogicException('secret@example.test 0912345678 encrypted-envelope'));
        $this->app->instance(ToyotaRealisticDemo::class, $service);
        Log::shouldReceive('warning')->once()->with('Dealer landing metrics unavailable; demo verification requires operator review.', [
            'dealer_id' => $dealer->id, 'reason' => 'demo_verification_failed',
        ]);
        DB::enableQueryLog();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertDontSee('secret@example.test')->assertDontSee('encrypted-envelope');
        $this->assertNull($response->viewData('dealerOwnerCount'));
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('COUNT(*) AS score', $query['query']);
            $this->assertStringNotContainsString('ROW_NUMBER()', $query['query']);
        }
        DB::disableQueryLog();
    }

    public function test_later_guard_failure_clears_partial_standing_and_does_not_leak_count(): void
    {
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'dealer_id' => $dealer->id]);
        // Explicit proof and standing pass; ownership then fails while preparing the count.
        $service = Mockery::mock(ToyotaRealisticDemo::class);
        $service->shouldReceive('ownedSubmissionIds')->twice()->andReturn([])->ordered();
        $service->shouldReceive('ownedSubmissionIds')->once()->andThrow(new \LogicException('ownership changed'))->ordered();
        $this->app->instance(ToyotaRealisticDemo::class, $service);
        Log::spy();
        $response = $this->get(route('dealers.profile', $dealer->slug))->assertOk()->assertDontSee('class="dr-hero-count"', false);
        $this->assertNull($response->viewData('dealerOwnerCount'));
        $this->assertNull($response->viewData('standing'));
    }
}
