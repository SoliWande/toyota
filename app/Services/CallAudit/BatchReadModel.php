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

    public function index(User $user, array $filters): array
    {
        $this->authorize($user);

        return DB::transaction(function () use ($filters) {
            $page = $this->query($filters)->orderByDesc('monthly_scope_key')->orderByDesc('generation')->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 20, self::BATCH_COLUMNS, 'page', $filters['page'] ?? 1);

            return ['data' => $page->getCollection()->map(fn ($batch) => $this->batch($batch))->all(), 'meta' => $this->pagination($page)];
        });
    }

    public function show(User $user, int $id, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $id, $filters) {
            $batch = $this->find($user, $id, $filters, true);

            return ['data' => $this->batch($batch) + ['dealers' => $this->dealers($batch)]];
        });
    }

    public function items(User $user, int $id, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $id, $filters) {
            $batch = $this->find($user, $id, $filters);
            $progress = $this->progress($batch);
            $query = $batch->items();
            if (($filters['tab'] ?? null) === 'needs_call') {
                $query->whereDoesntHave('attempts');
            } elseif (in_array($filters['tab'] ?? null, ['recall', 'completed'], true)) {
                if ($progress['called_count'] > 0) {
                    throw new ReadUnavailable('Attempt classification requires the call workflow contract.');
                }
                $query->whereRaw('1 = 0');
            }
            $page = $query->withExists('attempts')->orderBy('selection_order')->orderBy('id')
                ->paginate($filters['per_page'] ?? 20, self::ITEM_COLUMNS, 'page', $filters['page'] ?? 1);

            return ['data' => $page->getCollection()->map(fn ($item) => $this->item($item))->all(),
                'meta' => $this->pagination($page), 'batch' => $this->batch($batch), 'tabs' => $this->tabs($progress)];
        });
    }

    public function showItem(User $user, int $batchId, int $itemId, array $filters): array
    {
        $user = $this->authorize($user);

        return DB::transaction(function () use ($user, $batchId, $itemId, $filters) {
            $batch = $this->find($user, $batchId, $filters);
            $item = $batch->items()->select(self::ITEM_COLUMNS)->withExists('attempts')->whereKey($itemId)->firstOrFail();

            return ['data' => $this->item($item)];
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

    private function batch(CallAuditBatch $batch): array
    {
        return ['id' => (int) $batch->id, 'purpose' => $batch->purpose, 'month' => $batch->monthly_scope_key,
            'generation' => (int) $batch->generation, 'frozen_at' => $batch->frozen_at->toIso8601String(),
            'requested_count' => $batch->requested_count, 'actual_count' => $batch->actual_count,
            'progress' => $this->progress($batch)];
    }

    private function progress(CallAuditBatch $batch): array
    {
        $query = $batch->items();
        $total = (clone $query)->count();
        if ($total !== $batch->actual_count) {
            throw new ReadUnavailable('Frozen item count mismatch.');
        }
        $called = (clone $query)->whereHas('attempts')->count();

        return $this->counts($total, $called);
    }

    private function counts(int $total, int $called): array
    {
        return ['called_count' => $called, 'needs_call_count' => $total - $called,
            'recall_count' => $called > 0 ? null : 0, 'completed_count' => $called > 0 ? null : 0,
            'is_complete' => false];
    }

    private function tabs(array $progress): array
    {
        return [
            ['key' => 'needs_call', 'label' => 'Cần gọi', 'count' => $progress['needs_call_count']],
            ['key' => 'recall', 'label' => 'Gọi lại', 'count' => $progress['recall_count']],
            ['key' => 'completed', 'label' => 'Đã xong', 'count' => $progress['completed_count']],
        ];
    }

    private function item(CallAuditItem $item): array
    {
        [$source, $dealer] = $this->snapshots($item);
        $phone = (new PhoneEligibility)->inspect($source['phone'] ?? null, $source['phone_normalized'] ?? null);
        $masked = $phone['status'] === 'READY' ? substr($phone['phone'], 0, 2).'****'.substr($phone['phone'], -3) : null;

        return ['id' => (int) $item->id, 'row_version' => $item->row_version, 'selection_order' => $item->selection_order,
            'name' => $source['customer_name'] ?? null, 'phone_masked' => $masked,
            'dealer' => ['id' => (int) $item->dealer_id_snapshot, 'name' => $dealer['name'] ?? null],
            'state' => $item->attempts_exists ? null : 'needs_call'];
    }

    private function dealers(CallAuditBatch $batch): array
    {
        $result = [];
        $totals = $called = [];
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
                ->withExists('attempts')->orderBy('selection_order')->get() as $item) {
                $id = (int) $item->dealer_id_snapshot;
                $totals[$id] = ($totals[$id] ?? 0) + 1;
                $called[$id] = ($called[$id] ?? 0) + (int) $item->attempts_exists;
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
            $dealer['actual_count'] = $totals[$id] ?? 0;
            $dealer['progress'] = $this->counts($totals[$id] ?? 0, $called[$id] ?? 0);
            $dealer['wrong_ratio'] = null;
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
