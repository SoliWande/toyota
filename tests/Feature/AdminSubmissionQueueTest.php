<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\SubmissionVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminSubmissionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function verify(User $admin, CustomerSubmission $row, array $data): void
    {
        $service = app(SubmissionVerification::class);
        $service->adminUpdate($admin, $row, ['verification_version' => $service->version($row, $service->state($row->id)), ...$data]);
    }

    public function test_five_workflow_counts_reuse_profile_bound_evidence_and_never_change_moderation(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = CustomerSubmission::factory()->count(6)->create();
        [$unknown, $requested, $member, $supplement, $submitted, $stale] = $rows->all();
        foreach ([$requested, $stale] as $row) {
            $this->verify($admin, $row, ['action' => 'group', 'group_progress' => 'requested']);
        }
        $this->verify($admin, $member, ['action' => 'group', 'group_progress' => 'member']);
        foreach ([$supplement, $submitted] as $row) {
            $this->verify($admin, $row, ['action' => 'supplement', 'supplement_reason' => 'missing_information']);
        }
        // An isolated fixture for a customer response; submitted is ready for review, not an open request.
        DB::table('submission_verification_states')->where('submission_id', $submitted->id)->update(['supplement_state' => 'submitted']);
        $stale->facebook_url = 'https://facebook.com/changed.profile';
        $stale->save();
        $approved = CustomerSubmission::factory()->approved()->create();
        $rejected = CustomerSubmission::factory()->rejected()->create();
        $before = DB::table('submission_verification_audits')->count();
        $this->actingAs($admin)->get(route('admin.submissions.index'))->assertOk()
            ->assertViewHas('tabCounts', ['pending' => 6, 'supplement' => 1, 'group' => 1, 'approved' => 1, 'rejected' => 1]);
        foreach (['pending' => $rows->modelKeys(), 'supplement' => [$supplement->id], 'group' => [$requested->id], 'approved' => [$approved->id], 'rejected' => [$rejected->id]] as $workflow => $ids) {
            $response = $this->get(route('admin.submissions.index', ['workflow' => $workflow]))->assertOk();
            $this->assertSame($ids, $response->viewData('submissions')->modelKeys());
        }
        $this->get(route('admin.submissions.index', ['selected' => $stale->id]))->assertOk()->assertViewHas('groupProgress', null);
        $this->get(route('admin.submissions.index', ['selected' => $requested->id]))->assertOk()->assertViewHas('groupProgress', 'requested');
        $this->assertSame($before, DB::table('submission_verification_audits')->count());
        $this->assertDatabaseCount('moderation_reviews', 0);
        $this->assertSame(6, CustomerSubmission::where('status', 'pending')->count());
    }

    public function test_selection_masks_other_rows_and_preserves_filters_across_page_edges_and_stale_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $rows = CustomerSubmission::factory()->count(21)->sequence(fn ($sequence) => ['phone' => sprintf('090700%04d', $sequence->index)])
            ->create(['sales_id' => $sales->id, 'customer_name' => 'Queue selected', 'submitted_at' => now()->startOfDay()]);
        $filter = ['q' => 'Queue selected', 'dealer_id' => $sales->dealer_id, 'sales_id' => $sales->id, 'workflow' => 'pending', 'status' => 'pending', 'submitted_from' => now()->toDateString()];
        $response = $this->actingAs($admin)->get(route('admin.submissions.index', [...$filter, 'selected' => $rows[19]->id]))->assertOk()
            ->assertViewHas('selected', fn ($row) => $row->is($rows[19]))->assertViewHas('selectionPosition', 20);
        foreach ($rows as $index => $row) {
            if ($index !== 19) {
                $response->assertDontSee($row->phone);
            }
        }
        $response->assertSee($rows[19]->phone)->assertSee('name="edit_version"', false);
        $next = $response->viewData('selectionLinks')['next'];
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame('2', $query['page']);
        $this->assertSame((string) $rows[20]->id, $query['selected']);
        $this->assertSame($filter['submitted_from'], $query['submitted_from']);
        $page = $this->get($next)->assertOk()->assertViewHas('selected', fn ($row) => $row->is($rows[20]));
        $this->get($page->viewData('selectionLinks')['previous'])->assertOk()->assertViewHas('selected', fn ($row) => $row->is($rows[19]));
        $foreign = CustomerSubmission::factory()->create();
        $this->get(route('admin.submissions.index', [...$filter, 'selected' => $foreign->id]))->assertOk()
            ->assertViewHas('selected', fn ($row) => $row->is($rows[0]))->assertDontSee($foreign->customer_name);
        $rows[0]->forceFill(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->get(route('admin.submissions.index', [...$filter, 'selected' => $rows[0]->id]))->assertOk()
            ->assertViewHas('selected', fn ($row) => $row->is($rows[1]));
    }

    public function test_search_by_plate_qr_filter_tab_counts_and_empty_selection_are_truthful(): void
    {
        $admin = User::factory()->admin()->create();
        $normal = CustomerSubmission::factory()->create(['license_plate' => '68A-123.45', 'phone' => '0901111222']);
        $qr = CustomerSubmission::factory()->create(['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $normal->dealer_id,
            'phone' => '0903333444', 'phone_normalized' => '0903333444', 'vehicle_description' => 'Hilux', 'address_line' => 'Fixture address',
            'province_code' => '01', 'ward_code' => '00001', 'consented_at' => now(), 'consent_version' => 'v1']);
        $this->actingAs($admin)->get(route('admin.submissions.index', ['q' => '68A-123']))->assertOk()
            ->assertViewHas('submissions', fn ($page) => $page->modelKeys() === [$normal->id])
            ->assertViewHas('tabCounts', fn ($counts) => $counts['pending'] === 1)->assertViewHas('noDuplicates', true);
        $this->get(route('admin.submissions.index', ['source' => 'dealer_qr']))->assertOk()
            ->assertViewHas('submissions', fn ($page) => $page->modelKeys() === [$qr->id]);
        $this->get(route('admin.submissions.index', ['q' => 'NO_MATCH']))->assertOk()->assertViewHas('selected', null)
            ->assertDontSee($normal->phone)->assertDontSee($qr->phone);
        foreach (['flagged' => 1, 'source' => 'sales', 'workflow' => 'invented', 'selected' => -1] as $key => $value) {
            $this->getJson(route('admin.submissions.index', [$key => $value]))->assertUnprocessable();
        }
        $this->get(route('admin.submissions.show', $normal))->assertOk()->assertSee('data-inline-confirmation', false);
        $this->actingAs(User::factory()->dealer()->create())->getJson(route('admin.submissions.index'))->assertForbidden();
        $this->assertDatabaseCount('moderation_reviews', 0);
    }
}
