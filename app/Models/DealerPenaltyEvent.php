<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DealerPenaltyEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['command_key', 'metadata', 'intent_encrypted', 'receipt_encrypted', 'payload_digest',
        'previous_event_digest', 'event_digest', 'digest_key_id', 'encryption_version', 'encryption_key_id'];

    protected $casts = [
        'event_sequence' => 'integer', 'event_ordinal' => 'integer', 'before_version' => 'integer', 'after_version' => 'integer',
        'evidence_count_before' => 'integer', 'evidence_count_after' => 'integer',
        'occurred_at' => 'immutable_datetime', 'metadata' => 'array', 'intent_encrypted' => 'encrypted:array', 'receipt_encrypted' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Dealer penalty events are append-only.');
        });
        static::deleting(function () {
            throw new LogicException('Dealer penalty events are append-only.');
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DealerPenaltyEntry::class, 'entry_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(DealerPenaltyEvidence::class, 'evidence_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
