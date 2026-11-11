<?php

namespace App\Actions\CallAudit;

use App\Models\CallAuditItem;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\ClaimCoordinator;
use App\Services\CallAudit\SourceFingerprint;
use Carbon\CarbonImmutable;

final class ClaimItem
{
    public function __construct(private ClaimCoordinator $claims, private SourceFingerprint $fingerprints) {}

    public function execute(int $batchId, int $itemId, array $input): array
    {
        return $this->claims->run('claim_item', $batchId, $itemId, $input, function (CallAuditItem $item, User $actor, CarbonImmutable $now): array {
            // Until a call-result contract exists, do not reinterpret any historic attempt/projection.
            if ($item->attempts()->exists() || $item->queue_state !== 'uncalled' || $item->attempt_count !== 0
                || $item->latest_outcome !== null || $item->retry_due_at !== null || $item->source_stale_at !== null) {
                throw new CallCommandRejected('item_not_claimable');
            }
            if ($item->claim_owner_id !== null && $item->claim_expires_at > $now) {
                throw new CallCommandRejected('item_already_claimed');
            }
            $source = CustomerSubmission::whereKey($item->submission_id)->lockForUpdate()->first(SourceFingerprint::FIELDS);
            $dealer = $source === null ? null : Dealer::whereKey($source->dealer_id)->lockForUpdate()->first(['id', 'name', 'code']);
            $source?->setRelation('dealer', $dealer);
            if ($source === null || $item->fingerprint_version !== SourceFingerprint::VERSION
                || ! $this->fingerprints->matches($item->source_fingerprint, $source)) {
                throw new CallCommandRejected('source_drift_conflict');
            }
            $token = bin2hex(random_bytes(32));
            $item->claim_owner_id = $actor->id;
            $item->claim_token_hash = hash('sha256', $token);
            $item->claim_expires_at = $now->addSeconds(600);

            return ['claim_token' => $token, 'claim_expires_at' => $item->claim_expires_at->toIso8601String()];
        });
    }
}
