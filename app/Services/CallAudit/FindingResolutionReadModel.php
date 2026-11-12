<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FindingResolutionReadModel
{
    public function __construct(private FindingReadModel $findings, private FindingResolutionHistory $history, private FreezeClock $clock) {}

    public function read(User $actor, int $batchId, int $itemId, array $filters, bool $history = false): array
    {
        return DB::transaction(function () use ($actor, $batchId, $itemId, $filters, $history) {
            $evidence = $this->findings->show($actor, $batchId, $itemId, $filters);
            $batch = CallAuditBatch::findOrFail($batchId, ['id', 'purpose', 'monthly_scope_key']);
            $state = $this->history->read($batch, $evidence['data'], $this->clock->now());
            if (! $history) {
                return ['data' => $state['current'], 'batch' => $evidence['batch']];
            }
            $page = (int) ($filters['page'] ?? 1);
            $size = (int) ($filters['per_page'] ?? 20);
            $rows = array_reverse($state['history']);

            return ['data' => array_slice($rows, ($page - 1) * $size, $size), 'batch' => $evidence['batch'],
                'meta' => ['current_page' => $page, 'per_page' => $size, 'total' => count($rows),
                    'last_page' => max(1, (int) ceil(count($rows) / $size))]];
        });
    }
}
