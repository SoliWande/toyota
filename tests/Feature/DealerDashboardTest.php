<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_use_approved_only_and_match_week_month_all_time_rankings(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10)->startOfWeek()->addDays(2));
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHour()]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subYear()]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        $response = $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()
            ->assertSee($manager->dealer->name)->assertViewHas('salesCount', 1)->assertViewHas('customerCount', 4);
        $standings = $response->viewData('standings');
        $this->assertSame(2, (int) $standings['all']->score);
        $this->assertSame(1, (int) $standings['week']->score);
        $this->assertSame(1, (int) $standings['month']->score);
        $this->assertSame(1, (int) $standings['all']->rank);
        foreach (['dealer.sales.index', 'dealer.customers.index', 'dealer.awards.index'] as $route) {
            $response->assertSee(route($route));
        }
    }

    public function test_transfer_moves_current_staff_but_preserves_original_dealer_customers_scores_and_evidence(): void
    {
        Storage::fake('submission_evidence');
        $first = User::factory()->dealer()->create();
        $second = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $first->dealer_id]);
        $old = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'evidence_image_path' => 'protected.jpg']);
        Storage::disk('submission_evidence')->put('protected.jpg', 'preserved-evidence');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('admin.sales.update', $sales), ['name' => $sales->name, 'dealer_id' => $second->dealer_id])->assertSessionHasNoErrors();
        $new = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $this->actingAs($first)->get(route('dealer.sales.index'))->assertOk()->assertDontSee($sales->email);
        $this->get(route('dealer.customers.index'))->assertOk()->assertSee($old->customer_name)->assertDontSee($new->customer_name);
        $this->get(route('dealer.customers.show', $old))->assertOk()->assertSee($sales->name);
        $this->get(route('submissions.evidence', $old))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('dealer.sales.show', $sales))->assertNotFound();
        $this->get(route('dealer.customers.show', $new))->assertNotFound();
        $this->get(route('dealer.dashboard'))->assertOk()->assertViewHas('standings', fn ($items) => (int) $items['all']->score === 1);
        $this->actingAs($second)->get(route('dealer.sales.index'))->assertOk()->assertSee($sales->name);
        $this->get(route('dealer.customers.index'))->assertOk()->assertSee($new->customer_name)->assertDontSee($old->customer_name);
        $this->get(route('dealer.customers.show', $old))->assertNotFound();
        $this->get(route('submissions.evidence', $old))->assertForbidden();
        $this->assertSame($first->dealer_id, $old->fresh()->dealer_id);
        $this->assertSame('preserved-evidence', Storage::disk('submission_evidence')->get('protected.jpg'));
    }

    public function test_dealer_reads_only_whitelisted_customer_and_sales_fields_without_admin_notes(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id, 'admin_note' => 'PRIVATE_SALES_ADMIN_NOTE']);
        $submission = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'admin_note' => 'PRIVATE_CUSTOMER_ADMIN_NOTE']);
        $this->actingAs($manager)->get(route('dealer.sales.show', $sales))->assertOk()->assertSee($sales->email)
            ->assertDontSee($sales->admin_note)->assertViewHas('sales', fn ($item) => ! array_key_exists('password', $item->getAttributes())
                && ! array_key_exists('admin_note', $item->getAttributes()));
        $this->get(route('dealer.customers.show', $submission))->assertOk()->assertSee($submission->customer_name)
            ->assertDontSee($submission->admin_note)->assertViewHas('submission', fn ($item) => ! array_key_exists('admin_note', $item->getAttributes())
                && ! array_key_exists('approved_facebook_identity', $item->getAttributes()));
    }

    public function test_lists_paginate_filter_and_reject_dealer_id_injection(): void
    {
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->count(21)->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->count(21)->create(['sales_id' => $sales->first()->id]);
        $this->actingAs($manager)->get(route('dealer.sales.index'))->assertOk()->assertViewHas('sales', fn ($items) => $items->count() === 20 && $items->total() === 21);
        $this->get(route('dealer.sales.index', ['page' => 2]))->assertOk()->assertViewHas('sales', fn ($items) => $items->count() === 1);
        $this->get(route('dealer.sales.index', ['status' => 'pending']))->assertOk()->assertViewHas('sales', fn ($items) => $items->isEmpty());
        $this->get(route('dealer.customers.index'))->assertOk()->assertViewHas('customers', fn ($items) => $items->count() === 20 && $items->total() === 21);
        $this->get(route('dealer.customers.index', ['page' => 2]))->assertOk()->assertViewHas('customers', fn ($items) => $items->count() === 1);
        $this->get(route('dealer.customers.index', ['status' => 'approved']))->assertOk()->assertViewHas('customers', fn ($items) => $items->isEmpty());
        foreach (['dealer.sales.index', 'dealer.customers.index'] as $route) {
            $this->getJson(route($route, ['dealer_id' => 123]))->assertUnprocessable();
            $this->getJson(route($route, ['q' => ['bad']]))->assertUnprocessable();
            $this->getJson(route($route, ['status' => 'invalid']))->assertUnprocessable();
        }
    }

    public function test_awards_include_only_own_published_dealer_snapshots(): void
    {
        $manager = User::factory()->dealer()->create();
        $admin = User::factory()->admin()->create();
        $published = Award::factory()->weekly()->create();
        AwardWinner::factory()->dealer()->create(['award_id' => $published->id, 'dealer_id' => $manager->dealer_id, 'dealer_name_snapshot' => 'Own historic Dealer']);
        AwardWinner::factory()->dealer()->create(['award_id' => $published->id, 'rank' => 2, 'dealer_name_snapshot' => 'OTHER_PRIVATE_DEALER']);
        AwardWinner::factory()->create(['award_id' => $published->id, 'winner_name_snapshot' => 'PRIVATE_SALES_WINNER']);
        $published->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        AwardWinner::factory()->dealer()->create(['dealer_id' => $manager->dealer_id, 'award_id' => Award::factory()->create(), 'dealer_name_snapshot' => 'PRIVATE_DRAFT']);
        $this->actingAs($manager)->get(route('dealer.awards.index'))->assertOk()->assertSee('Own historic Dealer')
            ->assertDontSee('OTHER_PRIVATE_DEALER')->assertDontSee('PRIVATE_SALES_WINNER')->assertDontSee('PRIVATE_DRAFT')
            ->assertViewHas('awards', fn ($items) => $items->total() === 1);
    }
}
