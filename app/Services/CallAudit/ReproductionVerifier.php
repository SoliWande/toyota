<?php

namespace App\Services\CallAudit;

use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Models\CallAuditBatch;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class ReproductionVerifier
{
    public function __construct(private SamplingPlanner $planner, private ManifestCodec $codec, private SourceFingerprint $fingerprints) {}

    public function verify(int $batchId): FreezeResult
    {
        try {
            return DB::transaction(function () use ($batchId) {
                $actor = Auth::id() ? User::select(['id', 'role', 'status'])->find(Auth::id()) : null;
                $batch = CallAuditBatch::find($batchId);
                if ($actor === null || $batch === null || Gate::forUser($actor)->denies('verifyReproduction', $batch)) {
                    return FreezeResult::blocked('verification_forbidden');
                }
                if ($batch->encryption_key_id !== $this->fingerprints->keyId() || $batch->digest_key_id !== $this->fingerprints->keyId()) {
                    return FreezeResult::blocked('verification_key_unavailable');
                }
                $manifest = $batch->manifest_encrypted;
                if (! hash_equals($batch->manifest_digest, $this->codec->digest($manifest))) {
                    throw new FreezeBlocked('manifest_digest_mismatch');
                }
                $selection = $this->planner->reproduce($manifest, $batch->seed_encrypted);
                $items = $batch->items()->orderBy('selection_order')->get();
                $domain = $manifest['domain'];
                $proof = $manifest['freeze_evidence'];
                if ($domain['purpose'] !== $batch->purpose || $domain['monthly_scope_key'] !== $batch->monthly_scope_key
                    || $domain['generation'] !== (int) $batch->generation || $domain['period_start'] !== $batch->period_start->format('Y-m-d H:i:s')
                    || $domain['period_end'] !== $batch->period_end->format('Y-m-d H:i:s')
                    || $manifest['manifest_version'] !== $batch->manifest_version || $manifest['algorithm_version'] !== $batch->algorithm_version
                    || $manifest['counts']['requested_count'] !== $batch->requested_count || $manifest['counts']['actual_count'] !== $batch->actual_count
                    || $manifest['counts']['eligible_count'] !== $batch->eligible_count || $manifest['counts']['target_cap'] !== $batch->target_cap
                    || $manifest['counts']['strata'] !== $batch->strata_snapshot_encrypted
                    || $manifest['award_baseline'] !== $batch->award_baseline_encrypted
                    || ($manifest['award_baseline']['fingerprint'] ?? null) !== $batch->award_baseline_fingerprint
                    || $manifest['freeze_evidence'] !== $batch->review_completion_evidence_encrypted
                    || $this->codec->digest($manifest['context']['demo_policy']) !== $this->codec->digest($batch->demo_policy_snapshot)
                    || $manifest['frozen_at'] !== $batch->frozen_at->format('Y-m-d H:i:s.u')
                    || $proof['cutoff'] !== $batch->approval_cutoff_at->format('Y-m-d H:i:s.u')
                    || ! $batch->eligibility_as_of->equalTo($batch->approval_cutoff_at)
                    || ! $batch->created_at->equalTo($batch->approval_cutoff_at)
                    || $proof['policy'] !== CreateAndFreezeBatch::POLICY || $proof['pending_count'] !== 0
                    || $proof['permanent_period_close'] !== false || $proof['actor_id'] !== (int) $batch->frozen_by
                    || (int) $batch->created_by !== (int) $batch->frozen_by
                    || $batch->policy_version !== CreateAndFreezeBatch::POLICY || $batch->cutoff_policy_version !== CreateAndFreezeBatch::POLICY
                    || $batch->fingerprint_version !== SourceFingerprint::VERSION || $batch->encryption_version !== 'laravel_encrypted_cast_v1'
                    || $manifest['context']['fingerprint_key_id'] !== $batch->digest_key_id
                    || $manifest['context']['fingerprint_version'] !== $batch->fingerprint_version
                    || $manifest['context']['calendar_zone'] !== $batch->calendar_zone || $manifest['context']['calendar_version'] !== $batch->calendar_version
                    || count($selection) !== $batch->actual_count || $items->count() !== count($selection)
                    || ! hash_equals($batch->selection_digest, $this->codec->digest($selection))) {
                    throw new FreezeBlocked('frozen_envelope_mismatch');
                }
                if ($batch->generation > 1) {
                    $supplement = $manifest['supplemental_evidence'] ?? null;
                    $prior = CallAuditBatch::find($batch->predecessor_batch_id);
                    if ($batch->purpose !== CallAuditBatch::PURPOSE_AWARD || $batch->generation > 64
                        || ! is_array($supplement) || ($supplement['policy'] ?? null) !== 'once_per_dealer_max64_generations_v1'
                        || ($supplement['predecessor_batch_id'] ?? null) !== (int) $batch->predecessor_batch_id
                        || ($supplement['maximum_generation'] ?? null) !== 64
                        || $prior === null || $prior->generation !== $batch->generation - 1
                        || $prior->monthly_scope_key !== $batch->monthly_scope_key || (int) $prior->award_id !== (int) $batch->award_id
                        || array_intersect(array_column($manifest['strata'], 'dealer_id'), $supplement['covered_dealer_ids'] ?? [])) {
                        throw new FreezeBlocked('supplemental_history_mismatch');
                    }
                }
                foreach ($selection as $order => $candidate) {
                    $item = $items[$order];
                    if ($item->selection_order !== $order + 1 || (int) $item->submission_id !== $candidate['submission_id']
                        || (int) $item->dealer_id_snapshot !== $candidate['dealer_id']
                        || ($item->sales_id_snapshot === null ? null : (int) $item->sales_id_snapshot) !== $candidate['sales_id']
                        || $item->stratum_key !== ($batch->purpose === CallAuditBatch::PURPOSE_GENERAL ? 'all' : 'dealer:'.$candidate['dealer_id'])
                        || $item->source_fingerprint !== $candidate['source_fingerprint'] || $item->fingerprint_version !== SourceFingerprint::VERSION) {
                        throw new FreezeBlocked('frozen_item_mismatch');
                    }
                    if (! $item->source_observed_at->equalTo($batch->eligibility_as_of)) {
                        throw new FreezeBlocked('item_observation_mismatch');
                    }
                    $source = new CustomerSubmission;
                    $source->setRawAttributes($item->source_snapshot_encrypted, true);
                    $dealer = new Dealer;
                    $dealer->setRawAttributes($item->dealer_snapshot_encrypted, true);
                    $source->setRelation('dealer', $dealer);
                    if (! $this->fingerprints->matches($candidate['source_fingerprint'], $source)) {
                        throw new FreezeBlocked('snapshot_fingerprint_mismatch');
                    }
                }
                $events = $batch->events()->where('event_type', 'freeze')->get();
                if ($events->count() !== 1) {
                    throw new FreezeBlocked('freeze_event_missing');
                }
                $event = $events->first();
                $expected = ['batch_id' => (int) $batch->id, 'actor_id' => (int) $batch->frozen_by, 'purpose' => $batch->purpose,
                    'actual_count' => $batch->actual_count, 'manifest_digest' => $batch->manifest_digest];
                if ($this->codec->digest($event->metadata) !== $this->codec->digest($expected) || (int) $event->actor_id !== (int) $batch->frozen_by
                    || $event->event_sequence !== 1 || $event->command_key !== $batch->create_request_key
                    || $event->command_name !== 'create_and_freeze' || $event->event_schema_version !== 'call_audit_freeze_event_v1'
                    || $event->event_ordinal !== 0 || $event->before_version !== null || $event->after_version !== 1
                    || $event->previous_event_digest !== null || $event->digest_key_id !== $batch->digest_key_id
                    || $event->reason_code !== $batch->policy_version || ! $event->occurred_at->equalTo($batch->frozen_at)
                    || ! hash_equals($event->event_digest, hash_hmac('sha256', 'freeze-event-v1|'.$this->codec->encode($expected), config('app.key')))) {
                    throw new FreezeBlocked('freeze_event_mismatch');
                }

                return new FreezeResult(['status' => 'VERIFIED', 'batch_id' => (int) $batch->id, 'actual_count' => $batch->actual_count]);
            });
        } catch (Throwable) {
            return FreezeResult::blocked('integrity_verification_failed');
        }
    }
}
