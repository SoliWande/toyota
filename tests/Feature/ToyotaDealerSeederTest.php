<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\ToyotaDealerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ToyotaDealerSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    private function snapshot(): array
    {
        return json_decode(file_get_contents(database_path('data/toyota_dealers.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_imports_all_official_locations_and_published_contact_fields(): void
    {
        $snapshot = $this->snapshot();
        $this->assertSame(87, $snapshot['count']);
        $this->seed(ToyotaDealerSeeder::class);
        $this->assertDatabaseCount('dealers', 87);
        foreach ($snapshot['dealers'] as $row) {
            $this->assertDatabaseHas('dealers', $row + ['is_active' => true]);
        }
        $this->assertSame(2, Dealer::whereNull('website')->count());
        $this->assertSame(2, Dealer::whereNull('facebook_url')->count());
        $this->assertSame(5, Dealer::whereNull('zalo_url')->count());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_import_preserves_ids_and_unchanged_timestamps(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        $before = Dealer::orderBy('id')->get()->map->getRawOriginal()->all();
        $this->travel(1)->days();
        $this->seed(ToyotaDealerSeeder::class);
        $this->assertSame($before, Dealer::orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_matches_existing_name_preserving_status_code_and_sales_relations(): void
    {
        $row = $this->snapshot()['dealers'][0];
        $dealer = Dealer::factory()->inactive()->create(['name' => $row['name'], 'code' => 'LEGACY-CODE']);
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        $submission = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $manual = Dealer::factory()->create(['name' => 'Unrelated existing dealer']);
        $this->seed(ToyotaDealerSeeder::class);
        $this->assertSame($row['toyota_source_id'], $dealer->fresh()->toyota_source_id);
        $this->assertSame('LEGACY-CODE', $dealer->fresh()->code);
        $this->assertFalse($dealer->fresh()->is_active);
        $this->assertSame($dealer->id, $sales->fresh()->dealer_id);
        $this->assertSame($sales->id, $submission->fresh()->sales_id);
        $this->assertNotNull($manual->fresh());
        $this->assertDatabaseCount('dealers', 88);
        $this->assertSame(87, Dealer::whereNotNull('toyota_source_id')->count());
    }

    public function test_stable_source_identity_refreshes_existing_record_without_duplication(): void
    {
        $this->seed(ToyotaDealerSeeder::class);
        $row = $this->snapshot()['dealers'][0];
        $dealer = Dealer::where('toyota_source_id', $row['toyota_source_id'])->sole();
        $dealer->update(['name' => 'Previous dealer name', 'phone' => '000', 'is_active' => false]);
        $this->seed(ToyotaDealerSeeder::class);
        $this->assertSame($row['name'], $dealer->fresh()->name);
        $this->assertSame($row['phone'], $dealer->fresh()->phone);
        $this->assertFalse($dealer->fresh()->is_active);
        $this->assertDatabaseCount('dealers', 87);
    }

    public function test_ambiguous_existing_names_roll_back_the_entire_import(): void
    {
        $rows = $this->snapshot()['dealers'];
        // A conflict on the second source row also rolls back the first imported row.
        Dealer::factory()->count(2)->create(['name' => $rows[1]['name']]);
        try {
            $this->seed(ToyotaDealerSeeder::class);
            $this->fail('Ambiguous dealer names must abort the import.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Ambiguous existing dealers', $error->getMessage());
        }
        $this->assertDatabaseCount('dealers', 2);
        $this->assertSame(0, Dealer::whereNotNull('toyota_source_id')->count());
    }

    public function test_conflicting_source_identity_is_not_overwritten(): void
    {
        $row = $this->snapshot()['dealers'][0];
        $dealer = Dealer::factory()->create(['code' => $row['code']]);
        $dealer->forceFill(['toyota_source_id' => 999999])->save();
        try {
            $this->seed(ToyotaDealerSeeder::class);
            $this->fail('Conflicting source identity must abort the import.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Conflicting Toyota source identity', $error->getMessage());
        }
        $this->assertSame(999999, $dealer->fresh()->toyota_source_id);
        $this->assertDatabaseCount('dealers', 1);
    }
}
