<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/** Uses a verified backup restored to a NEW schema; never RefreshDatabase/DatabaseMigrations. */
class DealerOnlyDatabaseIntegrityTest extends TestCase
{
    use \Tests\Concerns\RestoresVerifiedDealerDatabase;

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_10_16_000015_prepare_dealer_only_database.php');
    }

    private function rows(string $table, ?array $columns = null): array
    {
        return DB::table($table)->orderBy('id')->get($columns ?? ['*'])->map(fn ($row) => (array) $row)->all();
    }

    private function rejects(callable $operation, int $driverCode = 1644): void
    {
        DB::beginTransaction();
        try {
            $operation();
        } catch (QueryException $error) {
            $this->assertSame($driverCode, $error->errorInfo[1]);

            return;
        } finally {
            DB::rollBack();
        }
        $this->fail('The database accepted an invalid/historical mutation.');
    }

    public function test_migration_preserves_all_history_and_enforces_dealer_only_writes(): void
    {
        $submissions = $this->rows('customer_submissions');
        $submissionColumns = array_keys($submissions[0]);
        $reviews = $this->rows('moderation_reviews');
        $reviewColumns = array_keys($reviews[0]);
        $awards = $this->rows('awards');
        $awardColumns = array_keys($awards[0]);
        $winners = $this->rows('award_winners');
        $users = $this->rows('users');
        $userColumns = array_keys($users[0]);
        $dealers = $this->rows('dealers');
        $unknown = DB::table('customer_submissions')->whereNull('dealer_id')->orderBy('id')->pluck('id')->all();
        $migration = $this->migration();
        $migration->up();

        $this->assertSame($submissions, $this->rows('customer_submissions', $submissionColumns));
        $this->assertSame($reviews, $this->rows('moderation_reviews', $reviewColumns));
        $this->assertSame($awards, $this->rows('awards', $awardColumns));
        $this->assertSame($winners, $this->rows('award_winners'));
        $this->assertSame($dealers, $this->rows('dealers'));
        $archived = array_values(array_filter($users, fn ($row) => $row['role'] === 'sales'));
        $admins = array_values(array_filter($users, fn ($row) => $row['role'] === 'admin'));
        $this->assertSame($archived, $this->rows('legacy_sales_accounts'));
        $this->assertSame($admins, $this->rows('users', $userColumns));
        $this->assertSame($unknown, DB::table('customer_submissions')->whereNull('dealer_id')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('users')->where('role', 'dealer')->count(), 'No Sales may be converted or provisioned automatically.');
        foreach (['customer_submissions', 'moderation_reviews', 'award_winners'] as $table) {
            $this->assertSame(0, DB::table($table.' as child')->leftJoin('legacy_sales_accounts as legacy', 'legacy.id', '=', 'child.sales_id')
                ->whereNotNull('child.sales_id')->whereNull('legacy.id')->count());
        }
        $this->assertSame(['legacy_mixed'], DB::table('awards')->distinct()->pluck('competition_scope')->all());
        $account = ['name' => 'Dealer integrity fixture', 'email' => 'dealer-integrity@example.test', 'password' => 'test-only-hash', 'role' => 'dealer', 'status' => 'pending', 'dealer_id' => $dealers[0]['id']];
        $this->rejects(fn () => DB::table('users')->insert(array_replace($account, ['role' => 'sales'])), 1265);
        $this->rejects(fn () => DB::table('users')->insert(array_replace($account, ['dealer_id' => null])), 3819);
        $this->rejects(fn () => DB::table('users')->insert(array_replace($account, ['dealer_id' => 999999999])), 1452);

        DB::beginTransaction();
        try {
            // Account uniqueness covers ALL states, including pending/rejected/blocked.
            $creator = DB::table('users')->insertGetId($account);
            foreach (['pending', 'active', 'rejected', 'blocked'] as $status) {
                $this->rejects(fn () => DB::table('users')->insert(array_replace($account, ['email' => $status.'@example.test', 'status' => $status])), 1062);
            }
            $other = DB::table('users')->insertGetId(array_replace($account, ['email' => 'other-dealer@example.test', 'dealer_id' => $dealers[1]['id']]));
            $this->rejects(fn () => DB::table('users')->where('id', $other)->update(['dealer_id' => $dealers[0]['id']]));
            $this->rejects(fn () => DB::table('users')->where('id', $other)->update(['dealer_id' => $dealers[2]['id']]));
            $this->rejects(fn () => DB::table('users')->where('id', $other)->update(['role' => 'admin']));
            $accountReview = ['account_user_id' => $creator, 'reviewed_by' => $admins[0]['id'], 'from_status' => 'pending', 'to_status' => 'active', 'reviewed_at' => '2026-10-16 12:00:00'];
            DB::table('moderation_reviews')->insert($accountReview);
            $this->rejects(fn () => DB::table('moderation_reviews')->insert(array_replace($accountReview, ['account_user_id' => $admins[0]['id']])));
            $this->rejects(fn () => DB::table('moderation_reviews')->insert(array_replace($accountReview, ['reviewed_by' => $creator])));
            $this->rejects(fn () => DB::table('moderation_reviews')->insert(array_replace($accountReview, ['sales_id' => $archived[0]['id']])));
            $new = ['sales_id' => null, 'created_by_user_id' => $creator, 'dealer_id' => $dealers[0]['id'],
                'customer_name' => 'Integrity fixture', 'facebook_url' => 'https://facebook.com/dealer.integrity',
                'facebook_url_normalized' => 'https://www.facebook.com/dealer.integrity', 'submitted_at' => '2026-10-16 12:00:00', 'status' => 'pending'];
            $id = DB::table('customer_submissions')->insertGetId($new);
            $this->assertSame($dealers[0]['id'], DB::table('customer_submissions')->where('id', $id)->value('dealer_id'));
            $this->rejects(fn () => DB::table('customer_submissions')->insert(array_replace($new, ['dealer_id' => null])));
            $this->rejects(fn () => DB::table('customer_submissions')->insert(array_replace($new, ['dealer_id' => $dealers[1]['id']])));
            $this->rejects(fn () => DB::table('customer_submissions')->insert(array_replace($new, ['created_by_user_id' => $admins[0]['id']])));
            $this->rejects(fn () => DB::table('customer_submissions')->insert(array_replace($new, ['created_by_user_id' => null])));
            $this->rejects(fn () => DB::table('customer_submissions')->insert(array_replace($new, ['sales_id' => $archived[0]['id']])));
            foreach (['dealer_id' => $dealers[1]['id'], 'created_by_user_id' => $other, 'sales_id' => $archived[0]['id'], 'submitted_at' => '2026-10-17 00:00:00'] as $field => $value) {
                $this->rejects(fn () => DB::table('customer_submissions')->where('id', $id)->update([$field => $value]));
            }
        } finally {
            DB::rollBack();
        }

        // Approval and appended history still work for unresolved legacy attribution.
        $legacyPending = DB::table('customer_submissions as pending')->where('pending.status', 'pending')->whereNull('pending.dealer_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('customer_submissions as approved')->whereColumn('approved.approved_facebook_identity', 'pending.facebook_url_normalized'))->first();
        $this->assertNotNull($legacyPending);
        DB::beginTransaction();
        try {
            $admin = $admins[0]['id'];
            DB::table('customer_submissions')->where('id', $legacyPending->id)->update(['status' => 'approved', 'reviewed_by' => $admin, 'reviewed_at' => '2026-10-16 12:00:00']);
            $this->assertNull(DB::table('customer_submissions')->where('id', $legacyPending->id)->value('dealer_id'));
            DB::table('moderation_reviews')->insert(['customer_submission_id' => $legacyPending->id, 'reviewed_by' => $admin, 'from_status' => 'pending', 'to_status' => 'approved', 'reviewed_at' => '2026-10-16 12:00:00']);
            $duplicate = DB::table('customer_submissions as pending')
                ->join('customer_submissions as approved', 'approved.approved_facebook_identity', '=', 'pending.facebook_url_normalized')
                ->where('pending.status', 'pending')->select('pending.*')->first();
            $this->assertNotNull($duplicate);
            $this->rejects(fn () => DB::table('customer_submissions')->where('id', $duplicate->id)->update(['status' => 'approved', 'reviewed_by' => $admin, 'reviewed_at' => '2026-10-16 12:00:00']), 1062);
        } finally {
            DB::rollBack();
        }

        $this->rejects(fn () => DB::table('customer_submissions')->where('id', $unknown[0])->update(['dealer_id' => $dealers[0]['id']]));
        $this->rejects(fn () => DB::table('legacy_sales_accounts')->where('id', $archived[0]['id'])->update(['dealer_id' => $dealers[1]['id']]));
        $this->rejects(fn () => DB::table('legacy_sales_accounts')->where('id', $archived[0]['id'])->delete());
        $this->rejects(fn () => DB::table('legacy_sales_accounts')->insert(array_replace($archived[0], ['id' => 9999999, 'email' => 'forged@example.test'])));
        $this->rejects(fn () => DB::table('moderation_reviews')->where('id', $reviews[0]['id'])->update(['admin_note' => 'rewritten']));
        $this->rejects(fn () => DB::table('moderation_reviews')->where('id', $reviews[0]['id'])->delete());
        $this->rejects(fn () => DB::table('awards')->where('id', $awards[0]['id'])->update(['title' => 'rewritten']));
        $this->rejects(fn () => DB::table('awards')->where('id', $awards[0]['id'])->delete());
        $this->rejects(fn () => DB::table('award_winners')->where('id', $winners[0]['id'])->update(['score' => 999]));
        $this->rejects(fn () => DB::table('award_winners')->where('id', $winners[0]['id'])->delete());
        $this->rejects(fn () => DB::table('dealers')->where('id', $dealers[0]['id'])->delete(), 1451);
        DB::beginTransaction();
        try {
            $draft = DB::table('awards')->insertGetId(['title' => 'Dealer draft', 'period_type' => 'monthly', 'period_start' => '2099-01-01', 'period_end' => '2099-02-01']);
            $this->assertSame('dealer', DB::table('awards')->where('id', $draft)->value('competition_scope'));
            $winner = ['award_id' => $draft, 'winner_type' => 'dealer', 'dealer_id' => $dealers[0]['id'], 'rank' => 1, 'score' => 1,
                'winner_name_snapshot' => $dealers[0]['name'], 'dealer_name_snapshot' => $dealers[0]['name'], 'dealer_code_snapshot' => $dealers[0]['code']];
            DB::table('award_winners')->insert($winner);
            $this->rejects(fn () => DB::table('award_winners')->insert(array_replace($winner, ['winner_type' => 'sales', 'dealer_id' => null, 'sales_id' => $archived[0]['id'], 'sales_dealer_id_snapshot' => $dealers[0]['id'], 'rank' => 2])));
            DB::table('awards')->where('id', $draft)->update(['published_by' => $admins[0]['id'], 'published_at' => '2099-02-01 00:00:00']);
            $this->rejects(fn () => DB::table('award_winners')->insert(array_replace($winner, ['dealer_id' => $dealers[1]['id'], 'rank' => 2])));
        } finally {
            DB::rollBack();
        }
        try {
            $migration->down();
            $this->fail('A destructive rollback must be refused.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('Forward-only', $error->getMessage());
        }
        $this->assertSame($submissions, $this->rows('customer_submissions', $submissionColumns));
        $this->assertSame($reviews, $this->rows('moderation_reviews', $reviewColumns));
        $this->assertSame($awards, $this->rows('awards', $awardColumns));
        $this->assertSame($winners, $this->rows('award_winners'));
        $this->assertSame($archived, $this->rows('legacy_sales_accounts'));
    }

    public function test_preflight_refuses_legacy_tokens_without_mutating_schema_or_accounts(): void
    {
        $before = $this->rows('users');
        $legacy = DB::table('users')->where('role', 'sales')->first();
        DB::beginTransaction();
        try {
            DB::table('password_reset_tokens')->insert(['email' => $legacy->email, 'token' => 'unexpired-token', 'created_at' => now()]);
            try {
                $this->migration()->up();
                $this->fail('Preflight must stop before DDL.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Legacy login tokens', $error->getMessage());
            }
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('legacy_sales_accounts'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->rows('users'));
    }

    public function test_missing_or_stale_backup_stops_before_any_schema_or_data_change(): void
    {
        $before = $this->rows('users');
        $validPath = config('dealer_migration.backup_manifest');
        config(['dealer_migration.backup_manifest' => null]);
        try {
            $this->migration()->up();
            $this->fail('Missing backup must stop deployment.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('backup manifest is required', $error->getMessage());
        }
        config(['dealer_migration.backup_manifest' => $validPath]);
        DB::beginTransaction();
        try {
            DB::table('users')->where('id', $before[0]['id'])->update(['name' => 'Changed after backup']);
            try {
                $this->migration()->up();
                $this->fail('A stale backup must stop deployment.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('differs from the verified backup', $error->getMessage());
            }
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('legacy_sales_accounts'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->rows('users'));
    }
}
