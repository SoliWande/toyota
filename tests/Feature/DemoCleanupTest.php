<?php

namespace Tests\Feature;

use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\DemoCleanup;
use App\Services\DemoCleanupBackup;
use App\Services\DemoDataGuard;
use App\Services\LeaderboardService;
use App\Services\SubmissionVerification;
use App\Services\ToyotaRealisticDemo;
use Database\Seeders\ToyotaDealerSeeder;
use Database\Seeders\ToyotaRealisticDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** The dump transport is synthetic in tests; cryptographic receipts and all DB guards are real. */
class IsolatedDemoBackup extends DemoCleanupBackup
{
    protected function dump(string $directory, string $target): void
    {
        file_put_contents($target, "-- synthetic isolated backup transport; not a restorable production dump\n");
    }
}

class DemoCleanupTest extends TestCase
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
        config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32)), 'cache.default' => 'array',
            'cache.stores.file.path' => base_path('.laravel-tools/demo-cleanup-tests/'.bin2hex(random_bytes(8))), 'realistic_demo.enabled' => false,
            'realistic_demo.retired' => false, 'realistic_demo.include_in_metrics' => false, 'realistic_demo.allow_production' => false]);
        $this->app->instance(DemoCleanupBackup::class, new IsolatedDemoBackup);
    }

    /** Protocol-faithful unsent test rows, never a seed against the business database. */
    private function install(): array
    {
        if (! User::where('role', 'admin')->where('status', 'active')->where('email', '!=', ToyotaRealisticDemo::EMAIL)->exists()) {
            User::factory()->admin()->create();
        }
        $admin = User::factory()->admin()->create(['email' => ToyotaRealisticDemo::EMAIL, 'remember_token' => null]);
        $sales = User::factory()->active()->create(['email' => 'toyota.realistic.v1.sales.000@example.com', 'remember_token' => null]);
        $base = CustomerSubmission::factory()->make(['sales_id' => $sales->id, 'dealer_id' => $sales->dealer_id,
            'notes' => ToyotaRealisticDemo::TAG, 'submitted_at' => '2026-10-10 12:00:00',
            'created_at' => '2026-10-10 12:00:00', 'updated_at' => '2026-10-10 12:00:00'])->getAttributes();
        for ($chunk = 0; $chunk < 6; $chunk++) {
            $rows = [];
            for ($i = $chunk * 250; $i < ($chunk + 1) * 250; $i++) {
                $facebook = 'https://www.facebook.com/toyota.demo.synthetic.v1.'.sprintf('%04d', $i);
                $rows[] = array_replace($base, ['facebook_url' => $facebook, 'facebook_url_normalized' => $facebook,
                    'license_plate' => sprintf('DEMO-V1-%04d', $i)]);
            }
            DB::table('customer_submissions')->insert($rows);
        }
        $ids = DB::table('customer_submissions')->where('notes', ToyotaRealisticDemo::TAG)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $users = [$admin->id, $sales->id];
        $fingerprint = (new ReflectionMethod(ToyotaRealisticDemo::class, 'fingerprint'))->invoke(app(ToyotaRealisticDemo::class), $users, $ids, []);
        $admin->forceFill(['admin_note' => json_encode(['tag' => ToyotaRealisticDemo::TAG, 'users' => $users,
            'submissions' => $ids, 'reviews' => [], 'fingerprint' => $fingerprint,
            'ownership' => Crypt::encryptString(json_encode(['dataset' => ToyotaRealisticDemo::TAG,
                'database' => DB::connection()->getDatabaseName(), 'admin' => $admin->id, 'submissions' => $ids], JSON_THROW_ON_ERROR)),
        ], JSON_THROW_ON_ERROR)])->save();

        return [$admin, $sales, $ids];
    }

    private function backup(): array
    {
        $plan = app(DemoCleanup::class)->audit();
        $this->assertTrue($plan['eligible'], json_encode($plan['blockers']));

        return [$plan, app(DemoCleanupBackup::class)->create($plan)];
    }

    private function execute(array $plan, array $backup): array
    {
        return app(DemoCleanup::class)->execute($plan['plan_hash'], $backup['receipt'], $backup['sha256']);
    }

    public function test_default_command_is_read_only_and_reports_verified_and_retained_counts(): void
    {
        $real = CustomerSubmission::factory()->approved()->create();
        $this->install();
        $before = app(DemoCleanup::class)->snapshots();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(0, Artisan::call('toyota:cleanup-demo'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(1500, $report['tables']['customer_submissions']['verified_demo']);
        $this->assertSame(1, $report['tables']['customer_submissions']['retained_unclassified']);
        $this->assertArrayNotHasKey('ids', $report);
        $this->assertStringNotContainsString($real->customer_name, Artisan::output());
        $this->assertCount(0, array_filter($queries, fn ($q) => preg_match('/^\s*(INSERT|UPDATE|DELETE|TRUNCATE|ALTER|DROP)/i', $q['query'])));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', ['--execute' => true]));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
    }

    public function test_execute_command_preserves_real_admin_customers_and_all_dealer_fields(): void
    {
        $real = CustomerSubmission::factory()->approved()->create();
        $realRaw = $real->refresh()->getRawOriginal();
        $this->install();
        $dealerSnapshot = DB::table('dealers')->orderBy('id')->get()->toJson();
        [$plan, $backup] = $this->backup();
        $this->assertSame(0, Artisan::call('toyota:cleanup-demo', ['--execute' => true,
            '--plan' => $plan['plan_hash'], '--receipt' => $backup['receipt'], '--restore-verified' => $backup['sha256'],
            '--confirm' => 'cleanup:'.DB::connection()->getDatabaseName()]));
        $this->assertTrue(json_decode(Artisan::output(), true)['retained_data_unchanged']);
        $this->assertSame($realRaw, $real->fresh()->getRawOriginal());
        $this->assertSame($dealerSnapshot, DB::table('dealers')->orderBy('id')->get()->toJson());
        $this->assertSame(1, CustomerSubmission::count());
        $this->assertNull(User::where('email', ToyotaRealisticDemo::EMAIL)->first());
        config(['realistic_demo.retired' => true]);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->first()->score);
        $this->get(route('home'))->assertOk();
    }

    #[DataProvider('invalidProofs')]
    public function test_invalid_ownership_or_fingerprint_never_deletes_or_counts_unverified_demo(string $kind): void
    {
        [$admin, $sales, $ids] = $this->install();
        $manifest = json_decode($admin->admin_note, true);
        if ($kind === 'crypto') {
            $manifest['ownership'] = 'unreadable-after-key-change';
        } elseif ($kind === 'unsigned') {
            unset($manifest['ownership']);
        } else {
            DB::table('customer_submissions')->where('id', $ids[0])->update(['customer_name' => 'Edited by operator']);
        }
        $admin->forceFill(['admin_note' => json_encode($manifest)])->save();
        $before = app(DemoCleanup::class)->snapshots();
        $report = app(DemoCleanup::class)->audit();
        $this->assertFalse($report['eligible']);
        $this->assertFalse($report['ownership_verified']);
        $this->assertSame(0, $report['tables']['customer_submissions']['verified_demo']);
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', ['--dry-run' => true]));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        config(['realistic_demo.retired' => true]);
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->count();
    }

    public static function invalidProofs(): array
    {
        return [['crypto'], ['unsigned'], ['fingerprint']];
    }

    #[DataProvider('dependencyTypes')]
    public function test_external_dependencies_and_uploads_block_cleanup_without_touching_history(string $kind): void
    {
        [$admin, $sales, $ids] = $this->install();
        $realAdmin = User::factory()->admin()->create();
        if ($kind === 'verification') {
            DB::table('submission_verification_states')->insert(['submission_id' => $ids[0], 'group_progress' => 'member', 'group_verified_by' => $realAdmin->id]);
        } elseif ($kind === 'receipt') {
            DB::table('dealer_registration_receipts')->insert(['draft_key' => str_repeat('a', 64), 'owner_key' => str_repeat('b', 64),
                'submission_id' => $ids[0], 'confirmation' => Crypt::encryptString('private'), 'expires_at' => now()->addDay()]);
        } elseif ($kind === 'edit') {
            DB::table('submission_edits')->insert(['customer_submission_id' => null, 'submission_id_snapshot' => $ids[0],
                'edited_by' => $realAdmin->id, 'changes' => '{}', 'edited_at' => now()]);
        } elseif ($kind === 'real_submission') {
            CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        } elseif ($kind === 'token') {
            DB::table('personal_access_tokens')->insert(['tokenable_type' => (new User)->getMorphClass(), 'tokenable_id' => $sales->id,
                'name' => 'new token', 'token' => hash('sha256', 'synthetic-test-token'), 'abilities' => '[]']);
        } elseif ($kind === 'award') {
            $winner = AwardWinner::factory()->create(['sales_id' => $sales->id]);
            $winner->award->forceFill(['published_by' => $realAdmin->id, 'published_at' => now()])->save();
        } elseif ($kind === 'remember_token' || $kind === 'account_note') {
            DB::table('users')->where('id', $sales->id)->update([$kind === 'remember_token' ? 'remember_token' : 'admin_note' => 'Operator activity after seed']);
        } else {
            DB::table('customer_submissions')->where('id', $ids[0])->update(['evidence_image_path' => 'do-not-delete.txt']);
        }
        $before = app(DemoCleanup::class)->snapshots();
        $report = app(DemoCleanup::class)->audit();
        $this->assertFalse($report['eligible']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', ['--backup' => true]));
    }

    public static function dependencyTypes(): array
    {
        return array_map(fn ($name) => [$name], ['verification', 'receipt', 'edit', 'real_submission', 'token', 'award', 'upload', 'remember_token', 'account_note']);
    }

    public function test_backup_tamper_restore_acknowledgement_and_stale_plan_are_rejected(): void
    {
        $this->install();
        [$plan, $backup] = $this->backup();
        $before = app(DemoCleanup::class)->snapshots();
        foreach (['wrong-restore', 'tampered-dump', 'stale'] as $case) {
            if ($case === 'tampered-dump') {
                $payload = json_decode(Crypt::decryptString(file_get_contents($backup['receipt'])), true);
                file_put_contents($payload['path'], 'tampered');
            } elseif ($case === 'stale') {
                User::factory()->admin()->create();
                $before = app(DemoCleanup::class)->snapshots();
            }
            try {
                app(DemoCleanup::class)->execute($plan['plan_hash'], $backup['receipt'], $case === 'wrong-restore' ? str_repeat('0', 64) : $backup['sha256']);
                $this->fail('Execution must be blocked.');
            } catch (LogicException) {
                $this->assertSame($before, app(DemoCleanup::class)->snapshots());
            }
        }
    }

    public function test_failure_after_deleting_submissions_rolls_back_every_table(): void
    {
        $this->install();
        [$plan, $backup] = $this->backup();
        $before = app(DemoCleanup::class)->snapshots();
        $injected = false;
        DB::listen(function ($query) use (&$injected) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'delete from `customer_submissions`')) {
                $injected = true;
                throw new RuntimeException('Injected cleanup failure');
            }
        });
        try {
            $this->execute($plan, $backup);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected cleanup failure', $exception->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
    }

    public function test_clean_retired_site_is_empty_then_accepts_real_registration_verification_and_approval(): void
    {
        $realAdmin = User::factory()->admin()->create();
        [$admin, $sales] = $this->install();
        $dealer = $sales->dealer;
        $directory = DB::table('dealers')->orderBy('id')->get()->toJson();
        [$plan, $backup] = $this->backup();
        $this->execute($plan, $backup);
        config(['realistic_demo.retired' => true]);
        $this->mock(ToyotaRealisticDemo::class)->shouldNotReceive('ownedSubmissionIds');
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('home'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['points'] === 0);
        $this->get(route('leaderboard'))->assertOk()->assertViewHas('dealers', fn ($rows) => $rows->total() === 0);
        $this->get(route('awards.index'))->assertOk()->assertSee('Chưa có kết quả vinh danh được công bố.');
        $this->get(route('dealers.index'))->assertOk();
        $this->get(route('dealers.profile', $dealer->slug))->assertOk();
        $this->post(route('dealers.registrations.step-one', $dealer->slug), ['customer_name' => 'REAL_FLOW_SYNTHETIC_TEST',
            'phone' => '0990000011', 'vehicle_description' => 'FJ'])->assertRedirect(route('dealers.registrations.step-two', $dealer->slug));
        $draft = $this->get(route('dealers.registrations.step-two', $dealer->slug))->assertOk()->viewData('draftId');
        $divisions = app(AdministrativeDivisions::class);
        $province = $divisions->provinces()[0]['code'];
        $ward = $divisions->wards($province)[0]['code'];
        $this->post(route('dealers.registrations.store', $dealer->slug), ['_flow' => 'two-step', '_draft' => $draft,
            'province_code' => $province, 'ward_code' => $ward, 'address_line' => 'Synthetic testing only',
            'license_plate' => '51K-900.11', 'first_registration_year' => 2024,
            'facebook_url' => 'https://facebook.com/realflow.synthetic.customer', 'consent' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dealers.registrations.success', $dealer->slug));
        $record = CustomerSubmission::sole();
        $verification = app(SubmissionVerification::class);
        $verification->adminUpdate($realAdmin, $record, ['verification_version' => $verification->version($record),
            'action' => 'group', 'group_progress' => 'member']);
        $this->assertSame('member', $verification->evidencedProgress($record, $verification->state($record->id)));
        $this->actingAs($realAdmin)->post(route('admin.submissions.approve', $record), ['verified_facebook_url' => $record->facebook_url])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status->value);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->first()->score);
        $this->assertSame($directory, DB::table('dealers')->orderBy('id')->get()->toJson());
        $this->assertNotNull($realAdmin->fresh());
    }

    public function test_a_label_without_authenticated_manifest_is_never_deletion_permission(): void
    {
        $record = CustomerSubmission::factory()->create(['notes' => ToyotaRealisticDemo::TAG]);
        $before = app(DemoCleanup::class)->snapshots();
        $report = app(DemoCleanup::class)->audit();
        $this->assertFalse($report['eligible']);
        $this->assertSame(0, $report['tables']['customer_submissions']['verified_demo']);
        $this->assertSame(1, $report['tables']['customer_submissions']['retained_unclassified']);
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
        $this->assertNotNull($record->fresh());
    }

    public function test_production_execution_requires_separate_approval_and_maintenance(): void
    {
        $this->install();
        [$plan, $backup] = $this->backup();
        $before = app(DemoCleanup::class)->snapshots();
        app()->detectEnvironment(fn () => 'production');
        $arguments = ['--execute' => true, '--plan' => $plan['plan_hash'], '--receipt' => $backup['receipt'],
            '--restore-verified' => $backup['sha256'], '--confirm' => 'cleanup:'.DB::connection()->getDatabaseName()];
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', $arguments));
        $this->assertSame(1, Artisan::call('toyota:cleanup-demo', $arguments + ['--allow-production' => true]));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
    }

    public function test_retired_mode_cannot_opt_back_into_demo_metrics_or_seeding(): void
    {
        config(['realistic_demo.retired' => true, 'realistic_demo.enabled' => true]);
        try {
            app(ToyotaRealisticDemo::class)->assertEnabled();
            $this->fail('Retirement must disable legacy demo commands.');
        } catch (LogicException) {
            $this->assertSame(0, CustomerSubmission::count());
        }
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->count();
    }

    public function test_actual_realistic_seeder_is_cleaned_with_its_reviews_and_dealers_unchanged(): void
    {
        $realAdmin = User::factory()->admin()->create();
        $this->seed(ToyotaDealerSeeder::class); // Guarded isolated fixture directory only.
        config(['realistic_demo.enabled' => true, 'realistic_demo.include_in_metrics' => true,
            'realistic_demo.password' => 'isolated-test-secret-12345']);
        $demo = app(ToyotaRealisticDemo::class);
        $demo->confirmed = true;
        try {
            $this->seed(ToyotaRealisticDemoSeeder::class);
        } finally {
            $demo->confirmed = false;
        }
        config(['realistic_demo.enabled' => false, 'realistic_demo.include_in_metrics' => false]);
        $directory = DB::table('dealers')->orderBy('id')->get()->toJson();
        [$plan, $backup] = $this->backup();
        $this->assertSame(1500, $plan['tables']['customer_submissions']['verified_demo']);
        $this->assertSame(1230, $plan['tables']['moderation_reviews']['verified_demo']);
        $result = $this->execute($plan, $backup);
        $this->assertSame(1230, $result['deleted']['moderation_reviews']);
        $this->assertSame(0, CustomerSubmission::count());
        $this->assertSame(1, User::count());
        $this->assertNotNull($realAdmin->fresh());
        $this->assertSame($directory, DB::table('dealers')->orderBy('id')->get()->toJson());
        $again = app(DemoCleanup::class)->audit();
        $this->assertTrue($again['eligible']);
        $this->assertSame([], $again['ids']['users']);
    }

    public function test_cleanup_cannot_remove_the_last_active_admin(): void
    {
        $this->install();
        DB::table('users')->where('role', 'admin')->where('email', '!=', ToyotaRealisticDemo::EMAIL)->delete();
        $before = app(DemoCleanup::class)->snapshots();
        $plan = app(DemoCleanup::class)->audit();
        $this->assertTrue($plan['ownership_verified']);
        $this->assertFalse($plan['eligible']);
        $this->assertSame(0, $plan['protected_active_admins']);
        $this->assertStringContainsString('administrative lockout', implode(' ', $plan['blockers']));
        $this->assertSame($before, app(DemoCleanup::class)->snapshots());
    }

    public function test_new_public_input_cannot_disable_the_retired_site_using_demo_markers(): void
    {
        $this->install();
        [$plan, $backup] = $this->backup();
        $this->execute($plan, $backup);
        config(['realistic_demo.retired' => true]);
        // A newly registered Sales cannot claim the removed privileged Admin's
        // identity merely by choosing the same public email string.
        $sales = User::factory()->active()->create(['email' => ToyotaRealisticDemo::EMAIL]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id,
            'dealer_id' => $sales->dealer_id, 'license_plate' => 'DEMO-V1-NEW',
            'facebook_url_normalized' => 'https://www.facebook.com/toyota.demo.synthetic.v1.new']);
        $this->mock(ToyotaRealisticDemo::class)->shouldNotReceive('ownedSubmissionIds');
        $this->get(route('home'))->assertOk();
        $this->get(route('leaderboard'))->assertOk();
        $this->get(route('awards.index'))->assertOk();
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->first()->score);
    }

    public function test_retirement_requires_authenticated_database_bound_cleanup_receipt(): void
    {
        $this->install();
        [$plan, $backup] = $this->backup();
        $this->execute($plan, $backup);
        config(['realistic_demo.retired' => true]);
        $guard = app(DemoDataGuard::class);
        $path = $guard->receiptPath();
        $original = file_get_contents($path);
        $payload = json_decode(Crypt::decryptString($original), true);
        $payload['binding'] = str_repeat('0', 64);
        try {
            foreach (['', 'tampered', Crypt::encryptString(json_encode($payload))] as $bad) {
                file_put_contents($path, $bad);
                try {
                    $guard->assertRetired();
                    $this->fail('Invalid retirement proof must not enable real-only metrics.');
                } catch (LogicException) {
                    $this->assertSame(0, CustomerSubmission::count());
                }
            }
        } finally {
            file_put_contents($path, $original);
        }
        $guard->assertRetired();
        $this->get(route('home'))->assertOk();
    }
}
