<?php

namespace App\Actions\CallAudit;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Models\Award;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\CallAudit\FreezeBlocked;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\FreezeResult;
use App\Services\CallAudit\ManifestCodec;
use App\Services\CallAudit\ReproductionVerifier;
use App\Services\CallAudit\SamplingPlanner;
use App\Services\CallAudit\SourceFingerprint;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class CreateAndFreezeBatch
{
    public const POLICY = 'review_complete_asof_freeze_v1';

    public function __construct(
        private SamplingPlanner $planner,
        private SourceFingerprint $fingerprints,
        private ManifestCodec $codec,
        private LeaderboardService $leaderboard,
        private FreezeClock $clock,
    ) {}

    /** Actor is the authenticated server session; no client cutoff/closed flag/seed/generation. */
    public function execute(string $purpose, CarbonImmutable $month, string $requestKey, ?int $awardId = null, bool $supplemental = false): FreezeResult
    {
        $actorId = Auth::id();
        if (! $actorId) {
            return FreezeResult::blocked('admin_required');
        }
        if (! in_array($purpose, [CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD], true)
            || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $requestKey)
            || ($purpose === CallAuditBatch::PURPOSE_GENERAL && $awardId !== null)
            || ($purpose === CallAuditBatch::PURPOSE_AWARD && ($awardId === null || $awardId < 1))) {
            return FreezeResult::blocked('invalid_request');
        }
        $db = DB::connection();
        if ($db->transactionLevel() !== 0) {
            return FreezeResult::blocked('requires_top_level_transaction');
        }
        $isolation = $db->selectOne('SELECT @@transaction_isolation AS level')->level;
        if (! in_array(strtoupper($isolation), ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
            return FreezeResult::blocked('unsupported_snapshot_isolation');
        }
        [$start, $end] = $this->leaderboard->bounds(LeaderboardPeriod::Month, $month);
        $intent = ['purpose' => $purpose, 'monthly_scope_key' => $start->format('Y-m'),
            'period_start' => $start->format('Y-m-d H:i:s'), 'period_end' => $end->format('Y-m-d H:i:s'),
            'award_id' => $awardId, 'generation' => 1, 'policy' => self::POLICY];
        if ($supplemental) {
            $intent['supplemental_policy'] = 'once_per_dealer_max64_generations_v1';
        }
        $payloadDigest = hash_hmac('sha256', 'freeze-intent-v1|'.$this->codec->encode($intent), config('app.key'));

        try {
            return $db->transaction(function () use ($purpose, $month, $requestKey, $awardId, $actorId, $start, $end, $intent, $payloadDigest, $supplemental) {
                // All reads before the source locks are current reads, never an old consistent snapshot.
                if (! DB::table('submission_intake_mutex')->where('id', 1)->lockForUpdate()->first()) {
                    throw new FreezeBlocked('intake_mutex_missing');
                }
                $rows = CustomerSubmission::where('submitted_at', '>=', $intent['period_start'])
                    ->where('submitted_at', '<', $intent['period_end'])->orderBy('id')->lockForUpdate()->get(SourceFingerprint::FIELDS);
                $users = User::whereIn('id', array_unique([$actorId, ...$rows->pluck('sales_id')->filter()->all()]))
                    ->orderBy('id')->lockForUpdate()->get(['id', 'role', 'status', 'name', 'dealer_id']);
                $actor = $users->firstWhere('id', $actorId);
                if ($actor === null || Gate::forUser($actor)->denies('create', CallAuditBatch::class)) {
                    throw new FreezeBlocked('admin_inactive_or_forbidden');
                }
                Dealer::whereIn('id', array_unique([...$rows->pluck('dealer_id')->all(), ...$users->pluck('dealer_id')->filter()->all()]))
                    ->orderBy('id')->lockForUpdate()->get(['id', 'name', 'code']);
                $award = $awardId === null ? null : Award::whereKey($awardId)->lockForUpdate()->first();
                $receipt = CallAuditBatch::where('created_by', $actorId)->where('create_request_key', $requestKey)->lockForUpdate()->first();
                if ($receipt) {
                    if ($receipt->digest_key_id !== $this->fingerprints->keyId()) {
                        throw new FreezeBlocked('digest_key_unavailable');
                    }
                    if (! hash_equals($receipt->create_payload_digest, $payloadDigest)) {
                        throw new FreezeBlocked('idempotency_payload_conflict');
                    }

                    return $this->result($receipt, false);
                }
                $previous = CallAuditBatch::where('purpose', $purpose)->where('monthly_scope_key', $intent['monthly_scope_key'])
                    ->orderBy('generation')->lockForUpdate()->get();
                $covered = [];
                if ($supplemental) {
                    if ($purpose !== CallAuditBatch::PURPOSE_AWARD || $previous->isEmpty() || $award === null || $award->published_at !== null) {
                        throw new FreezeBlocked('supplement_requires_existing_draft_audit');
                    }
                    if ($previous->count() >= 64) {
                        throw new FreezeBlocked('supplement_generation_limit');
                    }
                    foreach ($previous as $prior) {
                        if ((int) $prior->award_id !== $awardId || app(ReproductionVerifier::class)->verify($prior->id)->summary['status'] !== 'VERIFIED') {
                            throw new FreezeBlocked('prior_sampling_integrity_conflict');
                        }
                        foreach ($prior->strata_snapshot_encrypted as $stratum) {
                            $covered[] = (int) $stratum['dealer_id'];
                        }
                    }
                    $intent['generation'] = (int) $previous->last()->generation + 1;
                } elseif ($previous->isNotEmpty()) {
                    throw new FreezeBlocked('generation_already_frozen');
                }
                $asOf = $this->clock->now();
                if ($asOf < $end) {
                    throw new FreezeBlocked('month_not_ended');
                }
                if ($rows->contains(fn ($row) => $row->status === SubmissionStatus::Pending)) {
                    throw new FreezeBlocked('pending_review_in_month');
                }
                $sourceState = $this->codec->digest($rows->map(fn ($row) => $row->getAttributes())->all());
                foreach ($rows as $row) {
                    if ($row->reviewed_at === null || $row->reviewed_at > $asOf) {
                        throw new FreezeBlocked('review_timestamp_outside_cutoff');
                    }
                }
                if ($awardId !== null && ($award === null || ! $award->period_start->equalTo($start) || ! $award->period_end->equalTo($end))) {
                    throw new FreezeBlocked('award_month_mismatch');
                }
                $plan = $award === null ? $this->planner->general($actor, $month) : $this->planner->awardTop3($actor, $award, null, $intent['generation'], $covered);
                if ($supplemental && ($plan->summary['requested_count'] ?? 0) === 0) {
                    throw new FreezeBlocked('no_new_dealer_requires_sampling');
                }
                if ($plan->summary['status'] !== 'READY') {
                    throw new FreezeBlocked('sampling_blocked');
                }
                $manifest = $plan->manifest();
                $selected = $plan->selected();
                $currentRows = CustomerSubmission::where('submitted_at', '>=', $intent['period_start'])
                    ->where('submitted_at', '<', $intent['period_end'])->orderBy('id')->lockForUpdate()->get(SourceFingerprint::FIELDS);
                if (! hash_equals($sourceState, $this->codec->digest($currentRows->map(fn ($row) => $row->getAttributes())->all()))) {
                    throw new FreezeBlocked('source_changed_during_freeze');
                }
                // Recheck using current reads. Source changes inside the operation must abort, not reroll.
                $snapshots = [];
                foreach ($selected as $candidate) {
                    $source = CustomerSubmission::whereKey($candidate['submission_id'])->lockForUpdate()->first(SourceFingerprint::FIELDS);
                    $source?->load(['dealer:id,name,code']);
                    if ($source === null || ! $this->fingerprints->matches($candidate['source_fingerprint'], $source)) {
                        throw new FreezeBlocked('source_changed_during_freeze');
                    }
                    $snapshots[$source->id] = ['source' => array_intersect_key($source->getAttributes(), array_flip(SourceFingerprint::FIELDS)),
                        'dealer' => ['id' => (int) $source->dealer->id, 'name' => $source->dealer->name, 'code' => $source->dealer->code]];
                }
                $proof = ['policy' => self::POLICY, 'scope' => $intent['monthly_scope_key'], 'pending_count' => 0,
                    'scope_row_count' => $rows->count(), 'cutoff' => $asOf->format('Y-m-d H:i:s.u'),
                    'actor_id' => (int) $actorId, 'intake_mutex_id' => 1, 'permanent_period_close' => false,
                    'review_state_digest' => hash_hmac('sha256', $this->codec->encode($rows->map(fn ($row) => [
                        'id' => (int) $row->id, 'status' => $row->status->value, 'reviewed_at' => $row->getRawOriginal('reviewed_at'),
                    ])->all()), config('app.key'))];
                $frozen = $this->clock->now();
                if ($frozen < $asOf) {
                    throw new FreezeBlocked('server_clock_moved_backwards');
                }
                if ($supplemental) {
                    $manifest['supplemental_evidence'] = ['policy' => 'once_per_dealer_max64_generations_v1',
                        'predecessor_batch_id' => (int) $previous->last()->id, 'covered_dealer_ids' => array_values(array_unique($covered)),
                        'maximum_generation' => 64];
                }
                $manifest['freeze_evidence'] = $proof;
                $manifest['frozen_at'] = $frozen->format('Y-m-d H:i:s.u');
                $batch = new CallAuditBatch;
                $batch->forceFill(array_diff_key($intent, ['policy' => true, 'supplemental_policy' => true]) + [
                    'predecessor_batch_id' => $supplemental ? $previous->last()->id : null,
                    'period_type' => 'monthly', 'calendar_zone' => config('app.timezone'), 'calendar_version' => $manifest['context']['calendar_version'],
                    'approval_cutoff_at' => $asOf, 'cutoff_policy_version' => self::POLICY,
                    'eligibility_as_of' => $asOf, 'frozen_at' => $frozen, 'review_completion_evidence_encrypted' => $proof,
                    'created_by' => $actorId, 'frozen_by' => $actorId, 'policy_version' => self::POLICY,
                    'manifest_version' => $manifest['manifest_version'], 'algorithm_version' => $manifest['algorithm_version'],
                    'fingerprint_version' => SourceFingerprint::VERSION, 'eligible_count' => $plan->summary['eligible_count'],
                    'requested_count' => $plan->summary['requested_count'], 'actual_count' => $plan->summary['actual_count'],
                    'target_cap' => $plan->summary['target_cap'], 'strata_snapshot_encrypted' => $plan->summary['strata'],
                    'award_baseline_encrypted' => $manifest['award_baseline'],
                    'award_baseline_fingerprint' => $manifest['award_baseline']['fingerprint'] ?? null,
                    'manifest_encrypted' => $manifest, 'manifest_digest' => $this->codec->digest($manifest),
                    'selection_digest' => $manifest['selection_digest'], 'seed_encrypted' => $plan->seed(),
                    'encryption_key_id' => $this->fingerprints->keyId(), 'encryption_version' => 'laravel_encrypted_cast_v1',
                    'digest_key_id' => $this->fingerprints->keyId(), 'demo_policy_snapshot' => $manifest['context']['demo_policy'],
                    'lifecycle_state' => 'frozen', 'row_version' => 1, 'create_request_key' => $requestKey,
                    'create_payload_digest' => $payloadDigest, 'created_at' => $asOf, 'updated_at' => $frozen,
                ]);
                $batch->save();
                foreach ($selected as $order => $candidate) {
                    $snapshot = $snapshots[$candidate['submission_id']];
                    (new CallAuditItem)->forceFill([
                        'batch_id' => $batch->id, 'submission_id' => $candidate['submission_id'], 'selection_order' => $order + 1,
                        'stratum_key' => $award === null ? 'all' : 'dealer:'.$candidate['dealer_id'],
                        'dealer_id_snapshot' => $candidate['dealer_id'], 'sales_id_snapshot' => $candidate['sales_id'],
                        'dealer_snapshot_encrypted' => $snapshot['dealer'], 'source_snapshot_encrypted' => $snapshot['source'],
                        'source_fingerprint' => $candidate['source_fingerprint'], 'fingerprint_version' => SourceFingerprint::VERSION,
                        'source_observed_at' => $asOf, 'queue_state' => 'uncalled', 'attempt_count' => 0, 'row_version' => 1,
                        'created_at' => $frozen, 'updated_at' => $frozen,
                    ])->save();
                }
                $eventData = ['batch_id' => (int) $batch->id, 'actor_id' => (int) $actorId, 'purpose' => $purpose,
                    'actual_count' => count($selected), 'manifest_digest' => $batch->manifest_digest];
                (new CallAuditEvent)->forceFill([
                    'batch_id' => $batch->id, 'event_sequence' => 1, 'actor_id' => $actorId, 'occurred_at' => $frozen,
                    'event_type' => 'freeze', 'event_schema_version' => 'call_audit_freeze_event_v1', 'command_name' => 'create_and_freeze',
                    'command_key' => $requestKey, 'event_ordinal' => 0, 'before_version' => null, 'after_version' => 1,
                    'reason_code' => self::POLICY, 'correlation_id' => bin2hex(random_bytes(16)), 'metadata' => $eventData,
                    'event_digest' => hash_hmac('sha256', 'freeze-event-v1|'.$this->codec->encode($eventData), config('app.key')),
                    'digest_key_id' => $this->fingerprints->keyId(),
                ])->save();

                return $this->result($batch, true);
            }, 3);
        } catch (FreezeBlocked $blocked) {
            return FreezeResult::blocked($blocked->reason);
        } catch (Throwable) {
            // Never put SQL, decrypted source material, seed or manifest in response/logs.
            return FreezeResult::blocked('freeze_failed');
        }
    }

    private function result(CallAuditBatch $batch, bool $created): FreezeResult
    {
        return new FreezeResult(['status' => 'FROZEN', 'batch_id' => (int) $batch->id, 'created' => $created,
            'purpose' => $batch->purpose, 'monthly_scope_key' => $batch->monthly_scope_key,
            'requested_count' => $batch->requested_count, 'actual_count' => $batch->actual_count]);
    }
}
