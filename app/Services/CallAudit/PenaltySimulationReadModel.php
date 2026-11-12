<?php

namespace App\Services\CallAudit;

use App\Enums\LeaderboardPeriod;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PenaltySimulationReadModel
{
    public function __construct(private ImpactPreviewReadModel $impact, private LeaderboardService $leaderboard, private SimulationRanking $ranking) {}

    public function read(User $actor, string $month): array
    {
        return DB::transaction(function () use ($actor, $month) {
            // P09P verifies fresh authorization and every included E1/E2 evidence in this same snapshot.
            $impact = $this->impact->read($actor, ['month' => $month]);
            [$start, $end] = $this->leaderboard->bounds(LeaderboardPeriod::Month,
                CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone')));
            $dealers = $this->baseline($this->leaderboard->dealerScores($start, $end));
            $sales = $this->baseline($this->leaderboard->salesScores($start, $end), true);
            $dealerDeductions = [];
            $salesDeductions = [];
            $candidates = [];
            $seen = [];
            $unsupported = [];
            foreach ($impact['data'] as $candidate) {
                $id = $candidate['submission_id'];
                if (isset($seen[$id]) || $candidate['evidence'] === []) {
                    throw new ReadUnavailable('Invalid simulation candidate identity.');
                }
                $seen[$id] = true;
                $frozen = array_column($candidate['evidence'], 'frozen_attribution');
                $dealerIds = array_values(array_unique(array_column($frozen, 'dealer_id'), SORT_REGULAR));
                if (count($dealerIds) !== 1) {
                    throw new ReadUnavailable('Simulation dealer attribution conflict.');
                }
                $dealerId = $dealerIds[0];
                $dealerDeductions[$dealerId] = ($dealerDeductions[$dealerId] ?? 0) + 1;
                $salesIds = array_values(array_unique(array_column($frozen, 'sales_id'), SORT_REGULAR));
                $salesId = null;
                if (count($salesIds) !== 1 || $salesIds[0] !== $candidate['current_submission']['sales_id']) {
                    $status = 'not_simulated_attribution_changed';
                    $unsupported[] = $id;
                } elseif ($salesIds[0] === null) {
                    $status = 'no_sales_attribution';
                } elseif (! $candidate['live_contribution']['sales']) {
                    $status = 'not_simulated_no_live_contribution';
                    $unsupported[] = $id;
                } else {
                    $salesId = $salesIds[0];
                    $status = 'simulated_live_source_contribution';
                    $salesDeductions[$salesId] = ($salesDeductions[$salesId] ?? 0) + 1;
                }
                $candidates[] = ['submission_id' => $id, 'dealer_id' => $dealerId, 'dealer_deduction' => 1,
                    'sales_id' => $salesId, 'sales_deduction' => $salesId === null ? null : 1, 'sales_effect_status' => $status,
                    'evidence' => array_map(fn ($row) => ['batch_id' => $row['batch_id'], 'item_id' => $row['item_id'],
                        'purpose' => $row['purpose'], 'attempt_id' => $row['attempt_id'], 'resolution_version' => $row['resolution_version']], $candidate['evidence'])];
            }
            $dealerComparison = $this->ranking->compare($dealers, $dealerDeductions);
            $salesComparison = $this->ranking->compare($sales, $salesDeductions);
            $salesComparison['complete'] = $unsupported === [];
            $salesComparison['not_simulated_submission_ids'] = $unsupported;
            $awards = array_map(fn ($award) => ['id' => $award['id'], 'status' => $award['status'],
                'result_source' => $award['result_source'], 'published_at' => $award['published_at'],
                'comparison_basis' => $award['status'] === 'published' ? 'published_snapshot_vs_simulated_live' : 'draft_live_vs_simulated_live',
                'reference_records' => $award['records'],
                'simulated_live_top3' => ['dealers' => $dealerComparison['top3_after'], 'sales' => $salesComparison['top3_after']],
                'sales_simulation_complete' => $salesComparison['complete']], $impact['awards']);

            return ['mode' => 'SIMULATION_ONLY', 'policy_version' => 'confirmed_minus_one_simulation_v1',
                'assumptions' => ['points_per_submission' => 1, 'dealer_attribution' => 'unique_frozen_submission_dealer',
                    'negative_scores' => 'allowed', 'zero_negative_entities' => 'retained_in_simulated_ranking',
                    'missing_live_dealer_baseline' => 'arithmetic_zero_without_live_rank',
                    'tie_break' => 'score_desc_achieved_at_asc_id_asc', 'achieved_at' => 'unchanged_live_timestamp_null_if_absent',
                    'sales' => 'consistent_live_source_contribution_only'],
                'scope' => $impact['scope'], 'counts' => $impact['counts'], 'candidates' => $candidates,
                'dealers' => $dealerComparison, 'sales' => $salesComparison, 'awards' => $awards];
        });
    }

    private function baseline($query, bool $sales = false): array
    {
        return DB::query()->fromSub($query, 'simulation_scores')
            ->get($sales ? ['id', 'score', 'achieved_at', 'dealer_id'] : ['id', 'score', 'achieved_at'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'score' => (int) $row->score, 'achieved_at' => $row->achieved_at]
                + ($sales ? ['dealer_id' => (int) $row->dealer_id] : []))->all();
    }
}
