<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;

final class FindingEvidence
{
    public function __construct(private SourceFingerprint $fingerprints, private ManifestCodec $codec) {}

    /** The caller has already validated the entire batch through WorkflowReadModel. */
    public function project(CallAuditBatch $batch, CallAuditItem $item, array $predecessors): array
    {
        if ($batch->digest_key_id !== $this->fingerprints->keyId() || $batch->encryption_key_id !== $this->fingerprints->keyId()
            || $item->fingerprint_version !== SourceFingerprint::VERSION || $item->claim_owner_id !== null
            || $item->claim_token_hash !== null || $item->claim_expires_at !== null) {
            throw new ReadUnavailable('Finding key or terminal projection unavailable.');
        }
        $latest = $item->attempts->last();
        if ($latest === null || $latest->outcome !== 'wrong' || $item->row_version !== $latest->item_version_used + 1) {
            throw new ReadUnavailable('Finding is not the current terminal result.');
        }
        $lastEvent = null;
        foreach ($item->attempts as $attempt) {
            $event = $item->events->firstWhere('attempt_id', $attempt->id);
            if ($event === null) {
                throw new ReadUnavailable('Finding commit event missing.');
            }
            $this->verifyCommit($event, $attempt, $item, $predecessors);
            $lastEvent = $event;
        }
        $sourceData = $item->source_snapshot_encrypted;
        $dealerData = $item->dealer_snapshot_encrypted;
        if (! is_array($sourceData) || ! is_array($dealerData)
            || (int) ($sourceData['id'] ?? 0) !== (int) $item->submission_id
            || (int) ($sourceData['dealer_id'] ?? 0) !== (int) $item->dealer_id_snapshot
            || (int) ($dealerData['id'] ?? 0) !== (int) $item->dealer_id_snapshot
            || ! is_string($sourceData['customer_name'] ?? null) || ! is_string($dealerData['name'] ?? null)) {
            throw new ReadUnavailable('Finding snapshot identity mismatch.');
        }
        $source = new CustomerSubmission;
        $source->setRawAttributes($sourceData, true);
        $dealer = new Dealer;
        $dealer->setRawAttributes($dealerData, true);
        $source->setRelation('dealer', $dealer);
        if (! $this->fingerprints->matches($item->source_fingerprint, $source)) {
            throw new ReadUnavailable('Finding snapshot fingerprint mismatch.');
        }
        $phone = (new PhoneEligibility)->inspect($sourceData['phone'] ?? null, $sourceData['phone_normalized'] ?? null);
        if ($phone['status'] !== 'READY') {
            throw new ReadUnavailable('Finding snapshot phone is inconsistent.');
        }

        return ['id' => (int) $item->id, 'item_id' => (int) $item->id, 'batch_id' => (int) $batch->id,
            'attempt_id' => (int) $latest->id, 'event_id' => (int) $lastEvent->id,
            'row_version' => $item->row_version, 'status' => 'finding_pending',
            'dealer' => ['id' => (int) $item->dealer_id_snapshot, 'name' => $dealerData['name']],
            'customer' => ['name' => $sourceData['customer_name'],
                'phone_masked' => substr($phone['phone'], 0, 2).'****'.substr($phone['phone'], -3)],
            'recorded_by' => ['id' => (int) $latest->actor_id], 'recorded_at' => $latest->recorded_at->toIso8601String()];
    }

    public function verifyCommit(CallAuditEvent $event, $attempt, CallAuditItem $item, array $predecessors): void
    {
        $receipt = $event->private_payload_encrypted;
        $retry = $attempt->outcome === 'no_answer' && $attempt->attempt_no === 1;
        $expected = ['item_id' => (int) $item->id, 'row_version' => $attempt->item_version_used + 1,
            'attempt_id' => (int) $attempt->id, 'outcome' => $attempt->outcome,
            'queue_state' => $retry ? 'recall' : ($attempt->outcome === 'wrong' ? 'finding_pending' : 'completed'),
            'retry_due_at' => $attempt->retry_due_at?->toIso8601String(),
            'finding_status' => $attempt->outcome === 'wrong' ? 'pending' : null];
        if (! is_array($receipt) || ! is_string($receipt['payload_digest'] ?? null) || ! is_array($receipt['response'] ?? null)
            || ! hash_equals($attempt->payload_digest, $receipt['payload_digest'])
            || ! hash_equals($this->codec->digest($expected), $this->codec->digest($receipt['response']))
            || $event->command_key !== $attempt->idempotency_key || $event->digest_key_id !== $this->fingerprints->keyId()
            || ! isset($predecessors[$event->event_sequence - 1])
            || ! hash_equals($event->previous_event_digest ?? '', $predecessors[$event->event_sequence - 1])) {
            throw new ReadUnavailable('Finding receipt is stale or unavailable.');
        }
        // Rebuild exactly the versioned envelope emitted by ClaimCoordinator; no note/token/seed lookup.
        $data = [];
        foreach (['batch_id', 'item_id', 'actor_id', 'event_sequence', 'event_ordinal', 'before_version', 'after_version', 'attempt_id'] as $field) {
            $data[$field] = (int) $event->$field;
        }
        foreach (['event_type', 'event_schema_version', 'command_name', 'command_key', 'reason_code', 'correlation_id',
            'metadata', 'previous_event_digest', 'digest_key_id'] as $field) {
            $data[$field] = $event->$field;
        }
        $data['occurred_at'] = $event->occurred_at->format('Y-m-d H:i:s.u');
        $digest = hash_hmac('sha256', 'call-claim-event-v1|'.$this->codec->encode([
            'event' => $data, 'private_digest' => $this->codec->digest($receipt),
        ]), config('app.key'));
        if (! hash_equals($event->event_digest, $digest)) {
            throw new ReadUnavailable('Finding commit signature mismatch.');
        }
    }
}
