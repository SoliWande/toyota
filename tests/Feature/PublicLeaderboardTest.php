<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PublicLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_public_page_renders_tabs_sales_and_dealers_without_customer_or_private_account_data(): void
    {
        $sales = User::factory()->active()->create(['email' => 'private-sales@example.test', 'phone' => 'PrivateSalesPhone']);
        $target = CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'PrivateCustomerName', 'phone' => 'PrivateCustomerPhone',
            'facebook_url' => 'https://facebook.com/private.customer', 'notes' => 'PrivateCustomerNote', 'admin_note' => 'PrivateAdminNote',
        ]);
        $this->get(route('leaderboard'))->assertOk()->assertSee('Top Sales')->assertSee('Top Dealer')
            ->assertSee('Tuần này')->assertSee('Tháng này')->assertSee('Toàn chương trình')
            ->assertSee($sales->name)->assertSee($sales->dealer->name)
            ->assertDontSee($sales->email)->assertDontSee($sales->phone)
            ->assertDontSee($target->customer_name)->assertDontSee($target->phone)->assertDontSee($target->facebook_url)
            ->assertDontSee($target->notes)->assertDontSee($target->admin_note);
    }

    public function test_default_week_and_month_tabs_use_submitted_at_not_reviewed_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', config('app.timezone')));
        $old = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-10-31 12:00:00', 'reviewed_at' => now()]);
        $new = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-11-02 10:00:00', 'reviewed_at' => now()]);
        foreach (['week', 'month'] as $period) {
            $this->get(route('leaderboard', ['period' => $period]))->assertOk()
                ->assertViewHas('sales', fn ($items) => $items->pluck('id')->all() === [$new->sales_id])
                ->assertViewHas('dealers', fn ($items) => $items->pluck('id')->all() === [$new->sales->dealer_id]);
        }
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk()
            ->assertViewHas('sales', fn ($items) => $items->total() === 2);
    }

    public function test_top_three_stay_visible_and_ranks_are_global_on_later_pages(): void
    {
        CustomerSubmission::factory()->count(23)->approved()->create();
        $first = $this->get(route('leaderboard', ['period' => 'all']))->assertOk()
            ->assertViewHas('topSales', fn ($items) => $items->count() === 3)
            ->assertViewHas('topDealers', fn ($items) => $items->count() === 3)
            ->assertViewHas('sales', fn ($items) => $items->total() === 23 && $items->count() === 20);
        $topIds = $first->viewData('topSales')->pluck('id')->all();
        $this->get(route('leaderboard', ['period' => 'all', 'sales_page' => 2, 'dealers_page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($items) => $items->pluck('rank')->map(fn ($rank) => (int) $rank)->all() === [21, 22, 23])
            ->assertViewHas('topSales', fn ($items) => $items->pluck('id')->all() === $topIds)
            ->assertViewHas('dealers', fn ($items) => $items->count() === 3)
            ->assertSee('period=all');
    }

    public function test_page_has_constant_query_count_and_no_n_plus_one(): void
    {
        CustomerSubmission::factory()->count(23)->approved()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk();
        $this->assertCount(4, DB::getQueryLog());
        DB::flushQueryLog();
        $this->get(route('leaderboard', ['period' => 'all', 'sales_page' => 2, 'dealers_page' => 2]))->assertOk();
        $this->assertCount(6, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_pending_and_rejected_do_not_appear_and_empty_page_is_clear(): void
    {
        CustomerSubmission::factory()->create();
        CustomerSubmission::factory()->rejected()->create();
        $this->get(route('leaderboard'))->assertOk()->assertSee('Chưa có thành tích đã duyệt trong kỳ này.')
            ->assertViewHas('sales', fn ($items) => $items->total() === 0)
            ->assertViewHas('dealers', fn ($items) => $items->total() === 0);
    }

    public function test_invalid_period_and_page_are_rejected(): void
    {
        $this->get(route('leaderboard', ['period' => 'invalid']))->assertSessionHasErrors('period');
        $this->get(route('leaderboard', ['sales_page' => 0]))->assertSessionHasErrors('sales_page');
        $this->get(route('leaderboard', ['dealers_page' => ['bad']]))->assertSessionHasErrors('dealers_page');
    }

    public function test_public_names_are_escaped(): void
    {
        $sales = User::factory()->active()->create(['name' => '<script>alert("sales")</script>']);
        $sales->dealer->update(['name' => '<img src=x onerror=alert(1)>']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $this->get(route('leaderboard'))->assertOk()
            ->assertSee($sales->name)->assertDontSee($sales->name, false)
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
