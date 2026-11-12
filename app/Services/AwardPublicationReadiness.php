<?php

namespace App\Services;

use App\Enums\AwardPeriod;
use App\Enums\SubmissionStatus;
use App\Models\Award;
use App\Models\CallAuditBatch;
use App\Models\CustomerSubmission;
use App\Models\DealerPenaltyEntry;
use App\Models\User;
use App\Services\CallAudit\CohortBuilder;
use App\Services\CallAudit\FindingEvidence;
use App\Services\CallAudit\FindingReadModel;
use App\Services\CallAudit\FindingResolutionHistory;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ImpactPreviewReadModel;
use App\Services\CallAudit\ReadUnavailable;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\CallAudit\SourceFingerprint;
use App\Services\CallAudit\WorkflowReadModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AwardPublicationReadiness
{
    public const POLICY = 'monthly_snapshot_final_top3_audited_v1';

    public function __construct(private AwardResults $results, private FreezeClock $clock,
        private WorkflowReadModel $workflow, private ImpactPreviewReadModel $impact) {}

    public function read(User $actor, int $awardId): array
    {
        return DB::transaction(function () use ($actor, $awardId) {
            $actor = User::select(['id', 'role', 'status'])->findOrFail($actor->id);
            $award = Award::findOrFail($awardId);
            Gate::forUser($actor)->authorize('view', $award);
            if ($award->published_at !== null) {
                return ['award_id' => $awardId, 'status' => 'published', 'can_publish' => false, 'blockers' => ['already_published']];
            }
            if ($award->period_type !== AwardPeriod::Monthly) {
                return ['award_id' => $awardId, 'status' => 'draft', 'can_publish' => false, 'blockers' => ['weekly_publication_contract_missing']];
            }
            $preview = $this->results->preview($award);
            $month = $award->period_start->format('Y-m');
            $now = $this->clock->now();
            $blockers = [];
            if ($now < $award->period_end) {
                $blockers[] = 'period_not_ended';
            }
            $rows = CustomerSubmission::where('submitted_at', '>=', $award->period_start)->where('submitted_at', '<', $award->period_end)->get(['id', 'status', 'reviewed_at']);
            $pending = $rows->where('status', SubmissionStatus::Pending)->count();
            if ($pending) {
                $blockers[] = 'pending_review_in_month';
            }
            if ($rows->contains(fn ($row) => $row->reviewed_at === null || $row->reviewed_at > $now)) {
                $blockers[] = 'review_timestamp_outside_cutoff';
            }
            if (app(CohortBuilder::class)->build($award->period_start)['status'] !== 'READY') {
                $blockers[] = 'monthly_cohort_conflict';
            }
            $batches = CallAuditBatch::where('monthly_scope_key', $month)->where('lifecycle_state', 'frozen')
                ->where(fn ($q) => $q->where('purpose', 'general_audit')->orWhere('award_id', $awardId))->orderBy('id')->get();
            $audit = [];
            $coverage = [];
            $unresolved = 0;
            $covered = [];
            foreach ($batches as $batch) {
                if (! $batch->period_start->equalTo($award->period_start) || ! $batch->period_end->equalTo($award->period_end)
                    || app(ReproductionVerifier::class)->verify($batch->id)->summary['status'] !== 'VERIFIED') {
                    throw new ReadUnavailable('Publication sampling evidence unavailable.');
                }
                $state = $this->workflow->read($batch, $now);
                $evidenceItems = $batch->items()->with(['attempts' => fn ($q) => $q->orderBy('attempt_no'),
                    'events' => fn ($q) => $q->where('event_type', 'call_result_recorded')])->get();
                $predecessors = $batch->events()->pluck('event_digest', 'event_sequence')->all();
                foreach ($evidenceItems as $evidenceItem) {
                    foreach ($evidenceItem->attempts as $attempt) {
                        $event = $evidenceItem->events->firstWhere('attempt_id', $attempt->id);
                        app(FindingEvidence::class)->verifyCommit($event, $attempt, $evidenceItem, $predecessors);
                    }
                }

                $findings = app(FindingReadModel::class)->index($actor, $batch->id, ['purpose' => $batch->purpose, 'month' => $month, 'per_page' => 60])['data'];
                foreach ($findings as $finding) {
                    $resolution = app(FindingResolutionHistory::class)->read($batch, $finding, $now)['current'];
                    if (! in_array($resolution['decision'], ['confirmed', 'dismissed'], true)) {
                        $unresolved++;
                    }
                }
                $audit[] = ['id' => (int) $batch->id, 'purpose' => $batch->purpose, 'generation' => $batch->generation,
                    'requested_count' => $batch->requested_count, 'actual_count' => $batch->actual_count, 'progress' => $state['progress']];
                if ($batch->purpose !== 'award_top3') {
                    continue;
                }
                foreach ($batch->strata_snapshot_encrypted as $stratum) {
                    $dealer = (int) $stratum['dealer_id'];
                    if (in_array($dealer, $covered, true)) {
                        throw new ReadUnavailable('Duplicate C07 dealer coverage.');
                    }
                    $covered[] = $dealer;
                    $items = $batch->items()->where('dealer_id_snapshot', $dealer)->get();
                    $complete = $items->count() > 0 && $items->count() === $stratum['actual_count'];
                    foreach ($items as $item) {
                        $status = $state['states'][$item->id];
                        if (in_array($status['state'], ['needs_call', 'recall'], true)) {
                            $complete = false;
                        }
                        $source = CustomerSubmission::find($item->submission_id);
                        $source?->load('dealer:id,name,code');
                        if ($source === null || ! app(SourceFingerprint::class)->matches($item->source_fingerprint, $source)) {
                            $blockers[] = 'audited_source_changed';
                        }
                    }
                    $coverage[$dealer] = ['dealer_id' => $dealer, 'batch_id' => (int) $batch->id, 'generation' => $batch->generation,
                        'requested_count' => $stratum['requested_count'], 'actual_count' => $items->count(), 'complete' => $complete];
                }
            }
            if ($unresolved) {
                $blockers[] = 'findings_unresolved';
            }
            $impact = $this->impact->read($actor, ['month' => $month]);
            $unapplied = 0;
            foreach ($impact['data'] as $candidate) {
                if (count(array_unique(array_column(array_column($candidate['evidence'], 'frozen_attribution'), 'dealer_id'))) !== 1) {
                    throw new ReadUnavailable('Conflicting historical Dealer attribution.');
                }
                if (! DealerPenaltyEntry::where('submission_id', $candidate['submission_id'])->where('monthly_scope_key', $month)->exists()) {
                    $unapplied++;
                }
            }
            if ($unapplied) {
                $blockers[] = 'confirmed_penalties_outstanding';
            }
            $missing = [];
            $top = collect($preview['dealers'])->map(fn ($row) => ['dealer_id' => $row['dealer_id'], 'rank' => $row['rank'], 'score' => $row['score']])->all();
            if (count($top) !== 3) {
                $blockers[] = 'final_top3_incomplete';
            }
            foreach ($top as $row) {
                $c = $coverage[$row['dealer_id']] ?? null;
                if ($c === null) {
                    $missing[] = $row['dealer_id'];
                    $blockers[] = 'final_top3_requires_sampling';
                } elseif (! $c['complete']) {
                    $blockers[] = $c['actual_count'] === 0 ? 'award_audit_empty' : 'award_calls_outstanding';
                }
            }
            $blockers = array_values(array_unique($blockers));

            return ['award_id' => $awardId, 'status' => 'draft', 'can_publish' => $blockers === [], 'blockers' => $blockers,
                'preview_fingerprint' => $this->results->fingerprint($award, $preview), 'dealers' => $top,
                'audit_batches' => $audit, 'dealer_coverage' => array_values($coverage), 'supplemental_dealer_ids' => $missing,
                'impact_counts' => $impact['counts'], 'pending_submission_count' => $pending,
                'unresolved_finding_count' => $unresolved, 'unapplied_confirmed_submission_count' => $unapplied];
        });
    }
}
