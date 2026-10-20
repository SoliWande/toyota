<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerSlugBackfillTest extends TestCase
{
    use DatabaseMigrations;

    public function test_slug_backfill_preserves_all_existing_dealer_customer_and_account_fields(): void
    {
        TestDatabase::assertIsolated();
        // Exercise the slug migration itself; later migrations must remain installed.
        $migration = require database_path('migrations/2026_10_20_000019_add_stable_slug_to_dealers.php');
        $migration->down();
        $time = now()->subYear()->format('Y-m-d H:i:s');
        DB::table('dealers')->insert([
            ['name' => 'Toyota An Sương', 'code' => 'OLD-A', 'created_at' => $time, 'updated_at' => $time],
            ['name' => 'Toyota An Sương', 'code' => 'OLD-B', 'created_at' => $time, 'updated_at' => $time],
            ['name' => 'Admin', 'code' => 'OLD-C', 'created_at' => $time, 'updated_at' => $time],
        ]);
        $id = DB::table('dealers')->where('code', 'OLD-A')->value('id');
        $sales = User::factory()->active()->create(['dealer_id' => $id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $snapshots = [];
        foreach (['dealers', 'users', 'customer_submissions'] as $table) {
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $snapshots[$table] = ['columns' => $columns, 'rows' => DB::table($table)->select($columns)->orderBy('id')->get()->toJson()];
        }
        TestDatabase::assertIsolated();
        $migration->up();
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('dealers', 'slug'));
        foreach ($snapshots as $table => $snapshot) {
            $this->assertSame($snapshot['rows'], DB::table($table)->select($snapshot['columns'])->orderBy('id')->get()->toJson(), $table);
        }
        $slugs = DB::table('dealers')->orderBy('id')->pluck('slug')->all();
        $this->assertSame(['toyota-an-suong', 'toyota-an-suong-2', 'admin-2'], $slugs);
    }
}
