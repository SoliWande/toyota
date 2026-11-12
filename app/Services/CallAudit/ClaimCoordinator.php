<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ClaimCoordinator
{
    public const POLICY = 'claim_lease_600s_v1';

    public function __construct(private FreezeClock $clock, private SourceFingerprint $fingerprints, private ManifestCodec $codec, private CallResultPolicy $results) {}

    /** Batch lock serializes the event sequence; item lock protects lease and version. */
    public function run(string $command, int $batchId, int $itemId, array $input, callable $change): array
    {
        $actorId = Auth::id();
        if (! $actorId) {
            throw new CallCommandRejected('admin_required', 401);
        }
        if (DB::transactionLevel() !== 0) {
            throw new CallCommandRejected('requires_top_level_transaction');
        }
        $allowed = ['purpose', 'row_version', 'idempotency_key'];
        $recording = $command === 'record_call_result';
        if ($command === 'release_claim' || $recording) {
            $allowed[] = 'claim_token';
        }
        if ($recording) {
            array_push($allowed, 'outcome', 'answer_1', 'answer_2', 'answer_3', 'note');
            $this->results->validate($input);
            $input['note'] = isset($input['note']) && trim($input['note']) !== '' ? trim($input['note']) : null;
        }
        if (array_diff(array_keys($input), $allowed)
            || ! in_array($input['purpose'] ?? null, [CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD], true)
            || ! is_int($input['row_version'] ?? null) || $input['row_version'] < 1 || $input['row_version'] >= 4294967295
            || ! is_string($input['idempotency_key'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $input['idempotency_key'])
            || (($command === 'release_claim' || $recording) && (! is_string($input['claim_token'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['claim_token'])))) {
            throw new CallCommandRejected('invalid_call_command', 422);
        }
        $digest = hash_hmac('sha256', 'call-claim-intent-v1|'.$this->codec->encode([
            'command' => $command, 'batch_id' => $batchId, 'item_id' => $itemId, 'input' => $input,
        ]), config('app.key'));

        return DB::transaction(function () use ($command, $batchId, $itemId, $input, $change, $actorId, $digest, $recording) {
            $actor = User::select(['id', 'role', 'status'])->whereKey($actorId)->sharedLock()->first();
            if ($actor === null || Gate::forUser($actor)->denies('viewAny', CallAuditBatch::class)) {
                throw new CallCommandRejected('admin_inactive_or_forbidden', 403);
            }
            $batch = CallAuditBatch::whereKey($batchId)->where('purpose', $input['purpose'])
                ->where('lifecycle_state', 'frozen')->whereNotNull('frozen_at')->lockForUpdate()->first();
            if ($batch === null) {
                throw new CallCommandRejected('audit_item_not_found', 404);
            }
            if (Gate::forUser($actor)->denies($recording ? 'recordAttempt' : 'claim', $batch)) {
                throw new CallCommandRejected('claim_forbidden', 403);
            }
            $item = $batch->items()->whereKey($itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw new CallCommandRejected('audit_item_not_found', 404);
            }
            if ($batch->digest_key_id !== $this->fingerprints->keyId()) {
                throw new CallCommandRejected('audit_key_unavailable');
            }
            $now = $this->clock->now();
            $receipt = $batch->events()->where('actor_id', $actorId)->where('command_name', $command)
                ->where('command_key', $input['idempotency_key'])->where('event_ordinal', 0)->first();
            if ($receipt !== null) {
                $private = $receipt->private_payload_encrypted;
                if (! is_array($private) || ! hash_equals($private['payload_digest'] ?? '', $digest)) {
                    throw new CallCommandRejected('idempotency_payload_conflict');
                }
                if ($command === 'claim_item' && ((int) $item->claim_owner_id !== (int) $actorId
                    || $item->row_version !== $receipt->after_version || $item->claim_expires_at === null
                    || $item->claim_expires_at <= $now
                    || ! hash_equals($item->claim_token_hash ?? '', hash('sha256', $private['response']['claim_token'])))) {
                    throw new CallCommandRejected('claim_receipt_obsolete');
                }

                return $private['response'];
            }
            if ($item->row_version !== $input['row_version']) {
                throw new CallCommandRejected('item_version_conflict');
            }
            $before = $item->row_version;
            $response = $change($item, $actor, $now, $digest);
            $item->row_version = $before + 1;
            $item->updated_at = $now;
            $item->save();
            $response = ['item_id' => (int) $item->id, 'row_version' => $item->row_version] + $response;
            $previous = $batch->events()->orderByDesc('event_sequence')->first();
            if ($previous === null || $previous->occurred_at > $now) {
                throw new CallCommandRejected('audit_clock_or_history_conflict');
            }
            $private = ['payload_digest' => $digest, 'response' => $response];
            $event = [
                'batch_id' => $batch->id, 'item_id' => $item->id, 'actor_id' => $actor->id,
                'event_sequence' => $previous->event_sequence + 1, 'event_type' => $recording ? 'call_result_recorded' : $command,
                'event_schema_version' => $recording ? 'call_audit_result_event_v1' : 'call_audit_claim_event_v1', 'command_name' => $command,
                'command_key' => $input['idempotency_key'], 'event_ordinal' => 0,
                'before_version' => $before, 'after_version' => $item->row_version,
                'reason_code' => $recording ? CallResultPolicy::VERSION : self::POLICY, 'correlation_id' => bin2hex(random_bytes(16)),
                'metadata' => $recording ? ['policy' => CallResultPolicy::VERSION, 'finding_status' => $response['finding_status']] : ['policy' => self::POLICY],
                'previous_event_digest' => $previous->event_digest,
                'digest_key_id' => $this->fingerprints->keyId(), 'occurred_at' => $now->format('Y-m-d H:i:s.u'),
            ];
            if ($recording) {
                $event['attempt_id'] = $response['attempt_id'];
            }
            $event['event_digest'] = hash_hmac('sha256', 'call-claim-event-v1|'.$this->codec->encode([
                'event' => $event, 'private_digest' => $this->codec->digest($private),
            ]), config('app.key'));
            (new CallAuditEvent)->forceFill($event + ['private_payload_encrypted' => $private])->save();

            return $response;
        }, 3);
    }
}
