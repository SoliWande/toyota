<?php

namespace App\Actions\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FindingReadModel;
use App\Services\CallAudit\FindingResolutionHistory;
use App\Services\CallAudit\FindingResolutionPolicy;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\SourceFingerprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ResolveFinding
{
    public function __construct(private FindingResolutionPolicy $policy, private FindingReadModel $findings,
        private FindingResolutionHistory $history, private FreezeClock $clock, private SourceFingerprint $keys) {}

    public function execute(int $batchId, int $itemId, array $input): array
    {
        $actorId = Auth::id();
        if (! $actorId) {
            throw new CallCommandRejected('admin_required', 401);
        }
        if (DB::transactionLevel() !== 0) {
            throw new CallCommandRejected('requires_top_level_transaction');
        }
        $input = $this->policy->validate($input);

        return DB::transaction(function () use ($batchId, $itemId, $input, $actorId) {
            $actor = User::select(['id', 'role', 'status'])->whereKey($actorId)->sharedLock()->first();
            if ($actor === null || Gate::forUser($actor)->denies('viewAny', CallAuditBatch::class)) {
                throw new CallCommandRejected('admin_inactive_or_forbidden', 403);
            }
            $batch = CallAuditBatch::whereKey($batchId)->where('purpose', $input['purpose'])
                ->when($input['month'], fn ($q, $month) => $q->where('monthly_scope_key', $month))
                ->where('lifecycle_state', 'frozen')->whereNotNull('frozen_at')->lockForUpdate()->first();
            if ($batch === null || ! $batch->items()->whereKey($itemId)->lockForUpdate()->first(['id'])) {
                throw new CallCommandRejected('audit_finding_not_found', 404);
            }
            if (Gate::forUser($actor)->denies('resolveFinding', $batch)) {
                throw new CallCommandRejected('finding_resolution_forbidden', 403);
            }
            $finding = $this->findings->show($actor, $batchId, $itemId, $input)['data'];
            $now = $this->clock->now();
            $state = $this->history->read($batch, $finding, $now);
            $digest = $this->history->intent($batchId, $itemId, (int) $actorId, $input);
            // The database unique key is actor/batch/command scoped, including other items.
            $receipt = $batch->events()->where('actor_id', $actorId)->where('command_name', FindingResolutionPolicy::COMMAND)
                ->where('command_key', $input['idempotency_key'])->where('event_ordinal', 0)->first(['id', 'item_id']);
            if ($receipt !== null) {
                foreach ($state['receipts'] as $saved) {
                    if ((int) $receipt->item_id === $itemId && $saved['actor_id'] === (int) $actorId
                        && $saved['key'] === $input['idempotency_key'] && hash_equals($saved['digest'], $digest)) {
                        return $saved['response'];
                    }
                }
                throw new CallCommandRejected('idempotency_payload_conflict');
            }
            if ($input['attempt_id'] !== $finding['attempt_id'] || $input['row_version'] !== $finding['row_version']) {
                throw new CallCommandRejected('finding_evidence_version_conflict');
            }
            $version = $state['current']['resolution_version'];
            if ($input['resolution_version'] !== $version) {
                throw new CallCommandRejected('resolution_version_conflict');
            }
            $this->policy->transition($state['current']['decision'], $input['decision']);
            $previous = $batch->events()->orderByDesc('event_sequence')->first(['event_sequence', 'event_digest', 'occurred_at']);
            if ($previous === null || $previous->occurred_at > $now) {
                throw new CallCommandRejected('audit_clock_or_history_conflict');
            }
            $response = $this->history->response($finding, $version + 1, $input['decision'], (int) $actorId, $now);
            $private = ['input' => $input, 'payload_digest' => $digest, 'response' => $response];
            $event = ['batch_id' => $batchId, 'item_id' => $itemId, 'attempt_id' => $finding['attempt_id'], 'actor_id' => (int) $actorId,
                'event_sequence' => $previous->event_sequence + 1, 'event_type' => FindingResolutionPolicy::EVENT,
                'event_schema_version' => FindingResolutionPolicy::SCHEMA, 'command_name' => FindingResolutionPolicy::COMMAND,
                'command_key' => $input['idempotency_key'], 'event_ordinal' => 0, 'before_version' => $version, 'after_version' => $version + 1,
                'reason_code' => FindingResolutionPolicy::VERSION, 'correlation_id' => bin2hex(random_bytes(16)),
                'metadata' => ['policy' => FindingResolutionPolicy::VERSION, 'version_namespace' => 'resolution',
                    'decision' => $input['decision'], 'item_row_version' => $finding['row_version'], 'finding_event_id' => $finding['event_id']],
                'previous_event_digest' => $previous->event_digest, 'digest_key_id' => $this->keys->keyId(),
                'occurred_at' => $now->format('Y-m-d H:i:s.u')];
            (new CallAuditEvent)->forceFill($event + ['event_digest' => $this->history->signature($event, $private),
                'private_payload_encrypted' => $private])->save();

            return $response;
        }, 3);
    }
}
