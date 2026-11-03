<?php

namespace Tests\Feature;

use App\Actions\UpdateCustomerSubmission;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubmissionIntegrityUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_model_save_blocks_changed_identity_but_preserves_unrelated_historical_edits(): void
    {
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $first = CustomerSubmission::factory()->create(['customer_name' => 'SYNTHETIC_FIRST', 'phone' => '0990000071', 'license_plate' => '51K90071']);
        $second = CustomerSubmission::factory()->create(['customer_name' => 'SYNTHETIC_SECOND', 'phone' => '0990000072', 'license_plate' => '51K90072']);
        try {
            $second->update(['phone' => '+84 990 000 071', 'license_plate' => '51k-900.71']);
            $this->fail('Conflicting identity was accepted.');
        } catch (ValidationException $error) {
            $this->assertSame(['phone', 'license_plate'], array_keys($error->errors()));
        }
        $this->assertTrue($second->fresh()->phone !== $first->phone);
        // Synthetic historical conflict, created only within this rollback fixture.
        DB::table('customer_submissions')->where('id', $second->id)->update(['phone' => $first->phone]);
        $second->refresh()->update(['notes' => 'SYNTHETIC_UNRELATED_EDIT']);
        $this->assertTrue($second->fresh()->phone === $first->phone);
        $this->assertSame('SYNTHETIC_UNRELATED_EDIT', $second->fresh()->notes);
        $second->update(['phone' => '+84 990 000 071']);
        $this->assertTrue($second->fresh()->phone === '+84 990 000 071');
    }

    public function test_rejected_does_not_reserve_changed_identity(): void
    {
        $first = CustomerSubmission::factory()->create(['phone' => '0990000073', 'license_plate' => '51K90073']);
        $rejected = CustomerSubmission::factory()->rejected()->create();
        $rejected->update(['phone' => $first->phone, 'license_plate' => $first->license_plate]);
        $this->assertSame('rejected', $rejected->fresh()->status->value);
    }

    public function test_admin_unrelated_edit_preserves_historical_phone_bytes(): void
    {
        $record = CustomerSubmission::factory()->create(['customer_name' => 'SYNTHETIC_HISTORY', 'phone' => '+84 990 000 074']);
        $before = $record->refresh()->only(['phone', 'phone_normalized', 'license_plate', 'status']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.submissions.update', $record), [
            'customer_name' => 'SYNTHETIC_EDITED', 'phone' => '0990000074', 'vehicle_model' => $record->vehicle_model,
            'edit_version' => UpdateCustomerSubmission::version($record), 'edit_submission_id' => (string) $record->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($before === $record->fresh()->only(['phone', 'phone_normalized', 'license_plate', 'status']));
    }
}
