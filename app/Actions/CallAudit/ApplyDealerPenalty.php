<?php

namespace App\Actions\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\Dealer;
use App\Models\DealerPenaltyEntry;
use App\Models\DealerPenaltyEvent;
use App\Models\DealerPenaltyEvidence;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\DealerPenaltyIntegrity;
use App\Services\CallAudit\FindingResolutionPolicy;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ImpactPreviewReadModel;
use App\Services\CallAudit\SourceFingerprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ApplyDealerPenalty
{
    public function __construct(private ImpactPreviewReadModel $impact, private FreezeClock $clock,
        private SourceFingerprint $keys, private DealerPenaltyIntegrity $integrity) {}

    public function execute(int $batchId, int $itemId, array $input): array
    {
        if (! Auth::id()) {
            throw new CallCommandRejected('admin_required', 401);
        }
        if (DB::transactionLevel() !== 0) {
            throw new CallCommandRejected('requires_top_level_transaction');
        }
        if (array_diff(array_keys($input), ['month', 'purpose', 'resolution_version', 'idempotency_key'])
            || ! is_string($input['month'] ?? null) || ! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $input['month'])
            || ! in_array($input['purpose'] ?? null, ['general_audit', 'award_top3'], true)
            || ! is_int($input['resolution_version'] ?? null) || ! in_array($input['resolution_version'], [1, 2], true)
            || ! is_string($input['idempotency_key'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $input['idempotency_key'])) {
            throw new CallCommandRejected('invalid_penalty_command', 422);
        }
        $actorId = (int) Auth::id();
        $intent = ['batch_id' => $batchId, 'item_id' => $itemId] + $input;

        return DB::transaction(function () use ($actorId, $batchId, $itemId, $input, $intent) {
            // Shared intake mutex serializes both purposes and concurrent freezes without adding a lock table.
            if (! DB::table('submission_intake_mutex')->where('id', 1)->lockForUpdate()->first()) {
                throw new CallCommandRejected('intake_mutex_missing');
            }
            $actor = User::select(['id', 'role', 'status'])->whereKey($actorId)->sharedLock()->first();
            if ($actor === null || Gate::forUser($actor)->denies('applyDealerPenalty', DealerPenaltyEntry::class)) {
                throw new CallCommandRejected('penalty_forbidden', 403);
            }
            $batches = CallAuditBatch::where('monthly_scope_key', $input['month'])->orderBy('id')->lockForUpdate()->get();
            $batch = $batches->firstWhere('id', $batchId);
            if ($batch === null || $batch->purpose !== $input['purpose'] || ! $batch->items()->whereKey($itemId)->lockForUpdate()->first()) {
                throw new CallCommandRejected('audit_finding_not_found', 404);
            }
            $digest = $this->integrity->intent($actorId, $intent);
            $retry = DealerPenaltyEvent::where('actor_id', $actorId)->where('command_name', 'apply_dealer_penalty')
                ->where('command_key', $input['idempotency_key'])->where('event_ordinal', 0)->first();
            if ($retry) {
                $this->integrity->verify($retry->entry);
                if (! hash_equals($retry->payload_digest, $digest)) {
                    throw new CallCommandRejected('idempotency_payload_conflict');
                }

                return $retry->receipt_encrypted;
            }
            $preview = $this->impact->read($actor, ['month' => $input['month']]);
            $candidate = collect($preview['data'])->first(fn ($row) => collect($row['evidence'])->contains('item_id', $itemId));
            if ($candidate === null) {
                throw new CallCommandRejected('confirmed_finding_required');
            }
            $selected = collect($candidate['evidence'])->firstWhere('item_id', $itemId);
            if ($selected['resolution_version'] !== $input['resolution_version']) {
                throw new CallCommandRejected('resolution_version_conflict');
            }
            $dealers = array_values(array_unique(array_column(array_column($candidate['evidence'], 'frozen_attribution'), 'dealer_id')));
            if (count($dealers) !== 1) {
                throw new CallCommandRejected('dealer_attribution_conflict');
            }
            Dealer::whereKey($dealers[0])->lockForUpdate()->firstOrFail(['id']);
            $now = $this->clock->now();
            $entry = DealerPenaltyEntry::where('submission_id', $candidate['submission_id'])->where('monthly_scope_key', $input['month'])->lockForUpdate()->first();
            $created = $entry === null;
            $origin = $batch->items()->whereKey($itemId)->firstOrFail();
            if ($created) {
                $entry = (new DealerPenaltyEntry)->forceFill([
                    'submission_id' => $candidate['submission_id'], 'monthly_scope_key' => $input['month'], 'dealer_id' => $dealers[0],
                    'period_start' => $batch->period_start, 'period_end' => $batch->period_end,
                    'calendar_zone' => $batch->calendar_zone, 'calendar_version' => $batch->calendar_version,
                    'points' => 1, 'schema_version' => 'dealer_penalty_entry_v1', 'policy_version' => DealerPenaltyEntry::POLICY,
                    'projection_policy_version' => DealerPenaltyEntry::PROJECTION_POLICY, 'ranking_policy_version' => DealerPenaltyEntry::RANKING_POLICY,
                    'policy_snapshot_encrypted' => ['points' => 1, 'subject' => 'dealer', 'display_floor' => 0, 'approval' => 'USER_APPROVED'],
                    'dealer_attribution_encrypted' => ['dealer_id' => $dealers[0], 'batch_id' => $batchId, 'item_id' => $itemId],
                    'attribution_version' => 'frozen_submission_dealer_v1', 'attribution_observed_at' => $origin->source_observed_at,
                    'source_submitted_at' => $origin->source_snapshot_encrypted['submitted_at'],
                    'source_fingerprint' => $origin->source_fingerprint, 'fingerprint_version' => $origin->fingerprint_version,
                    'applied_by' => $actorId, 'applied_at' => $now, 'effective_at' => $now, 'created_at' => $now,
                    'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => $this->keys->keyId(),
                ]);
                $entry->save();
                $entry->refresh();
            } else {
                $this->integrity->verify($entry);
                if ((int) $entry->dealer_id !== $dealers[0]) {
                    throw new CallCommandRejected('dealer_attribution_conflict');
                }
            }
            $beforeCount = $entry->evidence()->count();
            $seals = [];
            foreach ($candidate['evidence'] as $proof) {
                $proofBatch = $batches->firstWhere('id', $proof['batch_id']);
                $item = $proofBatch->items()->whereKey($proof['item_id'])->firstOrFail();
                $resolution = $item->events()->where('event_type', FindingResolutionPolicy::EVENT)->orderByDesc('event_sequence')->firstOrFail();
                if ($entry->evidence()->where('resolution_event_id', $resolution->id)->exists()) {
                    continue;
                }
                $evidence = (new DealerPenaltyEvidence)->forceFill([
                    'entry_id' => $entry->id, 'batch_id' => $proof['batch_id'], 'item_id' => $proof['item_id'], 'attempt_id' => $proof['attempt_id'],
                    'call_event_id' => $proof['call_event_id'], 'resolution_event_id' => $resolution->id,
                    'resolution_version' => $proof['resolution_version'], 'item_version_used' => $item->row_version,
                    'schema_version' => 'dealer_penalty_evidence_v1', 'verification_version' => 'signed_e1_e2_v1',
                    'verified_by' => $actorId, 'call_recorded_at' => $item->attempts()->whereKey($proof['attempt_id'])->value('recorded_at'),
                    'resolution_recorded_at' => $resolution->occurred_at, 'verified_at' => $now,
                    'evidence_snapshot_encrypted' => ['proof' => $proof, 'call_digest' => $item->events()->whereKey($proof['call_event_id'])->value('event_digest'), 'resolution_digest' => $resolution->event_digest],
                    'evidence_digest' => hash('sha256', $item->source_fingerprint.'|'.$resolution->event_digest),
                    'digest_key_id' => $this->keys->keyId(), 'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => $this->keys->keyId(),
                ]);
                $evidence->save();
                $evidence->refresh();
                $seals[] = ['id' => (int) $evidence->id, 'seal' => $this->integrity->seal($evidence)];
            }
            $previous = $entry->events()->orderByDesc('event_sequence')->first();
            if ($previous && $previous->occurred_at > $now) {
                throw new CallCommandRejected('audit_clock_conflict');
            }
            $sequence = ($previous?->event_sequence ?? 0) + 1;
            $response = ['entry_id' => (int) $entry->id, 'submission_id' => (int) $entry->submission_id, 'month' => $entry->monthly_scope_key,
                'dealer_id' => (int) $entry->dealer_id, 'points' => 1, 'created' => $created, 'evidence_count' => $entry->evidence()->count()];
            $event = (new DealerPenaltyEvent)->forceFill([
                'entry_id' => $entry->id, 'evidence_id' => null, 'actor_id' => $actorId, 'event_sequence' => $sequence, 'event_type' => $created ? 'penalty_applied' : 'penalty_evidence_checked',
                'event_schema_version' => 'dealer_penalty_event_v1', 'command_name' => 'apply_dealer_penalty', 'command_key' => $input['idempotency_key'], 'event_ordinal' => 0,
                'before_version' => $sequence - 1, 'after_version' => $sequence, 'evidence_count_before' => $beforeCount, 'evidence_count_after' => $response['evidence_count'],
                'reason_code' => 'confirmed_finding', 'policy_version' => DealerPenaltyEntry::POLICY, 'occurred_at' => $now, 'correlation_id' => bin2hex(random_bytes(16)),
                'metadata' => ['entry_seal' => $this->integrity->seal($entry), 'evidence_seals' => $seals],
                'intent_encrypted' => $intent, 'receipt_encrypted' => $response, 'payload_digest' => $digest, 'previous_event_digest' => $previous?->event_digest,
                'digest_key_id' => $this->keys->keyId(), 'encryption_version' => 'laravel_encrypted_cast_v1', 'encryption_key_id' => $this->keys->keyId(),
            ]);
            $event->event_digest = $this->integrity->seal($event);
            $event->save();
            $this->integrity->verify($entry);

            return $response;
        }, 3);
    }
}
