<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class SalesPublicSlugMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_backfill_changes_no_existing_account_customer_or_evidence_fields(): void
    {
        TestDatabase::assertIsolated();
        $sales = User::factory()->active()->create();
        $other = User::factory()->active()->create(['name' => $sales->name]);
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'evidence_image_path' => 'preserved.jpg']);
        $migration = require database_path('migrations/2026_10_20_000021_add_public_slug_to_sales_accounts.php');
        $migration->down();
        $snapshots = [];
        foreach (['users', 'customer_submissions'] as $table) {
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $snapshots[$table] = ['columns' => $columns, 'rows' => DB::table($table)->select($columns)->orderBy('id')->get()->toJson()];
        }
        $migration->up();
        foreach ($snapshots as $table => $snapshot) {
            $this->assertSame($snapshot['rows'], DB::table($table)->select($snapshot['columns'])->orderBy('id')->get()->toJson());
        }
        $slugs = DB::table('users')->whereIn('id', [$sales->id, $other->id])->pluck('public_slug');
        $this->assertCount(2, $slugs->unique());
        foreach ($slugs as $slug) {
            $this->assertMatchesRegularExpression('/^s-[a-f0-9]{32}$/D', $slug);
        }
        $this->assertNull(DB::table('users')->where('id', $admin->id)->value('public_slug'));
    }
}
