<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class DealerDashboardTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    public function test_dashboard_counts_scoped_submissions_and_global_dealer_ranks(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-02-12 12:00:00'));
        $account = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        CustomerSubmission::factory()->count(5)->approved()->for($other, 'creator')->create(['customer_name' => 'Other private customer']);
        CustomerSubmission::factory()->count(2)->approved()->for($account, 'creator')->create(['submitted_at' => '2031-02-12']);
        CustomerSubmission::factory()->approved()->for($account, 'creator')->create(['submitted_at' => '2031-02-01']);
        CustomerSubmission::factory()->approved()->for($account, 'creator')->create(['submitted_at' => '2031-01-31']);
        CustomerSubmission::factory()->for($account, 'creator')->create();
        CustomerSubmission::factory()->rejected()->for($account, 'creator')->create();
        $response = $this->actingAs($account)->get(route('dealer.dashboard'))->assertOk()
            ->assertViewHas('stats', ['total' => 6, 'pending' => 1, 'approved' => 4, 'rejected' => 1])
            ->assertSee($account->dealer->name)->assertSee('Thứ hạng #2')->assertDontSee('Other private customer');
        $standings = $response->viewData('standings');
        foreach (['week' => 2, 'month' => 3, 'all' => 4] as $period => $score) {
            $this->assertSame($score, (int) $standings[$period]->score);
            $this->assertSame(2, (int) $standings[$period]->rank);
        }
        $recent = $response->viewData('recentSubmissions');
        $this->assertCount(5, $recent);
        $this->assertTrue($recent->every(fn ($row) => $row->dealer_id === $account->dealer_id));
        $this->assertSame($account->submissions()->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->pluck('id')->all(), $recent->pluck('id')->all());
        foreach (['dealer.submissions.create', 'dealer.submissions.index', 'profile.edit', 'leaderboard'] as $route) {
            $response->assertSee(route($route));
        }
        $response->assertDontSee(route('admin.dealer-accounts.index'))->assertDontSee('/sales/');
    }

    public function test_empty_dashboard_renders_zeroes_without_inventing_rank(): void
    {
        $this->actingAs(User::factory()->active()->create())->get(route('dealer.dashboard'))->assertOk()
            ->assertViewHas('stats', ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0])
            ->assertViewHas('standings', fn ($rows) => array_filter($rows) === [])
            ->assertSee('Chưa có dữ liệu xếp hạng.')->assertSee('Đại lý chưa có khai báo nào')->assertDontSee('Thứ hạng #1');
    }

    public function test_historical_snapshot_contributes_without_inference_from_legacy_sales(): void
    {
        $known = CustomerSubmission::whereNotNull('sales_id')->whereNotNull('dealer_id')->firstOrFail();
        $account = User::factory()->active()->create(['dealer_id' => $known->dealer_id]);
        $expected = CustomerSubmission::where('dealer_id', $known->dealer_id)->count();
        $before = serialize(DB::table('customer_submissions')->orderBy('id')->get()->all());
        $response = $this->actingAs($account)->get(route('dealer.dashboard'))->assertOk();
        $this->assertSame($expected, $response->viewData('stats')['total']);
        $this->assertSame(1, $response->viewData('stats')['approved']);
        $this->assertSame($before, serialize(DB::table('customer_submissions')->orderBy('id')->get()->all()));
    }

    public function test_dashboard_requires_active_dealer_and_old_sales_routes_are_gone(): void
    {
        $this->get(route('dealer.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->getJson(route('dealer.dashboard'))->assertForbidden();
        foreach (['pending', 'rejected', 'blocked'] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]))->getJson(route('dealer.dashboard'))->assertForbidden();
        }
        foreach (['/sales/dashboard', '/sales/submissions', '/admin/sales'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
