<?php

namespace App\Services\CallAudit;

use App\Models\DealerPenaltyEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DealerPenaltyReadModel
{
    public function __construct(private DealerPenaltyIntegrity $integrity) {}

    public function read(User $actor, string $month): array
    {
        return DB::transaction(function () use ($actor, $month) {
            $actor = User::select(['id', 'role', 'status'])->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('viewAny', DealerPenaltyEntry::class);
            $entries = DealerPenaltyEntry::where('monthly_scope_key', $month)->orderBy('id')->get();

            return ['month' => $month, 'data' => $entries->map(function ($entry) {
                $this->integrity->verify($entry);

                return ['entry_id' => (int) $entry->id, 'submission_id' => (int) $entry->submission_id, 'dealer_id' => (int) $entry->dealer_id,
                    'points' => $entry->points, 'applied_by' => (int) $entry->applied_by, 'applied_at' => $entry->applied_at->toIso8601String(),
                    'evidence_count' => $entry->evidence()->count(), 'event_count' => $entry->events()->count()];
            })->all()];
        });
    }
}
