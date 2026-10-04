<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_landing_has_navigation_join_cta_rules_and_accessible_empty_states(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Mỗi kết nối.')
            ->assertSee('Tham gia ngay')->assertSee(route('register'))->assertSee(route('login'))
            ->assertSee(route('leaderboard'))->assertSee(route('awards.index'))
            ->assertSee('id="the-le"', false)->assertSee('id="noi-dung"', false)
            ->assertSee('Bỏ qua điều hướng, đến nội dung')->assertSee('aria-label="Điều hướng mobile"', false)
            ->assertSee('Chưa có kỳ vinh danh được công bố.')
            ->assertViewHas('stats', ['sales' => 0, 'dealers' => 0, 'members' => 0]);
    }

    public function test_statistics_count_only_active_sales_active_dealers_and_approved_submissions(): void
    {
        $activeDealer = Dealer::factory()->create();
        $inactiveDealer = Dealer::factory()->inactive()->create();
        $active = User::factory()->active()->create(['dealer_id' => $activeDealer->id]);
        User::factory()->create(['dealer_id' => $activeDealer->id]);
        User::factory()->create(['dealer_id' => $inactiveDealer->id, 'status' => 'blocked']);
        $admin = User::factory()->admin()->create();
        CustomerSubmission::factory()->count(2)->approved()->create(['sales_id' => $active->id, 'reviewed_by' => $admin->id]);
        CustomerSubmission::factory()->create(['sales_id' => $active->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $active->id, 'reviewed_by' => $admin->id]);

        $this->get(route('home'))->assertOk()->assertViewHas('stats', ['sales' => 1, 'dealers' => 1, 'members' => 2]);
    }

    public function test_week_month_preview_reuses_leaderboard_and_does_not_count_late_review_in_wrong_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', config('app.timezone')));
        $weekly = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-11-02 10:00:00', 'reviewed_at' => now()]);
        $monthly = CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-11-01 10:00:00', 'reviewed_at' => now()]);
        CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-10-31 10:00:00', 'reviewed_at' => now()]);
        $this->get(route('home'))->assertOk()
            ->assertViewHas('topSales', fn ($items) => $items->pluck('id')->all() === [$weekly->sales_id])
            ->assertViewHas('topDealers', fn ($items) => $items->pluck('id')->all() === [$weekly->sales->dealer_id]);
        $this->get(route('home', ['period' => 'month']))->assertOk()
            ->assertViewHas('topSales', fn ($items) => $items->pluck('id')->all() === [$monthly->sales_id, $weekly->sales_id]);
        $this->get(route('home', ['period' => 'invalid']))->assertSessionHasErrors('period');
    }

    public function test_public_landing_never_displays_customer_or_private_account_information(): void
    {
        $sales = User::factory()->active()->create(['email' => 'private.sales@example.test', 'phone' => 'PrivateSalesPhone']);
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => $sales->id, 'customer_name' => 'PrivateCustomerName', 'phone' => 'PrivateCustomerPhone',
            'facebook_url' => 'https://facebook.com/private.customer', 'notes' => 'PrivateCustomerNote', 'admin_note' => 'PrivateAdminNote',
        ]);
        $this->get(route('home'))->assertOk()->assertSee($sales->name)->assertSee($sales->dealer->name)
            ->assertDontSee($sales->email)->assertDontSee($sales->phone)
            ->assertDontSee('PrivateCustomerName')->assertDontSee('PrivateCustomerPhone')
            ->assertDontSee('https://facebook.com/private.customer')->assertDontSee('PrivateCustomerNote')->assertDontSee('PrivateAdminNote');
    }

    public function test_latest_award_is_chosen_by_publication_date_and_uses_only_snapshots(): void
    {
        $winner = AwardWinner::factory()->create(['winner_name_snapshot' => 'Historic winner', 'dealer_name_snapshot' => 'Historic dealer']);
        $award = $winner->award;
        $admin = User::factory()->admin()->create();
        $award->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        Award::factory()->weekly()->create(['title' => 'Secret unpublished draft']);
        $start = now()->startOfMonth()->subMonth();
        Award::factory()->create(['title' => 'Older published award', 'period_start' => $start, 'period_end' => $start->copy()->addMonth(), 'published_at' => now()->subDay(), 'published_by' => $admin->id]);
        $winner->sales->update(['name' => 'Renamed live Sales']);
        $winner->sales->dealer->update(['name' => 'Renamed live Dealer']);
        $this->get(route('home'))->assertOk()->assertViewHas('latestAward', fn ($item) => $item->id === $award->id)
            ->assertSee('Historic winner')->assertSee('Historic dealer')->assertSee(route('awards.show', $award))
            ->assertDontSee('Secret unpublished draft')->assertDontSee('Older published award')
            ->assertDontSee('Renamed live Sales')->assertDontSee('Renamed live Dealer');
    }

    public function test_top_three_limit_and_query_count_are_constant(): void
    {
        CustomerSubmission::factory()->count(8)->approved()->create();
        $winner = AwardWinner::factory()->create();
        $winner->award->forceFill(['published_at' => now(), 'published_by' => User::factory()->admin()->create()->id])->save();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('home'))->assertOk()->assertViewHas('topSales', fn ($items) => $items->count() === 3)
            ->assertViewHas('topDealers', fn ($items) => $items->count() === 3);
        $this->assertCount(7, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_names_and_snapshot_content_are_escaped(): void
    {
        $sales = User::factory()->active()->create(['name' => '<script>alert(1)</script>']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $winner = AwardWinner::factory()->create(['winner_name_snapshot' => '<img src=x onerror=alert(1)>']);
        $winner->award->forceFill(['published_at' => now(), 'published_by' => User::factory()->admin()->create()->id])->save();
        $this->get(route('home'))->assertOk()->assertSee($sales->name)->assertDontSee($sales->name, false)
            ->assertSee($winner->winner_name_snapshot)->assertDontSee($winner->winner_name_snapshot, false);
    }

    public function test_authenticated_cta_links_to_existing_role_and_status_destination(): void
    {
        foreach ([User::factory()->active()->create(), User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('home'))->assertOk()->assertSee(route($user->homeRoute()))->assertDontSee(route('register'));
        }
    }
}
