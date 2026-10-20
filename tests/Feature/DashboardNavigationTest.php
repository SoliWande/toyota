<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_counts_link_to_the_correct_filtered_lists_and_leaders_link_to_details(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $inactive = Dealer::factory()->inactive()->create();
        User::factory()->create(['dealer_id' => $sales->dealer_id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.dealers.index', ['status' => 'active']))
            ->assertSee(route('admin.sales.index', ['status' => 'active']))
            ->assertSee(route('admin.sales.show', $sales))->assertSee(route('admin.dealers.show', $sales->dealer_id))
            ->assertSee('Ảnh đại diện mặc định')
            ->assertViewHas('stats', fn ($stats) => $stats['dealers'] === 2 && $stats['active_dealers'] === 1 && $stats['active_sales'] === 1);
        $this->get(route('admin.dealers.index', ['status' => 'active']))->assertOk()
            ->assertViewHas('dealers', fn ($items) => $items->pluck('id')->all() === [$sales->dealer_id])->assertDontSee($inactive->name);
        $this->get(route('admin.dealers.index', ['status' => 'inactive']))->assertOk()
            ->assertViewHas('dealers', fn ($items) => $items->pluck('id')->all() === [$inactive->id]);
        $this->get(route('admin.sales.index', ['status' => 'active']))->assertOk()
            ->assertViewHas('sales', fn ($items) => $items->pluck('id')->all() === [$sales->id]);
        $this->get(route('admin.dealers.show', $sales->dealer_id))->assertOk()->assertSee($sales->email)
            ->assertSee(route('admin.sales.show', $sales));
        $this->get(route('admin.sales.show', $sales))->assertOk()->assertSee($sales->email);
    }

    public function test_public_dealer_navigation_and_details_use_only_dealer_fields_and_historical_scores(): void
    {
        $sales = User::factory()->active()->create(['name' => 'PRIVATE_SALES_NAV', 'email' => 'private.nav@example.test']);
        $original = $sales->dealer;
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        $new = Dealer::factory()->create();
        $sales->forceFill(['dealer_id' => $new->id])->save();
        Dealer::factory()->inactive()->create();

        $this->get(route('home'))->assertOk()->assertSee(route('dealers.index'))->assertSee(route('dealers.show', $original));
        $this->get(route('leaderboard'))->assertOk()->assertSee(route('dealers.show', $original));
        $list = $this->get(route('dealers.index'))->assertOk()->assertDontSee($sales->name)->assertDontSee($sales->email)
            ->assertViewHas('dealers', fn ($items) => $items->total() === 2)->viewData('dealers');
        foreach ($list as $dealer) {
            $this->assertEmpty($dealer->getRelations());
            $this->assertArrayNotHasKey('sales_count', $dealer->getAttributes());
            $this->assertArrayNotHasKey('toyota_source_id', $dealer->getAttributes());
        }
        $this->assertSame(1, (int) $list->firstWhere('id', $original->id)->score);
        $this->assertSame(0, (int) $list->firstWhere('id', $new->id)->score);
        foreach ([false, true] as $json) {
            $response = $json ? $this->getJson(app(DealerProfile::class)->url($original)) : $this->get(app(DealerProfile::class)->url($original));
            $response->assertOk()->assertDontSee($sales->name)->assertDontSee($sales->email)
                ->assertViewHas('standing', fn ($standing) => (int) $standing->score === 1 && (int) $standing->rank === 1)
                ->assertViewMissing('sales')->assertViewHas('dealer', fn ($dealer) => $dealer->getRelations() === []);
        }
        $this->get(app(DealerProfile::class)->url($new))->assertOk()->assertViewHas('standing', null);
        $this->get('/dealers/999999999')->assertNotFound();
    }

    public function test_historical_top_dealer_still_has_a_detail_page_after_deactivation(): void
    {
        $submission = CustomerSubmission::factory()->approved()->create(['submitted_at' => now()]);
        $submission->dealer->update(['is_active' => false]);
        $this->get(route('home'))->assertOk()->assertSee(route('dealers.show', $submission->dealer_id));
        $this->get(route('dealers.index'))->assertOk()->assertViewHas('dealers', fn ($items) => $items->isEmpty());
        $this->get(app(DealerProfile::class)->url($submission->dealer_id))->assertOk()->assertSee('Ngừng hoạt động');
    }

    public function test_only_admin_can_access_internal_dealer_details_and_filters_validate(): void
    {
        $sales = User::factory()->active()->create();
        $this->get(route('admin.dealers.show', $sales->dealer_id))->assertRedirect(route('login'));
        $this->getJson(route('admin.dealers.show', $sales->dealer_id))->assertUnauthorized();
        $this->actingAs($sales)->get(route('admin.dealers.show', $sales->dealer_id))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.dealers.index', ['status' => 'unexpected']))->assertUnprocessable();
        $this->getJson(route('dealers.index', ['page' => 'bad']))->assertUnprocessable();
    }

    public function test_public_directory_is_paginated_and_does_not_echo_sales_query_selectors(): void
    {
        Dealer::factory()->count(21)->create();
        $response = $this->get(route('dealers.index', ['sales_id' => 1, 'email' => 'PRIVATE_QUERY', 'include' => 'sales']))->assertOk()
            ->assertDontSee('PRIVATE_QUERY')->assertDontSee('sales_id')->assertDontSee('include=sales');
        $this->assertCount(20, $response->viewData('dealers'));
        $this->assertSame(21, $response->viewData('dealers')->total());
    }
}
