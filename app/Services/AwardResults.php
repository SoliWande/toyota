<?php

namespace App\Services;

use App\Enums\AwardWinnerType;
use App\Models\Award;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AwardResults
{
    public function __construct(private LeaderboardService $leaderboard) {}

    public function preview(Award $award): array
    {
        if ($award->published_at !== null) {
            throw ValidationException::withMessages(['award' => 'Kỳ này đã công bố; vui lòng xem kết quả snapshot.']);
        }
        [$start, $end] = $this->leaderboard->bounds($award->period_type->leaderboardPeriod(), CarbonImmutable::instance($award->period_start));
        if (! $start->equalTo($award->period_start) || ! $end->equalTo($award->period_end)) {
            throw ValidationException::withMessages(['award' => 'Khoảng thời gian của kỳ không đúng lịch tuần/tháng.']);
        }

        return DB::transaction(function () use ($start, $end) {
            $sales = $this->leaderboard->salesForRange($start, $end)->limit(3)->get()->map(fn ($row) => [
                'winner_type' => AwardWinnerType::Sales->value,
                'sales_id' => (int) $row->id,
                'dealer_id' => null,
                'rank' => (int) $row->rank,
                'score' => (int) $row->score,
                'winner_name_snapshot' => $row->name,
                'sales_dealer_id_snapshot' => (int) $row->dealer_id,
                'dealer_name_snapshot' => $row->dealer_name,
                'dealer_code_snapshot' => $row->dealer_code,
            ]);
            $dealers = $this->leaderboard->dealersForRange($start, $end)->limit(3)->get()->map(fn ($row) => [
                'winner_type' => AwardWinnerType::Dealer->value,
                'sales_id' => null,
                'dealer_id' => (int) $row->id,
                'rank' => (int) $row->rank,
                'score' => (int) $row->score,
                'winner_name_snapshot' => $row->name,
                'sales_dealer_id_snapshot' => null,
                'dealer_name_snapshot' => $row->name,
                'dealer_code_snapshot' => $row->code,
            ]);

            return compact('sales', 'dealers');
        });
    }

    public function fingerprint(Award $award, array $results): string
    {
        return hash('sha256', json_encode([
            $award->id, $award->period_type->value,
            $award->period_start->format('Y-m-d H:i:s'), $award->period_end->format('Y-m-d H:i:s'),
            $results['sales']->all(), $results['dealers']->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
