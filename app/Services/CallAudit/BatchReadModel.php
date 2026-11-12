<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use JsonException;
use TypeError;

final class BatchReadModel
{
    private const BATCH_COLUMNS = ['id', 'purpose', 'monthly_scope_key', 'generation', 'frozen_at',
        'requested_count', 'actual_count'];

    private const ITEM_COLUMNS = ['id', 'batch_id', 'submission_id', 'row_version', 'selection_order', 'dealer_id_snapshot',
        'dealer_snapshot_encrypted', 'source_snapshot_encrypted'];

    public function __construct(private WorkflowReadModel $workflow, private FreezeClock $clock) {}

    public function index(User $user, array $filters): array
    {
        $this->authorize($user);

        return DB::transaction(function () use ($filters) {
            $now = $this->clock->now();
            $page = $this->query($filters)->orderByDesc('monthly_scope_key')->orderByDesc('generation')->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 20, self::BATCH_COLUMNS, 'page', $filters['page'] ?? 1);

            return ['data' => $page->getCollection()->map(fn ($batch) => $this->batch($batch, $this->workflow->read($batch, $now)))->all(), 'meta' => $this->pagination($page)];
        });
    }

    public function show(User $user, int $id, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $id, $filters) {
            $batch = $this->find($user, $id, $filters, true);
            $state = $this->workflow->read($batch, $this->clock->now());

            return ['data' => $this->batch($batch, $state) + ['dealers' => $this->dealers($batch, $state)]];
        });
    }

    public function items(User $user, int $id, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $id, $filters) {
            $batch = $this->find($user, $id, $filters);
            $state = $this->workflow->read($batch, $this->clock->now());
            $progress = $state['progress'];
            $query = $batch->items();
            if (isset($filters['tab'])) {
                $queues = $filters['tab'] === 'completed' ? ['completed', 'finding_pending'] : [$filters['tab']];
                $ids = array_keys(array_filter($state['states'], fn ($item) => in_array($item['state'], $queues, true)));
                $query->whereIn('id', $ids);
            }
            $page = $query->orderBy('selection_order')->orderBy('id')
                ->paginate($filters['per_page'] ?? 20, self::ITEM_COLUMNS, 'page', $filters['page'] ?? 1);

            return ['data' => $page->getCollection()->map(fn ($item) => $this->item($item, $state['states'][$item->id]))->all(),
                'meta' => $this->pagination($page), 'batch' => $this->batch($batch, $state), 'tabs' => $this->tabs($progress)];
        });
    }

    public function showItem(User $user, int $batchId, int $itemId, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $batchId, $itemId, $filters) {
            $batch = $this->find($user, $batchId, $filters);
            $item = $batch->items()->select(self::ITEM_COLUMNS)->whereKey($itemId)->firstOrFail();
            $state = $this->workflow->read($batch, $this->clock->now());

            return ['data' => $this->item($item, $state['states'][$item->id])];
        });
    }

    private function authorize(User $user): User
    {
        $current = User::select(['id', 'role', 'status'])->findOrFail($user->id);
        Gate::forUser($current)->authorize('viewAny', CallAuditBatch::class);

        return $current;
    }

    private function query(array $filters): Builder
    {
        return CallAuditBatch::select(self::BATCH_COLUMNS)->where('purpose', $filters['purpose'])
            ->where('lifecycle_state', 'frozen')->whereNotNull('frozen_at')
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->where('monthly_scope_key', $month));
    }

    private function find(User $user, int $id, array $filters, bool $dealers = false): CallAuditBatch
    {
        $query = $this->query($filters);
        if ($dealers) {
            $query->addSelect(['strata_snapshot_encrypted', 'award_baseline_encrypted']);
        }
        $batch = $query->whereKey($id)->firstOrFail();
        Gate::forUser($user)->authorize('view', $batch);

        return $batch;
    }

    private function batch(CallAuditBatch $batch, array $state): array
    {
        return ['id' => (int) $batch->id, 'purpose' => $batch->purpose, 'month' => $batch->monthly_scope_key,
            'generation' => (int) $batch->generation, 'frozen_at' => $batch->frozen_at->toIso8601String(),
            'requested_count' => $batch->requested_count, 'actual_count' => $batch->actual_count,
            'progress' => $state['progress']];
    }

    private function tabs(array $progress): array
    {
        return [
            ['key' => 'needs_call', 'label' => 'Cần gọi', 'count' => $progress['needs_call_count']],
            ['key' => 'recall', 'label' => 'Gọi lại', 'count' => $progress['recall_count']],
            ['key' => 'completed', 'label' => 'Đã xong', 'count' => $progress['completed_count'] + $progress['finding_pending_count']],
        ];
    }

    private function item(CallAuditItem $item, array $state): array
    {
        [$source, $dealer] = $this->snapshots($item);
        $phone = (new PhoneEligibility)->inspect($source['phone'] ?? null, $source['phone_normalized'] ?? null);
        $masked = $phone['status'] === 'READY' ? substr($phone['phone'], 0, 2).'****'.substr($phone['phone'], -3) : null;

        return ['id' => (int) $item->id, 'row_version' => $item->row_version, 'selection_order' => $item->selection_order,
            'name' => $source['customer_name'] ?? null, 'phone_masked' => $masked,
            'dealer' => ['id' => (int) $item->dealer_id_snapshot, 'name' => $dealer['name'] ?? null]] + $state;
    }

    private function dealers(CallAuditBatch $batch, array $state): array
    {
        $result = [];
        $byDealer = [];
        foreach ($state['items'] as $item) {
            $byDealer[$item->dealer_id_snapshot][] = $state['states'][$item->id];
        }
        try {
            if ($batch->purpose === CallAuditBatch::PURPOSE_AWARD) {
                $names = [];
                foreach (($batch->award_baseline_encrypted['dealers'] ?? []) as $dealer) {
                    $names[$dealer['dealer_id']] = $dealer['dealer_name_snapshot'];
                }
                foreach ($batch->strata_snapshot_encrypted as $stratum) {
                    $id = $stratum['dealer_id'];
                    $result[$id] = ['id' => $id, 'name' => $names[$id] ?? null, 'requested_count' => $stratum['requested_count']];
                }
            }
            foreach ($batch->items()->select(['id', 'batch_id', 'dealer_id_snapshot', 'dealer_snapshot_encrypted'])
                ->orderBy('selection_order')->get() as $item) {
                if (! isset($result[$item->dealer_id_snapshot])) {
                    $dealer = $this->dealerSnapshot($item);
                    $result[$item->dealer_id_snapshot] = ['id' => (int) $item->dealer_id_snapshot,
                        'name' => $dealer['name'] ?? null, 'requested_count' => null];
                }
            }
        } catch (DecryptException|JsonException|TypeError) {
            throw new ReadUnavailable('Private snapshot unavailable.');
        }
        foreach ($result as $id => &$dealer) {
            $states = $byDealer[$id] ?? [];
            $dealer['actual_count'] = count($states);
            $dealer['progress'] = $this->workflow->counts($states);
            $answered = count(array_filter($states, fn ($item) => in_array($item['outcome'], ['correct', 'wrong'], true)));
            $dealer['wrong_ratio_denominator'] = $answered;
            $dealer['wrong_ratio'] = $answered > 0 ? round(100 * $dealer['progress']['finding_pending_count'] / $answered, 2) : null;
        }
        unset($dealer);

        return array_values($result);
    }

    private function snapshots(CallAuditItem $item): array
    {
        try {
            $source = $item->source_snapshot_encrypted;
            if (! is_array($source) || (int) ($source['id'] ?? 0) !== (int) $item->submission_id
                || ! is_string($source['customer_name'] ?? null)) {
                throw new ReadUnavailable('Snapshot identity mismatch.');
            }
            foreach (['phone', 'phone_normalized'] as $field) {
                if (($source[$field] ?? null) !== null && ! is_string($source[$field])) {
                    throw new ReadUnavailable('Snapshot phone type mismatch.');
                }
            }

            return [$source, $this->dealerSnapshot($item)];
        } catch (DecryptException|JsonException|TypeError) {
            throw new ReadUnavailable('Private snapshot unavailable.');
        }
    }

    private function dealerSnapshot(CallAuditItem $item): array
    {
        $dealer = $item->dealer_snapshot_encrypted;
        if (! is_array($dealer) || (int) ($dealer['id'] ?? 0) !== (int) $item->dealer_id_snapshot || ! is_string($dealer['name'] ?? null)) {
            throw new ReadUnavailable('Dealer snapshot mismatch.');
        }

        return $dealer;
    }

    private function pagination($page): array
    {
        // No request URL/links/withQueryString: only allowlisted numeric pagination metadata.
        return ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'last_page' => $page->lastPage()];
    }
}
