<?php

namespace App\Services\CallAudit;

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ImpactPreviewReadModel
{
    public function __construct(private FindingReadModel $findings, private FindingResolutionHistory $resolutions,
        private LeaderboardService $leaderboard, private AwardResults $awards, private FreezeClock $clock) {}

    public function read(User $actor, array $filters, ?int $batchId = null, ?int $itemId = null): array
    {
        return DB::transaction(function () use ($actor, $filters, $batchId, $itemId) {
            $current = User::select(['id', 'role', 'status'])->find($actor->id);
            if ($current === null || Gate::forUser($current)->denies('viewAny', CallAuditBatch::class)) {
                throw new AuthorizationException;
            }
            [$start, $end] = $this->leaderboard->bounds(LeaderboardPeriod::Month,
                CarbonImmutable::createFromFormat('!Y-m', $filters['month'], config('app.timezone')));
            $now = $this->clock->now();
            $batches = CallAuditBatch::where('monthly_scope_key', $filters['month'])
                ->whereIn('purpose', ['general_audit', 'award_top3'])->where('lifecycle_state', 'frozen')->whereNotNull('frozen_at')
                ->when($filters['purpose'] ?? null, fn ($q, $purpose) => $q->where('purpose', $purpose))
                ->when($batchId, fn ($q) => $q->whereKey($batchId))->orderBy('id')->get([
                    'id', 'purpose', 'monthly_scope_key', 'period_type', 'period_start', 'period_end', 'award_id',
                ]);
            abort_if($batchId !== null && $batches->isEmpty(), 404);
            $candidates = [];
            foreach ($batches as $batch) {
                Gate::forUser($current)->authorize('view', $batch);
                if ($batch->period_type !== 'monthly' || ! $batch->period_start->equalTo($start) || ! $batch->period_end->equalTo($end)) {
                    throw new ReadUnavailable('Impact batch calendar mismatch.');
                }
                $scope = ['purpose' => $batch->purpose, 'month' => $filters['month']];
                if ($itemId !== null) {
                    $rows = [$this->findings->show($current, $batch->id, $itemId, $scope)['data']];
                } else {
                    $rows = [];
                    $page = 1;
                    do {
                        $result = $this->findings->index($current, $batch->id, $scope + ['page' => $page, 'per_page' => 50]);
                        array_push($rows, ...$result['data']);
                        $page++;
                    } while ($page <= $result['meta']['last_page']);
                    $targets = $batch->events()->where(fn ($q) => $q->where('event_type', FindingResolutionPolicy::EVENT)
                        ->orWhere('command_name', FindingResolutionPolicy::COMMAND)->orWhere('event_schema_version', FindingResolutionPolicy::SCHEMA))
                        ->pluck('item_id');
                    if ($targets->contains(fn ($id) => ! in_array((int) $id, array_column($rows, 'item_id'), true))) {
                        throw new ReadUnavailable('Resolution without trusted finding.');
                    }
                }
                $items = $batch->items()->whereIn('id', array_column($rows, 'item_id'))
                    ->get(['id', 'submission_id', 'dealer_id_snapshot', 'sales_id_snapshot', 'source_snapshot_encrypted'])->keyBy('id');
                foreach ($rows as $finding) {
                    $state = $this->resolutions->read($batch, $finding, $now)['current'];
                    if ($state['decision'] !== 'confirmed') {
                        continue;
                    }
                    $item = $items->get($finding['item_id']);
                    $snapshot = $item->source_snapshot_encrypted;
                    $submitted = $snapshot['submitted_at'] ?? null;
                    if (! is_string($submitted) || $submitted < $start->format('Y-m-d H:i:s') || $submitted >= $end->format('Y-m-d H:i:s')
                        || (int) ($snapshot['dealer_id'] ?? 0) !== (int) $item->dealer_id_snapshot
                        || ($snapshot['sales_id'] === null ? null : (int) $snapshot['sales_id']) !== ($item->sales_id_snapshot === null ? null : (int) $item->sales_id_snapshot)) {
                        throw new ReadUnavailable('Impact attribution or month mismatch.');
                    }
                    $id = (int) $item->submission_id;
                    $candidates[$id] ??= ['submission_id' => $id, 'evidence' => []];
                    $candidates[$id]['evidence'][] = [
                        'batch_id' => (int) $batch->id, 'item_id' => $finding['item_id'], 'purpose' => $batch->purpose,
                        'award_id' => $batch->award_id === null ? null : (int) $batch->award_id,
                        'attempt_id' => $finding['attempt_id'], 'call_event_id' => $finding['event_id'],
                        'resolution_version' => $state['resolution_version'], 'confirmed_by' => $state['actor_id'], 'confirmed_at' => $state['recorded_at'],
                        'frozen_attribution' => ['dealer_id' => (int) $item->dealer_id_snapshot,
                            'sales_id' => $item->sales_id_snapshot === null ? null : (int) $item->sales_id_snapshot, 'submitted_at' => $submitted],
                    ];
                }
            }
            abort_if($itemId !== null && $candidates === [], 404);
            ksort($candidates, SORT_NUMERIC);
            foreach ($candidates as &$candidate) {
                $candidate = $this->sourceContext($candidate, $start, $end);
            }
            unset($candidate);
            $dealerIds = $this->identities($candidates, 'dealer_id');
            $salesIds = $this->identities($candidates, 'sales_id');
            $evidence = array_merge([], ...array_column($candidates, 'evidence'));

            return ['data' => array_values($candidates),
                'scope' => ['month' => $filters['month'], 'purpose' => $filters['purpose'] ?? null,
                    'period_start' => $start->toIso8601String(), 'period_end_exclusive' => $end->toIso8601String(),
                    'observed_at' => $now->toIso8601String(), 'award_scope' => 'monthly_exact_bounds'],
                'counts' => ['candidate_count' => count($candidates), 'confirmed_evidence_count' => count($evidence),
                    'general_audit_evidence_count' => count(array_filter($evidence, fn ($row) => $row['purpose'] === 'general_audit')),
                    'award_top3_evidence_count' => count(array_filter($evidence, fn ($row) => $row['purpose'] === 'award_top3'))],
                'current_rankings' => [
                    'dealers' => $this->rankings($this->leaderboard->dealersForRange($start, $end), $dealerIds),
                    'sales' => $this->rankings($this->leaderboard->salesForRange($start, $end), $salesIds, true),
                ],
                'awards' => $this->awardContext($candidates, $start, $end)];
        });
    }

    private function sourceContext(array $candidate, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $source = DB::table('customer_submissions')->where('id', $candidate['submission_id'])
            ->first(['id', 'status', 'source', 'dealer_id', 'sales_id', 'submitted_at']);
        if ($source === null) {
            throw new ReadUnavailable('Impact source unavailable.');
        }
        $candidate['current_submission'] = ['status' => $source->status, 'source' => $source->source,
            'dealer_id' => (int) $source->dealer_id, 'sales_id' => $source->sales_id === null ? null : (int) $source->sales_id,
            'submitted_at' => $source->submitted_at,
            'in_month' => $source->submitted_at >= $start->format('Y-m-d H:i:s') && $source->submitted_at < $end->format('Y-m-d H:i:s')];
        // Ask the existing score builders about this source, preserving role/demo/boundary rules.
        $candidate['live_contribution'] = [
            'dealer' => $this->leaderboard->dealerScores($start, $end)->where('submissions.id', $source->id)->exists(),
            'sales' => $this->leaderboard->salesScores($start, $end)->where('submissions.id', $source->id)->exists(),
        ];
        foreach ($candidate['evidence'] as &$evidence) {
            $frozen = $evidence['frozen_attribution'];
            $evidence['comparison'] = ['dealer_changed' => $frozen['dealer_id'] !== (int) $source->dealer_id,
                'sales_changed' => $frozen['sales_id'] !== ($source->sales_id === null ? null : (int) $source->sales_id),
                'submitted_at_changed' => $frozen['submitted_at'] !== $source->submitted_at];
        }
        unset($evidence);

        return $candidate;
    }

    private function identities(array $candidates, string $field): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            $ids[] = $candidate['current_submission'][$field];
            foreach ($candidate['evidence'] as $evidence) {
                $ids[] = $evidence['frozen_attribution'][$field];
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));
    }

    private function rankings($query, array $ids, bool $sales = false): array
    {
        // Filter OUTSIDE the window query, otherwise each requested entity would rank first.
        $rows = DB::query()->fromSub($query, 'impact_ranked')->whereIn('id', $ids)
            ->get($sales ? ['id', 'score', 'rank', 'dealer_id'] : ['id', 'score', 'rank'])->keyBy('id');

        return array_map(fn ($id) => ['id' => $id, 'score' => isset($rows[$id]) ? (int) $rows[$id]->score : null,
            'rank' => isset($rows[$id]) ? (int) $rows[$id]->rank : null]
            + ($sales ? ['dealer_id' => isset($rows[$id]) ? (int) $rows[$id]->dealer_id : null] : []), $ids);
    }

    private function awardContext(array $candidates, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Award::where('period_type', 'monthly')->where('period_start', $start->format('Y-m-d H:i:s'))
            ->where('period_end', $end->format('Y-m-d H:i:s'))->orderBy('id')
            ->get(['id', 'period_type', 'period_start', 'period_end', 'published_at']);

        return $rows->map(function ($award) use ($candidates) {
            $published = $award->published_at !== null;
            if ($published) {
                $results = $award->winners()->orderBy('winner_type')->orderBy('rank')->orderBy('id')
                    ->get(['id', 'winner_type', 'sales_id', 'dealer_id', 'rank', 'score', 'sales_dealer_id_snapshot'])
                    ->map(fn ($winner) => ['record_id' => (int) $winner->id, 'winner_type' => $winner->winner_type->value,
                        'sales_id' => $winner->sales_id === null ? null : (int) $winner->sales_id,
                        'dealer_id' => $winner->dealer_id === null ? null : (int) $winner->dealer_id,
                        'sales_dealer_id_snapshot' => $winner->sales_dealer_id_snapshot, 'rank' => $winner->rank, 'score' => $winner->score])->all();
            } else {
                $preview = $this->awards->preview($award);
                $results = collect($preview['dealers'])->merge($preview['sales'])->map(fn ($row) => [
                    'record_id' => null, 'winner_type' => $row['winner_type'], 'sales_id' => $row['sales_id'], 'dealer_id' => $row['dealer_id'],
                    'sales_dealer_id_snapshot' => $row['sales_dealer_id_snapshot'], 'rank' => $row['rank'], 'score' => $row['score'],
                ])->all();
            }
            foreach ($results as &$result) {
                $field = $result['winner_type'] === 'dealer' ? 'dealer_id' : 'sales_id';
                $result['related_submission_ids'] = array_values(array_map(fn ($row) => $row['submission_id'],
                    array_filter($candidates, fn ($row) => in_array($result[$field], $this->identities([$row], $field), true))));
            }
            unset($result);

            return ['id' => (int) $award->id, 'status' => $published ? 'published' : 'draft',
                'result_source' => $published ? 'published_snapshot' : 'live_preview',
                'published_at' => $award->published_at?->toIso8601String(), 'records' => $results];
        })->all();
    }
}
