<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DealerPenaltyEvidence extends Model
{
    protected $table = 'dealer_penalty_evidence';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['evidence_snapshot_encrypted', 'evidence_digest', 'digest_key_id', 'encryption_key_id', 'encryption_version'];

    protected $casts = [
        'resolution_version' => 'integer', 'item_version_used' => 'integer',
        'call_recorded_at' => 'immutable_datetime', 'resolution_recorded_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime',
        'evidence_snapshot_encrypted' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Dealer penalty evidence is append-only.');
        });
        static::deleting(function () {
            throw new LogicException('Dealer penalty evidence is append-only.');
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DealerPenaltyEntry::class, 'entry_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallAuditBatch::class, 'batch_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CallAuditItem::class, 'item_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CallAuditAttempt::class, 'attempt_id');
    }

    public function callEvent(): BelongsTo
    {
        return $this->belongsTo(CallAuditEvent::class, 'call_event_id');
    }

    public function resolutionEvent(): BelongsTo
    {
        return $this->belongsTo(CallAuditEvent::class, 'resolution_event_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
