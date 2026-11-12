<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FindingReadModel
{
    public function __construct(private WorkflowReadModel $workflow, private FindingEvidence $evidence, private FreezeClock $clock) {}

    public function index(User $actor, int $batchId, array $filters): array
    {
        return $this->read($actor, $batchId, $filters, null);
    }

    public function show(User $actor, int $batchId, int $itemId, array $filters): array
    {
        return $this->read($actor, $batchId, $filters, $itemId);
    }

    private function read(User $actor, int $batchId, array $filters, ?int $itemId): array
    {
        $current = User::select(['id', 'role', 'status'])->find($actor->id);
        if ($current === null || Gate::forUser($current)->denies('viewAny', CallAuditBatch::class)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($current, $batchId, $filters, $itemId) {
            $batch = CallAuditBatch::whereKey($batchId)->where('purpose', $filters['purpose'])
                ->where('lifecycle_state', 'frozen')->whereNotNull('frozen_at')
                ->when($filters['month'] ?? null, fn ($query, $month) => $query->where('monthly_scope_key', $month))
                ->firstOrFail(['id', 'purpose', 'monthly_scope_key', 'generation', 'actual_count', 'frozen_at', 'digest_key_id', 'encryption_key_id']);
            Gate::forUser($current)->authorize('view', $batch);
            if ($itemId !== null) {
                $batch->items()->whereKey($itemId)->firstOrFail(['id']);
            }
            $state = $this->workflow->read($batch, $this->clock->now());
            $ids = array_keys(array_filter($state['states'], fn ($item) => $item['state'] === 'finding_pending'));
            $items = $batch->items()->whereIn('id', $ids)->orderBy('selection_order')->orderBy('id')->get([
                'id', 'batch_id', 'submission_id', 'dealer_id_snapshot', 'row_version', 'fingerprint_version', 'source_fingerprint',
                'source_snapshot_encrypted', 'dealer_snapshot_encrypted', 'claim_owner_id', 'claim_token_hash', 'claim_expires_at',
            ]);
            $items->load(['attempts' => fn ($query) => $query->orderBy('attempt_no')->select([
                'id', 'item_id', 'actor_id', 'outcome', 'attempt_no', 'recorded_at', 'item_version_used',
                'retry_due_at', 'idempotency_key', 'payload_digest',
            ]), 'events' => fn ($query) => $query->where('event_type', 'call_result_recorded')->select([
                'id', 'batch_id', 'item_id', 'actor_id', 'attempt_id', 'event_sequence', 'event_type', 'event_schema_version',
                'command_name', 'command_key', 'event_ordinal', 'before_version', 'after_version', 'reason_code', 'correlation_id',
                'metadata', 'previous_event_digest', 'event_digest', 'digest_key_id', 'occurred_at', 'private_payload_encrypted',
            ])]);
            $sequences = $items->flatMap(fn ($item) => $item->events->pluck('event_sequence'))->map(fn ($value) => $value - 1)->unique()->all();
            $predecessors = $batch->events()->whereIn('event_sequence', $sequences)->pluck('event_digest', 'event_sequence')->all();
            // Verify every pending finding before pagination: untrusted off-page rows must not inflate total.
            $findings = $items->map(fn ($item) => $this->evidence->project($batch, $item, $predecessors))->all();
            $metadata = ['id' => (int) $batch->id, 'purpose' => $batch->purpose, 'month' => $batch->monthly_scope_key,
                'generation' => (int) $batch->generation, 'frozen_at' => $batch->frozen_at->toIso8601String()];
            if ($itemId !== null) {
                $finding = collect($findings)->firstWhere('item_id', $itemId);
                abort_if($finding === null, 404);

                return ['data' => $finding, 'batch' => $metadata];
            }
            $page = (int) ($filters['page'] ?? 1);
            $size = (int) ($filters['per_page'] ?? 20);

            return ['data' => array_slice($findings, ($page - 1) * $size, $size), 'batch' => $metadata,
                'meta' => ['current_page' => $page, 'per_page' => $size, 'total' => count($findings),
                    'last_page' => max(1, (int) ceil(count($findings) / $size))]];
        });
    }
}
