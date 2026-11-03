<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationReceiptCleanupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cleanup_dry_run_batches_and_expiration_preserve_submission(): void
    {
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $record = CustomerSubmission::factory()->create(['customer_name' => 'SYNTHETIC_RECEIPT']);
        foreach ([-2, -1, 1] as $i) {
            DB::table('dealer_registration_receipts')->insert(['draft_key' => hash('sha256', 'draft'.$i),
                'owner_key' => hash('sha256', 'owner'), 'submission_id' => $record->id,
                'confirmation' => Crypt::encryptString('{}'), 'expires_at' => now()->addHours($i), 'created_at' => now()]);
        }
        $this->artisan('dealer-registration:prune-receipts', ['--dry-run' => true])->expectsOutput('Eligible expired receipts: 2')->assertSuccessful();
        $this->assertSame(3, DB::table('dealer_registration_receipts')->count());
        $this->artisan('dealer-registration:prune-receipts', ['--batch' => 1, '--max-batches' => 1])->expectsOutput('Deleted expired receipts: 1')->assertSuccessful();
        $this->artisan('dealer-registration:prune-receipts')->expectsOutput('Deleted expired receipts: 1')->assertSuccessful();
        $this->assertSame(1, DB::table('dealer_registration_receipts')->count());
        $this->assertTrue(DB::table('dealer_registration_receipts')->value('expires_at') > now());
        $this->assertTrue($record->fresh() !== null);
        $this->assertSame(0, $record->moderationReviews()->count());
        $this->artisan('dealer-registration:prune-receipts', ['--batch' => 1001])->assertExitCode(2);
        $this->assertFalse((bool) config('dealer-registration.receipt_cleanup_enabled'));
    }
}
