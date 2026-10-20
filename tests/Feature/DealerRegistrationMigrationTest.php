<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerRegistrationMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_new_migration_roundtrip_preserves_existing_rows_and_unique_identity(): void
    {
        TestDatabase::assertIsolated();
        $old = CustomerSubmission::factory()->approved()->create(['evidence_image_path' => 'must-not-touch.jpg']);
        DB::table('customer_submissions')->where('id', $old->id)->update(['dealer_id' => null]);
        $before = (array) DB::table('customer_submissions')->where('id', $old->id)->first();
        foreach (['source', 'phone_normalized', 'vehicle_description', 'consented_at', 'consent_version'] as $field) {
            unset($before[$field]);
        }
        $migration = require database_path('migrations/2026_10_20_000020_support_dealer_qr_registrations.php');
        $migration->down();
        $this->assertEquals($before, (array) DB::table('customer_submissions')->where('id', $old->id)->first());
        $migration->up();
        $after = (array) DB::table('customer_submissions')->where('id', $old->id)->first();
        $this->assertSame('sales', $after['source']);
        $this->assertEquals($before, array_intersect_key($after, $before));
        $this->assertSame(1, DB::table('customer_submissions')->where('approved_facebook_identity', $old->facebook_url_normalized)->count());
    }

    public function test_rollback_refuses_to_discard_existing_qr_registrations(): void
    {
        TestDatabase::assertIsolated();
        $dealer = Dealer::factory()->create();
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];
        $this->postJson(route('dealers.registrations.store', ['slug' => $dealer->slug]), [
            'customer_name' => 'Test QR', 'phone' => '0901234567', 'address_line' => 'Test address',
            'province_code' => $province['code'], 'ward_code' => $directory->wards($province['code'])[0]['code'],
            'vehicle_description' => 'Toyota Veloz', 'consent' => '1',
        ])->assertCreated();
        $migration = require database_path('migrations/2026_10_20_000020_support_dealer_qr_registrations.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse while QR registrations exist.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('QR registrations exist', $error->getMessage());
            $this->assertSame(1, CustomerSubmission::where('source', 'dealer_qr')->count());
        } finally {
            // Only this isolated test fixture is removed to permit DatabaseMigrations teardown.
            CustomerSubmission::where('source', 'dealer_qr')->delete();
        }
    }
}
