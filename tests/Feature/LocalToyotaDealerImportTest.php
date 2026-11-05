<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use App\Services\LocalToyotaDealerImport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class LocalToyotaDealerImportTest extends TestCase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFixtureTransaction; }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->beginFixtureTransaction();
    }

    public function test_command_refuses_testing_environment_even_with_execute_confirmation(): void
    {
        $count = Dealer::count();
        $this->artisan('toyota:import-local-dealers', ['--execute' => true, '--confirm' => 'toyota:87:'.LocalToyotaDealerImport::SOURCE_HASH])
            ->expectsOutputToContain('Requires single local loopback MySQL toyota.')->assertExitCode(1);
        $this->assertSame($count, Dealer::count());
    }

    public function test_approved_snapshot_plans_all_87_including_legacy_provinces_without_writes(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $source = $service->source();
        $count = Dealer::count();
        $plan = $service->plan($source);
        $this->assertSame(['INSERT' => 87, 'UPDATE' => 0, 'SKIP' => 0, 'CONFLICT' => 0], $plan['counts']);
        $this->assertSame($source, array_column($plan['items'], 'source'));
        $this->assertSame(87, count(array_unique(array_column($plan['items'], 'slug'))));
        $this->assertSame('Bình Dương', $plan['items'][10]['source']['province']);
        $this->assertSame('Thừa Thiên - Huế', $plan['items'][39]['source']['province']);
        $this->assertSame($count, Dealer::count());
    }

    public function test_batch_preserves_originals_business_tables_and_phone_approval_and_rerun_only_skips(): void
    {
        $original = Dealer::factory()->inactive()->create(['code' => 'UNCHANGED-LOCAL', 'province' => null]);
        User::factory()->create(['dealer_id' => $original->id]);
        $raw = $original->refresh()->getRawOriginal();
        $users = DB::table('users')->get()->all();
        $approval = config('dealer-registration.approved_call_phones');
        $service = app(LocalToyotaDealerImport::class);
        $source = $service->source();
        $created = $service->insertBatch($service->plan($source));
        $this->assertCount(87, $created);
        $this->assertSame($raw, $original->fresh()->getRawOriginal());
        $this->assertEquals($users, DB::table('users')->get()->all());
        $this->assertSame($approval, config('dealer-registration.approved_call_phones'));
        foreach ($source as $row) {
            $dealer = Dealer::where('toyota_source_id', $row['toyota_source_id'])->sole();
            foreach ($row as $field => $value) {
                $this->assertSame($value, $dealer->getRawOriginal($field));
            }
            $this->assertTrue($dealer->is_active);
            $this->assertNull($dealer->photo_path);
            $this->assertSame(0, $dealer->submissions()->count());
        }
        $this->assertSame(['INSERT' => 0, 'UPDATE' => 0, 'SKIP' => 87, 'CONFLICT' => 0], $service->plan($source)['counts']);
    }

    public function test_matching_code_without_trusted_source_is_conflict_never_merge(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $source = $service->source();
        $dealer = Dealer::factory()->create(['code' => $source[0]['code'], 'name' => 'Locally edited']);
        $before = $dealer->refresh()->getRawOriginal();
        $plan = $service->plan($source);
        $this->assertSame(1, $plan['counts']['CONFLICT']);
        $this->assertSame($before, $dealer->fresh()->getRawOriginal());
    }

    public function test_matching_name_is_review_only_and_does_not_assign_source_identity(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $source = $service->source();
        $dealer = Dealer::factory()->create(['name' => $source[0]['name']]);
        $this->assertSame('CONFLICT', $service->plan($source)['items'][0]['action']);
        $this->assertNull($dealer->fresh()->toyota_source_id);
    }

    public function test_manual_edits_after_import_are_conflicts_not_updates(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $source = $service->source();
        $service->insertBatch($service->plan($source));
        $dealer = Dealer::where('toyota_source_id', $source[0]['toyota_source_id'])->sole();
        $dealer->update(['address' => 'Operator edited address']);
        $plan = $service->plan($source);
        $this->assertSame(['INSERT' => 0, 'UPDATE' => 0, 'SKIP' => 86, 'CONFLICT' => 1], $plan['counts']);
        $this->assertSame('Operator edited address', $dealer->fresh()->address);
    }

    public function test_mid_batch_collision_rolls_back_every_insert(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $plan = $service->plan($service->source());
        $plan['items'][86]['source']['code'] = $plan['items'][0]['source']['code'];
        $before = Dealer::count();
        try {
            DB::transaction(fn () => $service->insertBatch($plan));
            $this->fail('Collision must fail the batch.');
        } catch (RuntimeException $error) {
            $this->assertSame('Collision immediately before insert.', $error->getMessage());
        }
        $this->assertSame($before, Dealer::count());
    }

    public function test_restore_schema_hash_only_normalizes_redundant_charset_spelling(): void
    {
        $service = app(LocalToyotaDealerImport::class);
        $original = 'CREATE TABLE `d` (`name` varchar(50) COLLATE utf8mb4_unicode_ci) ENGINE=InnoDB';
        $restored = str_replace(' COLLATE', ' CHARACTER SET utf8mb4 COLLATE', $original);
        $this->assertSame($service->schemaHash($original, 'toyota'), $service->schemaHash($restored, 'toyota'));
        foreach ([str_replace('varchar(50)', 'varchar(51)', $original), str_replace('utf8mb4_unicode_ci', 'utf8mb4_bin', $original), str_replace('ENGINE=InnoDB', 'ENGINE=MyISAM', $original)] as $changed) {
            $this->assertNotSame($service->schemaHash($original,'toyota'),$service->schemaHash($changed,'toyota'));
        }
    }
}
