<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MonthlyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_stat_card_matches_its_filtered_list_including_pagination(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        Dealer::factory()->inactive()->count(21)->create();
        User::factory()->dealer()->create(['dealer_id' => $dealer->id]);
        $sales = User::factory()->active()->count(23)->create(['dealer_id' => $dealer->id]);
        User::factory()->create(['dealer_id' => $dealer->id]);
        User::factory()->create(['dealer_id' => $dealer->id, 'status' => 'blocked']);
        CustomerSubmission::factory()->approved()->count(23)->create(['sales_id' => $sales[0]->id, 'submitted_at' => now()->subHour()]);
        CustomerSubmission::factory()->count(2)->create(['sales_id' => $sales[1]->id, 'submitted_at' => '2026-11-01 00:00:00']);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales[1]->id, 'submitted_at' => '2026-11-30 23:59:59']);
        CustomerSubmission::factory()->create(['sales_id' => $sales[1]->id, 'submitted_at' => '2026-10-31 23:59:59']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales[1]->id, 'submitted_at' => '2026-12-01 00:00:00']);
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(25, $stats['sales']);
        $this->assertSame(28, $stats['submissions']);
        $this->assertSame(26, $stats['month_submissions']);
        foreach ($response->viewData('statLinks') as $key => $url) {
            $response->assertSee('data-stat="'.$key.'"', false)->assertSee($url);
            $list = str_contains($key, 'dealer') ? 'dealers' : (in_array($key, ['sales', 'active_sales', 'pending_sales']) ? 'sales' : 'submissions');
            $paginator = $this->get($url)->assertOk()->viewData($list);
            $this->assertSame($stats[$key], $paginator->total(), $key);
            $seen = $paginator->count();
            while ($next = $paginator->nextPageUrl()) {
                $paginator = $this->get($next)->assertOk()->viewData($list);
                $this->assertSame($stats[$key], $paginator->total(), $key.' after pagination');
                $seen += $paginator->count();
            }
            $this->assertSame($stats[$key], $seen, $key.' all pages');
        }
        $this->get(route('admin.submissions.index'))->assertViewHas('status', 'pending');
    }

    public function test_monthly_top_sales_and_dealers_share_approved_records_and_preserve_historical_attribution(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', config('app.timezone')));
        $admin = User::factory()->admin()->create();
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $third = User::factory()->active()->create();
        foreach ([$first, $second, $third] as $sales) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-11-01 00:00:00']);
            CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
            CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-10-31 23:59:59', 'reviewed_at' => now()]);
        }
        $original = $first->dealer_id;
        $first->forceFill(['dealer_id' => $second->dealer_id])->save();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Dẫn đầu tháng này')->assertDontSee('Dẫn đầu tuần')->assertDontSee('period=week');
        $this->assertSame([$first->id, $second->id, $third->id], $response->viewData('topSales')->pluck('id')->all());
        $this->assertSame([$original, $second->dealer_id, $third->dealer_id], $response->viewData('topDealers')->pluck('id')->all());
        $this->assertSame([1, 1, 1], $response->viewData('topDealers')->pluck('score')->all());
        $this->assertSame(3, $response->viewData('topSales')->sum('score'));
        $this->assertSame($original, CustomerSubmission::where('sales_id', $first->id)->first()->dealer_id);
    }

    public function test_qr_approved_points_belong_only_to_the_historical_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $registration = ['source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id,
            'phone_normalized' => '0901234567', 'vehicle_description' => 'Toyota Vios',
            'address_line' => 'Địa chỉ kiểm thử', 'province_code' => '79', 'ward_code' => '26734',
            'consented_at' => now(), 'consent_version' => config('dealer.consent_version')];
        CustomerSubmission::factory()->approved()->create($registration);
        CustomerSubmission::factory()->create($registration);
        $service = app(LeaderboardService::class);
        $this->assertTrue($service->sales(LeaderboardPeriod::Month)->get()->isEmpty());
        $this->assertSame(1, (int) $service->dealers(LeaderboardPeriod::Month)->sole()->score);
    }

    public function test_weekly_leaderboard_is_removed_from_public_and_dealer_ui_but_service_remains_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', config('app.timezone')));
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-11-01 00:00:00']);
        foreach (['home', 'leaderboard'] as $route) {
            foreach ([[], ['period' => 'week'], ['period' => 'month']] as $query) {
                $this->get(route($route, $query))->assertOk()->assertViewHas('period', LeaderboardPeriod::Month)
                    ->assertDontSee('period=week')->assertDontSee('Tuần này')
                    ->assertViewHas('topDealers', fn ($rows) => $rows->sole()->id === $manager->dealer_id);
            }
        }
        $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()->assertDontSee('Tuần này')->assertSee('Tháng này');
        $this->assertTrue(app(LeaderboardService::class)->dealers(LeaderboardPeriod::Week)->get()->isEmpty());
    }

    public function test_dealer_and_sales_cannot_follow_admin_card_links(): void
    {
        foreach ([User::factory()->dealer()->create(), User::factory()->active()->create()] as $user) {
            foreach (['admin.dashboard', 'admin.dealers.index', 'admin.sales.index', 'admin.submissions.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_monthly_dashboard_browser_fixtures(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 3) as $i) {
            $sales = User::factory()->active()->create(['name' => 'Sales kiểm thử '.$i]);
            CustomerSubmission::factory()->approved()->count($i)->create(['sales_id' => $sales->id]);
        }
        $directory = base_path('.laravel-tools/monthly-dashboard/qa');
        File::ensureDirectoryExists($directory);
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        File::put($directory.'/admin.html', $response->getContent());
        File::put($directory.'/links.json', json_encode($response->viewData('statLinks'), JSON_THROW_ON_ERROR));
        $this->app['auth']->forgetGuards();
        File::put($directory.'/home.html', $this->get(route('home'))->assertOk()->getContent());
        File::put($directory.'/leaderboard.html', $this->get(route('leaderboard'))->assertOk()->getContent());
    }
}
