<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\DemoCleanup;
use App\Services\LeaderboardService;
use App\Services\ResetDemoBackup;
use App\Services\ResetDemoData;
use App\Services\ResetDemoRetirement;
use App\Services\SubmissionVerification;
use App\Services\ToyotaRealisticDemo;
use Database\Seeders\ToyotaDealerSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class IsolatedResetBackup extends ResetDemoBackup
{
    protected function dump(string $directory, string $target): void
    {
        file_put_contents($target, "-- synthetic testing transport; not a restorable backup\n");
    }
}

class IsolatedResetRetirement extends ResetDemoRetirement
{
    public function __construct(private string $testPath) {}

    public function path(): string
    {
        return $this->testPath;
    }
}

class ResetDemoDataTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32)), 'cache.default' => 'array',
            'cache.stores.file.path' => base_path('.laravel-tools/reset-tests/'.bin2hex(random_bytes(8))),
            'realistic_demo.enabled' => false, 'realistic_demo.allow_production' => false,
            'realistic_demo.include_in_metrics' => false, 'realistic_demo.retired' => false]);
        $this->app->instance(ResetDemoBackup::class, new IsolatedResetBackup);
        $this->app->instance(ResetDemoRetirement::class, new IsolatedResetRetirement(
            storage_path('app/private/reset-test-receipts/'.bin2hex(random_bytes(16)).'.enc')));
    }

    private function fixture(): array
    {
        $this->assertSame(0, DB::table('users')->count(), 'Tests require an empty isolated user fixture baseline, never clear existing data.');
        $this->seed(ToyotaDealerSeeder::class); // Guarded test transaction, no production seeder invocation.
        $dealers = Dealer::orderBy('id')->get();
        $real = User::factory()->admin()->create();
        $blockedAdmin = User::factory()->admin()->create(['status' => 'blocked']);
        $demo = User::factory()->admin()->create(['email' => ToyotaRealisticDemo::EMAIL, 'admin_note' => 'invalid-old-demo-manifest']);
        $manager = User::factory()->dealer()->create(['dealer_id' => $dealers[0]->id, 'reviewed_by' => $real->id, 'reviewed_at' => now()]);
        $blockedManager = User::factory()->dealer()->create(['dealer_id' => $dealers[1]->id, 'status' => 'blocked']);
        $sales = User::factory()->active()->create(['dealer_id' => $dealers[0]->id, 'reviewed_by' => $demo->id, 'reviewed_at' => now()]);
        $record = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id,
            'dealer_id' => $dealers[0]->id, 'reviewed_by' => $demo->id, 'notes' => ToyotaRealisticDemo::TAG]);
        DB::table('moderation_reviews')->insert(['customer_submission_id' => $record->id, 'reviewed_by' => $demo->id,
            'from_status' => 'pending', 'to_status' => 'approved', 'reviewed_at' => now()]);
        DB::table('submission_verification_states')->insert(['submission_id' => $record->id, 'group_progress' => 'member',
            'group_verified_by' => $demo->id, 'group_verified_at' => now()]);
        DB::table('submission_verification_audits')->insert(['submission_id' => $record->id, 'actor_id' => $demo->id,
            'actor_type' => 'admin', 'action' => 'group', 'old_values' => '{}', 'new_values' => '{}', 'created_at' => now()]);
        DB::table('submission_edits')->insert(['customer_submission_id' => $record->id, 'submission_id_snapshot' => $record->id,
            'edited_by' => $demo->id, 'changes' => '{}', 'edited_at' => now()]);
        DB::table('dealer_registration_receipts')->insert(['draft_key' => str_repeat('a', 64), 'owner_key' => str_repeat('b', 64),
            'submission_id' => $record->id, 'confirmation' => Crypt::encryptString('isolated'), 'expires_at' => now()->addDay()]);
        $award = Award::factory()->create();
        AwardWinner::factory()->create(['award_id' => $award->id, 'sales_id' => $sales->id]);
        AwardWinner::factory()->dealer()->create(['award_id' => $award->id, 'dealer_id' => $dealers[0]->id, 'rank' => 1]);
        $award->forceFill(['published_by' => $demo->id, 'published_at' => now()])->save();
        foreach ([$real, $manager, $sales] as $account) {
            DB::table('personal_access_tokens')->insert(['tokenable_type' => (new User)->getMorphClass(), 'tokenable_id' => $account->id,
                'name' => 'fixture', 'token' => hash('sha256', 'fixture-'.$account->id), 'abilities' => '[]']);
            DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'isolated', 'created_at' => now()]);
        }

        return compact('real', 'blockedAdmin', 'demo', 'manager', 'blockedManager', 'sales', 'record');
    }

    private function plan(array $fixture): array
    {
        return app(ResetDemoData::class)->audit('toyota_testing', [$fixture['real']->id, $fixture['blockedAdmin']->id]);
    }

    private function backup(array $plan): array
    {
        $this->assertTrue($plan['eligible'], json_encode($plan['blockers']));

        return app(ResetDemoBackup::class)->create($plan);
    }

    private function arguments(array $plan, array $backup): array
    {
        return ['--database' => 'toyota_testing', '--keep-admin' => array_map('strval', $plan['approved_admin_ids']),
            '--execute' => true, '--plan' => $plan['plan_hash'], '--receipt' => $backup['receipt'],
            '--restore-verified' => $backup['sha256'], '--confirm' => 'reset-demo-data:toyota_testing'];
    }

    public function test_default_dry_run_exports_account_ids_counts_and_hashes_without_writes(): void
    {
        $f = $this->fixture();
        $before = app(DemoCleanup::class)->snapshots();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', ['--database' => 'toyota_testing',
            '--keep-admin' => [(string) $f['real']->id, (string) $f['blockedAdmin']->id]]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('dry-run', $plan['mode']);
        $this->assertArrayNotHasKey('delete', $plan);
        $this->assertStringNotContainsString($f['sales']->email, Artisan::output());
        $this->assertSame(87, $plan['tables']['dealers']['keep']);
        $this->assertSame(0, $plan['tables']['dealers']['delete']);
        $this->assertSame([$f['demo']->id, $f['sales']->id], $plan['deleted_user_ids']);
        $this->assertCount(2, $plan['kept_dealer_accounts']);
        $this->assertCount(3, $plan['admin_candidates']);
        foreach (ResetDemoData::BUSINESS as $table) {
            $this->assertGreaterThan(0, $plan['tables'][$table]['delete']);
        }
        $this->assertCount(0, array_filter($queries, fn ($q) => preg_match('/^\s*(INSERT|UPDATE|DELETE|TRUNCATE|ALTER|DROP)/i', $q['query'])));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
        file_put_contents(base_path('.laravel-tools/reset-demo-fixture-plan.json'), json_encode($plan, JSON_PRETTY_PRINT));
    }

    public function test_execute_clears_all_business_rows_preserving_every_dealer_and_approved_account_field(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $kept = DB::table('users')->whereIn('id', $plan['kept_user_ids'])->orderBy('id')->get()->toJson();
        $directory = DB::table('dealers')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $output = json_decode(Artisan::output(), true);
        $this->assertTrue($output['retained_data_unchanged']);
        $this->assertSame($kept, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($directory, DB::table('dealers')->orderBy('id')->get()->toJson());
        $this->assertSame(87, Dealer::count());
        foreach (ResetDemoData::BUSINESS as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(2, DB::table('personal_access_tokens')->count());
        $this->assertSame(2, DB::table('password_reset_tokens')->count());
        $this->assertSame([], app(ResetDemoData::class)->audit('toyota_testing', $plan['approved_admin_ids'])['orphans']);
        app(ResetDemoRetirement::class)->assertValid();
        $this->assertFalse(config('realistic_demo.retired')); // Receipt activates safe retirement even before config refresh.
        $this->mock(ToyotaRealisticDemo::class)->shouldNotReceive('ownedSubmissionIds');
        $this->get(route('home'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['points'] === 0);
        $this->get(route('leaderboard'))->assertOk()->assertViewHas('dealers', fn ($rows) => $rows->total() === 0);
        $this->get(route('awards.index'))->assertOk()->assertSee('Chưa có kết quả vinh danh được công bố.');
        $this->get(route('dealers.index'))->assertOk();
        $this->assertSame(0, app(LeaderboardService::class)->dealerScores()->count());
    }

    public function test_preserved_admin_and_dealer_can_log_in_after_reset(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $this->post('/login', ['email' => $f['real']->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($f['real']);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['email' => $f['manager']->email, 'password' => 'password'])->assertRedirect(route('dealer.dashboard'));
        $this->assertAuthenticatedAs($f['manager']);
        $this->get(route('dealer.dashboard'))->assertOk();
    }

    public function test_empty_lookup_then_new_registration_verification_approval_and_score_are_functional(): void
    {
        $f = $this->fixture();
        $dealer = $f['manager']->dealer;
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post(route('dealers.lookup.store', $dealer->slug), ['phone' => '0990000012', 'license_plate' => '51K-900.12'])
            ->assertOk()->assertViewHas('result', fn ($result) => $result['kind'] === 'not_found');
        $this->post(route('dealers.registrations.step-one', $dealer->slug), ['customer_name' => 'ISOLATED NEW REAL FLOW',
            'phone' => '0990000012', 'vehicle_description' => 'FJ'])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $draft = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->viewData('draftId');
        $divisions = app(AdministrativeDivisions::class);
        $province = $divisions->provinces()[0]['code'];
        $ward = $divisions->wards($province)[0]['code'];
        $this->post(route('dealers.registrations.store', $dealer->slug), ['_flow' => 'two-step', '_draft' => $draft,
            'province_code' => $province, 'ward_code' => $ward, 'address_line' => 'Isolated synthetic address',
            'license_plate' => '51K-900.12', 'first_registration_year' => 2024,
            'facebook_url' => 'https://facebook.com/new.reset.isolated.flow', 'consent' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $record = CustomerSubmission::sole();
        $verification = app(SubmissionVerification::class);
        $verification->adminUpdate($f['real'], $record, ['verification_version' => $verification->version($record),
            'action' => 'group', 'group_progress' => 'member']);
        $this->assertSame('member', $verification->evidencedProgress($record, $verification->state($record->id)));
        $this->actingAs($f['real'])->post(route('admin.submissions.approve', $record), ['verified_facebook_url' => $record->facebook_url])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status->value);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->sole()->score);
        $this->assertSame(87, Dealer::count());
    }

    #[DataProvider('invalidPlans')]
    public function test_invalid_preservation_plans_are_blocked_without_writes(string $kind): void
    {
        $f = $this->fixture();
        $admins = [$f['real']->id, $f['blockedAdmin']->id];
        if ($kind === 'missing-admins') {
            $admins = [];
        } elseif ($kind === 'non-admin') {
            $admins = [$f['sales']->id];
        } elseif ($kind === 'unknown-admin') {
            $admins = [PHP_INT_MAX];
        } elseif ($kind === 'inactive-only') {
            $admins = [$f['blockedAdmin']->id];
        } elseif ($kind === 'dealer-count') {
            Dealer::factory()->create();
        } elseif ($kind === 'kept-reviewer') {
            DB::table('users')->where('id', $f['manager']->id)->update(['reviewed_by' => $f['demo']->id]);
        } elseif ($kind === 'review-cycle') {
            DB::table('users')->where('id', $f['demo']->id)->update(['reviewed_by' => $f['demo']->id, 'reviewed_at' => now()]);
        } elseif ($kind === 'queue') {
            DB::table('failed_jobs')->insert(['uuid' => 'isolated-reset-test', 'connection' => 'database',
                'queue' => 'default', 'payload' => '{}', 'exception' => 'fixture', 'failed_at' => now()]);
        } else {
            config(['realistic_demo.include_in_metrics' => true]);
        }
        $before = app(DemoCleanup::class)->snapshots();
        $plan = app(ResetDemoData::class)->audit('toyota_testing', $admins);
        $this->assertFalse($plan['eligible']);
        $this->assertNotEmpty($plan['blockers']);
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertSame(1, Artisan::call('toyota:reset-demo-data', ['--database' => 'toyota_testing',
            '--keep-admin' => array_map('strval', $admins), '--backup' => true]));
        $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
    }

    public static function invalidPlans(): array
    {
        return array_map(fn ($name) => [$name], ['missing-admins', 'non-admin', 'unknown-admin', 'inactive-only',
            'dealer-count', 'kept-reviewer', 'review-cycle', 'queue', 'demo-flags']);
    }

    #[DataProvider('invalidExecutions')]
    public function test_bad_execution_arguments_backup_or_stale_plan_never_delete(string $kind): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $arguments = $this->arguments($plan, $backup);
        if ($kind === 'database') {
            $arguments['--database'] = 'wrong_database';
            $arguments['--confirm'] = 'reset-demo-data:wrong_database';
        } elseif ($kind === 'confirmation') {
            $arguments['--confirm'] = 'yes';
        } elseif ($kind === 'restore') {
            $arguments['--restore-verified'] = str_repeat('0', 64);
        } elseif ($kind === 'allowlist') {
            $arguments['--keep-admin'] = [(string) $f['real']->id];
        } elseif ($kind === 'stale-data') {
            DB::table('dealers')->where('id', $f['manager']->dealer_id)->update(['name' => 'Changed after plan']);
        } elseif ($kind === 'backup-tamper') {
            $payload = json_decode(Crypt::decryptString(file_get_contents($backup['receipt'])), true);
            file_put_contents($payload['path'], 'tampered');
        } elseif ($kind === 'receipt-purpose') {
            $payload = json_decode(Crypt::decryptString(file_get_contents($backup['receipt'])), true);
            $payload['purpose'] = 'ownership-cleanup';
            file_put_contents($backup['receipt'], Crypt::encryptString(json_encode($payload)));
        } elseif ($kind === 'production') {
            app()->detectEnvironment(fn () => 'production');
            $arguments['--allow-production'] = true; // Still must refuse without maintenance.
        } else {
            unset($arguments['--receipt']);
        }
        $before = app(DemoCleanup::class)->snapshots();
        $this->assertSame(1, Artisan::call('toyota:reset-demo-data', $arguments));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
    }

    public static function invalidExecutions(): array
    {
        return array_map(fn ($name) => [$name], ['database', 'confirmation', 'restore', 'allowlist', 'stale-data',
            'backup-tamper', 'receipt-purpose', 'production', 'missing-backup']);
    }

    public function test_mid_delete_failure_rolls_back_and_never_creates_retirement_receipt(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $before = app(DemoCleanup::class)->snapshots();
        $injected = false;
        DB::listen(function ($query) use (&$injected) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'delete from `awards`')) {
                $injected = true;
                throw new RuntimeException('Injected reset failure');
            }
        });
        $this->assertSame(1, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $this->assertTrue($injected);
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
    }

    public function test_reset_does_not_weaken_original_ownership_cleanup_and_blocks_demo_reinstallation(): void
    {
        $f = $this->fixture();
        $this->assertFalse(app(DemoCleanup::class)->audit()['eligible']);
        $before = app(DemoCleanup::class)->snapshots();
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', ['--execute' => true]));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        config(['realistic_demo.enabled' => true, 'realistic_demo.allow_production' => true]);
        $this->expectException(LogicException::class);
        app(ToyotaRealisticDemo::class)->assertEnabled();
    }

    public function test_corrupt_reset_receipt_cannot_fall_back_to_unverified_metrics(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        file_put_contents(app(ResetDemoRetirement::class)->path(), 'tampered');
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->count();
    }

    public function test_a_completed_reset_cannot_be_reused_to_delete_new_business_data(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $newSales = User::factory()->active()->create(['dealer_id' => $f['manager']->dealer_id]);
        CustomerSubmission::factory()->create(['sales_id' => $newSales->id, 'dealer_id' => $f['manager']->dealer_id]);
        $before = app(DemoCleanup::class)->snapshots();
        $next = app(ResetDemoData::class)->audit('toyota_testing', $plan['approved_admin_ids']);
        $this->assertFalse($next['eligible']);
        $this->assertStringContainsString('already exists', implode(' ', $next['blockers']));
        $this->assertSame(1, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
    }

    public function test_receipt_is_not_written_until_the_outer_application_transaction_commits(): void
    {
        $f = $this->fixture();
        $plan = $this->plan($f);
        $backup = $this->backup($plan);
        $before = app(DemoCleanup::class)->snapshots();
        DB::beginTransaction(); // An application transaction, beyond the testing fixture wrapper.
        try {
            app(ResetDemoData::class)->execute('toyota_testing', $plan['approved_admin_ids'], $plan['plan_hash'], $backup['receipt'], $backup['sha256']);
            $this->assertSame(0, CustomerSubmission::count());
            $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertFalse(app(ResetDemoRetirement::class)->isPresent());
    }

    public function test_even_reserved_demo_admin_email_is_retained_only_when_explicitly_approved(): void
    {
        $f = $this->fixture();
        $plan = app(ResetDemoData::class)->audit('toyota_testing', [$f['real']->id, $f['demo']->id]);
        $backup = $this->backup($plan);
        $kept = $f['demo']->refresh()->getRawOriginal();
        $this->assertSame(0, Artisan::call('toyota:reset-demo-data', $this->arguments($plan, $backup)));
        $this->assertSame($kept, $f['demo']->fresh()->getRawOriginal());
        $this->mock(ToyotaRealisticDemo::class)->shouldNotReceive('ownedSubmissionIds');
        $this->get(route('home'))->assertOk();
    }
}
