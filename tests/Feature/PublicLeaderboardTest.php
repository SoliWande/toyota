<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class PublicLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_public_page_renders_only_dealers_without_sales_or_customer_data(): void
    {
        $sales = User::factory()->active()->create(['email' => 'private-sales@example.test', 'phone' => 'PrivateSalesPhone']);
        $target = CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'PrivateCustomerName', 'phone' => 'PrivateCustomerPhone',
            'facebook_url' => 'https://facebook.com/private.customer', 'notes' => 'PrivateCustomerNote', 'admin_note' => 'PrivateAdminNote',
        ]);
        $this->get(route('leaderboard'))->assertOk()->assertDontSee('Top Sales')->assertSee('Top Đại Lý')
            ->assertDontSee('Tuần này')->assertSee('Tháng này')->assertSee('Toàn chương trình')
            ->assertDontSee($sales->name)->assertSee($sales->dealer->name)
            ->assertViewMissing('sales')->assertViewMissing('topSales')
            ->assertDontSee($sales->email)->assertDontSee($sales->phone)
            ->assertDontSee($target->customer_name)->assertDontSee($target->phone)->assertDontSee($target->facebook_url)
            ->assertDontSee($target->notes)->assertDontSee($target->admin_note);
    }

    public function test_month_and_legacy_week_urls_use_monthly_submitted_at_not_reviewed_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', config('app.timezone')));
        $old = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-10-31 12:00:00', 'reviewed_at' => now()]);
        $new = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-11-02 10:00:00', 'reviewed_at' => now()]);
        foreach (['week', 'month'] as $period) {
            $this->get(route('leaderboard', ['period' => $period]))->assertOk()
                ->assertViewHas('dealers', fn ($items) => $items->pluck('id')->all() === [$new->sales->dealer_id]);
        }
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk()
            ->assertViewHas('dealers', fn ($items) => $items->total() === 2);
    }

    public function test_top_three_stay_visible_and_ranks_are_global_on_later_pages(): void
    {
        CustomerSubmission::factory()->count(23)->approved()->create();
        $first = $this->get(route('leaderboard', ['period' => 'all']))->assertOk()
            ->assertViewHas('topDealers', fn ($items) => $items->count() === 3)
            ->assertViewHas('dealers', fn ($items) => $items->total() === 23 && $items->count() === 20);
        $topIds = $first->viewData('topDealers')->pluck('id')->all();
        $this->get(route('leaderboard', ['period' => 'all', 'dealers_page' => 2]))->assertOk()
            ->assertViewHas('dealers', fn ($items) => $items->pluck('rank')->map(fn ($rank) => (int) $rank)->all() === [21, 22, 23])
            ->assertViewHas('topDealers', fn ($items) => $items->pluck('id')->all() === $topIds)
            ->assertViewHas('dealers', fn ($items) => $items->count() === 3)
            ->assertSee('period=all');
    }

    public function test_page_has_constant_query_count_and_no_n_plus_one(): void
    {
        CustomerSubmission::factory()->count(23)->approved()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk();
        $this->assertCount(2, DB::getQueryLog());
        DB::flushQueryLog();
        $this->get(route('leaderboard', ['period' => 'all', 'dealers_page' => 2]))->assertOk();
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_pending_and_rejected_do_not_appear_and_empty_page_is_clear(): void
    {
        CustomerSubmission::factory()->create();
        CustomerSubmission::factory()->rejected()->create();
        $this->get(route('leaderboard'))->assertOk()->assertSee('Chưa có thành tích đã duyệt trong kỳ này.')
            ->assertViewHas('dealers', fn ($items) => $items->total() === 0);
    }

    public function test_invalid_period_and_page_are_rejected(): void
    {
        $this->get(route('leaderboard', ['period' => 'invalid']))->assertSessionHasErrors('period');
        $this->get(route('leaderboard', ['dealers_page' => 0]))->assertSessionHasErrors('dealers_page');
        $this->get(route('leaderboard', ['dealers_page' => ['bad']]))->assertSessionHasErrors('dealers_page');
    }

    public function test_public_names_are_escaped(): void
    {
        $sales = User::factory()->active()->create(['name' => '<script>alert("sales")</script>']);
        $sales->dealer->update(['name' => '<img src=x onerror=alert(1)>']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $this->get(route('leaderboard'))->assertOk()
            ->assertDontSee($sales->name)
            ->assertSee($sales->dealer->name)->assertDontSee($sales->dealer->name, false);
    }

    public function test_sales_dashboard_reads_personal_all_time_rank_from_service(): void
    {
        $sales = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->count(2)->approved()->create(['sales_id' => User::factory()->active()->create()->id]);
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()->assertViewHas('currentRank', 2)
            ->assertSee('Thứ hạng toàn chương trình')->assertSee('#2');
    }
}
