<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_empty_dashboard_has_zero_stats_and_all_quick_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('stats', [
                'dealers' => 0, 'active_sales' => 0, 'pending_sales' => 0,
                'approved_submissions' => 0, 'pending_submissions' => 0,
                'week_submissions' => 0, 'month_submissions' => 0,
            ])->assertSee('0 mục chờ duyệt')->assertSee('Đã xử lý hết hàng chờ.')
            ->assertSee('Không có khai báo chờ duyệt.')
            ->assertSee(route('admin.sales.index', ['status' => 'pending']))
            ->assertSee(route('admin.submissions.index', ['status' => 'pending']))
            ->assertSee(route('admin.dealers.index'))->assertSee(route('leaderboard'))
            ->assertSee(route('admin.awards.index'))->assertSee('Tạo vinh danh')
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
                'dealers' => 2, 'active_sales' => 1, 'pending_sales' => 2,
                'approved_submissions' => 2, 'pending_submissions' => 3,
                'week_submissions' => 4, 'month_submissions' => 4,
            ])->assertSee('5 mục chờ duyệt');
    }

    public function test_period_counts_use_submitted_at_and_application_timezone_with_exclusive_end_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-02 12:00:00', 'Asia/Ho_Chi_Minh'));
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
            ->assertViewHas('topSales', fn ($items) => $items->sole()->score === 2)
            ->assertSee('Asia/Ho_Chi_Minh');
    }

    public function test_recent_pending_records_are_limited_ordered_and_link_to_review_details(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->create();
        $old = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'submitted_at' => now()->subMonth()]);
        $recent = CustomerSubmission::factory()->count(5)->create(['sales_id' => $sales->id, 'submitted_at' => now()->subHour()]);
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

    public function test_weekly_summary_uses_existing_ranking_and_limits_each_list_to_three(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2));
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->active()->count(4)->create();
        foreach ($sales as $i => $user) {
            CustomerSubmission::factory()->approved()->create([
                'sales_id' => $user->id, 'reviewed_by' => $admin->id,
                'submitted_at' => now()->startOfWeek()->addHours($i + 1),
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
        CustomerSubmission::factory()->count(6)->create([
            'customer_name' => '<script>alert("customer")</script>',
            'phone' => 'private-phone', 'notes' => 'private-sales-note',
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_starts_with(strtolower($query['query']), 'select'));
        DB::disableQueryLog();

        $this->assertCount(8, $queries);
        $response->assertSee('<script>alert("customer")</script>')
            ->assertDontSee('<script>alert("customer")</script>', false)
            ->assertDontSee('private-phone')->assertDontSee('private-sales-note');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    /** @dataProvider salesStatuses */
    public function test_sales_cannot_access_admin_dashboard(UserStatus $status): void
    {
        $this->actingAs(User::factory()->create(['status' => $status]))
            ->get(route('admin.dashboard'))->assertForbidden();
    }

    public static function salesStatuses(): array
    {
        return array_map(fn ($status) => [$status], UserStatus::cases());
    }

    /** @dataProvider inactiveStatuses */
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
