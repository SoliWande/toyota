<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_empty_dashboard_has_zero_stats_and_all_quick_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('stats', [
                'dealers' => 0, 'active_dealers' => 0, 'active_sales' => 0, 'pending_sales' => 0,
                'sales' => 0, 'submissions' => 0,
                'approved_submissions' => 0, 'pending_submissions' => 0,
                'week_submissions' => 0, 'month_submissions' => 0,
            ])->assertSee('0 khai báo quá 48 giờ')
            ->assertDontSee('Đã xử lý hết hàng chờ.')
            ->assertSee(route('admin.sales.index', ['status' => 'pending']))
            ->assertSee(route('admin.submissions.index', ['status' => 'pending']))
            ->assertSee(route('admin.dealers.index'))->assertSee(route('leaderboard'))
            ->assertSee(route('admin.awards.index'))->assertSee('Tạo kỳ vinh danh')
            ->assertViewHas('topSales', fn ($items) => $items->isEmpty())
            ->assertViewHas('topDealers', fn ($items) => $items->isEmpty());
    }

    public function test_global_counts_include_inactive_dealers_but_exclude_admins_from_sales_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->inactive()->create();
        Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        User::factory()->count(2)->create(['dealer_id' => $dealer->id]);
        foreach ([UserStatus::Rejected, UserStatus::Blocked] as $status) {
            User::factory()->create(['dealer_id' => $dealer->id, 'status' => $status]);
        }
        User::factory()->admin()->create(['status' => UserStatus::Pending]);
        CustomerSubmission::factory()->count(3)->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->count(2)->approved()->create([
            'sales_id' => $sales->id, 'reviewed_by' => $admin->id, 'submitted_at' => now()->subYear(),
        ]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'reviewed_by' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', [
                'dealers' => 2, 'active_dealers' => 1, 'active_sales' => 1, 'pending_sales' => 2,
                'sales' => 5, 'submissions' => 6,
                'approved_submissions' => 2, 'pending_submissions' => 3,
                'week_submissions' => 4, 'month_submissions' => 4,
            ])->assertViewHas('overview', fn ($data) => $data['pending'] === 3 && $data['pending_managers'] === 0);
    }

    public function test_period_counts_use_submitted_at_and_application_timezone_with_exclusive_end_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00', 'Asia/Ho_Chi_Minh'));
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        foreach (['2026-10-31 23:59:59', '2026-11-01 00:00:00', '2026-11-01 23:59:59',
            '2026-11-02 00:00:00', '2026-11-08 23:59:59', '2026-11-09 00:00:00',
            '2026-11-30 23:59:59', '2026-12-01 00:00:00'] as $date) {
            CustomerSubmission::factory()->approved()->create([
                'sales_id' => $sales->id, 'reviewed_by' => $admin->id,
                'submitted_at' => $date, 'reviewed_at' => now(), 'created_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['week_submissions'] === 2
                && $stats['month_submissions'] === 6 && $stats['approved_submissions'] === 8)
            ->assertViewHas('topSales', fn ($items) => $items->sole()->score === 6)
            ->assertViewHas('overview', fn ($data) => $data['approved_month'] === 6);
    }

    public function test_recent_pending_records_are_limited_ordered_and_link_to_review_details(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $old = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subMonth()]);
        $recent = CustomerSubmission::factory()->count(4)->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHour()]);
        $approved = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'reviewed_by' => $admin->id]);
        $rejected = CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id, 'reviewed_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('recentPendingSubmissions', fn ($items) => $items->modelKeys() === $recent->sortByDesc('id')->values()->modelKeys())
            ->assertSeeInOrder($recent->sortByDesc('id')->pluck('customer_name')->all())
            ->assertSee($sales->name)->assertSee($sales->dealer->name)
            ->assertDontSee($old->customer_name)->assertDontSee($approved->customer_name)->assertDontSee($rejected->customer_name);
        foreach ($recent as $submission) {
            $response->assertSee(route('admin.submissions.show', $submission));
        }
    }

    public function test_monthly_summary_uses_existing_ranking_and_limits_each_list_to_three(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->count(4)->create();
        foreach ($sales as $i => $user) {
            CustomerSubmission::factory()->approved()->create([
                'sales_id' => $user->id, 'reviewed_by' => $admin->id,
                'submitted_at' => now()->startOfMonth()->addHours($i + 1),
            ]);
        }
        CustomerSubmission::factory()->count(5)->create(['sales_id' => $sales[3]->id]);
        CustomerSubmission::factory()->approved()->count(5)->create([
            'sales_id' => $sales[3]->id, 'reviewed_by' => $admin->id, 'submitted_at' => now()->subMonth(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('topSales', fn ($items) => $items->pluck('id')->all() === $sales->take(3)->modelKeys()
                && $items->pluck('score')->all() === [1, 1, 1])
            ->assertViewHas('topDealers', fn ($items) => $items->pluck('id')->all() === $sales->take(3)->pluck('dealer_id')->all());
    }

    public function test_pending_relations_are_eager_loaded_and_private_fields_are_not_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $phones = collect(range(0, 5))->map(fn ($index) => sprintf('090000%04d', $index));
        CustomerSubmission::factory()->count(6)->sequence(fn ($sequence) => ['phone' => $phones[$sequence->index]])->create([
            'customer_name' => '<script>alert("customer")</script>',
            'notes' => 'private-sales-note',
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_starts_with(strtolower($query['query']), 'select'));
        DB::disableQueryLog();

        // Fixed aggregates/eager loading, including P02 queue/account/KPI reads; no per-row queries.
        $this->assertLessThanOrEqual(17, $queries->count());
        $response->assertSee('<script>alert("customer")</script>')
            ->assertDontSee('<script>alert("customer")</script>', false)
            ->assertDontSee('private-sales-note');
        foreach ($phones as $phone) {
            $response->assertDontSee($phone);
        }
    }

    public function test_queue_clocks_use_submitted_at_and_latest_account_resubmission_with_strict_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        foreach ([now()->subHours(48)->subSecond(), now()->subHours(48), now()->subHour()] as $date) {
            CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => $date, 'created_at' => now()->subYear()]);
        }
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subYear()]);
        User::factory()->dealer()->create(['status' => UserStatus::Pending]);
        User::factory()->create(['created_at' => now()->subDays(3)->subSecond()]);
        User::factory()->create(['created_at' => now()->subDays(3)]);
        $resubmitted = User::factory()->create(['created_at' => now()->subMonth()]);
        $review = new ModerationReview;
        $review->forceFill(['sales_id' => $resubmitted->id, 'reviewed_by' => $resubmitted->id,
            'from_status' => 'rejected', 'to_status' => 'pending', 'reviewed_at' => now()->subDay()])->save();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('overview', fn ($data) => $data['pending'] === 3 && $data['overdue'] === 1
                && $data['oldest']->equalTo(now()->subHours(48)->subSecond()) && $data['oldest_days'] === 2
                && $data['pending_managers'] === 1 && $data['overdue_sales'] === 1);
    }

    public function test_qualified_dealers_count_approved_snapshot_customers_including_qr_without_changing_scores(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $qualified = Dealer::factory()->create();
        $below = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $qualified->id]);
        CustomerSubmission::factory()->approved()->count(4)->create([
            'sales_id' => $sales->id, 'reviewed_by' => $admin->id, 'submitted_at' => now()->subMonth(),
        ]);
        CustomerSubmission::factory()->approved()->create([
            'sales_id' => null, 'source' => 'dealer_qr', 'dealer_id' => $qualified->id, 'reviewed_by' => $admin->id,
            'phone' => '0901234567', 'consented_at' => now(),
            'phone_normalized' => '0901234567', 'vehicle_description' => 'Veloz 2022',
            'address_line' => 'Địa chỉ fixture', 'province_code' => '01', 'ward_code' => '00001', 'consent_version' => 'v1',
        ]);
        $otherSales = User::factory()->active()->create(['dealer_id' => $below->id]);
        CustomerSubmission::factory()->approved()->count(4)->create(['sales_id' => $otherSales->id, 'reviewed_by' => $admin->id]);
        CustomerSubmission::factory()->count(2)->create(['sales_id' => $otherSales->id]);
        // Moving Sales later must not move the original dealer attribution.
        $sales->forceFill(['dealer_id' => $below->id])->save();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('overview', fn ($data) => $data['qualified_dealers'] === 1 && $data['approved_month'] === 5)
            ->assertViewHas('topDealers', fn ($items) => $items->pluck('score', 'id')->all() === [$below->id => 4, $qualified->id => 1]);
    }

    public function test_ranking_tabs_use_admin_get_routes_real_rankings_and_preserve_stat_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create(['name' => 'Sales bảng nội bộ']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $this->actingAs($admin)->get(route('admin.dashboard', ['ranking' => 'sales']))->assertOk()
            ->assertViewHas('ranking', 'sales')->assertSee(route('admin.sales.show', $sales))
            ->assertSee('Sales (nội bộ)')
            ->assertDontSee('Top 3 Sales')->assertDontSee('Tạo vinh danh')
            ->assertSee(route('admin.dashboard', ['ranking' => 'dealers']).'#ranking');
        $response = $this->get(route('admin.dashboard', ['ranking' => 'dealers']))->assertOk()
            ->assertViewHas('ranking', 'dealers')->assertSee(route('admin.dealers.show', $sales->dealer_id));
        $links = $response->viewData('statLinks');
        parse_str(parse_url($links['submissions'], PHP_URL_QUERY), $all);
        parse_str(parse_url($links['month_submissions'], PHP_URL_QUERY), $month);
        $this->assertSame('', $all['status']);
        $this->assertSame('', $month['status']);
        $this->assertSame(now()->startOfMonth()->toDateString(), $month['submitted_from']);
        $this->assertSame(now()->endOfMonth()->toDateString(), $month['submitted_to']);
        $this->getJson(route('admin.dashboard', ['ranking' => 'untrusted']))->assertUnprocessable();
        $this->actingAs(User::factory()->dealer()->create())->get(route('admin.dashboard', ['ranking' => 'sales']))->assertForbidden();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    #[DataProvider('salesStatuses')]
    public function test_sales_cannot_access_admin_dashboard(UserStatus $status): void
    {
        $this->actingAs(User::factory()->create(['status' => $status]))
            ->get(route('admin.dashboard'))->assertForbidden();
    }

    public static function salesStatuses(): array
    {
        return array_map(fn ($status) => [$status], UserStatus::cases());
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_admin_cannot_access_dashboard(UserStatus $status): void
    {
        $this->actingAs(User::factory()->admin()->create(['status' => $status]))
            ->get(route('admin.dashboard'))->assertRedirect(route('account.status'));
        $this->getJson(route('admin.dashboard'))->assertForbidden();
    }

    public static function inactiveStatuses(): array
    {
        return [[UserStatus::Pending], [UserStatus::Rejected], [UserStatus::Blocked]];
    }
}
