<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class LeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    public function test_only_approved_submissions_count_and_dealer_score_sums_its_sales(): void
    {
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        $secondSales = User::factory()->active()->create(['dealer_id' => $dealer->id]);
        CustomerSubmission::factory()->count(2)->approved()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $secondSales->id]);
        CustomerSubmission::factory()->count(3)->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $secondSales->id]);
        $service = app(LeaderboardService::class);
        $scores = $service->salesScores()->get()->keyBy('id');
        $this->assertSame(2, (int) $scores[$sales->id]->score);
        $this->assertSame(1, (int) $scores[$secondSales->id]->score);
        $dealerScore = $service->dealerScores()->first();
        $this->assertSame($dealer->id, $dealerScore->id);
        $this->assertSame(3, (int) $dealerScore->score);
    }

    public function test_october_submission_approved_in_november_counts_for_october_only(): void
    {
        $submission = CustomerSubmission::factory()->approved()->create([
            'submitted_at' => '2026-10-31 23:59:59', 'reviewed_at' => '2026-11-02 12:00:00',
        ]);
        $service = app(LeaderboardService::class);
        $octoberStart = CarbonImmutable::parse('2026-10-01', config('app.timezone'));
        $novemberStart = $octoberStart->addMonth();
        $this->assertSame(1, (int) $service->salesScores($octoberStart, $novemberStart)->first()->score);
        $this->assertSame($submission->sales->dealer_id, $service->dealerScores($octoberStart, $novemberStart)->first()->id);
        $this->assertTrue($service->salesScores($novemberStart, $novemberStart->addMonth())->get()->isEmpty());
        $this->assertTrue($service->dealerScores($novemberStart, $novemberStart->addMonth())->get()->isEmpty());
    }

    public function test_range_includes_start_excludes_end_for_sales_and_dealers(): void
    {
        $sales = User::factory()->active()->create();
        foreach (['2026-10-01 00:00:00', '2026-10-31 23:59:59', '2026-09-30 23:59:59', '2026-11-01 00:00:00'] as $time) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $time]);
        }
        $service = app(LeaderboardService::class);
        $start = CarbonImmutable::parse('2026-10-01', config('app.timezone'));
        $end = $start->addMonth();
        $this->assertSame(2, (int) $service->salesScores($start, $end)->first()->score);
        $this->assertSame(2, (int) $service->dealerScores($start, $end)->first()->score);
        $this->assertSame(4, (int) $service->salesScores()->first()->score);
    }

    public function test_range_converts_instant_boundaries_to_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $sales = User::factory()->active()->create();
        foreach (['2026-10-01 00:00:00', '2026-10-01 00:00:01', '2026-09-30 23:59:59', '2026-11-01 00:00:00'] as $time) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $time]);
        }
        $service = app(LeaderboardService::class);
        $startUtc = CarbonImmutable::parse('2026-09-30 17:00:00', 'UTC');
        $endUtc = CarbonImmutable::parse('2026-10-31 17:00:00', 'UTC');
        $this->assertSame(2, (int) $service->salesScores($startUtc, $endUtc)->first()->score);
        $this->assertSame(2, (int) $service->dealerScores($startUtc, $endUtc)->first()->score);
    }

    public function test_approved_history_is_not_removed_when_sales_or_dealer_is_inactive(): void
    {
        $dealer = Dealer::factory()->inactive()->create();
        $sales = User::factory()->create(['dealer_id' => $dealer->id, 'status' => UserStatus::Blocked]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $service = app(LeaderboardService::class);
        $this->assertSame(1, (int) $service->salesScores()->first()->score);
        $this->assertSame(1, (int) $service->dealerScores()->first()->score);
    }

    public function test_aggregate_results_do_not_select_customer_or_private_account_data(): void
    {
        CustomerSubmission::factory()->approved()->create();
        $service = app(LeaderboardService::class);
        $salesRow = (array) $service->sales(LeaderboardPeriod::AllTime)->first();
        $dealerRow = (array) $service->dealers(LeaderboardPeriod::AllTime)->first();
        $this->assertSame(['id', 'name', 'dealer_name', 'score', 'rank'], array_keys($salesRow));
        $this->assertSame(['id', 'name', 'code', 'score', 'rank'], array_keys($dealerRow));
    }

    public function test_aggregation_uses_two_queries_regardless_of_participant_count(): void
    {
        CustomerSubmission::factory()->count(8)->approved()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service = app(LeaderboardService::class);
        $this->assertCount(8, $service->salesScores()->get());
        $this->assertCount(8, $service->dealerScores()->get());
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_invalid_or_partial_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LeaderboardService::class)->salesScores(CarbonImmutable::now());
    }

    public function test_current_week_uses_monday_start_and_next_monday_exclusive(): void
    {
        $sales = User::factory()->active()->create();
        foreach (['2026-09-28 00:00:00', '2026-10-04 23:59:59', '2026-09-27 23:59:59', '2026-10-05 00:00:00'] as $time) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $time, 'reviewed_at' => '2026-10-10 12:00:00']);
        }
        $service = app(LeaderboardService::class);
        foreach (['2026-09-28 00:00:00', '2026-10-04 23:59:59'] as $instant) {
            $at = CarbonImmutable::parse($instant, config('app.timezone'));
            $this->assertSame(2, (int) $service->sales(LeaderboardPeriod::Week, $at)->first()->score);
            $this->assertSame(2, (int) $service->dealers(LeaderboardPeriod::Week, $at)->first()->score);
        }
    }

    public function test_current_month_late_approval_remains_in_original_month_and_all_time(): void
    {
        CustomerSubmission::factory()->approved()->create(['submitted_at' => '2026-10-31 23:59:59', 'reviewed_at' => '2026-11-02 12:00:00']);
        $service = app(LeaderboardService::class);
        $october = CarbonImmutable::parse('2026-10-31 23:59:59', config('app.timezone'));
        $november = $october->addSecond();
        $this->assertSame(1, (int) $service->sales(LeaderboardPeriod::Month, $october)->first()->score);
        $this->assertSame(1, (int) $service->dealers(LeaderboardPeriod::Month, $october)->first()->score);
        $this->assertTrue($service->sales(LeaderboardPeriod::Month, $november)->get()->isEmpty());
        $this->assertTrue($service->dealers(LeaderboardPeriod::Month, $november)->get()->isEmpty());
        $this->assertSame(1, (int) $service->sales(LeaderboardPeriod::AllTime, $november)->first()->score);
    }

    public function test_equal_scores_prioritize_earliest_last_submitted_time_not_reviewed_time(): void
    {
        $late = User::factory()->active()->create();
        $early = User::factory()->active()->create();
        CustomerSubmission::factory()->approved()->create(['sales_id' => $late->id, 'submitted_at' => '2026-10-01 10:00:00', 'reviewed_at' => '2026-10-01 12:00:00']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $late->id, 'submitted_at' => '2026-10-03 10:00:00', 'reviewed_at' => '2026-10-03 12:00:00']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $early->id, 'submitted_at' => '2026-10-01 10:00:00', 'reviewed_at' => '2026-11-02 12:00:00']);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $early->id, 'submitted_at' => '2026-10-02 10:00:00', 'reviewed_at' => '2026-11-02 12:00:00']);
        $service = app(LeaderboardService::class);
        $this->assertSame([$early->id, $late->id], $service->sales(LeaderboardPeriod::AllTime)->pluck('id')->all());
        $this->assertSame([$early->dealer_id, $late->dealer_id], $service->dealers(LeaderboardPeriod::AllTime)->pluck('id')->all());
        $this->assertSame(1, $service->salesRank($early->id));
        $this->assertSame(2, $service->salesRank($late->id));
        $this->assertNull($service->salesRank(User::factory()->active()->create()->id));
    }

    public function test_higher_score_wins_and_identical_score_time_uses_stable_id_order(): void
    {
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $high = User::factory()->active()->create();
        foreach ([$first, $second] as $sales) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => '2026-10-01 10:00:00']);
        }
        CustomerSubmission::factory()->count(2)->approved()->create(['sales_id' => $high->id, 'submitted_at' => '2026-10-03 10:00:00']);
        $service = app(LeaderboardService::class);
        $rows = $service->sales(LeaderboardPeriod::AllTime)->get();
        $this->assertSame([$high->id, $first->id, $second->id], $rows->pluck('id')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
    }
}
