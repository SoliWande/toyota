<?php

namespace App\Actions\CallAudit;

use App\Models\CallAuditItem;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\ClaimCoordinator;
use Carbon\CarbonImmutable;

final class ReleaseClaim
{
    public function __construct(private ClaimCoordinator $claims) {}

    public function execute(int $batchId, int $itemId, array $input): array
    {
        return $this->claims->run('release_claim', $batchId, $itemId, $input, function (CallAuditItem $item, User $actor, CarbonImmutable $now) use ($input): array {
            if ((int) $item->claim_owner_id !== (int) $actor->id || $item->claim_expires_at === null
                || $item->claim_expires_at <= $now
                || ! hash_equals($item->claim_token_hash ?? '', hash('sha256', $input['claim_token']))) {
                throw new CallCommandRejected('claim_ownership_conflict');
            }
            $item->claim_owner_id = null;
            $item->claim_token_hash = null;
            $item->claim_expires_at = null;

            return ['released' => true];
        });
    }
}
