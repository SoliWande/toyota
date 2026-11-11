<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CallAuditBatch extends Model
{
    public const PURPOSE_GENERAL = 'general_audit';

    public const PURPOSE_AWARD = 'award_top3';

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = [
        'review_completion_evidence_encrypted', 'strata_snapshot_encrypted',
        'award_baseline_encrypted', 'award_baseline_fingerprint', 'manifest_encrypted',
        'manifest_digest', 'selection_digest', 'seed_encrypted', 'encryption_key_id',
        'digest_key_id', 'create_request_key', 'create_payload_digest', 'demo_policy_snapshot',
    ];

    protected $casts = [
        'period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime',
        'approval_cutoff_at' => 'immutable_datetime', 'eligibility_as_of' => 'immutable_datetime',
        'frozen_at' => 'immutable_datetime', 'generation' => 'integer',
        'eligible_count' => 'integer', 'requested_count' => 'integer', 'actual_count' => 'integer',
        'target_cap' => 'integer', 'row_version' => 'integer', 'demo_policy_snapshot' => 'array',
        'review_completion_evidence_encrypted' => 'encrypted:array',
        'strata_snapshot_encrypted' => 'encrypted:array', 'award_baseline_encrypted' => 'encrypted:array',
        'manifest_encrypted' => 'encrypted:array', 'seed_encrypted' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $batch) {
            if (array_diff(array_keys($batch->getDirty()), ['lifecycle_state', 'row_version', 'updated_at'])) {
                throw new LogicException('Frozen call audit batch content is immutable.');
            }
        });
        static::deleting(function () {
            throw new LogicException('Call audit batches cannot be deleted.');
        });
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function freezer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_batch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CallAuditItem::class, 'batch_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallAuditEvent::class, 'batch_id');
    }
}
