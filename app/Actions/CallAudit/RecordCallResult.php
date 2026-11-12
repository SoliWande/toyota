<?php

namespace App\Actions\CallAudit;

use App\Models\CallAuditAttempt;
use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\CallResultPolicy;
use App\Services\CallAudit\ClaimCoordinator;
use App\Services\CallAudit\SourceFingerprint;
use Carbon\CarbonImmutable;

final class RecordCallResult
{
    public function __construct(private ClaimCoordinator $claims, private CallResultPolicy $results, private SourceFingerprint $fingerprints) {}

    public function execute(int $batchId, int $itemId, array $input): array
    {
        return $this->claims->run('record_call_result', $batchId, $itemId, $input,
            function (CallAuditItem $item, User $actor, CarbonImmutable $now, string $digest) use ($input): array {
                if ((int) $item->claim_owner_id !== (int) $actor->id || $item->claim_expires_at === null
                    || $item->claim_expires_at <= $now
                    || ! hash_equals($item->claim_token_hash ?? '', hash('sha256', $input['claim_token']))) {
                    throw new CallCommandRejected('claim_ownership_conflict');
                }
                $number = $this->results->assertCallable($item, $now);
                $source = CustomerSubmission::whereKey($item->submission_id)->lockForUpdate()->first(SourceFingerprint::FIELDS);
                $dealer = $source === null ? null : Dealer::whereKey($source->dealer_id)->lockForUpdate()->first(['id', 'name', 'code']);
                $source?->setRelation('dealer', $dealer);
                if ($source === null || $item->fingerprint_version !== SourceFingerprint::VERSION
                    || ! $this->fingerprints->matches($item->source_fingerprint, $source)) {
                    throw new CallCommandRejected('source_drift_conflict');
                }
                $started = $item->claim_expires_at->subSeconds(600);
                if ($started > $now) {
                    throw new CallCommandRejected('audit_clock_or_history_conflict');
                }
                $retry = $input['outcome'] === 'no_answer' && $number === 1 ? $now->utc()->addSeconds(86400)->setTimezone($now->timezone) : null;
                $queue = $retry !== null ? 'recall' : ($input['outcome'] === 'wrong' ? 'finding_pending' : 'completed');
                $note = isset($input['note']) && trim($input['note']) !== '' ? trim($input['note']) : null;
                $attempt = (new CallAuditAttempt)->forceFill([
                    'item_id' => $item->id, 'attempt_no' => $number, 'actor_id' => $actor->id,
                    'started_at' => $started, 'recorded_at' => $now, 'questionnaire_version' => CallResultPolicy::QUESTIONNAIRE,
                    'outcome' => $input['outcome'], 'answer_1' => $input['answer_1'], 'answer_2' => $input['answer_2'], 'answer_3' => $input['answer_3'],
                    'note_encrypted' => $note, 'source_fingerprint_used' => $item->source_fingerprint, 'item_version_used' => $item->row_version,
                    'retry_due_at' => $retry, 'retry_policy_version' => $retry !== null ? CallResultPolicy::RETRY : null,
                    'idempotency_key' => $input['idempotency_key'], 'payload_digest' => $digest,
                ]);
                $attempt->save();
                $item->attempt_count = $number;
                $item->queue_state = $queue;
                $item->latest_outcome = $input['outcome'];
                $item->retry_due_at = $retry;
                $item->claim_owner_id = null;
                $item->claim_token_hash = null;
                $item->claim_expires_at = null;

                return ['attempt_id' => (int) $attempt->id, 'outcome' => $input['outcome'], 'queue_state' => $queue,
                    'retry_due_at' => $retry?->toIso8601String(), 'finding_status' => $input['outcome'] === 'wrong' ? 'pending' : null];
            });
    }
}
