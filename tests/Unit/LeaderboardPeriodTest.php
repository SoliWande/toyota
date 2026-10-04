<?php

namespace Tests\Unit;

use App\Enums\LeaderboardPeriod;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class LeaderboardPeriodTest extends TestCase
{
    /** @dataProvider boundaries */
    public function test_calendar_boundaries(LeaderboardPeriod $period, string $instant, string $expectedStart, string $expectedEnd): void
    {
        config(['app.timezone' => 'UTC']);
        [$start, $end] = app(LeaderboardService::class)->bounds($period, CarbonImmutable::parse($instant, 'UTC'));
        $this->assertSame($expectedStart, $start->format('Y-m-d H:i:s'));
        $this->assertSame($expectedEnd, $end->format('Y-m-d H:i:s'));
    }

    public static function boundaries(): array
    {
        return [
            [LeaderboardPeriod::Week, '2026-10-04 23:59:59', '2026-09-28 00:00:00', '2026-10-05 00:00:00'],
            [LeaderboardPeriod::Week, '2026-10-05 00:00:00', '2026-10-05 00:00:00', '2026-10-12 00:00:00'],
            [LeaderboardPeriod::Week, '2027-01-01 12:00:00', '2026-12-28 00:00:00', '2027-01-04 00:00:00'],
            [LeaderboardPeriod::Month, '2026-10-31 23:59:59', '2026-10-01 00:00:00', '2026-11-01 00:00:00'],
            [LeaderboardPeriod::Month, '2026-11-01 00:00:00', '2026-11-01 00:00:00', '2026-12-01 00:00:00'],
            [LeaderboardPeriod::Month, '2028-02-29 12:00:00', '2028-02-01 00:00:00', '2028-03-01 00:00:00'],
            [LeaderboardPeriod::Month, '2026-12-31 23:59:59', '2026-12-01 00:00:00', '2027-01-01 00:00:00'],
        ];
    }

    public function test_clock_uses_configured_timezone_instead_of_utc_week_or_month(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $at = CarbonImmutable::parse('2026-10-04 17:00:00', 'UTC');
        [$weekStart] = app(LeaderboardService::class)->bounds(LeaderboardPeriod::Week, $at);
        $this->assertSame('2026-10-05 00:00:00', $weekStart->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Ho_Chi_Minh', $weekStart->timezoneName);
        [$monthStart] = app(LeaderboardService::class)->bounds(LeaderboardPeriod::Month, CarbonImmutable::parse('2026-10-31 17:00:00', 'UTC'));
        $this->assertSame('2026-11-01 00:00:00', $monthStart->format('Y-m-d H:i:s'));
        $this->assertSame([null, null], app(LeaderboardService::class)->bounds(LeaderboardPeriod::AllTime, $at));
    }

    public function test_default_clock_is_converted_to_configured_timezone(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00:00', 'UTC'));
        [$start] = app(LeaderboardService::class)->bounds(LeaderboardPeriod::Month);
        $this->assertSame('2026-11-01 00:00:00', $start->format('Y-m-d H:i:s'));
    }
}
