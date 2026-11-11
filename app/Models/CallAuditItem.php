<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CallAuditItem extends Model
{
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = [
        'dealer_snapshot_encrypted', 'source_snapshot_encrypted', 'source_fingerprint',
        'claim_token_hash', 'source_drift_digest',
    ];

    protected $casts = [
        'selection_order' => 'integer', 'attempt_count' => 'integer', 'row_version' => 'integer',
        'source_observed_at' => 'immutable_datetime', 'retry_due_at' => 'immutable_datetime',
        'claim_expires_at' => 'immutable_datetime', 'source_stale_at' => 'immutable_datetime',
        'dealer_snapshot_encrypted' => 'encrypted:array', 'source_snapshot_encrypted' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $item) {
            $projection = ['queue_state', 'attempt_count', 'row_version', 'latest_outcome',
                'retry_due_at', 'claim_owner_id', 'claim_token_hash', 'claim_expires_at',
                'source_stale_at', 'source_drift_digest', 'updated_at'];
            if (array_diff(array_keys($item->getDirty()), $projection)) {
                throw new LogicException('Selected call audit membership and snapshots are immutable.');
            }
        });
        static::deleting(function () {
            throw new LogicException('Selected call audit items cannot be deleted.');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallAuditBatch::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CustomerSubmission::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id_snapshot');
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id_snapshot');
    }

    public function claimOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_owner_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CallAuditAttempt::class, 'item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallAuditEvent::class, 'item_id');
    }
}
