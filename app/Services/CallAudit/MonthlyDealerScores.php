<?php

namespace App\Services\CallAudit;

use App\Models\DealerPenaltyEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class MonthlyDealerScores
{
    public function __construct(private DealerPenaltyIntegrity $integrity) {}

    public function scores(Builder $raw, CarbonImmutable $start): Builder
    {
        $month = $start->format('Y-m');
        foreach (DealerPenaltyEntry::where('monthly_scope_key', $month)->get() as $entry) {
            $this->integrity->verify($entry);
        }
        $penalties = DB::table('dealer_penalty_entries')->where('monthly_scope_key', $month)
            ->select('dealer_id')->selectRaw('SUM(points) AS penalty_points')->groupBy('dealer_id');
        $ids = DB::query()->fromSub(clone $raw, 'raw_ids')->select('id')
            ->union(DB::table('dealer_penalty_entries')->where('monthly_scope_key', $month)->select('dealer_id as id'));
        $net = '(CAST(COALESCE(raw_scores.score,0) AS SIGNED)-CAST(COALESCE(penalties.penalty_points,0) AS SIGNED))';

        return DB::query()->fromSub($ids, 'subjects')->join('dealers', 'dealers.id', '=', 'subjects.id')
            ->leftJoinSub($raw, 'raw_scores', 'raw_scores.id', '=', 'subjects.id')
            ->leftJoinSub($penalties, 'penalties', 'penalties.dealer_id', '=', 'subjects.id')
            ->select(['dealers.id', 'dealers.name', 'dealers.code', 'raw_scores.achieved_at'])
            ->selectRaw('COALESCE(raw_scores.score,0) AS raw_score, COALESCE(penalties.penalty_points,0) AS penalty_points')
            ->selectRaw($net.' AS net_score, GREATEST(0,'.$net.') AS display_score, GREATEST(0,'.$net.') AS score');
    }
}
