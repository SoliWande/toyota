<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class SalesCustomerPillsTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginIsolatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->beginIsolatedTransaction();
    }

    public function test_pills_follow_evidenced_workflow_counts_and_scope(): void
    {
        $sales = User::factory()->active()->create();
        $admin = User::factory()->admin()->create();
        $service = app(SubmissionVerification::class);
        $new = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHours(5)]);
        $group = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $supplement = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $verification = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $approved = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $rejected = CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'admin_note' => 'PRIVATE-ADMIN', 'notes' => 'PRIVATE-NOTES']);
        $foreign = CustomerSubmission::factory()->create(['customer_name' => 'FOREIGN-NAME']);
        foreach ([[$group, 'requested'], [$verification, 'member']] as [$row, $progress]) {
            $service->adminUpdate($admin, $row, ['action' => 'group', 'group_progress' => $progress, 'verification_version' => $service->version($row)]);
        }
        $service->adminUpdate($admin, $supplement, ['action' => 'supplement', 'supplement_reason' => 'facebook_unavailable', 'verification_version' => $service->version($supplement)]);
        $expected = ['remind' => [$new->id, $group->id, $supplement->id], 'verification' => [$verification->id], 'verified' => [$approved->id], 'all' => [$new->id, $group->id, $supplement->id, $verification->id, $approved->id, $rejected->id]];
        foreach ($expected as $tab => $ids) {
            $response = $this->actingAs($sales)->get(route('sales.dashboard', ['tab' => $tab, 'sales_id' => $foreign->sales_id]))
                ->assertOk()->assertViewHas('tabCounts', ['remind' => 3, 'verification' => 1, 'verified' => 1, 'all' => 6])
                ->assertDontSee('FOREIGN-NAME')->assertDontSee('PRIVATE-ADMIN')->assertDontSee('PRIVATE-NOTES')
                ->assertDontSee('name="q"', false)->assertDontSee('name="status"', false)->assertDontSee('Lọc khách');
            $this->assertEqualsCanonicalizing($ids, $response->viewData('customers')->modelKeys());
        }
        // A changed profile invalidates old Group evidence; no inferred membership from status/time.
        $verification->update(['facebook_url' => 'https://www.facebook.com/changed.pill.profile']);
        $this->get(route('sales.dashboard', ['tab' => 'verification']))->assertOk()
            ->assertViewHas('tabCounts', ['remind' => 4, 'verification' => 0, 'verified' => 1, 'all' => 6]);
    }

    public function test_tab_pagination_preserves_selection_and_rejects_invalid_tabs(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->count(21)->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $first = $this->actingAs($sales)->get(route('sales.dashboard', ['tab' => 'verified']))->assertOk();
        $this->assertStringContainsString('tab=verified', $first->viewData('customers')->nextPageUrl());
        $this->get($first->viewData('customers')->nextPageUrl())->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 21 && $rows->count() === 1);
        $this->getJson(route('sales.dashboard', ['tab' => 'invented']))->assertUnprocessable();
    }
}
