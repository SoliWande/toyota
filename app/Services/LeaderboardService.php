<?php

namespace App\Services;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaderboardService
{
    public function bounds(LeaderboardPeriod $period, ?CarbonImmutable $at = null): array
    {
        $at = ($at ?? CarbonImmutable::now(config('app.timezone')))->setTimezone(config('app.timezone'));

        return match ($period) {
            LeaderboardPeriod::Week => [$at->startOfWeek(CarbonImmutable::MONDAY), $at->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
            LeaderboardPeriod::Month => [$at->startOfMonth(), $at->startOfMonth()->addMonth()],
            LeaderboardPeriod::AllTime => [null, null],
        };
    }

    public function dealers(LeaderboardPeriod $period, ?CarbonImmutable $at = null): Builder
    {
        return $this->ranked($this->dealerScores(...$this->bounds($period, $at)), ['id', 'name', 'code', 'score']);
    }

    public function dealersForRange(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $this->ranked($this->dealerScores($start, $end), ['id', 'name', 'code', 'score']);
    }

    public function dealerStanding(int $dealerId, LeaderboardPeriod $period = LeaderboardPeriod::AllTime, ?CarbonImmutable $at = null): ?\stdClass
    {
        // Filter after computing the global window rank, never before it.
        return DB::query()->fromSub($this->dealers($period, $at), 'ranked_dealers')->where('id', $dealerId)->first();
    }

    private function ranked(Builder $scores, array $columns): Builder
    {
        return DB::query()->fromSub($scores, 'scores')->select($columns)
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY score DESC, achieved_at ASC, id ASC) AS `rank`')
            ->orderBy('rank');
    }

    public function dealerScores(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): Builder
    {
        return $this->approvedSubmissions($start, $end)
            ->join('dealers', 'dealers.id', '=', 'submissions.dealer_id')
            ->select(['submissions.dealer_id as id', 'dealers.name', 'dealers.code'])
            ->selectRaw('COUNT(*) AS score, MAX(submissions.submitted_at) AS achieved_at')
            ->groupBy('submissions.dealer_id', 'dealers.name', 'dealers.code');
    }

    private function approvedSubmissions(?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        if (($start === null) !== ($end === null) || ($start !== null && $start >= $end)) {
            throw new InvalidArgumentException('Leaderboard range requires both boundaries and a positive duration.');
        }

        $query = DB::table('customer_submissions as submissions')
            ->where('submissions.status', SubmissionStatus::Approved->value);

        if ($start !== null) {
            // MySQL DATETIME values follow the application timezone used when submitting.
            $timezone = config('app.timezone');
            $query->where('submissions.submitted_at', '>=', $start->setTimezone($timezone)->format('Y-m-d H:i:s'))
                ->where('submissions.submitted_at', '<', $end->setTimezone($timezone)->format('Y-m-d H:i:s'));
        }

        return $query;
    }
}
