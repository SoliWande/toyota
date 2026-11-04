<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CompetitionLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_rendering_keeps_service_order_global_ranks_links_and_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 23) as $index) {
            CustomerSubmission::factory()->approved()->create([
                'reviewed_by' => $admin->id, 'license_plate' => '79A-'.(30000 + $index),
                'submitted_at' => '2026-11-01 10:00:00',
            ]);
        }
        $expected = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->get();
        $this->assertSame(range(1, 23), $expected->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
        $measure = function (int $page) use (&$expected): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->get(route('leaderboard', ['period' => 'all', 'dealers_page' => $page, 'email' => 'PRIVATE_QUERY_TEST']))->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertSame($expected->forPage($page, 20)->pluck('id')->values()->all(), $response->viewData('dealers')->pluck('id')->all());
            $this->assertSame($expected->take(3)->pluck('id')->all(), $response->viewData('topDealers')->pluck('id')->all());
            $response->assertDontSee('PRIVATE_QUERY_TEST')->assertDontSee('Top Sales')->assertSee('aria-label="Top Đại Lý"', false);
            foreach ($response->viewData('dealers') as $row) {
                $response->assertSee(route('dealers.show', $row->id));
            }
            $business = array_filter($queries, fn ($query) => ! (str_contains($query['query'], '`users`') && in_array(ToyotaRealisticDemo::EMAIL, $query['bindings'], true)));
            // Exact baseline query budget: COUNT + page rows, plus top3 on later pages.
            $this->assertCount($page === 1 ? 2 : 3, $business);
            $this->assertLessThanOrEqual($page === 1 ? 1 : 2, count($queries) - count($business));

            return $response->viewData('topDealers')->pluck('id')->all();
        };
        $this->assertSame($measure(1), $measure(2));
        foreach (range(24, 46) as $index) {
            CustomerSubmission::factory()->approved()->create(['reviewed_by' => $admin->id, 'license_plate' => '79A-'.(30000 + $index), 'submitted_at' => '2026-11-01 10:00:00']);
        }
        $expected = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->get();
        $measure(1);
        $measure(2);
    }

    public function test_empty_state_does_not_render_fake_podium_or_pagination(): void
    {
        CustomerSubmission::factory()->create(['license_plate' => '79A-40001']);
        CustomerSubmission::factory()->rejected()->create(['license_plate' => '79A-40002']);
        $response = $this->get(route('leaderboard'))->assertOk()
            ->assertSee('Chưa có thành tích đã duyệt trong kỳ này.')
            ->assertDontSee('class="wc-lb-card', false)->assertDontSee('class="wc-lb-pagination', false);
        $this->assertSame(0, $response->viewData('dealers')->total());
    }

    public function test_ties_keep_achieved_time_then_dealer_id_order(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->count(3)->active()->create();
        foreach ($sales as $index => $person) {
            foreach ([1, 2] as $entry) {
                CustomerSubmission::factory()->approved()->create([
                    'sales_id' => $person->id, 'reviewed_by' => $admin->id,
                    'license_plate' => '79A-'.(50000 + $index * 10 + $entry),
                    'submitted_at' => $index === 0 ? '2026-11-02 10:00:00' : '2026-11-01 10:00:00',
                ]);
            }
        }
        $response = $this->get(route('leaderboard', ['period' => 'all']))->assertOk();
        $rows = $response->viewData('topDealers');
        $this->assertSame([$sales[1]->dealer_id, $sales[2]->dealer_id, $sales[0]->dealer_id], $rows->pluck('id')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
        $this->assertSame([2, 2, 2], $rows->pluck('score')->map(fn ($score) => (int) $score)->all());
    }
}
