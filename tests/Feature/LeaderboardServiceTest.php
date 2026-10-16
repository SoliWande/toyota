<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\UsesMigratedDealerDatabase;
use Tests\TestCase;

class LeaderboardServiceTest extends TestCase
{
    use UsesMigratedDealerDatabase;

    private function submission(User $account, string $time, string $state = 'approved'): CustomerSubmission
    {
        return CustomerSubmission::factory()->for($account, 'creator')->{$state}()->create([
            'submitted_at' => $time, 'reviewed_at' => '2031-03-05 12:00:00',
        ]);
    }

    public function test_scores_group_by_historical_dealer_without_any_account_join(): void
    {
        $account = User::factory()->active()->create();
        $this->submission($account, '2031-02-01');
        $this->submission($account, '2031-02-02');
        CustomerSubmission::factory()->for($account, 'creator')->count(3)->create(['submitted_at' => '2031-02-03']);
        $this->submission($account, '2031-02-04', 'rejected');
        $account->update(['status' => 'blocked']);
        $account->dealer->update(['is_active' => false]);
        $service = app(LeaderboardService::class);
        $query = $service->dealers(LeaderboardPeriod::Month, CarbonImmutable::parse('2031-02-12'));
        $rows = $query->get();
        $this->assertCount(1, $rows);
        $this->assertSame($account->dealer_id, $rows[0]->id);
        $this->assertSame(2, (int) $rows[0]->score);
        $this->assertStringNotContainsString('users', $query->toSql());
        $this->assertStringContainsString('group by `submissions`.`dealer_id`', $query->toSql());
        $this->assertSame(['id', 'name', 'code', 'score', 'rank'], array_keys((array) $rows[0]));
    }

    public function test_legacy_known_snapshots_count_but_unknown_ownership_is_never_inferred(): void
    {
        $before = DB::table('customer_submissions')->orderBy('id')->get()->toArray();
        $expected = DB::table('customer_submissions')->where('status', 'approved')->whereNotNull('dealer_id')
            ->select('dealer_id')->selectRaw('COUNT(*) as score')->groupBy('dealer_id')->pluck('score', 'dealer_id')->all();
        $actual = app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->pluck('score', 'id')->all();
        $this->assertSame($expected, $actual);
        $this->assertGreaterThan(0, CustomerSubmission::whereNull('dealer_id')->where('status', 'approved')->count());
        $this->assertSame(serialize($before), serialize(DB::table('customer_submissions')->orderBy('id')->get()->toArray()));
    }

    /** @dataProvider periodCases */
    public function test_half_open_periods_and_late_approval(LeaderboardPeriod $period, string $start, string $end): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $account = User::factory()->active()->create();
        $start = CarbonImmutable::parse($start, config('app.timezone'));
        $end = CarbonImmutable::parse($end, config('app.timezone'));
        foreach ([$start->subSecond(), $start, $end->subSecond(), $end] as $time) {
            $this->submission($account, $time->format('Y-m-d H:i:s'));
        }
        $service = app(LeaderboardService::class);
        foreach ([$start, $end->subSecond()] as $at) {
            $row = $service->dealers($period, $at)->where('id', $account->dealer_id)->first();
            $this->assertSame(2, (int) $row->score);
        }
        $this->assertSame(2, (int) $service->dealersForRange($start->utc(), $end->utc())->first()->score);
        $this->assertSame(4, (int) $service->dealers(LeaderboardPeriod::AllTime)->where('id', $account->dealer_id)->first()->score);
        $this->assertSame(1, (int) $service->dealers($period, $end)->where('id', $account->dealer_id)->first()->score);
    }

    public static function periodCases(): array
    {
        return [
            [LeaderboardPeriod::Week, '2030-12-30', '2031-01-06'],
            [LeaderboardPeriod::Month, '2032-02-01', '2032-03-01'],
            [LeaderboardPeriod::Month, '2030-12-01', '2031-01-01'],
        ];
    }

    public function test_ties_use_score_then_max_submitted_time_then_stable_dealer_id(): void
    {
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $early = User::factory()->active()->create();
        $high = User::factory()->active()->create();
        foreach ([$first, $second] as $account) {
            $this->submission($account, '2031-02-01');
            $this->submission($account, '2031-02-03');
        }
        $this->submission($early, '2031-02-01');
        $this->submission($early, '2031-02-02');
        for ($i = 0; $i < 3; $i++) {
            $this->submission($high, '2031-02-04');
        }
        $rows = app(LeaderboardService::class)->dealers(LeaderboardPeriod::Month, CarbonImmutable::parse('2031-02-12'))->get();
        $this->assertSame([$high->dealer_id, $early->dealer_id, $first->dealer_id, $second->dealer_id], $rows->pluck('id')->all());
        $this->assertSame([3, 2, 2, 2], $rows->pluck('score')->map(fn ($score) => (int) $score)->all());
        $this->assertSame([1, 2, 3, 4], $rows->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
    }

    public function test_aggregation_is_one_query_and_does_not_load_private_data(): void
    {
        CustomerSubmission::factory()->count(8)->approved()->create(['submitted_at' => '2031-02-12']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $rows = app(LeaderboardService::class)->dealers(LeaderboardPeriod::Month, CarbonImmutable::parse('2031-02-12'))->get();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(8, $rows);
        $this->assertCount(1, $queries);
        $this->assertSame(['id', 'name', 'code', 'score', 'rank'], array_keys((array) $rows->first()));
        $this->assertFalse(method_exists(LeaderboardService::class, 'sales'));
    }

    /** @dataProvider invalidRanges */
    public function test_invalid_ranges_are_rejected(?string $start, ?string $end): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LeaderboardService::class)->dealerScores($start ? CarbonImmutable::parse($start) : null, $end ? CarbonImmutable::parse($end) : null);
    }

    public static function invalidRanges(): array
    {
        return [['2031-02-01', null], [null, '2031-03-01'], ['2031-02-01', '2031-02-01'], ['2031-03-01', '2031-02-01']];
    }
}
