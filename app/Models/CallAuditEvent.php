<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CallAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = [
        'command_key', 'metadata', 'private_payload_encrypted', 'previous_event_digest',
        'event_digest', 'digest_key_id',
    ];

    protected $casts = [
        'event_sequence' => 'integer', 'event_ordinal' => 'integer',
        'before_version' => 'integer', 'after_version' => 'integer',
        'occurred_at' => 'immutable_datetime', 'metadata' => 'array',
        'private_payload_encrypted' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Call audit events are append-only.');
        });
        static::deleting(function () {
            throw new LogicException('Call audit events are append-only.');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallAuditBatch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CallAuditItem::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CallAuditAttempt::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
